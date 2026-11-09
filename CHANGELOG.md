# Changelog

## [Unreleased]

### Internal

- The content, `image_downsize` and srcset paths read site data through Image Facts (ADR 0003), and have unit tests. No change in output.
- `Content::filter()` is split into private steps (image source, dimensions, content width, Tachyon arguments, tag rewrite), with tests for tag rewriting. `gravity()` moved to `Images`. No change in output.
- `Rest` keeps its stack of nested REST requests on an instance, so the test-only `reset()` is gone. A test checks that `editor.js` sends the header `Rest` reads. No change in output.

## [0.1.0] - 2026-11-09

### Added

- First release. A compatible replacement for tachyon-plugin 0.11.1: the same `tachyon_url()`, `tachyon_server_version()`, `class Tachyon`, `tachyon_*` filters and constants.
- Seven Deviations that fix tachyon-plugin bugs: escaped link URLs, per-image size detection, no PHP warnings or empty resizes, `&` after an existing query, arguments before a fragment, no double encoding, and original images in the block editor.
- Stand-down: while tachyon-plugin is active, the plugin does nothing and shows an admin notice with a **Deactivate Tachyon** button.
- Site Health test and debug information.
- Opt-in `TACHYON_EDGE_IMAGES_EXTEND_TACHYON_URLS` constant and `tachyon_edge_images_extend_tachyon_urls` filter to add arguments to existing Tachyon URLs.
- Differential test against tachyon-plugin in CI.
