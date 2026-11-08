#!/usr/bin/env python3
"""Audit an explicit public origin without changing WordPress or indexing settings."""

from argparse import ArgumentParser
from collections import Counter
from concurrent.futures import ThreadPoolExecutor
from datetime import datetime, timezone
from hashlib import sha256
from html.parser import HTMLParser
from http.client import HTTPException
from pathlib import Path
from urllib.error import HTTPError, URLError
from urllib.parse import unquote, urljoin, urlsplit, urlunsplit
from urllib.request import HTTPRedirectHandler, Request, build_opener
from urllib.robotparser import RobotFileParser
import json
import os
import re
import xml.etree.ElementTree as ET


AGENTS = {
    "browser": "Mozilla/5.0 Skye-read-only-crawler-audit/1.0",
    "Googlebot": "Mozilla/5.0 (compatible; Googlebot/2.1; +https://www.google.com/bot.html)",
    "bingbot": "Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)",
    "OAI-SearchBot": "Mozilla/5.0 (compatible; OAI-SearchBot/1.4; +https://openai.com/searchbot)",
    "Claude-SearchBot": "Claude-SearchBot/1.0",
    "PerplexityBot": "Mozilla/5.0 (compatible; PerplexityBot/1.0; +https://perplexity.ai/perplexitybot)",
}
SOURCE_DOCS = [
    "https://www.sitemaps.org/protocol.html",
    "https://developers.google.com/search/docs/essentials/technical",
    "https://developers.google.com/search/docs/crawling-indexing/robots-meta-tag",
    "https://developers.openai.com/api/docs/bots",
    "https://www.bing.com/webmasters/help/which-crawlers-does-bing-use-8c184ec0",
    "https://support.claude.com/en/articles/8896518-does-anthropic-crawl-data-from-the-web-and-how-can-site-owners-block-the-crawler",
    "https://docs.perplexity.ai/docs/resources/perplexity-crawlers",
]
SITEMAP_NS = "http://www.sitemaps.org/schemas/sitemap/0.9"
MAX_PAGES = 500
MAX_SITEMAPS = 16
MAX_BYTES = 2_000_000
ROBOT_RULES = {
    "all", "none", "index", "noindex", "follow", "nofollow", "nosnippet",
    "max-snippet", "max-image-preview", "max-video-preview", "noarchive",
    "noimageindex", "notranslate", "unavailable_after", "indexifembedded",
}


def same_origin(first, second):
    """Compare scheme and effective authority, never accepting credentials."""
    try:
        a, b = urlsplit(first), urlsplit(second)
        ports = {"http": 80, "https": 443}
        if not (a.hostname and b.hostname and a.scheme in ports and b.scheme in ports) or any(value is not None for value in (a.username, a.password, b.username, b.password)):
            return False
        first_port = a.port if a.port is not None else ports[a.scheme]
        second_port = b.port if b.port is not None else ports[b.scheme]
        return (a.scheme, a.hostname, first_port) == (b.scheme, b.hostname, second_port)
    except ValueError:
        return False


def public_url(value, base):
    """Accept bounded same-origin public URLs, excluding query/fragment capabilities."""
    if not isinstance(value, str) or not value or len(value) > 2048 or re.search(r"[\s\x00-\x1f\x7f]", value):
        raise ValueError("Expected a bounded absolute public URL without whitespace.")
    parsed = urlsplit(value)
    if parsed.scheme not in {"http", "https"} or not parsed.hostname or not same_origin(value, base) or parsed.query or parsed.fragment:
        raise ValueError("Inventory/sitemap URLs must be absolute, same-origin and have no credentials, query or fragment.")
    return urlunsplit((parsed.scheme, parsed.netloc, parsed.path or "/", "", ""))


def safe_link(value, base):
    """Reduce public link evidence to scheme/authority/path without private URL data."""
    try:
        parsed = urlsplit(urljoin(base, value))
        parsed.port
        if parsed.scheme not in {"http", "https"} or not parsed.hostname or parsed.username is not None or parsed.password is not None:
            return None
        return urlunsplit((parsed.scheme, parsed.netloc, parsed.path or "/", "", ""))
    except ValueError:
        return None


