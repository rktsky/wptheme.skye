#!/usr/bin/env bash
# Verify an original CentOS 7 userland with an unmodified official Node 14 runtime.
# JavaScript Actions run on the Ubuntu host; this container shares its host kernel.
# Pins checked 2026-11-04 against Quay registry manifest bytes and Node SHASUMS256.txt:
# https://quay.io/api/v1/repository/centos/centos/tag/?specificTag=7
# https://nodejs.org/dist/v14.21.3/SHASUMS256.txt
set -euo pipefail

repository=$(cd "$(dirname "$0")/../.." && pwd -P)
image='quay.io/centos/centos@sha256:e4ca2ed0202e76be184e75fb26d14bf974193579039d5573fb2348664deef76e'
archive='node-v14.21.3-linux-x64.tar.xz'
checksum='05c08a107c50572ab39ce9e8663a2a2d696b5d262d5bd6f98d84b997ce932d9a'
node_checksum='c993f18a419aac830e324640c3ca0429ce4c447544c0881b20b5d4d052e61496'
if [ "$#" -ne 1 ]; then
	printf 'Usage: bash tools/testing/legacy-build.sh /absolute/new/evidence-directory\n' >&2
	exit 2
fi
evidence=$1
case "$evidence" in
	/*) ;;
	*) printf 'Evidence directory must be absolute.\n' >&2; exit 2 ;;
esac
if [ -e "$evidence" ] || [ -L "$evidence" ]; then
	printf 'Evidence directory already exists; nothing was replaced.\n' >&2
	exit 2
fi
docker info >/dev/null
temporary=$(mktemp -d)
# This exact temporary directory belongs to this invocation; never remove caller paths.
trap 'rm -rf -- "$temporary"' EXIT
mkdir -m 0700 "$evidence"
curl --fail --location --proto '=https' --tlsv1.2 --retry 2 \
	"https://nodejs.org/dist/v14.21.3/$archive" --output "$temporary/$archive"
printf '%s  %s\n' "$checksum" "$temporary/$archive" | sha256sum --check --status
tar -xJf "$temporary/$archive" -C "$temporary"
printf '%s  %s\n' "$node_checksum" "$temporary/node-v14.21.3-linux-x64/bin/node" | sha256sum --check --status
mkdir -m 0700 "$temporary/work"
# The exact ELF version-needs table was inspected, not inferred from its Node major.
printf '{"image":"%s","node_archive":"%s","node_sha256":"%s","node_elf_sha256":"%s","elf_requirements":{"glibc_max":"2.17","glibcxx_max":"3.4.18","cxxabi_max":"1.3.5"},"kernel_shared_with_host":true}\n' \
	"$image" "$archive" "$checksum" "$node_checksum" > "$evidence/provenance.json"
docker pull --platform linux/amd64 "$image"
docker image inspect "$image" > "$evidence/image.json"
# No system package installation, vendor patch, host Docker socket or privileged mode.
docker run --rm --platform linux/amd64 --read-only \
	--user "$(id -u):$(id -g)" --cap-drop ALL --security-opt no-new-privileges \
	--pids-limit 256 --tmpfs /tmp:rw,nosuid,nodev,noexec \
	--mount "type=bind,source=$repository,target=/source,readonly" \
	--mount "type=bind,source=$temporary/node-v14.21.3-linux-x64,target=/opt/node,readonly" \
	--mount "type=bind,source=$temporary/work,target=/work" \
	--mount "type=bind,source=$evidence,target=/evidence" \
	--env PATH=/opt/node/bin:/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin \
	--env HOME=/work --env npm_config_cache=/work/npm-cache \
	--env npm_config_engine_strict=true --env npm_config_ignore_scripts=true \
	--env SKYE_BUILD_NODE=/opt/node/bin/node --env "SKYE_LEGACY_IMAGE=$image" \
	"$image" /bin/bash -c 'set -euo pipefail; cat /etc/centos-release; /usr/bin/getconf GNU_LIBC_VERSION; ldd /opt/node/bin/node; exec /opt/node/bin/node /source/tools/testing/legacy-build.cjs /source /work /evidence' \
	2>&1 | tee "$evidence/container.log"
