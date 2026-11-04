#!/usr/bin/env python3
"""Prepare an owned, disposable WordPress/SQLite fixture beneath this checkout's .git/runtime.

Original release files are checksum-verified and never patched. This program does
not start a server, contact a real site, or embed credentials in tracked files.
"""

import argparse
import hashlib
import json
import os
from pathlib import Path, PurePosixPath
import secrets
import shutil
import stat
import subprocess
import sys
import tarfile
import tempfile
import urllib.request
import zipfile

WORDPRESS = {
    "7.1.4": "881e53a4681ff2cd911651b11cccdede5ae477ddf0765af5d6a1a8b219a1d835",
    "6.8.12": "4bdd6bef38ac46f80acc0d3bf686d96708b5a44dab6bb66a0c97d3c3d740371e",
}
SQLITE = {
    "version": "3.1.3",
    "url": "https://downloads.wordpress.org/plugin/sqlite-database-integration.3.1.3.zip",
    "sha256": "f6ce35e8419d0b9326221a907627d905e25c2a5ba2afc86492811f14c851efe0",
}
CLASSIC = {
    "version": "1.7.0",
    "url": "https://downloads.wordpress.org/plugin/classic-editor.1.7.0.zip",
    "sha256": "7cc7799b0b7d820fbb7528dbff8027c736e52513992416cb37279f44d3d9dd77",
}
BASE_URL = "http://127.0.0.1:8973"
MAX_ARCHIVE_BYTES = 100 * 1024 * 1024
MAX_EXTRACTED_BYTES = 512 * 1024 * 1024


def digest(path: Path) -> str:
    """Hash an existing regular file without loading release archives into memory."""
    value = hashlib.sha256()
    with path.open("rb") as stream:
        for chunk in iter(lambda: stream.read(1024 * 1024), b""):
            value.update(chunk)
    return value.hexdigest()


def beneath(path: Path, parent: Path) -> Path:
    """Require a strict descendant and reject pre-existing symlink traversal."""
    path = Path(os.path.abspath(path))
    if path == parent or not path.is_relative_to(parent):
        raise RuntimeError(f"Fixture paths must be strict descendants of {parent}.")
    current = parent
    for part in path.relative_to(parent).parts:
        current = current / part
        if current.is_symlink():
            raise RuntimeError(f"Refusing a symlink at controlled fixture path: {current}")
    if not path.resolve().is_relative_to(parent.resolve()):
        raise RuntimeError("Fixture path escapes the private runtime directory.")
    return path


def write_json(path: Path, value: dict) -> None:
    """Atomically update an owned private evidence file without exposing its contents."""
    path.parent.mkdir(parents=True, exist_ok=True)
    with tempfile.NamedTemporaryFile("w", dir=path.parent, prefix=".skye-evidence-", delete=False) as stream:
        temporary = Path(stream.name)
        os.chmod(temporary, 0o600)
        json.dump(value, stream, indent=2)
        stream.write("\n")
    try:
        os.replace(temporary, path)
    finally:
        temporary.unlink(missing_ok=True)


def original_archive(cache: Path, name: str, url: str, expected: str) -> Path:
    """Download only a pinned HTTPS release and verify it before any extraction."""
    target = cache / name
    if target.is_symlink():
        raise RuntimeError("Refusing a symlink in the release download cache.")
    if not target.exists():
        with tempfile.NamedTemporaryFile(dir=cache, prefix=".skye-download-", delete=False) as stream:
            temporary = Path(stream.name)
            try:
                request = urllib.request.Request(url, headers={"User-Agent": "Skye-disposable-fixture/3.0"})
                with urllib.request.urlopen(request, timeout=60) as response:
                    if not response.geturl().startswith("https://"):
                        raise RuntimeError("Release download left HTTPS.")
                    size = 0
                    while chunk := response.read(1024 * 1024):
                        size += len(chunk)
                        if size > MAX_ARCHIVE_BYTES:
                            raise RuntimeError("Release archive exceeds the explicit download limit.")
                        stream.write(chunk)
                stream.flush()
                if digest(temporary) != expected:
                    raise RuntimeError(f"Release checksum mismatch: {name}")
                os.replace(temporary, target)
            finally:
                temporary.unlink(missing_ok=True)
    if not target.is_file() or digest(target) != expected:
        raise RuntimeError(f"Cached release checksum mismatch: {name}")
    return target


