# Skye

Skye is a classic WordPress starter theme for custom websites, built with PHP components and **Bootstrap 6.0.0-alpha.1**. Posts and pages use the Classic Editor. Gutenberg editing and the block-based widget editor are disabled.

**[Explore Skye](https://skye.rocketsky.ch/)** · [Documentation](#documentation) · [Releases](https://github.com/rktsky/wptheme.skye/releases)

The Skye website is a standalone project built from this starter. Its design and content belong to that project.

## Start a project

Create a standalone copy or fork for the customer theme, for example `wptheme.customer`. Update its theme identity and text domain, and record the Skye version and source commit you started from. [README.tpl.md](README.tpl.md) is a manual project-documentation template.

### Requirements

- WordPress **6.8.12** or later and PHP **8.3** or later, using a compatible Core/PHP pair. The [test matrix](docs/testing.md) records the actual combinations checked.
- **Node 14.21.3 / npm 6.14.18** for build and watch, pinned in `.nvmrc`, `package.json` and `package-lock.json`. This preserves the existing CentOS 7 build workflow. Node 14 no longer receives upstream security fixes; the [build documentation](docs/build-deployment.md) explains the runtime limits and separate Node 24 test setup.
- **ACF PRO** when using its flexible-content builder. Install, license and update the original plugin separately. Native editor content also renders without ACF; a bound builder still needs its actual field profile.

Redis is optional infrastructure, installed and configured separately. Skye includes neither ACF PRO nor a persistent object-cache drop-in.

### Build and install

```sh
npm ci --ignore-scripts
npm run build
npm run watch
```

Install the theme with its complete `dist/` directory, then activate it in WordPress. A source checkout needs a build before it can serve its styles and scripts. For a deployable export from a clean committed checkout, follow the [release workflow](docs/release.md).

Watch rebuilds assets when asset files or relevant PHP sources change. Builds publish a complete asset set atomically; a failed build retains the previous output. Edit sources, then rebuild.

To configure the installed theme as WordPress's default theme, set `WP_DEFAULT_THEME` in the site's `wp-config.php` using the actual theme directory. [Configuration and fallback limits](docs/build-deployment.md#skye-als-standardtheme) explain the required setup.

Skye updates are reviewed merges into each project. Check that project's content, plugins and customizations before deployment. Bootstrap 6 Alpha updates need their own menu, form and browser checks; existing Bootstrap 5 projects require a migration.

## Theme structure

| Path | Responsibility |
| --- | --- |
| `lib/` | Application code, access checks and presentation models |
| `components/` | Reusable HTML loaded through `get_component()` |
| `views/` | Page, post, archive, search and error views |
| `assets/styles/` | Sass sources |
| `assets/scripts/` | Browser behavior |
| `build.mjs` | Asset compilation and manifest publication |

Prepare data in `lib/` and pass a small explicit context to the component:

```php
get_component( 'posts', 'element', array( 'article' => skye_post_model( get_the_ID() ) ) );
```

The loader also preserves one- and two-argument calls and native child-theme overrides. See [components and raw-data helpers](docs/components.md) for its path, context and compatibility contracts.

### ACF page building

The [source chooser](docs/editor-sources.md) keeps the Classic Editor and ACF
builder drafts in their original stores and selects one for rendering and analysis.
Both the standard page route and `templates/pagebuilder.php` use the shared source
dispatcher. The default builder field is `pagebuilder`; each layout uses its
matching `components/pagebuilder/` component. Define the actual fields and layouts
in the customer project. Skye does not ship a layout catalogue or field definitions.

The chooser preserves native inputs and synchronizes existing TinyMCE values
before switching or submitting. Its visibility and inactive-draft validation
integration requires the reviewed ACF PRO 6.8.11 contract. Unknown provider
contracts keep the native interfaces visible and retain native validation.

Core and ACF keep separate save phases. A rejected direct POST can already
have updated Core content before ACF validation fails; the chooser does not add
a shared transaction or rollback. See the [negative-save boundary](docs/editor-sources.md#grenze-bei-abgelehnten-speicheraufrufen).

When merging 3.5, review source and component overrides. A bound builder no longer
publishes the inactive editor when its content is empty; an explicitly saved
builder also stays bound if its field profile disappears. Pure native editor
markup now belongs in `components/page/native.php`; `components/page/content.php`
is the source dispatcher. The [migration details](docs/editor-sources.md#rendering-und-übernahme-bestehender-projekte)
explain the required project checks.

Public builder REST content, feeds and automatic excerpts use the same explicit
PHP semantic callback through [SourcePublicContext](docs/public-content-sources.md).
Without a valid projection, those builder exports stay empty. Review
`skye_yoast_analysis_content` and the actual export responses before merging.
Manual excerpts, explicit SEO/social descriptions and authorized native REST
edit reads retain their native rules.

The Classic Editor remains available for native content. A separate Classic Editor plugin is optional; Skye enforces classic editing itself. Previously stored block content continues to render through WordPress.

### Yoast and editorial analysis

Install Yoast SEO and the original **ACF Content Analysis for Yoast SEO** separately
when the project uses ACF. The ready source chooser connects automatically to the
[analysis bridge](docs/yoast-analysis.md): builder mode excludes the inactive
editor before the original ACF collector, and editor mode keeps Core content.
The collector itself remains unchanged. Unknown source readiness retains native
analysis.

A project may add a complete semantic projection with its actual headings,
links, images and selected records. No layout names, editorial copy or analysis
scores are supplied by the starter. Other ACF fields retain their original
provider behavior; the bridge does not infer the meaning of unknown layouts.

The [SEO acceptance workflow](docs/seo-acceptance.md) covers native editor checks,
social-image priorities, language variants, sitemaps and optional Yoast discovery.
The [crawler audit](docs/crawler-audit.md) compares anonymous public responses with
search-crawler request profiles. It is a separate development tool and does not
change indexing or bot policies.

## Performance and security

Skye uses native WordPress asset registration, dependency order and script strategies. The build produces content-hashed assets, full and mobile CSS, and source-bound critical CSS. It can generate an icon subset from the project's explicit inventory; complete icon styles remain the default until the project confirms coverage. Full page styles also remain the default. Progressive delivery requires a project-verified first viewport; unknown registrations, layouts and form providers retain their native delivery. See the [performance contracts](docs/performance.md) and [native form boundaries](docs/forms-native-contracts.md).

The media pipeline creates smaller WebP alternatives in bounded background jobs while retaining originals and attachment metadata. Responsive preloads use the same verified media source as the rendered image. [Media operations](docs/media.md) and [responsive preload contracts](docs/responsive-image-preloads.md) describe the available controls.

Password and optional Members checks run before protected content models or page-builder fields are read. Form adapters preserve native nonces, honeypots and provider controls. Staging mail is blocked through a documented native policy. Theme activation does not enable unrestricted SVG uploads or rewrite production content. [Security](docs/security.md) and [staging mail](docs/environment-mail.md) document these boundaries.

Admin colors and the login logo follow the existing site palette and brand identity, without a second set of editorial settings. See [backend and login branding](docs/backend-branding.md).

Project performance and browser coverage need their own checks. The [verification record](docs/testing.md) distinguishes measured results, test fixtures and remaining device coverage.

The three legacy theme settings use Core's finite option-cache priming before
ordinary native reads. Physical POSIX asset paths use a fresh native fast path;
aliases retain the bounded security checks. [Project learnings in 3.5.0](docs/project-learnings-3.5.0.md)
record these backports and the reviewed project-specific exclusions.

## Development

Read [AGENTS.md](AGENTS.md) and the [coding standards](docs/coding-standards.md) before making changes. Keep application code and data access in `lib/`, HTML in components and views, styles in Sass, and behavior in JavaScript. Commit logic, styling and formatting separately with their relevant verification evidence.

WordPress, plugins and third-party packages retain their original files and update paths. Keep plugin licenses, credentials and private deployment configuration outside this repository.

## Documentation

The detailed guides below are currently in German.

- [Build, configuration and dependencies](docs/build-deployment.md)
- [Builder and native editor sources](docs/editor-sources.md)
- [Public excerpts, REST and feeds](docs/public-content-sources.md)
- [Yoast analysis integration](docs/yoast-analysis.md)
- [SEO acceptance](docs/seo-acceptance.md)
- [Public crawler audit](docs/crawler-audit.md)
- [Project learnings in 3.5.0](docs/project-learnings-3.5.0.md)
- [Backend and login branding](docs/backend-branding.md)
- [Performance contracts and limits](docs/performance.md)
- [Native delivery, CDN and Core update setup](docs/website-delivery.md)
- [Native form boundaries](docs/forms-native-contracts.md)
- [Staging mail policy](docs/environment-mail.md)
- [Mobile navigation and child-theme integration](docs/mobile-navigation.md)
- [External links and theme helpers](docs/external-links.md)
- [Media pipeline and operations](docs/media.md)
- [Responsive image preloads](docs/responsive-image-preloads.md)
- [Components and raw-data helpers](docs/components.md)
- [Security and separate infrastructure](docs/security.md)
- [Migration from Skye 2](docs/migration-3.md)
- [Backport provenance](docs/backports.md)
- [Testing and remaining coverage](docs/testing.md)
- [Release export and checks](docs/release.md)
- [Legacy Git and release boundaries](docs/release-compatibility.md)

## License

Skye's own code is licensed under [BSD-3-Clause](LICENSE). Third-party packages retain their own licenses; the build publishes the applicable notices in `dist/licenses/`. ACF PRO code, licenses and credentials are kept separate from this public repository.