class ScopedRedirect(HTTPRedirectHandler):
    """Keep anonymous GETs within the explicitly selected origin and public scope."""

    def redirect_request(self, request, fp, code, message, headers, newurl):
        try:
            public_url(newurl, request.full_url)
        except ValueError:
            raise HTTPError(request.full_url, code, "Blocked off-origin or private redirect", headers, fp)
        return super().redirect_request(request, fp, code, message, headers, newurl)


class Page(HTMLParser):
    """Extract public server HTML while excluding inert templates and script text."""

    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.language = None
        self.ids = []
        self.headings = []
        self.title = ""
        self.description = []
        self.robots = []
        self.canonicals = []
        self.open_graph = {}
        self.links = []
        self.resources = []
        self.schemas = []
        self.forms = 0
        self.main_text = []
        self._main = False
        self._ignore = 0
        self._heading = None
        self._title = False
        self._schema = None
        self._template = 0

    def handle_starttag(self, tag, attrs):
        if tag == "template":
            self._template += 1
            return
        if self._template:
            return
        item = dict(attrs)
        if tag == "html":
            self.language = item.get("lang")
        if item.get("id"):
            self.ids.append(item["id"])
        if tag == "main":
            self._main = True
        if tag in {"script", "style"}:
            self._ignore += 1
        if tag == "script" and item.get("type", "").lower() == "application/ld+json":
            self._schema = ""
        if tag == "title":
            self._title = True
        if tag in {"h1", "h2", "h3"}:
            self.headings.append({"level": tag, "text": ""})
            self._heading = len(self.headings) - 1
        if tag == "meta":
            if item.get("property", "").startswith("og:"):
                self.open_graph.setdefault(item["property"], []).append(item.get("content", ""))
            name = item.get("name", "").lower()
            if name == "description":
                self.description.append(item.get("content", ""))
            if name == "robots" or name in {agent.lower() for agent in AGENTS if agent != "browser"}:
                self.robots.append({"agent": name, "content": item.get("content", "")})
        if tag == "link" and "canonical" in item.get("rel", "").lower().split():
            self.canonicals.append(item.get("href"))
        if tag == "a" and item.get("href"):
            self.links.append({"href": item["href"], "target": item.get("target", ""), "rel": item.get("rel", "").lower().split()})
        for key in {"src", "srcset", "poster"}:
            if item.get(key):
                self.resources.append(item[key])
        if tag == "form":
            self.forms += 1

    def handle_endtag(self, tag):
        if tag == "template" and self._template:
            self._template -= 1
            return
        if self._template:
            return
        if tag == "main":
            self._main = False
        if tag in {"script", "style"} and self._ignore:
            self._ignore -= 1
        if tag == "script" and self._schema is not None:
            try:
                self.schemas.append(json.loads(self._schema))
            except ValueError:
                self.schemas.append({"invalid_json": True})
            self._schema = None
        if tag == "title":
            self._title = False
        if tag in {"h1", "h2", "h3"}:
            self._heading = None

    def handle_data(self, text):
        if self._template:
            return
        if self._schema is not None:
            self._schema += text
        if self._title:
            self.title += text
        if self._heading is not None:
            self.headings[self._heading]["text"] += text
        if self._main and not self._ignore:
            self.main_text.append(text)


def fetch(url, agent="browser"):
    """Bound anonymous GETs; retain only public metadata, never cookies or bodies in reports."""
    try:
        response = build_opener(ScopedRedirect()).open(Request(url, headers={"User-Agent": AGENTS[agent]}), timeout=20)
    except HTTPError as error:
        response = error
    except (URLError, TimeoutError, OSError, HTTPException) as error:
        return {"status": 0, "body": "", "final_url": url, "headers": {}, "error": type(error).__name__}
    try:
        with response:
            content = response.read(MAX_BYTES + 1)
            headers = {"content-type": response.headers.get("content-type", "")}
            headers["x-robots-tag"] = response.headers.get_all("X-Robots-Tag", [])
            return {"status": response.code, "body": content[:MAX_BYTES].decode("utf-8", errors="replace"), "final_url": safe_link(response.url, url), "headers": headers, "truncated": len(content) > MAX_BYTES}
    except (OSError, TimeoutError, HTTPException) as error:
        return {"status": 0, "body": "", "final_url": url, "headers": {}, "error": type(error).__name__}


