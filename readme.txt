=== Tachyon Edge Images ===
Contributors: PerS
Tags: tachyon, images, cdn, resize, cloudflare
Requires at least: 7.0
Tested up to: 7.1
Requires PHP: 8.3
Stable tag: 0.1.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Rewrites image URLs to a Tachyon server. A compatible replacement for tachyon-plugin, with bug fixes.

== Description ==

Tachyon Edge Images rewrites WordPress image URLs to a [Tachyon](https://github.com/humanmade/tachyon) server, such as [Tachyon Edge](https://github.com/soderlind/tachyon-edge) on Cloudflare.

It replaces Human Made's [tachyon-plugin](https://github.com/humanmade/tachyon-plugin) and stays compatible with it: the same `tachyon_url()` function, `Tachyon` class, `tachyon_*` filters and constants, and the same URLs for the same input. The exceptions are seven bug fixes:

1. Image links whose URL contains regex characters, such as `photo(1).jpg`, are rewritten.
2. Size detection starts fresh for every image and passes the size name to filters.
3. No PHP warnings, errors or empty `resize=,` arguments for missing images, metadata or `TACHYON_URL`.
4. String arguments are joined with `&` when the URL already has a query.
5. String arguments go before a `#fragment`.
6. Already-encoded file names are not encoded twice.
7. The block editor shows original images, not resized ones.

Also included:

* Stand-down: while tachyon-plugin is active, this plugin does nothing and shows a notice with a **Deactivate Tachyon** button.
* A Site Health test that requests an image through your Tachyon server.

== Installation ==

1. Upload and activate the plugin.
2. Add the Tachyon server URL to `wp-config.php`:

`define( 'TACHYON_URL', 'https://images.example.com/wp-content/uploads' );`

Optional constants:

* `TACHYON_SERVER_VERSION`: set to `3.0.0` or later to pass presigned S3 URLs to Tachyon.
* `TACHYON_EDGE_IMAGES_EXTEND_TACHYON_URLS`: set to `true` to add arguments to URLs that already point at the Tachyon server.

Switching from tachyon-plugin needs no configuration changes. Activate Tachyon Edge Images, then click **Deactivate Tachyon** in the notice.

== Frequently Asked Questions ==

= Will my image URLs change? =

No, except where one of the seven bug fixes applies.

= Can I switch back to tachyon-plugin? =

Yes. Deactivate Tachyon Edge Images first, then activate tachyon-plugin. With Tachyon Edge Images active, WordPress refuses to activate tachyon-plugin because both declare `tachyon_url()`.

= Do I need Tachyon Edge? =

No. Any Tachyon server works.

== Changelog ==

= 0.1.0 =
* First release: a compatible replacement for tachyon-plugin 0.11.1 with seven bug fixes, Stand-down and a Site Health test.
