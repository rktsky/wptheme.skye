#!/usr/bin/env bash
# Install the pinned browser's native dependencies on the disposable Ubuntu CI runner.
set -euo pipefail

if [ "$#" -ne 2 ] || [ "${GITHUB_ACTIONS:-}" != true ]; then
	printf 'Usage on GitHub Actions: bash tools/testing/browser-dependencies.sh /absolute/playwright/cli.js browser\n' >&2
	exit 2
fi
cli=$1
browser=$2
case "$browser" in
	chromium|firefox|webkit) ;;
	*) printf 'Unknown browser.\n' >&2; exit 2 ;;
esac
case "$cli" in
	/*) test -f "$cli" ;;
	*) printf 'The browser CLI path must be absolute.\n' >&2; exit 2 ;;
esac
distribution=$(. /etc/os-release; printf '%s:%s' "$ID" "$VERSION_ID")
test "$distribution" = ubuntu:24.04
test "$(uname -m)" = x86_64

# Keep repository/configuration files outside /etc; retain native archive signatures.
apt_directory=$(mktemp -d "$RUNNER_TEMP/skye-browser-apt.XXXXXX")
chmod 755 "$apt_directory"
mkdir -p "$apt_directory/lists/partial"
cat > "$apt_directory/ubuntu.sources" <<'SOURCES'
Types: deb
URIs: https://archive.ubuntu.com/ubuntu
Suites: noble noble-updates noble-backports
Components: main restricted universe multiverse
Signed-By: /usr/share/keyrings/ubuntu-archive-keyring.gpg

Types: deb
URIs: https://security.ubuntu.com/ubuntu
Suites: noble-security
Components: main restricted universe multiverse
Signed-By: /usr/share/keyrings/ubuntu-archive-keyring.gpg
SOURCES
cat > "$apt_directory/apt.conf" <<CONFIG
Dir::Etc::sourcelist "$apt_directory/ubuntu.sources";
Dir::Etc::sourceparts "-";
Dir::State::lists "$apt_directory/lists";
Acquire::https::Timeout "30";
Acquire::Retries "2";
CONFIG

# Run as root explicitly so the native installer retains APT_CONFIG across its child.
sudo env APT_CONFIG="$apt_directory/apt.conf" "$(command -v node)" "$cli" install-deps "$browser"