def normalized(text):
    """Compare public textual content independently of incidental whitespace."""
    return re.sub(r"\s+", " ", text).strip()


def effective_policies(page, headers, agent):
    """Apply generic and matching UA rules; each HTTP header resets its UA scope."""
    token = agent.lower()
    policies = [row["content"] for row in page.robots if row["agent"] in {"robots", token}]
    values = headers.get("x-robots-tag", [])
    if isinstance(values, str):
        values = [values]
    for value in values:
        scope = None
        for part in value.split(","):
            part = part.strip()
            scoped = re.match(r"^([a-z0-9_*.-]+)\s*:\s*(.*)$", part, re.I)
            if scoped and scoped.group(1).lower() not in ROBOT_RULES:
                scope = scoped.group(1).lower()
                part = scoped.group(2)
            if scope in {None, "*", token}:
                policies.append(part)
    rules = ", ".join(policies)
    return {
        "noindex": bool(re.search(r"(?:^|[,\s])(?:noindex|none)(?=$|[,\s])", rules, re.I)),
        "snippets_allowed": not bool(re.search(r"(?:^|[,\s])nosnippet(?=$|[,\s])|\bmax-snippet\s*:\s*0(?=$|[,\s])", rules, re.I)),
    }


def graph_nodes(page):
    """Read native JSON-LD nodes without assuming every valid document is an object."""
    nodes = []
    for schema in page.schemas:
        roots = schema if isinstance(schema, list) else [schema]
        for root in roots:
            if isinstance(root, dict):
                graph = root.get("@graph", [root])
                if isinstance(graph, list):
                    nodes.extend(node for node in graph if isinstance(node, dict))
    return nodes


def schema_types(node):
    """Return only valid textual schema types for evidence signatures."""
    value = node.get("@type", [])
    return [value] if isinstance(value, str) else [kind for kind in value if isinstance(kind, str)] if isinstance(value, list) else []


def expected_language(value):
    """Validate a project-selected HTML language without imposing one site locale."""
    if not isinstance(value, str) or not re.fullmatch(r"[A-Za-z]{2,8}(?:-[A-Za-z0-9]{1,8})*", value):
        raise ValueError("Expected a bounded HTML language tag, such as en or de-CH.")
    return value


def minimum_main_characters(value):
    """Bound the optional project-specific meaningful server-content requirement."""
    if isinstance(value, bool) or not isinstance(value, int) or not 1 <= value <= 100000:
        raise ValueError("Minimum main-content characters must be an integer from 1 to 100000.")
    return value


def required_schema_types(values):
    """Accept explicit schema type names; entity identity belongs to each project."""
    if not isinstance(values, list) or len(values) > 16 or any(not isinstance(value, str) or not re.fullmatch(r"[A-Za-z][A-Za-z0-9]{0,79}", value) for value in values):
        raise ValueError("Require at most 16 plain schema type names.")
    return list(dict.fromkeys(values))


