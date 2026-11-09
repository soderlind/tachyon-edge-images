<?php

declare(strict_types=1);

namespace Soderlind\Plugin\TachyonEdgeImages\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use Soderlind\Plugin\TachyonEdgeImages\Rest;
use WP_REST_Request;

final class RestTest extends TestCase {

	private Rest $rest;

	private bool $active = false;

	private bool $is_admin = false;

	private bool $editor_screen = false;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		$this->rest          = new Rest();
		$this->active        = false;
		$this->is_admin      = false;
		$this->editor_screen = false;

		Functions\when( 'add_filter' )->alias( fn () => $this->active = true );
		Functions\when( 'remove_filter' )->alias( fn () => $this->active = false );
		Functions\when( 'is_admin' )->alias( fn () => $this->is_admin );
		Functions\when( 'wp_should_load_block_editor_scripts_and_styles' )->alias( fn () => $this->editor_screen );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public static function override(): bool {
		return true;
	}

	public function test_media_in_edit_context_gets_original_urls(): void {
		$this->run_request( new WP_REST_Request( '/wp/v2/media/5', [ 'context' => 'edit' ] ), true );
	}

	public function test_media_in_view_context_is_resized(): void {
		$this->run_request( new WP_REST_Request( '/wp/v2/media/5', [ 'context' => 'view' ] ), false );
	}

	public function test_marked_editor_request_gets_original_urls(): void {
		$this->run_request( new WP_REST_Request( '/wp/v2/posts/1', [], [ 'x-wp-api-fetch-from-editor' => 'true' ] ), true );
	}

	public function test_combined_header_value_counts(): void {
		$this->run_request( new WP_REST_Request( '/wp/v2/media', [], [ 'x-wp-api-fetch-from-editor' => 'true, true' ] ), true );
	}

	public function test_preloaded_request_on_editor_screen_gets_original_urls(): void {
		$this->is_admin      = true;
		$this->editor_screen = true;
		$this->run_request( new WP_REST_Request( '/wp/v2/media' ), true );
	}

	public function test_error_response_is_ignored(): void {
		$override = [ self::class, 'override' ];
		$this->rest->before( new \WP_Error( 'x' ), $override, new WP_REST_Request( '/wp/v2/media', [ 'context' => 'edit' ] ) );
		$this->assertFalse( $this->active );
		$this->rest->after( null, $override );
	}

	public function test_nested_request_keeps_the_outer_override(): void {
		$override = [ self::class, 'override' ];
		$outer    = new WP_REST_Request( '/wp/v2/posts/1', [], [ 'x-wp-api-fetch-from-editor' => 'true' ] );
		$inner    = new WP_REST_Request( '/wp/v2/media/5' );

		$this->rest->before( null, $override, $outer );
		$this->rest->before( null, $override, $inner );
		$this->rest->after( null, $override );
		$this->assertTrue( $this->active, 'Inner request must not switch Tachyon back on.' );

		$this->rest->after( null, $override );
		$this->assertFalse( $this->active );
	}

	private function run_request( WP_REST_Request $request, bool $original ): void {
		$override = [ self::class, 'override' ];

		$this->assertSame( 'response', $this->rest->before( 'response', $override, $request ) );
		$this->assertSame( $original, $this->active );
		$this->assertSame( 'response', $this->rest->after( 'response', $override ) );
		$this->assertFalse( $this->active );
	}

	public function test_editor_script_sends_the_header_rest_reads(): void {
		$script = (string) file_get_contents( dirname( __DIR__ ) . '/assets/js/editor.js' );
		$this->assertSame( 1, preg_match( "/var HEADER = '([^']+)';/", $script, $match ), 'editor.js must declare its header name.' );
		$this->assertSame( Rest::EDITOR_HEADER, $match[1] );
	}
}
