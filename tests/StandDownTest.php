<?php

declare(strict_types=1);

namespace Soderlind\Plugin\TachyonEdgeImages\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use Soderlind\Plugin\TachyonEdgeImages\StandDown;

final class StandDownTest extends TestCase {

	/** @var array<string, mixed> */
	private array $options = [];

	/** @var array<string, mixed> */
	private array $site_options = [];

	private bool $multisite = false;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		$this->options      = [];
		$this->site_options = [];
		$this->multisite    = false;

		Functions\when( 'get_option' )->alias( fn ( $name, $default = false ) => $this->options[ $name ] ?? $default );
		Functions\when( 'get_site_option' )->alias( fn ( $name, $default = false ) => $this->site_options[ $name ] ?? $default );
		Functions\when( 'is_multisite' )->alias( fn () => $this->multisite );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_loads_when_reference_plugin_is_absent(): void {
		$this->options['active_plugins'] = [ 'tachyon-r2/tachyon-r2.php', 'tachyon-edge-images/tachyon-edge-images.php' ];

		$this->assertNull( StandDown::reference_basename() );
		$this->assertNull( StandDown::reason() );
	}

	public function test_stands_down_when_reference_plugin_is_active(): void {
		$this->options['active_plugins'] = [ 'tachyon-plugin/tachyon.php' ];

		$this->assertSame( 'tachyon-plugin/tachyon.php', StandDown::reference_basename() );
		$this->assertSame( StandDown::ACTIVE, StandDown::reason() );
	}

	public function test_finds_reference_plugin_in_any_folder(): void {
		$this->options['active_plugins'] = [ 'tachyon/tachyon.php' ];

		$this->assertSame( 'tachyon/tachyon.php', StandDown::reference_basename() );
	}

	public function test_finds_network_active_reference_plugin(): void {
		$this->multisite                            = true;
		$this->site_options['active_sitewide_plugins'] = [ 'tachyon-plugin/tachyon.php' => time() ];

		$this->assertSame( 'tachyon-plugin/tachyon.php', StandDown::reference_basename() );
		$this->assertTrue( StandDown::is_network_active( 'tachyon-plugin/tachyon.php' ) );
	}

	public function test_site_activation_is_not_network_activation(): void {
		$this->multisite                 = true;
		$this->options['active_plugins'] = [ 'tachyon-plugin/tachyon.php' ];

		$this->assertFalse( StandDown::is_network_active( 'tachyon-plugin/tachyon.php' ) );
	}
}