def inspect(job):
    """Audit current native URLs; forms remain observations rather than SEO requirements."""
    url, item, agent, mode, robots = job[:5]
    constraints = job[5] if len(job) > 5 else {}
    response = fetch(url, agent)
    page = Page()
    page.feed(response["body"])
    policy = effective_policies(page, response["headers"], agent)
    allowed = robots.can_fetch(agent if agent != "browser" else "*", url)
    indexable = item.get("expected_indexable", True)
    checks = {
        "http_200": response["status"] == 200,
        "html_content_type": response["headers"].get("content-type", "").lower().split(";")[0].strip() == "text/html",
        "one_h1": sum(row["level"] == "h1" for row in page.headings) == 1,
        "language_present": bool(page.language and page.language.strip()),
        "unique_ids": len(page.ids) == len(set(page.ids)),
        "title_present": bool(page.title.strip()),
        "one_description": len(page.description) == 1 and bool(page.description[0].strip()),
        "server_rendered_main": len(normalized(" ".join(page.main_text))) >= constraints.get("minimum_main_characters", 1),
        "no_mixed_resources": not any(re.search(r"(?:^|[,\s])http://", value) for value in page.resources) if url.startswith("https:") else True,
        "bounded_response": not response.get("truncated", False),
        "valid_schema_json": not any(isinstance(schema, dict) and schema.get("invalid_json") for schema in page.schemas),
    }
    if mode == "staging" or not indexable:
        checks["stage_indexing_guard" if mode == "staging" else "utility_noindex"] = policy["noindex"]
    else:
        checks["indexing_allowed"] = not policy["noindex"] and allowed
        checks["search_snippets_allowed"] = policy["snippets_allowed"]
    checks["native_canonical"] = (not page.canonicals or page.canonicals == [url]) if mode == "staging" or not indexable else page.canonicals == [url]
    checks["open_graph_metadata"] = all(len(page.open_graph.get(key, [])) == 1 and bool(page.open_graph[key][0]) for key in ["og:title", "og:description", "og:type", "og:site_name", "og:locale"])
    checks["open_graph_url"] = page.open_graph.get("og:url") == [url]
    nodes = graph_nodes(page)
    language = item.get("expected_language", constraints.get("expected_language"))
    if language:
        checks["expected_language"] = (page.language or "").lower() == language.lower()
    for kind in constraints.get("required_schema_types", []):
        checks["schema_type_" + kind] = any(kind in schema_types(node) for node in nodes)
    signature = {"title": page.title, "headings": page.headings, "description": page.description, "schema_types": sorted({kind for node in nodes for kind in schema_types(node)})}
    internal, fragments, external_errors = set(), [], []
    for link in page.links:
        target = safe_link(link["href"], url)
        if not target:
            continue
        if same_origin(target, url):
            parsed = urlsplit(urljoin(url, link["href"]))
            if not parsed.query:
                internal.add(target)
                fragment = unquote(parsed.fragment)
                if fragment and re.fullmatch(r"[A-Za-z][A-Za-z0-9_.:-]{0,127}", fragment):
                    fragments.append({"url": target, "fragment": fragment})
        elif link["target"] != "_blank" or not {"noopener", "noreferrer"}.issubset(link["rel"]):
            external_errors.append(target)
    checks["external_link_policy"] = not external_errors
    return {
        "url": url, "agent": agent, "status": response["status"], "final_url": response["final_url"],
        "title": page.title, "description": page.description, "checks": checks,
        "errors": [name for name, passed in checks.items() if not passed], "noindex": policy["noindex"],
        "search_snippets_allowed": policy["snippets_allowed"], "robots_allowed": allowed,
        "canonical": [safe_link(value, url) for value in page.canonicals], "open_graph": {key: [safe_link(value, url) if key in {"og:url", "og:image"} else value for value in values] for key, values in page.open_graph.items()},
        "metadata_signature_sha256": sha256(json.dumps(signature, ensure_ascii=False, sort_keys=True).encode()).hexdigest(),
        "main_text_signature_sha256": sha256(normalized(" ".join(page.main_text)).encode()).hexdigest(),
        "main_text_characters": len(normalized(" ".join(page.main_text))), "main_text_without_JS": True,
        "observations": {"form_count": page.forms}, "same_origin_links": sorted(internal) if agent == "browser" else [],
        "internal_fragments": fragments if agent == "browser" else [], "ids": page.ids if agent == "browser" else [],
        "external_link_policy_failures": sorted(set(external_errors)), "response_error": response.get("error"),
    }


def parse_sitemap(body):
    """Read only direct canonical Sitemap-namespace entries, never image/video locs."""
    if "<!DOCTYPE" in body.upper() or "<!ENTITY" in body.upper():
        raise ValueError("DTD/entities are outside this bounded sitemap reader.")
    try:
        root = ET.fromstring(body)
    except ET.ParseError as error:
        raise ValueError("Sitemap response is not valid XML.") from error
    kinds = {"{" + SITEMAP_NS + "}urlset": ("urlset", "url"), "{" + SITEMAP_NS + "}sitemapindex": ("sitemapindex", "sitemap")}
    if root.tag not in kinds:
        raise ValueError("Expected a Sitemap-namespace urlset or sitemapindex root.")
    kind, entry = kinds[root.tag]
    entries = root.findall("{" + SITEMAP_NS + "}" + entry)
    limit = MAX_PAGES if kind == "urlset" else MAX_SITEMAPS
    if len(entries) > limit:
        raise ValueError("Sitemap entry limit exceeded; no partial inventory is accepted.")
    urls = []
    for item in entries:
        locations = item.findall("{" + SITEMAP_NS + "}loc")
        if len(locations) != 1 or not (locations[0].text or "").strip():
            raise ValueError("Every sitemap entry requires exactly one direct loc.")
        urls.append(locations[0].text.strip())
    return kind, urls