def member_path(name: str, prefix: str) -> PurePosixPath:
    """Reject links and ambiguous archive names before joining any destination."""
    relative = PurePosixPath(name)
    if "\\" in name or "\0" in name or relative.is_absolute() or ".." in relative.parts or any( ":" in part for part in relative.parts ):
        raise RuntimeError("Unsafe path in pinned release archive.")
    if not relative.parts or relative.parts[0] != prefix:
        raise RuntimeError("Unexpected release archive root.")
    return relative


def unpack_original(archive: Path, parent: Path, prefix: str, destination: Path) -> None:
    """Extract regular release files into a new staging directory, then rename once."""
    if destination.exists() or destination.is_symlink():
        raise RuntimeError(f"Refusing to overwrite an existing package: {destination}")
    with tempfile.TemporaryDirectory(prefix=".skye-package-", dir=parent) as temporary:
        stage = Path(temporary)
        total = 0
        if archive.name.endswith(".zip"):
            with zipfile.ZipFile(archive) as package:
                for item in package.infolist():
                    relative = member_path(item.filename, prefix)
                    mode = item.external_attr >> 16
                    if stat.S_IFMT(mode) not in (0, stat.S_IFREG, stat.S_IFDIR) or item.flag_bits & 1:
                        raise RuntimeError("Links/encrypted entries are not allowed in fixture packages.")
                    total += item.file_size
                    if total > MAX_EXTRACTED_BYTES:
                        raise RuntimeError("Extracted package exceeds the explicit size limit.")
                    target = stage.joinpath(*relative.parts)
                    if item.is_dir():
                        target.mkdir(parents=True, exist_ok=True)
                    else:
                        target.parent.mkdir(parents=True, exist_ok=True)
                        with package.open(item) as source, target.open("xb") as output:
                            shutil.copyfileobj(source, output)
                        target.chmod(0o644)
        else:
            with tarfile.open(archive, "r:gz") as package:
                for item in package:
                    relative = member_path(item.name, prefix)
                    if not item.isdir() and not item.isfile():
                        raise RuntimeError("Only original regular files/directories may be extracted.")
                    total += item.size
                    if total > MAX_EXTRACTED_BYTES:
                        raise RuntimeError("Extracted package exceeds the explicit size limit.")
                    target = stage.joinpath(*relative.parts)
                    if item.isdir():
                        target.mkdir(parents=True, exist_ok=True)
                    else:
                        target.parent.mkdir(parents=True, exist_ok=True)
                        source = package.extractfile(item)
                        if source is None:
                            raise RuntimeError("Release archive member cannot be read.")
                        with source, target.open("xb") as output:
                            shutil.copyfileobj(source, output)
                        target.chmod(0o644)
        destination.parent.mkdir(parents=True, exist_ok=True)
        (stage / prefix).rename(destination)


def private_php(path: Path, content: str, parent: Path) -> None:
    """Create an owned generated file, reusing exact bytes and refusing foreign changes."""
    beneath(path, parent)
    if path.exists():
        if not path.is_file() or path.read_text() != content:
            raise RuntimeError(f"Refusing to replace an existing foreign/modified fixture file: {path}")
        return
    path.parent.mkdir(parents=True, exist_ok=True)
    descriptor = os.open(path, os.O_WRONLY | os.O_CREAT | os.O_EXCL, 0o600)
    with os.fdopen(descriptor, "w") as stream:
        stream.write(content)


def php_quote(value: str) -> str:
    """Encode generated configuration literals without shell interpolation."""
    return "'" + value.replace("\\", "\\\\").replace("'", "\\'") + "'"


def run_php(binary: str, script: Path, site: Path, repository: Path) -> str:
    """Run only a known local fixture program and fail on any unsuccessful process."""
    environment = os.environ.copy()
    environment["SKYE_SITE_ROOT"] = str(site)
    result = subprocess.run([binary, str(script)], cwd=repository, env=environment, text=True, capture_output=True)
    if result.returncode:
        raise RuntimeError(f"Fixture PHP failed ({script.name}): {result.stderr.strip() or result.stdout.strip()}")
    if result.stderr.strip():
        raise RuntimeError(f"Fixture PHP emitted unexpected diagnostics ({script.name}): {result.stderr.strip()}")
    return result.stdout


