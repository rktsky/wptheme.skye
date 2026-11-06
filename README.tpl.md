# Project name

Standalone WordPress theme based on [Skye](https://github.com/rktsky/wptheme.skye). Replace this template with the project's actual setup and operating record.

## Project baseline

Record the website URL, installed theme directory and text domain, starting Skye version/source commit, and date of the fork. List the actual WordPress/PHP pair, deployment environments and maintenance contacts.

ACF PRO and Redis remain separate installations. Document the original plugins in use and their update paths. Keep licenses, credentials and private server configuration outside Git.

## Development

Use the project's pinned Node/npm runtime. The Skye baseline retains Node 14.21.3 / npm 6.14.18 for existing CentOS 7 build systems; record any reviewed toolchain changes.

```sh
npm ci --ignore-scripts
npm run build
npm run watch
```

Add project-specific configuration and required commands. Deploy the complete built `dist/` output using the documented release process. Follow `AGENTS.md` and the coding standards; keep logic, styling and formatting commits separate.

## Content and components

Document the project's native editor content and ACF fields/layouts, their matching components, shared options and access boundaries. Record local changes to Skye and how later upstream changes are reviewed and merged.

## Operations and verification

Record enabled performance contracts, image sizes, media/cron operation, mail policy, CDN/cache configuration and migration steps. Link actual build, security and browser results, including open device checks. Keep project evidence separate from the inherited Skye test record.
