<?php
/**
 * Minimal add_query_arg() and remove_query_arg() for unit tests.
 */

declare(strict_types=1);

namespace Soderlind\Plugin\TachyonEdgeImages\Tests\Support;

final class QueryArgs {

	/**
	 * Minimal add_query_arg(): ( array $args, string $url ) or ( string $key, mixed $value, string $url ).
	 */
	public static function add_query_arg( mixed ...$a ): string {
		[ $args, $url ] = is_array( $a[0] ) ? [ $a[0], $a[1] ] : [ [ $a[0] => $a[1] ], $a[2] ];
		[ $base, $query ] = array_pad( explode( '?', $url, 2 ), 2, '' );
		$pairs = [];
		foreach ( array_filter( explode( '&', $query ) ) as $pair ) {
			[ $k, $v ] = array_pad( explode( '=', $pair, 2 ), 2, '' );
			$pairs[ $k ] = $v;
		}
		foreach ( $args as $k => $v ) {
			$pairs[ $k ] = $v;
		}
		$query = implode( '&', array_map( static fn ( $k, $v ) => "$k=$v", array_keys( $pairs ), $pairs ) );

		return $base . ( '' === $query ? '' : '?' . $query );
	}

	public static function remove_query_arg( string $key, string $url ): string {
		[ $base, $query ] = array_pad( explode( '?', $url, 2 ), 2, '' );
		$pairs = array_filter( explode( '&', $query ), static fn ( $p ) => '' !== $p && explode( '=', $p, 2 )[0] !== $key );

		return $base . ( $pairs ? '?' . implode( '&', $pairs ) : '' );
	}
}