def sitemap_audit(base, mode, robots_text=""):
    """Discover bounded same-origin native sitemaps; Stage absence is an observation."""
    declared = [line.split(":", 1)[1].strip() for line in robots_text.splitlines() if re.match(r"^\s*sitemap\s*:", line, re.I)]
    queue = list(dict.fromkeys(declared or [urljoin(base, "sitemap_index.xml")]))
    result = {"documents": [], "urls": [], "errors": [], "notes": []}
    seen, urls = set(), set()
    while queue:
        if len(seen) >= MAX_SITEMAPS:
            result["errors"].append("Sitemap document limit exceeded; inventory is incomplete.")
            break
        candidate = queue.pop(0)
        try:
            candidate = public_url(candidate, base)
        except ValueError:
            result["errors"].append("Skipped an off-origin/private or malformed sitemap URL.")
            continue
        if candidate in seen:
            continue
        seen.add(candidate)
        response = fetch(candidate)
        result["documents"].append({"url": candidate, "status": response["status"]})
        if response["status"] != 200:
            if mode == "staging" and response["status"] in {404, 410}:
                result["notes"].append("The noindex Stage does not provide this native sitemap.")
            else:
                result["errors"].append("Native sitemap did not return HTTP 200.")
            continue
        try:
            if response.get("truncated"):
                raise ValueError("Sitemap response exceeds the byte bound.")
            kind, locations = parse_sitemap(response["body"])
            validated = [public_url(value, base) for value in locations]
            if kind == "sitemapindex":
                queue.extend(value for value in validated if value not in seen and value not in queue)
            else:
                urls.update(validated)
                if len(urls) > MAX_PAGES:
                    raise ValueError("Combined sitemap page limit exceeded; inventory is incomplete.")
        except ValueError as error:
            result["errors"].append(str(error))
    result["urls"] = sorted(urls) if len(urls) <= MAX_PAGES else []
    return result


def load_inventory(path, base):
    """Load explicit native URL evidence, never the historical content-import seed."""
    if path.stat().st_size > MAX_BYTES:
        raise ValueError("Inventory exceeds the byte bound.")
    data = json.loads(path.read_text())
    if not isinstance(data, dict) or data.get("schema_version") != 1 or not isinstance(data.get("base_url"), str) or data["base_url"].rstrip("/") + "/" != base:
        raise ValueError("Inventory requires schema_version 1 and the exact selected base_url.")
    if not isinstance(data.get("pages"), list) or not isinstance(data.get("archives", []), list):
        raise ValueError("Inventory pages and optional archives must be arrays.")
    captured_at = data.get("captured_at")
    if captured_at is not None:
        if not isinstance(captured_at, str) or len(captured_at) > 64:
            raise ValueError("captured_at must be an ISO timestamp, not arbitrary metadata.")
        datetime.fromisoformat(captured_at.replace("Z", "+00:00"))
    rows = data["pages"] + data.get("archives", [])
    if not rows or len(rows) > MAX_PAGES:
        raise ValueError("Inventory must contain 1–500 public URLs.")
    items = []
    for row in rows:
        if not isinstance(row, dict) or not isinstance(row.get("expected_indexable", True), bool):
            raise ValueError("Inventory entries require url and an optional boolean expected_indexable.")
        item = {"url": public_url(row.get("url"), base), "expected_indexable": row.get("expected_indexable", True)}
        if "expected_language" in row:
            item["expected_language"] = expected_language(row["expected_language"])
        items.append(item)
    if len({row["url"] for row in items}) != len(items):
        raise ValueError("Inventory contains duplicate URLs.")
    return items, {"kind": "explicit_native_inventory", "captured_at": captured_at, "sha256": sha256(path.read_bytes()).hexdigest()}


