#!/usr/bin/env python3
"""Offline regression tests for public inventories, sitemap parsing and crawler policy."""

from contextlib import redirect_stderr, redirect_stdout
from email.message import Message
from importlib.util import module_from_spec, spec_from_file_location
from io import BytesIO, StringIO
from pathlib import Path
from tempfile import TemporaryDirectory
from unittest import TestCase, main
from unittest.mock import patch
from urllib.error import HTTPError
from urllib.request import Request
from urllib.robotparser import RobotFileParser
import json
import sys


sys.dont_write_bytecode = True

SPEC = spec_from_file_location("crawler_audit", Path(__file__).with_name("audit-crawlers.py"))
AUDIT = module_from_spec(SPEC)
SPEC.loader.exec_module(AUDIT)
BASE = "https://example.test/"
NS = AUDIT.SITEMAP_NS


def html(extra="", links=""):
    """Provide a small realistic server page independent of WordPress import seeds."""
    return '<html lang="de-CH"><head><title>Native page</title><meta name="description" content="A concrete page description."><link rel="canonical" href="https://example.test/"><meta property="og:title" content="Native page"><meta property="og:description" content="A concrete page description."><meta property="og:type" content="website"><meta property="og:site_name" content="Example"><meta property="og:locale" content="de_CH"><meta property="og:url" content="https://example.test/"><meta property="og:image" content="https://example.test/image.webp">' + extra + '</head><body><main id="main"><h1>Native page</h1><p>' + ("Actual public server content. " * 12) + '</p>' + links + '<script type="application/ld+json">{"@graph":[{"@type":"Organization","name":"Example"}]}</script></main></body></html>'


def response(body="", status=200, content_type="text/html", headers=None):
    """Build only the public fields supplied by the anonymous GET adapter."""
    return {"status": status, "body": body, "final_url": BASE, "headers": {"content-type": content_type, **(headers or {})}, "truncated": False}


def robots(text="User-agent: *\nAllow: /\n"):
    """Use Python's native robots parser for actual UA-group decisions."""
    parser = RobotFileParser()
    parser.parse(text.splitlines())
    return parser


class SitemapTests(TestCase):
    """Do not confuse image locations, extension namespaces or malformed XML with pages."""

    def test_only_direct_sitemap_page_locations(self):
        body = f'<urlset xmlns="{NS}" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"><url><loc>{BASE}</loc><image:image><image:loc>{BASE}picture.webp</image:loc></image:image><foreign><loc>{BASE}nested/</loc></foreign></url></urlset>'
        self.assertEqual(("urlset", [BASE]), AUDIT.parse_sitemap(body))

    def test_only_direct_index_locations(self):
        body = f'<sitemapindex xmlns="{NS}" xmlns:x="urn:other"><sitemap><loc>{BASE}page-sitemap.xml</loc><x:loc>{BASE}wrong.xml</x:loc></sitemap></sitemapindex>'
        self.assertEqual(("sitemapindex", [BASE + "page-sitemap.xml"]), AUDIT.parse_sitemap(body))

    def test_invalid_root_missing_namespace_xml_entities_and_duplicate_loc(self):
        for body in ["<html>Not XML", "<urlset><url><loc>x</loc></url></urlset>", f'<other xmlns="{NS}"/>', f'<urlset xmlns="{NS}"><url><loc>x</loc><loc>y</loc></url></urlset>', f'<!DOCTYPE urlset [<!ENTITY x "x">]><urlset xmlns="{NS}"/>']:
            with self.subTest(body=body), self.assertRaises(ValueError):
                AUDIT.parse_sitemap(body)

    def test_overbound_is_error_not_silent_partial_inventory(self):
        with patch.object(AUDIT, "MAX_PAGES", 1), self.assertRaisesRegex(ValueError, "limit"):
            AUDIT.parse_sitemap(f'<urlset xmlns="{NS}"><url><loc>{BASE}</loc></url><url><loc>{BASE}two/</loc></url></urlset>')

    def test_current_native_index_child_discovery_skips_images(self):
        docs = {BASE + "native.xml": response(f'<sitemapindex xmlns="{NS}"><sitemap><loc>{BASE}pages.xml</loc></sitemap></sitemapindex>'), BASE + "pages.xml": response(f'<urlset xmlns="{NS}" xmlns:i="urn:image"><url><loc>{BASE}</loc><i:image><i:loc>{BASE}picture.png</i:loc></i:image></url></urlset>')}
        with patch.object(AUDIT, "fetch", side_effect=lambda url: docs[url]) as fetch:
            result = AUDIT.sitemap_audit(BASE, "live", "Sitemap: " + BASE + "native.xml")
        self.assertEqual([BASE], result["urls"])
        self.assertEqual([], result["errors"])
        self.assertEqual(2, fetch.call_count)

    def test_offorigin_and_query_child_urls_are_not_fetched_or_emitted(self):
        body = f'<sitemapindex xmlns="{NS}"><sitemap><loc>https://other.test/private.xml</loc></sitemap><sitemap><loc>{BASE}pages.xml?key=private</loc></sitemap></sitemapindex>'
        with patch.object(AUDIT, "fetch", return_value=response(body)) as fetch:
            result = AUDIT.sitemap_audit(BASE, "live")
        self.assertEqual(1, fetch.call_count)
        self.assertTrue(result["errors"])
        self.assertNotIn("private", json.dumps(result))

    def test_stage_404_is_observation_not_xml_error(self):
        with patch.object(AUDIT, "fetch", return_value=response("", 404)):
            result = AUDIT.sitemap_audit(BASE, "staging")
        self.assertEqual([], result["errors"])
        self.assertTrue(result["notes"])

    def test_combined_bound_and_document_bound_fail_clearly(self):
        body = f'<sitemapindex xmlns="{NS}"><sitemap><loc>{BASE}child.xml</loc></sitemap></sitemapindex>'
        with patch.object(AUDIT, "MAX_SITEMAPS", 1), patch.object(AUDIT, "fetch", return_value=response(body)):
            result = AUDIT.sitemap_audit(BASE, "live")
        self.assertIn("document limit", " ".join(result["errors"]))


