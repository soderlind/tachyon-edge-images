# Tachyon Edge Images

Rewrites WordPress image URLs to a [Tachyon](https://github.com/humanmade/tachyon) server. It replaces Human Made's [tachyon-plugin](https://github.com/humanmade/tachyon-plugin) (the Reference Plugin) and stays compatible with it: same functions, class, hooks and constants, and the same URLs for the same input. The exceptions are seven documented bug fixes (Deviations).

It works with any Tachyon server, including [Tachyon Edge](https://github.com/soderlind/tachyon-edge) on Cloudflare. Pair it with [Tachyon R2](https://github.com/soderlind/tachyon-r2) to store the images in R2.

## Requirements

- WordPress 7.0 or later
- PHP 8.3 or later
- A Tachyon server

## Install

1. Download `tachyon-edge-images.zip` from the [latest release](https://github.com/soderlind/tachyon-edge-images/releases/latest) and upload it in **Plugins → Add New → Upload Plugin**.
2. Add `TACHYON_URL` to `wp-config.php` (see below).
3. Activate the plugin.

### Switching from tachyon-plugin

No configuration changes are needed: Tachyon Edge Images reads the same constants.

1. Activate Tachyon Edge Images. While tachyon-plugin is active, Tachyon Edge Images does nothing (Stand-down) and shows an admin notice.
2. Click **Deactivate Tachyon** in the notice, or deactivate tachyon-plugin on the Plugins screen.

Tachyon Edge Images never deactivates tachyon-plugin by itself. If tachyon-plugin is a must-use plugin or loaded through Composer, the notice tells you to remove it. On multisite, a network-activated tachyon-plugin must be deactivated by a network administrator.

To switch back, deactivate Tachyon Edge Images first. With Tachyon Edge Images running, activating tachyon-plugin fails: WordPress reports a fatal error (`Cannot redeclare tachyon_url()`) and refuses the activation. The site is not affected.

## Configure

```php
// Required: the Tachyon server URL that replaces the uploads base URL.
define( 'TACHYON_URL', 'https://images.example.com/wp-content/uploads' );

// Optional: the Tachyon server version. 3.0.0 or later enables presigned S3 URLs.
define( 'TACHYON_SERVER_VERSION', '3.0.0' );

// Optional: add arguments to URLs that already point at the Tachyon server (default false).
define( 'TACHYON_EDGE_IMAGES_EXTEND_TACHYON_URLS', true );
```

Without `TACHYON_URL`, `tachyon_url()` and `tachyon_server_version()` still exist, as in tachyon-plugin, but `tachyon_url()` returns the URL unchanged and images are not rewritten.

There is no settings page.

## Compatibility

Tachyon Edge Images has the same Public API as tachyon-plugin 0.11.1:

- Functions `tachyon_url( $image_url, $args = [], $scheme = null )` and `tachyon_server_version()`.
- `class Tachyon` (`Tachyon::instance()`) with the same methods, properties and visibility, registered on the same hooks at the same priorities.
- The same `tachyon_*` filters with the same arguments: `tachyon_skip_for_url`, `tachyon_pre_image_url`, `tachyon_pre_args`, `tachyon_url`, `tachyon_server_version`, `tachyon_skip_image`, `tachyon_image_is_local`, `tachyon_post_image_args`, `tachyon_remove_size_attributes`, `tachyon_disable_in_admin`, `tachyon_override_image_downsize`, `tachyon_image_downsize_string`, `tachyon_image_downsize_array`, `tachyon_srcset_image_args` and `tachyon_validate_image_url`.

Every pull request runs a differential test that executes the same cases against tachyon-plugin (pinned at `652ecd2`) and Tachyon Edge Images in a real WordPress. It compares the output, the `tachyon_*` filter calls and their arguments, the hook registrations and the class signature. Cases tagged with a Deviation must differ; all others must be identical.

### Deviations

Deviations fix output no working site can depend on. They are always on.

1. **Escaped link URLs.** An image link whose URL contains regex characters, such as `photo(1).jpg`, is now rewritten to Tachyon. tachyon-plugin used the URL as a regex pattern and left the link alone.
2. **Size detection per image.** Each image in the content starts with a fresh size filter, and size detection passes the size name (`medium`) to filters, not the size settings array. tachyon-plugin carried the filter over from the previous image.
3. **No warnings or empty resizes.** Missing image sources, metadata or `TACHYON_URL` no longer cause PHP warnings, errors, or `resize=,` / `fit=,` arguments.
4. **`&` after an existing query.** String arguments are joined with `&` when the URL already has a query (`photo.jpg?ver=2&w=300`, not `?ver=2?w=300`).
5. **Arguments before the fragment.** String arguments go before a `#fragment` (`photo.jpg?w=300#top`, not `#top?w=300`).
6. **No double encoding.** An already-encoded file name (`my%20photo.jpg`) is not encoded again (`my%2520photo.jpg`).
7. **Original images in the block editor.** REST requests from the block editor get original image URLs, not resized Tachyon URLs, so the editor shows the full image. A nested REST request no longer switches Tachyon back on too early. A small editor script marks the editor's requests.

### Plugin Quirks

Odd tachyon-plugin behaviour that is kept because sites may depend on it:

- `tachyon_url()` returns a URL that already points at the Tachyon server unchanged and drops the new arguments. Set `TACHYON_EDGE_IMAGES_EXTEND_TACHYON_URLS` or the `tachyon_edge_images_extend_tachyon_urls` filter to add them instead.
- On multisite, `/sites/N` is stripped from the uploads base URL before it is replaced with `TACHYON_URL`.
- A `size-*` class is looked up before the attachment ID is known, so it uses the global post rather than the image's attachment.
- Matching an image's `width`/`height` attributes against attachment sizes compares strings with integers, so it never matches.

### Known limitations

- Subclassing `Tachyon` to override its protected static helpers (`image_sizes()`, `strip_image_dimensions_maybe()`) has no effect: the internals call the helpers directly.

## Site Health

**Tools → Site Health** shows whether Tachyon Edge Images is active, standing down, or missing `TACHYON_URL`. It also requests the latest uploaded image through the Tachyon server, and reports a problem if it doesn't get an image back. The **Info** tab lists the version, configuration and Deviations.

## Filters

All tachyon-plugin filters work as before. One filter is new:

- `tachyon_edge_images_extend_tachyon_urls` (`bool $extend, string $image_url, array|string $args`): whether `tachyon_url()` adds arguments to a URL that already points at the Tachyon server. Defaults to `TACHYON_EDGE_IMAGES_EXTEND_TACHYON_URLS`, or false.

## Development

```bash
composer install
composer test               # PHPUnit + Brain Monkey
composer test:js            # Editor script (node --test)
composer test:differential  # Compare with tachyon-plugin in a throwaway WordPress (needs WP-CLI)
composer lint
```

The differential test downloads WordPress, the SQLite database drop-in and tachyon-plugin into `tests/differential/.work/`. Set `DIFF_REUSE=1` to reuse that install, or `REFERENCE_DIR=../tachyon-plugin` to use a local checkout.

[GLOSSARY.md](GLOSSARY.md) defines the terms used here, and [docs/adr/](docs/adr/) records the design decisions.

## License

GPL-2.0-or-later. Based on [Tachyon](https://github.com/humanmade/tachyon-plugin) by Human Made Limited and Automattic Inc.