def link_findings(pages, expected, base):
    """Report missing audited fragments and bounded graph observations without extra GETs."""
    indexed = {page["url"]: set(page["ids"]) for page in pages}
    incoming = set()
    missing, outside = [], set()
    for page in pages:
        for target in page["same_origin_links"]:
            if target != page["url"]:
                incoming.add(target)
            if target not in indexed and not re.search(r"\.[A-Za-z0-9]{1,8}$", urlsplit(target).path):
                outside.add(target)
        for link in page["internal_fragments"]:
            if link["url"] in indexed and link["fragment"] not in indexed[link["url"]]:
                missing.append({"from": page["url"], "target": link["url"] + "#" + link["fragment"]})
    return {"missing_internal_fragments": missing, "potential_orphan_urls": sorted(set(expected) - incoming - {base}), "linked_internal_urls_outside_inventory": sorted(outside)}


def main():
    parser = ArgumentParser(description=__doc__)
    parser.add_argument("--base-url", required=True)
    parser.add_argument("--mode", choices=["staging", "live"], default="staging")
    parser.add_argument("--inventory", type=Path, help="Current native JSON URL inventory; mandatory on Stage.")
    parser.add_argument("--expected-language", help="Optional HTML language for all URLs; an inventory row can select its own language.")
    parser.add_argument("--minimum-main-characters", type=int, default=1, help="Explicit meaningful server-text threshold (1–100000); default checks for nonempty main content.")
    parser.add_argument("--required-schema-type", action="append", default=[], help="Optional expected JSON-LD type; repeat for each factual project requirement.")
    parser.add_argument("--output", required=True, type=Path)
    args = parser.parse_args()
    if args.output.exists() or args.output.is_symlink():
        parser.error("Select a new output file; existing paths are never overwritten.")
    base = args.base_url.rstrip("/") + "/"
    location = urlsplit(base)
    try:
        public_url(base, base)
        constraints = {"minimum_main_characters": minimum_main_characters(args.minimum_main_characters), "required_schema_types": required_schema_types(args.required_schema_type)}
        if args.expected_language:
            constraints["expected_language"] = expected_language(args.expected_language)
        if location.path != "/" or location.scheme != "https" and location.hostname not in {"localhost", "127.0.0.1"}:
            raise ValueError("Use an origin-root HTTPS URL, except for explicitly local QA.")
        if args.mode == "staging" and not args.inventory:
            raise ValueError("Stage requires --inventory; its intentionally absent sitemap cannot establish completeness.")
        items, inventory = load_inventory(args.inventory, base) if args.inventory else ([], {"kind": "native_sitemap_discovery"})
    except (ValueError, OSError) as error:
        parser.error(str(error))
    robots_response = fetch(urljoin(base, "robots.txt"))
    robots_text = robots_response["body"] if robots_response["status"] == 200 else ""
    robots = RobotFileParser()
    robots.parse(robots_text.splitlines())
    if robots_response["status"] in {401, 403} or robots_response["status"] == 0 or robots_response["status"] >= 500:
        robots.disallow_all = True
    sitemap = sitemap_audit(base, args.mode, robots_text)
    if not args.inventory:
        if sitemap["errors"] or not sitemap["urls"]:
            parser.error("Native sitemap discovery is incomplete: " + "; ".join(sitemap["errors"] or ["No public page URLs found."]))
        items = [{"url": url, "expected_indexable": True} for url in sitemap["urls"]]
    expected = [item["url"] for item in items]
    jobs = [(item["url"], item, agent, args.mode, robots, constraints) for item in items for agent in AGENTS]
    with ThreadPoolExecutor(max_workers=2) as executor:
        pages = list(executor.map(inspect, jobs))
    variants = {url: len({page["metadata_signature_sha256"] for page in pages if page["url"] == url}) == 1 for url in expected}
    main_text_variants = {url: len({page["main_text_signature_sha256"] for page in pages if page["url"] == url}) == 1 for url in expected}
    browser_pages = [page for page in pages if page["agent"] == "browser"]
    duplicate_titles = [title for title, count in Counter(page["title"] for page in browser_pages).items() if title and count > 1]
    duplicate_descriptions = [value for value, count in Counter(page["description"][0] if page["description"] else "" for page in browser_pages).items() if value and count > 1]
    links = link_findings(browser_pages, expected, base)
    sitemap_missing = sorted({item["url"] for item in items if item["expected_indexable"]} - set(sitemap["urls"])) if args.mode == "live" else []
    missing = fetch(urljoin(base, "qa-crawler-nonexistent-" + sha256(base.encode()).hexdigest()[:12] + "/"))
    missing_page = Page()
    missing_page.feed(missing["body"])
    missing_policy = effective_policies(missing_page, missing["headers"], "browser")
    failures = sum(len(page["errors"]) for page in pages)
    passed = not (failures or duplicate_titles or duplicate_descriptions or links["missing_internal_fragments"] or not all(variants.values()) or missing["status"] != 404 or args.mode == "live" and (sitemap_missing or sitemap["errors"]))
    summary = {"page_count": len(items), "anonymous_GET_checks": len(pages), "page_check_failures": failures, "duplicate_titles": duplicate_titles, "duplicate_descriptions": duplicate_descriptions, **links, "agent_metadata_equivalent": all(variants.values()), "agent_main_text_equivalent": all(main_text_variants.values()), "social_preview_image_complete": all(page["open_graph"].get("og:image") for page in browser_pages), "native_404": missing["status"] == 404, "staging_noindex_preserved": all(page["noindex"] for page in pages) if args.mode == "staging" else None, "indexability_controls_pass": passed if args.mode == "live" else False}
    report = {
        "checked_at": datetime.now(timezone.utc).isoformat(), "base_url": base, "mode": args.mode,
        "inventory": inventory, "project_constraints": constraints, "network_scope": "offline_loopback_QA" if location.hostname in {"localhost", "127.0.0.1"} else "HTTP_from_audit_host_only",
        "verified_provider_network_access": False, "summary": summary, "robots_status": robots_response["status"],
        "training_crawler_preferences": {agent: robots.can_fetch(agent, base) for agent in ["GPTBot", "ClaudeBot"]},
        "sitemap": sitemap, "sitemap_missing_expected_urls": sitemap_missing, "user_agent_metadata_equivalence": variants,
        "user_agent_main_text_equivalence": main_text_variants,
        "not_found": {"status": missing["status"], "noindex_observed": missing_policy["noindex"], "note": "A genuine 404 is not indexable; an additional noindex directive is not mandatory."},
        "pages": pages, "sources": SOURCE_DOCS,
        "limits": [
            "User-Agent probes originate from this audit host, not verified crawler IPs or Search Console.",
            "Robots parsing does not prove every provider's implementation or actual network access.",
            "Stage remains noindex. It cannot establish Live indexability.",
            "Search eligibility does not guarantee indexing, rankings, citations or AI inclusion.",
            "Training crawler preferences are separate from search crawler access.",
            "Live acceptance also needs real crawler/WAF access and Search Console inspection.",
            "Form counts and potential orphans are observations; no form submission or additional link crawl occurs.",
            "Metadata parity is a check. Main-text parity is a separate observation: live metrics may change between GETs; any difference needs review, and a pass does not prove full content equivalence.",
            "A sitemap-only inventory cannot reveal pages absent from that sitemap; compare native WordPress inventory for completeness.",
            "Query/credential URLs and nontrivial fragments are not fetched or emitted; utility capability links are outside this audit.",
        ],
    }
    args.output.parent.mkdir(parents=True, exist_ok=True)
    descriptor = os.open(str(args.output), os.O_WRONLY | os.O_CREAT | os.O_EXCL, 0o600)
    with os.fdopen(descriptor, "w", encoding="utf-8") as destination:
        destination.write(json.dumps(report, ensure_ascii=False, indent=2) + "\n")
    print(json.dumps(summary, ensure_ascii=False, indent=2))
    if not passed:
        raise SystemExit(1)


if __name__ == "__main__":
    main()