class InventoryTests(TestCase):
    """Require explicit current native evidence and reject unsafe/incomplete inputs."""

    def load(self, data):
        with TemporaryDirectory() as directory:
            path = Path(directory) / "inventory.json"
            path.write_text(json.dumps(data))
            return AUDIT.load_inventory(path, BASE)

    def test_native_pages_and_archive_no_seed_or_private_metadata(self):
        items, source = self.load({"schema_version": 1, "base_url": BASE, "captured_at": "2026-11-06T16:00:00Z", "pages": [{"url": BASE, "id": 42, "post_type": "page", "private": "not copied"}], "archives": [{"url": BASE + "references/"}, {"url": BASE + "utility/", "expected_indexable": False}]})
        self.assertEqual(3, len(items))
        self.assertFalse(items[2]["expected_indexable"])
        self.assertNotIn("private", json.dumps(items) + json.dumps(source))
        self.assertEqual(64, len(source["sha256"]))

    def test_bad_shape_duplicate_origin_credentials_query_and_fragment_rejected(self):
        base = {"schema_version": 1, "base_url": BASE, "pages": [{"url": BASE}]}
        candidates = [{**base, "schema_version": 2}, {**base, "pages": []}, {**base, "pages": [{"url": BASE}, {"url": BASE}]}, {**base, "pages": [{"url": BASE, "expected_indexable": "false"}]}, {**base, "captured_at": {"secret": "not timestamp"}}]
        candidates += [{**base, "pages": [{"url": value}]} for value in ["/relative/", "http://example.test/", "https://other.test/", "https://user:secret@example.test/", BASE + "?cap=secret", BASE + "#result=secret", BASE + "bad\npath"]]
        for data in candidates:
            with self.subTest(data=data), self.assertRaises(ValueError):
                self.load(data)

    def test_stage_without_inventory_rejected_before_any_http(self):
        with patch("sys.argv", ["audit", "--base-url", BASE, "--output", "/unused.json"]), patch.object(AUDIT, "fetch") as fetch, redirect_stderr(StringIO()), self.assertRaises(SystemExit):
            AUDIT.main()
        fetch.assert_not_called()

    def test_effective_origin_normalizes_standard_ports_only(self):
        self.assertTrue(AUDIT.same_origin("https://example.test/a", "https://example.test:443/b"))
        self.assertTrue(AUDIT.same_origin("http://example.test/a", "http://example.test:80/b"))
        for first, second in [(BASE, "https://example.test:8443/"), (BASE, "https://example.test:0/"), (BASE, "http://example.test/"), (BASE, "https://user:secret@example.test/"), (BASE, "https://@example.test/"), ("https://user@example.test/", BASE), (BASE, "https://example.test:bad/"), ("/relative/", "/relative/")]:
            with self.subTest(first=first, second=second):
                self.assertFalse(AUDIT.same_origin(first, second))


