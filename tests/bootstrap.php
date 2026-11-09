<?php
/**
 * Unit test bootstrap: Composer autoload plus minimal WordPress shims.
 */

declare(strict_types=1);

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/' );
}

// Constants can only be defined once per process; the tests share these.
define( 'TACHYON_URL', 'https://images.example.com/uploads' );

if ( ! class_exists( 'WP_Error' ) ) {
	// phpcs:ignore Generic.Files.OneObjectStructurePerFile
	class WP_Error {
		public function __construct( private string $code = '', private string $message = '' ) {}
		public function get_error_code(): string { return $this->code; }
		public function get_error_message(): string { return $this->message; }
	}
}

if ( ! class_exists( 'WP_REST_Request' ) ) {
	// phpcs:ignore Generic.Files.OneObjectStructurePerFile
	class WP_REST_Request implements ArrayAccess {
		/**
		 * @param array<string, mixed>  $params
		 * @param array<string, string> $headers
		 */
		public function __construct( private string $route = '', private array $params = [], private array $headers = [] ) {}
		public function get_route(): string { return $this->route; }
		public function get_header( string $name ): ?string { return $this->headers[ strtolower( $name ) ] ?? null; }
		public function offsetExists( mixed $offset ): bool { return isset( $this->params[ $offset ] ); }
		public function offsetGet( mixed $offset ): mixed { return $this->params[ $offset ] ?? null; }
		public function offsetSet( mixed $offset, mixed $value ): void { $this->params[ $offset ] = $value; }
		public function offsetUnset( mixed $offset ): void { unset( $this->params[ $offset ] ); }
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ): bool {
		return $thing instanceof WP_Error;
	}
}
