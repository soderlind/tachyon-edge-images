=== Tachyon Edge Images ===
Contributors: PerS
Tags: tachyon, images, cdn, resize, cloudflare
Requires at least: 7.0
Tested up to: 7.1
Requires PHP: 8.3
Stable tag: 0.2.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Serves your images, resized, from a Tachyon server. A drop-in replacement for tachyon-plugin, with bug fixes.

== Description ==

Tachyon Edge Images rewrites WordPress image URLs so images are served, resized, by a [Tachyon](https://github.com/humanmade/tachyon) server, such as [Tachyon Edge](https://github.com/soderlind/tachyon-edge) on Cloudflare.

It replaces Human Made's [tachyon-plugin](https://github.com/humanmade/tachyon-plugin). It has the same `tachyon_url()` function, `Tachyon` class, `tachyon_*` filters and constants, and produces the same URLs. The only differences are seven bug fixes:

1. A link around an image whose file name has special characters, such as `photo(1).jpg`, now points at Tachyon too.
2. The size found for one image in a post no longer affects the images after it.
3. A missing image, missing image metadata, or a missing `TACHYON_URL` no longer causes PHP warnings, errors or broken sizes such as `resize=,`.
4. Arguments added to a URL that already has a query use `&`: `photo.jpg?ver=2&w=300`, not `?ver=2?w=300`.
5. Arguments go before a `#fragment`: `photo.jpg?w=300#top`, not `#top?w=300`.
6. An already-encoded file name such as `my%20photo.jpg` is not encoded twice.
7. The block editor shows the original image, not a resized one.

Also included:

* Stand-down: while tachyon-plugin is active, this plugin does nothing and shows a notice with a **Deactivate Tachyon** button.
* A Site Health test that fetches an image through your Tachyon server.

== Installation ==

1. Upload the plugin.
2. Add your Tachyon server URL to `wp-config.php`:

`define( 'TACHYON_URL', 'https://images.example.com/wp-content/uploads' );`

3. Activate the plugin.

Optional constants:

* `TACHYON_SERVER_VERSION`: set to `3.0.0` or later to pass presigned S3 URLs to Tachyon.

Optional fixes. They are off by default, because they change URLs a site may depend on. Set one to `true` to turn it on:

* `TACHYON_EDGE_IMAGES_EXTEND_TACHYON_URLS`: `tachyon_url()` adds arguments to a URL that already points at the Tachyon server, instead of ignoring them.
* `TACHYON_EDGE_IMAGES_KEEP_UNATTACHED_FILENAMES`: an image in post content that is not in the Media Library keeps a size ending in its file name, so `photo-900x600.jpg` is not changed to `photo.jpg`.
* `TACHYON_EDGE_IMAGES_PRESERVE_AT_SIGN`: `@` in file names is kept (`photo@2x.jpg`), not encoded as `%40`.

Switching from tachyon-plugin needs no configuration changes. Activate Tachyon Edge Images, then click **Deactivate Tachyon** in the notice.

== Frequently Asked Questions ==

= Will my image URLs change? =

Only where one of the seven bug fixes applies, or where you turn on an optional fix.

= Can I switch back to tachyon-plugin? =

Yes. Deactivate Tachyon Edge Images first, then activate tachyon-plugin. While Tachyon Edge Images is active, WordPress refuses to activate tachyon-plugin, because both define `tachyon_url()`.

= Do I need Tachyon Edge? =

No. Any Tachyon server works.

== Changelog ==

= 0.2.1 =
* Fixed: no PHP warnings when attachment metadata has no width or height.
* Added: optional fix that keeps a size ending such as `-900x600` in file names of images that are not in the Media Library (`TACHYON_EDGE_IMAGES_KEEP_UNATTACHED_FILENAMES`).
* Added: optional fix that keeps `@` in file names instead of encoding it as `%40` (`TACHYON_EDGE_IMAGES_PRESERVE_AT_SIGN`).
* Docs: clearer readme.

= 0.2.0 =
* Internal: the sizing paths share Image Facts, content rewriting is split into steps, and the REST editor check keeps its state on an instance. More unit tests. No change in output.

= 0.1.0 =
* First release: a compatible replacement for tachyon-plugin 0.11.1 with seven bug fixes, Stand-down and a Site Health test.