class PolicyTests(TestCase):
    """Generic and matching crawler rules must combine without leaking across UA groups."""

    def parse(self, value):
        page = AUDIT.Page()
        page.feed(value)
        return page

    def test_targeted_meta_does_not_block_other_agents(self):
        page = self.parse('<meta name="GOOGLEBOT" content="noindex,nosnippet"><meta name="robots" content="follow">')
        self.assertTrue(AUDIT.effective_policies(page, {}, "Googlebot")["noindex"])
        for agent in ("browser", "bingbot", "OAI-SearchBot"):
            self.assertEqual({"noindex": False, "snippets_allowed": True}, AUDIT.effective_policies(page, {}, agent))

    def test_repeated_headers_reset_scope_and_preserve_restrictive_combination(self):
        page = self.parse("")
        headers = {"x-robots-tag": ["googlebot: noindex, nosnippet", "max-snippet: 0"]}
        self.assertEqual({"noindex": True, "snippets_allowed": False}, AUDIT.effective_policies(page, headers, "Googlebot"))
        self.assertEqual({"noindex": False, "snippets_allowed": False}, AUDIT.effective_policies(page, headers, "bingbot"))

    def test_multiple_named_scopes_and_global_header(self):
        page = self.parse('<meta name="robots" content="index,follow">')
        headers = {"x-robots-tag": ["googlebot: noindex, OAI-SearchBot: nosnippet", "noarchive"]}
        self.assertEqual({"noindex": False, "snippets_allowed": False}, AUDIT.effective_policies(page, headers, "OAI-SearchBot"))
        self.assertEqual({"noindex": False, "snippets_allowed": True}, AUDIT.effective_policies(page, headers, "bingbot"))

    def test_none_and_zero_snippet_not_zero_prefix(self):
        for value, blocked in [("max-snippet:0", True), ("max-snippet: 0, follow", True), ("max-snippet: 01", False), ("max-snippet:100", False)]:
            with self.subTest(value=value):
                policy = AUDIT.effective_policies(self.parse('<meta name="robots" content="' + value + '">'), {}, "browser")
                self.assertEqual(not blocked, policy["snippets_allowed"])
        self.assertTrue(AUDIT.effective_policies(self.parse('<meta name="robots" content="none">'), {}, "browser")["noindex"])

    def test_robots_specific_group_remains_distinct(self):
        policy = robots("User-agent: Googlebot\nDisallow: /\n\nUser-agent: *\nAllow: /\n")
        self.assertFalse(policy.can_fetch("Googlebot", BASE))
        self.assertTrue(policy.can_fetch("bingbot", BASE))