def main() -> None:
    """Prepare or repeat one explicitly owned local installation; never delete an existing site."""
    repository = Path(__file__).resolve().parents[2]
    runtime = repository / ".git" / "runtime"
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--root", type=Path, default=runtime / "site", help="Empty/owned fixture directory beneath this checkout's .git/runtime.")
    parser.add_argument("--wordpress-version", choices=tuple(WORDPRESS), default="7.1.4")
    parser.add_argument("--php-binary", default=os.environ.get("SKYE_PHP_BINARY", "php"))
    parser.add_argument("--classic-editor", action="store_true", help="Install original Classic Editor 1.7.0, leaving it inactive for the first test phase.")
    parser.add_argument("--fixture-output", type=Path, default=runtime / "site-fixture.json")
    args = parser.parse_args()
    if not (repository / ".git").is_dir() or (repository / ".git").is_symlink():
        raise RuntimeError("This fixture helper requires this checkout's real .git directory.")
    if runtime.is_symlink():
        raise RuntimeError("Refusing a symlinked private runtime directory.")
    root = beneath(args.root, runtime)
    output = beneath(args.fixture_output, runtime)
    marker = root / ".skye-disposable.json"
    owner = {"schema": 1, "repository": str(repository), "root": str(root), "wordpress": args.wordpress_version}
    state = dict(owner)
    if root.exists():
        if not root.is_dir():
            raise RuntimeError("Fixture root must be a directory.")
        if marker.is_symlink():
            raise RuntimeError("Refusing a linked fixture ownership marker.")
        if marker.is_file():
            state = json.loads(marker.read_text())
            if not isinstance(state, dict):
                raise RuntimeError("Invalid fixture ownership marker.")
            if any(state.get(key) != value for key, value in owner.items()):
                raise RuntimeError("Existing fixture ownership/version does not match; choose a fresh runtime root.")
        elif any(root.iterdir()):
            raise RuntimeError("Existing directory is not an owned Skye fixture; nothing was replaced.")
    if output.exists():
        previous = json.loads(output.read_text())
        if not isinstance(previous, dict) or previous.get("site_root") != str(root / "wordpress"):
            raise RuntimeError("Fixture output belongs to another/unmarked site; choose --fixture-output explicitly.")
    existing_config = root / "wordpress" / "wp-config.php"
    beneath(existing_config, root)
    if existing_config.exists() and (not state.get("config_sha256") or digest(existing_config) != state["config_sha256"]):
        raise RuntimeError("Existing WordPress configuration is foreign/modified; no packages were changed.")
    php_check = (
        "$ok=PHP_VERSION_ID>=80300&&extension_loaded('pdo_sqlite')&&extension_loaded('mbstring')&&extension_loaded('dom')&&extension_loaded('gd');"
        "$sqlite=$ok?(new PDO('sqlite::memory:'))->query('SELECT sqlite_version()')->fetchColumn():null;"
        "echo json_encode(['version'=>PHP_VERSION,'sqlite'=>$sqlite,'ok'=>$ok&&version_compare((string)$sqlite,'3.37.0','>=')]);"
    )
    check = subprocess.run([args.php_binary, "-r", php_check], text=True, capture_output=True, check=True)
    php = json.loads(check.stdout)
    if not php["ok"]:
        raise RuntimeError("Fixture requires PHP >=8.3, SQLite >=3.37.0, PDO SQLite, mbstring, DOM and GD; no tests were skipped.")

    root.mkdir(parents=True, exist_ok=True, mode=0o700)
    write_json(marker, state)
    cache = runtime / "downloads"
    if cache.is_symlink():
        raise RuntimeError("Refusing a linked download cache.")
    cache.mkdir(parents=True, exist_ok=True)
    core = {"version": args.wordpress_version, "url": f"https://wordpress.org/wordpress-{args.wordpress_version}.tar.gz", "sha256": WORDPRESS[args.wordpress_version]}
    sources = {"wordpress": core, "sqlite": SQLITE}
    if args.classic_editor or state.get("classic_editor"):
        sources["classic_editor"] = CLASSIC
    archives = {
        name: original_archive(cache, package["url"].rsplit("/", 1)[-1], package["url"], package["sha256"])
        for name, package in sources.items()
    }
    site = root / "wordpress"
    beneath(site, root)
    if not site.exists():
        unpack_original(archives["wordpress"], root, "wordpress", site)
    else:
        version_file = site / "wp-includes" / "version.php"
        if not version_file.is_file() or f"'{args.wordpress_version}'" not in version_file.read_text():
            raise RuntimeError("Existing Core does not match the selected immutable release.")
    content = site / "wp-content"
    for path in (content, content / "plugins", content / "mu-plugins", content / "themes"):
        beneath(path, root)
        path.mkdir(parents=True, exist_ok=True)
    sqlite = content / "plugins" / "sqlite-database-integration"
    beneath(sqlite, root)
    if not sqlite.exists():
        unpack_original(archives["sqlite"], root, "sqlite-database-integration", sqlite)
    dropin = content / "db.php"
    beneath(dropin, root)
    original_dropin = (sqlite / "db.copy").read_bytes()
    if dropin.exists() and dropin.read_bytes() != original_dropin:
        raise RuntimeError("Existing database drop-in is foreign/modified; nothing was overwritten.")
    if not dropin.exists():
        dropin.write_bytes(original_dropin)
    if "classic_editor" in sources:
        plugin = content / "plugins" / "classic-editor"
        beneath(plugin, root)
        if not plugin.exists():
            unpack_original(archives["classic_editor"], root, "classic-editor", plugin)
        state["classic_editor"] = True
    theme = content / "themes" / "skye"
    if theme.is_symlink():
        if theme.resolve() != repository:
            raise RuntimeError("Existing theme link targets another checkout.")
    elif theme.exists():
        raise RuntimeError("Existing skye theme is not this checkout's owned fixture link.")
    else:
        theme.symlink_to(repository, target_is_directory=True)
    config = site / "wp-config.php"
    beneath(config, root)
    if config.exists():
        if not state.get("config_sha256") or digest(config) != state["config_sha256"]:
            raise RuntimeError("Existing WordPress configuration is foreign/modified; nothing was overwritten.")
    else:
        constants = {
            "DB_NAME": "skye_disposable", "DB_USER": "unused", "DB_PASSWORD": "unused", "DB_HOST": "localhost",
            "DB_PATH": str(root / "database.sqlite"), "WP_HOME": BASE_URL, "WP_SITEURL": BASE_URL,
            "WP_ENVIRONMENT_TYPE": "local", "WP_DEBUG_LOG": str(root / "debug.log"),
        }
        for key in ("AUTH_KEY", "SECURE_AUTH_KEY", "LOGGED_IN_KEY", "NONCE_KEY", "AUTH_SALT", "SECURE_AUTH_SALT", "LOGGED_IN_SALT", "NONCE_SALT"):
            constants[key] = secrets.token_urlsafe(64)
        source = "<?php\n/** Owned disposable Skye configuration; never use for a public installation. */\n"
        source += "\n".join(f"define( {php_quote(key)}, {php_quote(value)} );" for key, value in constants.items())
        source += "\ndefine( 'WP_DEBUG', true );\ndefine( 'WP_DEBUG_DISPLAY', false );\ndefine( 'DISABLE_WP_CRON', true );\ndefine( 'WP_AUTO_UPDATE_CORE', false );\ndefine( 'AUTOMATIC_UPDATER_DISABLED', true );\ndefine( 'DISALLOW_FILE_MODS', true );\ndefine( 'WP_HTTP_BLOCK_EXTERNAL', true );\ndefine( 'WP_ACCESSIBLE_HOSTS', '127.0.0.1' );\n$table_prefix = 'wp_';\nif ( ! defined( 'ABSPATH' ) ) {\n\tdefine( 'ABSPATH', __DIR__ . '/' );\n}\nrequire_once ABSPATH . 'wp-settings.php';\n"
        private_php(config, source, root)
        state["config_sha256"] = digest(config)
    write_json(marker, state)
    blocker = """<?php
/** Block test mail and external HTTP before any optional provider can transmit. */

/**
 * Treat fixture mail as handled without invoking a transport.
 *
 * @since 3.0.0
 * @param mixed $result Existing native preflight response.
 * @return bool True to bypass all mail transports.
 */
function skye_fixture_block_mail( $result ) {
	return true;
}
add_filter( 'pre_wp_mail', 'skye_fixture_block_mail', PHP_INT_MAX );

/**
 * Restrict native HTTP to the exact private test server origin.
 *
 * @since 3.0.0
 * @param mixed $result Existing native preflight response.
 * @param array $args Native HTTP arguments.
 * @param string $url Requested destination.
 * @return mixed Original local response or a blocked external request error.
 */
function skye_fixture_block_http( $result, $args, $url ) {
	$parts = wp_parse_url( $url );
	if ( is_array( $parts ) && 'http' === ( $parts['scheme'] ?? '' ) && '127.0.0.1' === ( $parts['host'] ?? '' ) && 8973 === ( $parts['port'] ?? 0 ) && ! isset( $parts['user'] ) && ! isset( $parts['pass'] ) ) {
		return $result;
	}
	return new WP_Error( 'skye_fixture_blocked', 'External HTTP is disabled in the disposable fixture.' );
}
add_filter( 'pre_http_request', 'skye_fixture_block_http', PHP_INT_MAX, 3 );
"""
    private_php(content / "mu-plugins" / "skye-fixture-boundaries.php", blocker, root)
    activation = """<?php
/** Activate only the owned local fixture theme and reset its known optional editor plugin. */
$site = realpath( getenv( 'SKYE_SITE_ROOT' ) ?: '' );
if ( 'cli' !== PHP_SAPI || ! $site || ! str_contains( $site, '/.git/runtime/' ) ) {
	throw new RuntimeException( 'Only an isolated .git/runtime fixture may be activated.' );
}
require $site . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
wp_set_current_user( 1 );
deactivate_plugins( 'classic-editor/classic-editor.php', true );
switch_theme( 'skye' );
$menu = wp_get_nav_menu_object( 'Fixture navigation' );
if ( ! $menu ) {
	throw new RuntimeException( 'The native fixture menu was not created.' );
}
set_theme_mod( 'nav_menu_locations', array( 'primary_navigation' => $menu->term_id ) );
echo "Owned fixture activated.\\n";
"""
    helper = root / "activate-fixture.php"
    private_php(helper, activation, root)
    log = root / "debug.log"
    log_size = log.stat().st_size if log.is_file() else 0
    raw_fixture = run_php(args.php_binary, repository / "tests" / "site-fixture.php", site, repository).strip()
    try:
        fixture = json.loads(raw_fixture)
    except ValueError as error:
        raise RuntimeError("site-fixture.php did not return its expected JSON; inspect the owned fixture diagnostics.") from error
    run_php(args.php_binary, helper, site, repository)
    if log.is_file() and log.stat().st_size > log_size:
        raise RuntimeError(f"WordPress emitted new diagnostics; inspect private log {log}.")
    fixture["site_root"] = str(site)
    fixture["base_url"] = BASE_URL
    write_json(output, fixture)
    write_json(root / "provenance.json", {"sources": sources, "php": php["version"], "sqlite_library": php["sqlite"], "source_bytes_modified": False, "external_transports": "mail and external HTTP blocked"})
    state["stage"] = "ready"
    state["fixture_output"] = str(output)
    write_json(marker, state)
    print(json.dumps({"status": "ready", "wordpress": args.wordpress_version, "php": php["version"], "site_root": str(site), "fixture_output": str(output), "classic_editor": "installed inactive" if "classic_editor" in sources else "not installed", "server_started": False}, indent=2))


if __name__ == "__main__":
    try:
        main()
    except (OSError, ValueError, RuntimeError, subprocess.CalledProcessError, tarfile.TarError, zipfile.BadZipFile) as error:
        print(f"Fixture setup failed: {error}", file=sys.stderr)
        sys.exit(1)
