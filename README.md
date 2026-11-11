# Tachyon Edge Images

Tachyon Edge Images rewrites WordPress image URLs so images are served, resized, by a [Tachyon](https://github.com/humanmade/tachyon) server.

It is a drop-in replacement for Human Made's [tachyon-plugin](https://github.com/humanmade/tachyon-plugin) (called the Reference Plugin in the code and docs). It has the same functions, class, hooks and constants, and produces the same URLs. The only differences are seven bug fixes, listed under [Bug fixes](#bug-fixes-deviations).

It works with any Tachyon server, including [Tachyon Edge](https://github.com/soderlind/tachyon-edge) on Cloudflare. Pair it with [Tachyon R2](https://github.com/soderlind/tachyon-r2) to store the images in R2.

## Requirements

- WordPress 7.0 or later
- PHP 8.3 or later
- A Tachyon server

## Install

1. Download `tachyon-edge-images.zip` from the [latest release](https://github.com/soderlind/tachyon-edge-images/releases/latest) and upload it in **Plugins → Add New → Upload Plugin**.
2. Add `TACHYON_URL` to `wp-config.php` (see [Configure](#configure)).
3. Activate the plugin.

### Switching from tachyon-plugin

You don't need to change any settings: Tachyon Edge Images reads the same constants.

1. Activate Tachyon Edge Images. While tachyon-plugin is still active, Tachyon Edge Images does nothing and shows an admin notice. This is called Stand-down.
2. Click **Deactivate Tachyon** in the notice, or deactivate tachyon-plugin on the Plugins screen.

Tachyon Edge Images never deactivates tachyon-plugin on its own. If tachyon-plugin is a must-use plugin or loaded through Composer, the notice tells you to remove it. On multisite, a network administrator must deactivate a network-activated tachyon-plugin.

To switch back, deactivate Tachyon Edge Images first. While it is active, WordPress refuses to activate tachyon-plugin, because both plugins define `tachyon_url()`. The site keeps working.

## Configure

Add the constants to `wp-config.php`. There is no settings page.

```php
// Required: the Tachyon server URL that replaces the uploads URL.
define( 'TACHYON_URL', 'https://images.example.com/wp-content/uploads' );

// Optional: the Tachyon server version. 3.0.0 or later enables presigned S3 URLs.
define( 'TACHYON_SERVER_VERSION', '3.0.0' );
```

Without `TACHYON_URL`, images are not rewritten. `tachyon_url()` and `tachyon_server_version()` still exist, as in tachyon-plugin, and `tachyon_url()` returns the URL unchanged.

### Optional fixes

These fixes change URLs that a site may depend on, so they are off by default. Set a constant to `true` to turn one on for the whole site, or use its [filter](#filters) to decide per image.

| Constant | What changes when it is `true` |
| --- | --- |
| `TACHYON_EDGE_IMAGES_EXTEND_TACHYON_URLS` | `tachyon_url()` adds the arguments you pass to a URL that already points at the Tachyon server. By default, the URL is returned unchanged and the arguments are dropped. |
| `TACHYON_EDGE_IMAGES_KEEP_UNATTACHED_FILENAMES` | An image in post content that is not linked to a Media Library item keeps a size ending in its file name: `photo-900x600.jpg` stays `photo-900x600.jpg`. By default, the ending is removed (`photo.jpg?resize=900,600`), which breaks the image if `photo.jpg` doesn't exist. |
| `TACHYON_EDGE_IMAGES_PRESERVE_AT_SIGN` | `@` in a file name stays as it is: `photo@2x.jpg`. By default, it is encoded as `photo%402x.jpg`. |

## Differences from tachyon-plugin

Tachyon Edge Images has the same public API as tachyon-plugin 0.11.1:

- The functions `tachyon_url( $image_url, $args = [], $scheme = null )` and `tachyon_server_version()`.
- `class Tachyon` (`Tachyon::instance()`), with the same methods, properties and visibility, on the same hooks and priorities.
- The same `tachyon_*` filters with the same arguments: `tachyon_skip_for_url`, `tachyon_pre_image_url`, `tachyon_pre_args`, `tachyon_url`, `tachyon_server_version`, `tachyon_skip_image`, `tachyon_image_is_local`, `tachyon_post_image_args`, `tachyon_remove_size_attributes`, `tachyon_disable_in_admin`, `tachyon_override_image_downsize`, `tachyon_image_downsize_string`, `tachyon_image_downsize_array`, `tachyon_srcset_image_args` and `tachyon_validate_image_url`.

A test on every pull request checks this against tachyon-plugin (see [Development](#development)).

### Bug fixes (Deviations)

These fixes only change output that was broken, so no working site can depend on the old behavior. They are always on.

1. **Image links with special characters.** A link around an image, such as `photo(1).jpg`, now points at Tachyon too. tachyon-plugin read the URL as a regular expression and left the link unchanged.
2. **Each image gets its own size.** The size found for one image in a post no longer affects the images after it. Filters get the size name (`medium`), not the size settings.
3. **No PHP warnings or broken sizes.** A missing image, missing image metadata, or a missing `TACHYON_URL` no longer causes PHP warnings, fatal errors, or empty sizes such as `resize=,`. Metadata without a width and height counts as missing.
4. **`&` after an existing query.** Arguments passed as a string are added with `&` when the URL already has a query: `photo.jpg?ver=2&w=300`, not `photo.jpg?ver=2?w=300`.
5. **Arguments before `#`.** Arguments passed as a string go before a fragment: `photo.jpg?w=300#top`, not `photo.jpg#top?w=300`.
6. **No double encoding.** An already-encoded file name such as `my%20photo.jpg` is not encoded again into `my%2520photo.jpg`.
7. **Full images in the block editor.** The block editor shows the original image, not a resized Tachyon copy. This also works when one REST request runs inside another.

### Kept quirks (Plugin Quirks)

These are odd tachyon-plugin behaviors that are kept because sites may depend on them:

- `tachyon_url()` returns a Tachyon URL unchanged and ignores new arguments. See [Optional fixes](#optional-fixes).
- A size ending such as `-900x600` is removed from the file name of an image that is not in the Media Library. See [Optional fixes](#optional-fixes).
- `@` in a file name is encoded as `%40`. See [Optional fixes](#optional-fixes).
- On multisite, `/sites/N` is removed from the uploads URL before it is replaced with `TACHYON_URL`.
- An image's `size-*` class is looked up against the current post, not the image's attachment, because the attachment is not known yet at that point.
- An image's `width` and `height` attributes never match an attachment size, because they are compared as text with numbers.

### Known limitations

- Subclassing `Tachyon` to override its protected static helpers (`image_sizes()`, `strip_image_dimensions_maybe()`) has no effect, because the plugin calls the helpers directly.

## Site Health

**Tools → Site Health** shows whether Tachyon Edge Images is active, in Stand-down, or missing `TACHYON_URL`. It also fetches the latest uploaded image through the Tachyon server, and reports a problem if no image comes back. The **Info** tab lists the version, the constants and the bug fixes.

## Filters

All tachyon-plugin filters work as before. Each new filter controls one of the [optional fixes](#optional-fixes). It receives the constant's value (or `false`) and returns `true` to turn the fix on:

- `tachyon_edge_images_extend_tachyon_urls( bool $extend, string $image_url, array|string $args )`
- `tachyon_edge_images_keep_unattached_filenames( bool $keep, string $src, string $tag )`
- `tachyon_edge_images_preserve_at_sign( bool $preserve, string $image_url )`

For example, to keep `@` only for retina images:

```php
add_filter( 'tachyon_edge_images_preserve_at_sign', fn ( $preserve, $url ) => str_contains( $url, '@2x.' ), 10, 2 );
```

## Development

```bash
composer install
composer test               # PHPUnit + Brain Monkey
composer test:js            # Editor script (node --test)
composer test:differential  # Compare with tachyon-plugin in a throwaway WordPress (needs WP-CLI)
composer lint
```

The differential test runs the same cases through tachyon-plugin (pinned at `652ecd2`) and Tachyon Edge Images in a real WordPress. It compares the output, the `tachyon_*` filter calls and their arguments, the hook registrations and the class signature. A case tagged with a bug fix number must differ; every other case must be identical. It runs on every pull request.

The differential test downloads WordPress, the SQLite database drop-in and tachyon-plugin into `tests/differential/.work/`. Set `DIFF_REUSE=1` to reuse that install, or `REFERENCE_DIR=../tachyon-plugin` to use a local checkout.

[GLOSSARY.md](GLOSSARY.md) defines the terms used here, and [docs/adr/](docs/adr/) records the design decisions.

## License

GPL-2.0-or-later. Based on [Tachyon](https://github.com/humanmade/tachyon-plugin) by Human Made Limited and Automattic Inc.