class RenderingTests(TestCase):
    """Exercise the real inspector using bounded synthetic responses, never real HTTP."""

    def inspect(self, body, agent="browser", mode="live", item=None, headers=None, constraints=None):
        with patch.object(AUDIT, "fetch", return_value=response(body, headers=headers)):
            return AUDIT.inspect((BASE, item or {}, agent, mode, robots(), constraints or {}))

    def test_language_and_schema_do_not_assume_german_organization(self):
        body = html().replace('lang="de-CH"', 'lang="en-GB"').replace('"@type":"Organization"', '"@type":"Person"')
        page = self.inspect(body)
        self.assertEqual([], page["errors"])
        self.assertNotIn("organization", page["checks"])
        self.assertNotIn("expected_language", page["checks"])
        selected = self.inspect(body, constraints={"expected_language": "en-gb", "required_schema_types": ["Person"]})
        self.assertEqual([], selected["errors"])
        mismatched = self.inspect(body, constraints={"expected_language": "fr", "required_schema_types": ["Organization"]})
        self.assertIn("expected_language", mismatched["errors"])
        self.assertIn("schema_type_Organization", mismatched["errors"])

    def test_inventory_locale_overrides_explicit_global_language(self):
        page = self.inspect(html().replace('lang="de-CH"', 'lang="fr-CH"'), item={"expected_language": "fr-CH"}, constraints={"expected_language": "en"})
        self.assertTrue(page["checks"]["expected_language"])

    def test_project_main_text_requirement_is_explicit_and_has_no_fixed_copy_length(self):
        body = html().replace("Actual public server content. " * 12, "Short authored page.")
        self.assertTrue(self.inspect(body)["checks"]["server_rendered_main"])
        self.assertFalse(self.inspect(body, constraints={"minimum_main_characters": 200})["checks"]["server_rendered_main"])

    def test_forms_are_observations_even_with_zero_or_many_forms(self):
        for forms in ("", "<form></form><form></form><form></form>"):
            page = self.inspect(html(links=forms))
            self.assertEqual([], page["errors"])
            self.assertEqual(forms.count("<form>"), page["observations"]["form_count"])
            self.assertNotIn("form_count", page["checks"])
            self.assertNotIn("service_preselected", page["checks"])

    def test_template_content_is_inert_and_array_jsonld_is_valid(self):
        page = AUDIT.Page()
        page.feed('<main><h1>Visible</h1><template><h1>Hidden</h1><form/><a href="/hidden/">Hidden</a></template><script type="application/ld+json">[{"@type":"Organization"}]</script></main>')
        self.assertEqual(1, len(page.headings))
        self.assertEqual(0, page.forms)
        self.assertEqual([], page.links)
        self.assertEqual([{"@type": "Organization"}], AUDIT.graph_nodes(page))

    def test_external_policy_and_public_evidence_strip_capabilities(self):
        page = self.inspect(html(links='<a href="https://else.test/path?key=secret#result=secret" target="_blank" rel="noopener">External</a><a href="/?cap=secret">Private query</a><a href="#result=secret">Private fragment</a>'))
        self.assertIn("external_link_policy", page["errors"])
        self.assertEqual(["https://else.test/path"], page["external_link_policy_failures"])
        self.assertEqual([], page["internal_fragments"])
        self.assertNotIn("secret", json.dumps(page))

    def test_protocol_relative_external_policy_and_existing_rel(self):
        page = self.inspect(html(links='<a href="//else.test/" target="_blank" rel="nofollow noopener noreferrer">External</a>'))
        self.assertTrue(page["checks"]["external_link_policy"])

    def test_stage_guard_and_live_snippet_rules_are_separate(self):
        page = self.inspect(html('<meta name="robots" content="noindex,nosnippet">'), mode="staging")
        self.assertTrue(page["checks"]["stage_indexing_guard"])
        self.assertNotIn("search_snippets_allowed", page["checks"])
        self.assertEqual([], page["errors"])
        self.assertIn("search_snippets_allowed", self.inspect(html('<meta name="robots" content="max-snippet:0">'))["errors"])

    def test_malformed_schema_and_nonhtml_are_reported(self):
        page = self.inspect(html('<script type="application/ld+json">not JSON</script>'))
        self.assertIn("valid_schema_json", page["errors"])
        with patch.object(AUDIT, "fetch", return_value=response(html(), content_type="application/xml")):
            page = AUDIT.inspect((BASE, {}, "browser", "live", robots()))
        self.assertIn("html_content_type", page["errors"])

    def test_body_variation_is_visible_independently_of_metadata(self):
        browser = self.inspect(html())
        crawler = self.inspect(html().replace("Actual public server content.", "Different crawler-visible body."), agent="Googlebot")
        self.assertEqual(browser["metadata_signature_sha256"], crawler["metadata_signature_sha256"])
        self.assertNotEqual(browser["main_text_signature_sha256"], crawler["main_text_signature_sha256"])
        self.assertNotIn("signature_sha256", browser)

    def test_fragments_orphans_and_outside_inventory_are_observations(self):
        rows = [{"url": BASE, "ids": ["home"], "same_origin_links": [BASE + "two/", BASE + "unknown/"], "internal_fragments": [{"url": BASE + "two/", "fragment": "missing"}]}, {"url": BASE + "two/", "ids": ["present"], "same_origin_links": [], "internal_fragments": []}, {"url": BASE + "orphan/", "ids": [], "same_origin_links": [], "internal_fragments": []}]
        findings = AUDIT.link_findings(rows, [row["url"] for row in rows], BASE)
        self.assertEqual([BASE + "orphan/"], findings["potential_orphan_urls"])
        self.assertEqual([BASE + "unknown/"], findings["linked_internal_urls_outside_inventory"])
        self.assertEqual(BASE + "two/#missing", findings["missing_internal_fragments"][0]["target"])

    def test_redirect_rejects_crossorigin_credentials_and_capability_urls(self):
        handler = AUDIT.ScopedRedirect()
        for target in ["https://other.test/", "https://user:secret@example.test/", BASE + "?cap=secret"]:
            with self.subTest(target=target), self.assertRaises(HTTPError) as error:
                handler.redirect_request(Request(BASE), None, 302, "Found", {}, target)
            error.exception.close()
        self.assertEqual(BASE + "new/", handler.redirect_request(Request(BASE), None, 302, "Found", {}, BASE + "new/").full_url)


