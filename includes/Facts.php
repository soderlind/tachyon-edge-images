<?php
/**
 * Holder for the current Image Facts adapter.
 *
 * @package TachyonEdgeImages
 */

namespace Soderlind\Plugin\TachyonEdgeImages;

/**
 * Gives the sizing paths their Image Facts. WordPress unless a test swaps it.
 */
final class Facts {

	/**
	 * Current adapter, created on first use.
	 *
	 * @var ImageFacts|null
	 */
	private static $facts = null;

	/**
	 * The current Image Facts adapter.
	 *
	 * @return ImageFacts
	 */
	public static function get() {
		if ( null === self::$facts ) {
			self::$facts = new WordPressImageFacts();
		}

		return self::$facts;
	}

	/**
	 * Replace the adapter. Null restores a fresh WordPress adapter on next use.
	 *
	 * @param ImageFacts|null $facts Adapter.
	 * @return void
	 */
	public static function swap( ?ImageFacts $facts ) {
		self::$facts = $facts;
	}
}
