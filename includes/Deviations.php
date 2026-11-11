<?php
/**
 * The documented differences from the Reference Plugin (ADR 0001).
 *
 * @package TachyonEdgeImages
 */

declare(strict_types=1);

namespace Soderlind\Plugin\TachyonEdgeImages;

/**
 * Lists Deviations.
 */
final class Deviations {

	/**
	 * Deviation number => short description. Keep in sync with README.md.
	 *
	 * @var array<int, string>
	 */
	public const ALL = [
		1 => 'Image link hrefs are escaped before they are matched and replaced.',
		2 => 'Size detection uses the size name and starts fresh for every image.',
		3 => 'Missing image sources, metadata (or metadata without dimensions) or TACHYON_URL no longer cause PHP warnings, errors or `resize=,`.',
		4 => 'String arguments are joined with `&` when the URL already has a query.',
		5 => 'String arguments are placed before a #fragment.',
		6 => 'URLs are never encoded or rewritten to Tachyon twice.',
		7 => 'The block editor gets original image URLs, including in nested REST requests.',
	];
}