class CLITests(TestCase):
    """Run the complete CLI with fake HTTP, including six UAs and a genuine untagged 404."""

    def test_current_inventory_drives_cli_and_report_without_network_or_form_assumptions(self):
        archive = BASE + "references/"
        sitemap = f'<urlset xmlns="{NS}"><url><loc>{BASE}</loc></url><url><loc>{archive}</loc></url></urlset>'

        def get(url, agent="browser"):
            if url.endswith("robots.txt"):
                return response("User-agent: *\nAllow: /", content_type="text/plain")
            if url.endswith("sitemap_index.xml"):
                return response(sitemap, content_type="application/xml")
            if "qa-crawler-nonexistent-" in url:
                return response("<html><body>Not found</body></html>", status=404)
            body = html(links='<a href="/references/">References</a><form></form><form></form>')
            if url == archive:
                body = body.replace("Native page", "Reference archive").replace("A concrete page description.", "Project archive description.").replace('content="https://example.test/"', 'content="' + archive + '"').replace('href="https://example.test/"', 'href="' + archive + '"')
            return response(body)

        with TemporaryDirectory() as directory:
            inventory = Path(directory) / "inventory.json"
            output = Path(directory) / "report.json"
            inventory.write_text(json.dumps({"schema_version": 1, "base_url": BASE, "pages": [{"url": BASE}], "archives": [{"url": archive}]}))
            argv = ["audit", "--base-url", BASE, "--mode", "live", "--inventory", str(inventory), "--expected-language", "de-CH", "--required-schema-type", "Organization", "--minimum-main-characters", "200", "--output", str(output)]
            original_executor = AUDIT.ThreadPoolExecutor
            with patch("sys.argv", argv), patch.object(AUDIT, "fetch", side_effect=get) as fetch, patch.object(AUDIT, "ThreadPoolExecutor", wraps=original_executor) as executor, redirect_stdout(StringIO()):
                AUDIT.main()
            report = json.loads(output.read_text())
            self.assertEqual(12, report["summary"]["anonymous_GET_checks"])
            self.assertEqual(15, fetch.call_count)
            executor.assert_called_once_with(max_workers=2)
            self.assertTrue(report["summary"]["indexability_controls_pass"])
            self.assertEqual({"expected_language": "de-CH", "required_schema_types": ["Organization"], "minimum_main_characters": 200}, report["project_constraints"])
            self.assertEqual({"status": 404, "noindex_observed": False, "note": "A genuine 404 is not indexable; an additional noindex directive is not mandatory."}, report["not_found"])
            self.assertEqual(0o600, output.stat().st_mode & 0o777)
            self.assertEqual(set(AUDIT.AGENTS), {page["agent"] for page in report["pages"]})

    def test_invalid_live_sitemap_cannot_silently_establish_inventory(self):
        with patch("sys.argv", ["audit", "--base-url", BASE, "--mode", "live", "--output", "/unused.json"]), patch.object(AUDIT, "fetch", return_value=response("<html>not XML")), redirect_stderr(StringIO()) as stderr, self.assertRaises(SystemExit):
            AUDIT.main()
        self.assertIn("not valid XML", stderr.getvalue())


