<?php

declare(strict_types=1);

namespace Soderlind\Plugin\TachyonEdgeImages\Tests;

use Brain\Monkey;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Soderlind\Plugin\TachyonEdgeImages\Tests\Support\QueryArgs;
use Soderlind\Plugin\TachyonEdgeImages\Url;

final class UrlTest extends TestCase {

	private const UPLOADS = 'https://example.com/wp-content/uploads';

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( 'wp_upload_dir' )->justReturn( [ 'baseurl' => self::UPLOADS ] );
		Functions\when( 'is_multisite' )->justReturn( false );
		Functions\when( 'tachyon_server_version' )->alias( [ Url::class, 'server_version' ] );
		Functions\when( 'add_query_arg' )->alias( [ QueryArgs::class, 'add_query_arg' ] );
		Functions\when( 'remove_query_arg' )->alias( [ QueryArgs::class, 'remove_query_arg' ] );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_rewrites_upload_url_to_tachyon(): void {
		$this->assertSame(
			'https://images.example.com/uploads/2026/05/a.jpg?w=100&h=50',
			Url::build( self::UPLOADS . '/2026/05/a.jpg', [ 'w' => 100, 'h' => 50 ] )
		);
	}

	public function test_leaves_foreign_urls_alone(): void {
		$this->assertSame( 'https://cdn.example.org/a.jpg', Url::build( 'https://cdn.example.org/a.jpg', [ 'w' => 1 ] ) );
	}

	public function test_query_string_args_use_ampersand_after_existing_query(): void {
		// Deviation 4.
		$this->assertSame(
			'https://images.example.com/uploads/a.jpg?ver=2&resize=10,10',
			Url::build( self::UPLOADS . '/a.jpg?ver=2', 'resize=10,10' )
		);
		$this->assertSame(
			'https://images.example.com/uploads/a.jpg?resize=10,10',
			Url::build( self::UPLOADS . '/a.jpg', 'resize=10,10' )
		);
	}

	public function test_query_string_args_go_before_the_fragment(): void {
		// Deviation 5.
		$this->assertSame(
			'https://images.example.com/uploads/a.jpg?w=10#top',
			Url::build( self::UPLOADS . '/a.jpg#top', 'w=10' )
		);
	}

	public function test_encodes_a_raw_basename_like_the_reference_plugin(): void {
		$this->assertSame(
			'https://images.example.com/uploads/my+photo.jpg',
			Url::build( self::UPLOADS . '/my photo.jpg' )
		);
	}

	public function test_does_not_encode_an_encoded_basename_twice(): void {
		// Deviation 6.
		$this->assertSame(
			'https://images.example.com/uploads/my%20photo.jpg',
			Url::build( self::UPLOADS . '/my%20photo.jpg' )
		);
	}

	public function test_encodes_an_at_sign_like_the_reference_plugin(): void {
		$this->assertSame(
			'https://images.example.com/uploads/photo%402x.jpg?w=10',
			Url::build( self::UPLOADS . '/photo@2x.jpg', [ 'w' => 10 ] )
		);
	}

	public function test_keeps_an_at_sign_literal_when_opted_in(): void {
		Filters\expectApplied( 'tachyon_edge_images_preserve_at_sign' )->once()->andReturn( true );

		$this->assertSame(
			'https://images.example.com/uploads/my+photo@2x.jpg?w=10',
			Url::build( self::UPLOADS . '/my photo@2x.jpg', [ 'w' => 10 ] )
		);
	}

	public function test_returns_existing_tachyon_url_unchanged_and_drops_args(): void {
		$url = 'https://images.example.com/uploads/a.jpg?w=10';
		$this->assertSame( $url, Url::build( $url, [ 'h' => 20 ] ) );
		$this->assertSame( $url, Url::build( $url, 'h=20' ) );
	}

	public function test_extends_existing_tachyon_url_when_opted_in(): void {
		Filters\expectApplied( 'tachyon_edge_images_extend_tachyon_urls' )->andReturn( true );

		$this->assertSame(
			'https://images.example.com/uploads/my%20photo.jpg?w=10&h=20',
			Url::build( 'https://images.example.com/uploads/my%20photo.jpg?w=10', 'h=20' )
		);
	}

	public function test_moves_signed_params_into_presign_on_tachyon_3(): void {
		Filters\expectApplied( 'tachyon_server_version' )->andReturn( '3.0.0' );

		$this->assertSame(
			'https://images.example.com/uploads/a.jpg?presign=X-Amz-Signature%3Dabc&w=10',
			Url::build( self::UPLOADS . '/a.jpg?X-Amz-Signature=abc', [ 'w' => 10 ] )
		);
	}

	public function test_rewrites_uploads_when_tachyon_url_equals_uploads_url(): void {
		Functions\when( 'wp_upload_dir' )->justReturn( [ 'baseurl' => TACHYON_URL ] );

		$this->assertSame( TACHYON_URL . '/a.jpg?w=300', Url::build( TACHYON_URL . '/a.jpg', [ 'w' => 300 ] ) );
	}

	public function test_rewrites_uploads_when_tachyon_url_prefixes_uploads_url(): void {
		Functions\when( 'wp_upload_dir' )->justReturn( [ 'baseurl' => TACHYON_URL . '/site' ] );

		$this->assertSame( TACHYON_URL . '/a.jpg?w=300', Url::build( TACHYON_URL . '/site/a.jpg', [ 'w' => 300 ] ) );
	}

	public function test_skip_filter_returns_url_untouched(): void {
		Filters\expectApplied( 'tachyon_skip_for_url' )->andReturn( true );

		$this->assertSame( self::UPLOADS . '/a.jpg', Url::build( self::UPLOADS . '/a.jpg', [ 'w' => 1 ] ) );
	}

	/**
	 * @return array<string, array{string, bool}>
	 */
	public static function tachyon_urls(): array {
		return [
			'base'          => [ 'https://images.example.com/uploads', true ],
			'path'          => [ 'https://images.example.com/uploads/a.jpg', true ],
			'query'         => [ 'https://images.example.com/uploads?x=1', true ],
			'longer prefix' => [ 'https://images.example.com/uploads-old/a.jpg', false ],
			'other host'    => [ 'https://example.com/wp-content/uploads/a.jpg', false ],
		];
	}

	#[DataProvider( 'tachyon_urls' )]
	public function test_is_tachyon_url( string $url, bool $expected ): void {
		$this->assertSame( $expected, Url::is_tachyon_url( $url ) );
	}
}