class ProjectConstraintTests(TestCase):
    """Require bounded explicit expectations rather than copying one site's facts."""

    def test_inventory_retains_only_its_bounded_language_contract(self):
        with TemporaryDirectory() as directory:
            path = Path(directory) / "inventory.json"
            path.write_text(json.dumps({"schema_version": 1, "base_url": BASE, "pages": [{"url": BASE, "expected_language": "fr-CH", "ignored_private": "not copied"}]}))
            items, source = AUDIT.load_inventory(path, BASE)
            self.assertEqual("fr-CH", items[0]["expected_language"])
            self.assertNotIn("ignored_private", json.dumps(items) + json.dumps(source))
            for value in (None, [], "wrong language"):
                path.write_text(json.dumps({"schema_version": 1, "base_url": BASE, "pages": [{"url": BASE, "expected_language": value}]}))
                with self.assertRaises(ValueError):
                    AUDIT.load_inventory(path, BASE)

    def test_invalid_project_flags_stop_before_network(self):
        cases = [("--expected-language", "de_CH"), ("--minimum-main-characters", "0"), ("--minimum-main-characters", "100001"), ("--required-schema-type", "<script>"), ("--required-schema-type", "https://schema.org/Person")]
        for flag, value in cases:
            with self.subTest(flag=flag, value=value), patch("sys.argv", ["audit", "--base-url", BASE, "--mode", "live", "--output", "/unused-new-audit.json", flag, value]), patch.object(AUDIT, "fetch") as fetch, redirect_stderr(StringIO()), self.assertRaises(SystemExit):
                AUDIT.main()
            fetch.assert_not_called()

    def test_existing_output_and_symlinks_are_preserved_without_http(self):
        with TemporaryDirectory() as directory:
            existing = Path(directory) / "preserve.json"
            existing.write_text("Existing operator evidence.")
            linked = Path(directory) / "linked.json"
            linked.symlink_to(existing)
            dangling = Path(directory) / "dangling.json"
            dangling.symlink_to(Path(directory) / "missing.json")
            for output in (existing, linked, dangling):
                with patch("sys.argv", ["audit", "--base-url", BASE, "--mode", "live", "--output", str(output)]), patch.object(AUDIT, "fetch") as fetch, redirect_stderr(StringIO()), self.assertRaises(SystemExit):
                    AUDIT.main()
                fetch.assert_not_called()
            self.assertEqual("Existing operator evidence.", existing.read_text())

    def test_nonstring_inventory_origin_and_excessive_schema_set_fail_closed(self):
        with TemporaryDirectory() as directory:
            path = Path(directory) / "inventory.json"
            for origin in (None, {}, 123):
                path.write_text(json.dumps({"schema_version": 1, "base_url": origin, "pages": [{"url": BASE}]}))
                with self.assertRaises(ValueError):
                    AUDIT.load_inventory(path, BASE)
        with self.assertRaises(ValueError):
            AUDIT.required_schema_types(["Person"] * 17)
        for value in (True, 0, -1, "200"):
            with self.assertRaises(ValueError):
                AUDIT.minimum_main_characters(value)


class TransportTests(TestCase):
    """Check native fetch bounds and repeated policy headers without opening a socket."""

    def test_body_bound_headers_and_request_identity(self):
        native = BytesIO(b"abcdef")
        native.code = 200
        native.url = BASE
        native.headers = Message()
        native.headers.add_header("Content-Type", "text/html")
        native.headers.add_header("X-Robots-Tag", "googlebot: noindex")
        native.headers.add_header("X-Robots-Tag", "max-snippet:0")
        native.headers.add_header("Set-Cookie", "private=not-recorded")
        with patch.object(AUDIT, "MAX_BYTES", 4), patch.object(AUDIT, "build_opener") as opener:
            opener.return_value.open.return_value = native
            result = AUDIT.fetch(BASE, "bingbot")
        self.assertEqual("abcd", result["body"])
        self.assertTrue(result["truncated"])
        self.assertEqual(["googlebot: noindex", "max-snippet:0"], result["headers"]["x-robots-tag"])
        self.assertNotIn("private", json.dumps(result))
        request = opener.return_value.open.call_args.args[0]
        self.assertEqual(AUDIT.AGENTS["bingbot"], request.get_header("User-agent"))
        self.assertEqual({"timeout": 20}, opener.return_value.open.call_args.kwargs)

    def test_transport_failure_does_not_emit_raw_exception_details(self):
        with patch.object(AUDIT, "build_opener") as opener:
            opener.return_value.open.side_effect = TimeoutError("private details")
            result = AUDIT.fetch(BASE)
        self.assertEqual(0, result["status"])
        self.assertEqual("TimeoutError", result["error"])
        self.assertNotIn("private", json.dumps(result))


if __name__ == "__main__":
    main(verbosity=2)
