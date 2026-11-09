<?php
/**
 * Differential runner. Loads one subject plugin, runs every case and prints JSON.
 *
 * Each case may name the Deviation that is expected to change its result.
 * compare.php requires untagged cases to be identical and tagged cases to differ.
 */

$subject    = getenv( 'DIFF_SUBJECT' );
$configured = 'configured' === getenv( 'DIFF_MODE' );

if ( $configured ) {
	define( 'TACHYON_URL', 'https://images.example.com/wp-content/uploads' );
}

require getenv( 'reference' === $subject ? 'DIFF_REFERENCE' : 'DIFF_EDGE' );

$ids     = get_option( 'differential_fixtures' );
$uploads = wp_get_upload_dir()['baseurl'];
$photo   = $ids['photo'];
$parens  = $ids['parens'];
$small   = $ids['small'];

add_image_size( 'hero', 1600, 600, true );
add_image_size( 'focal', 400, 400, [ 'left', 'top' ] );

$trace = [];
add_filter(
	'all',
	static function ( $hook ) use ( &$trace ) {
		// Edge-only filters (tachyon_edge_images_*) are additions, not part of the comparison.
		if ( str_starts_with( $hook, 'tachyon_' ) && ! str_starts_with( $hook, 'tachyon_edge_images_' ) ) {
			$args    = array_slice( func_get_args(), 1 );
			$trace[] = $hook . ' ' . wp_json_encode( $args, JSON_UNESCAPED_SLASHES );
		}
		return func_num_args() > 1 ? func_get_arg( 1 ) : null;
	}
);

/**
 * @param string     $html Content.
 */
$content = static fn ( string $html ) => static fn () => Tachyon::filter_the_content( $html );

$img = static fn ( int $id, string $file, string $size, string $extra = '' ) =>
	sprintf( '<img src="%s/2026/05/%s" class="wp-image-%d size-%s"%s alt="">', $uploads, $file, $id, $size, $extra );

$rest = static function ( int $id, string $context, bool $editor ) {
	return static function () use ( $id, $context, $editor ) {
		$request = new WP_REST_Request( 'GET', "/wp/v2/media/$id" );
		$request->set_param( 'context', $context );
		if ( $editor ) {
			$request->set_header( 'X-WP-API-Fetch-From-Editor', 'true' );
		}
		$data  = rest_get_server()->response_to_data( rest_do_request( $request ), false );
		$sizes = array_map( static fn ( $s ) => $s['source_url'], $data['media_details']['sizes'] ?? [] );

		return [ 'source_url' => $data['source_url'] ?? null, 'sizes' => $sizes, 'after' => wp_get_attachment_image_src( $id, 'medium' ) ];
	};
};

if ( $configured ) {
	$cases = [
		// tachyon_url().
		'url/upload'                 => [ null, static fn () => tachyon_url( "$uploads/2026/05/photo.jpg" ) ],
		'url/array-args'             => [ null, static fn () => tachyon_url( "$uploads/2026/05/photo.jpg", [ 'resize' => '300,200', 'quality' => 80 ] ) ],
		'url/string-args'            => [ null, static fn () => tachyon_url( "$uploads/2026/05/photo.jpg", 'w=300&h=200' ) ],
		'url/existing-query-array'   => [ null, static fn () => tachyon_url( "$uploads/2026/05/photo.jpg?ver=2", [ 'w' => 300 ] ) ],
		'url/existing-query-string'  => [ 4, static fn () => tachyon_url( "$uploads/2026/05/photo.jpg?ver=2", 'w=300' ) ],
		'url/fragment-string'        => [ 5, static fn () => tachyon_url( "$uploads/2026/05/photo.jpg#top", 'w=300' ) ],
		'url/raw-space'              => [ null, static fn () => tachyon_url( "$uploads/2026/05/my photo.jpg", [ 'w' => 300 ] ) ],
		'url/encoded-space'          => [ 6, static fn () => tachyon_url( "$uploads/2026/05/my%20photo.jpg", [ 'w' => 300 ] ) ],
		'url/parens'                 => [ null, static fn () => tachyon_url( "$uploads/2026/05/photo(1).jpg", [ 'w' => 300 ] ) ],
		'url/tachyon-passthrough'    => [ null, static fn () => tachyon_url( TACHYON_URL . '/2026/05/photo.jpg?w=10', [ 'h' => 20 ] ) ],
		'url/foreign'                => [ null, static fn () => tachyon_url( 'https://cdn.example.org/photo.jpg', [ 'w' => 300 ] ) ],
		'url/presign-unversioned'    => [ null, static fn () => tachyon_url( "$uploads/2026/05/photo.jpg?X-Amz-Signature=abc&X-Amz-Date=1", [ 'w' => 300 ] ) ],
		'url/presign-v3'             => [
			null,
			static function () use ( $uploads ) {
				$version = static fn () => '3.0.0';
				add_filter( 'tachyon_server_version', $version );
				$url = tachyon_url( "$uploads/2026/05/photo.jpg?X-Amz-Signature=abc&X-Amz-Date=1", [ 'w' => 300 ] );
				remove_filter( 'tachyon_server_version', $version );
				return $url;
			},
		],
		'url/skip-filter'            => [
			null,
			static function () use ( $uploads ) {
				add_filter( 'tachyon_skip_for_url', '__return_true' );
				$url = tachyon_url( "$uploads/2026/05/photo.jpg", [ 'w' => 300 ] );
				remove_filter( 'tachyon_skip_for_url', '__return_true' );
				return $url;
			},
		],
		'url/tachyon-is-uploads'     => [
			null,
			static function () {
				$dir = static function ( $d ) {
					$d['baseurl'] = TACHYON_URL;
					return $d;
				};
				add_filter( 'upload_dir', $dir );
				$url = tachyon_url( TACHYON_URL . '/2026/05/photo.jpg', [ 'w' => 300 ] );
				remove_filter( 'upload_dir', $dir );
				return $url;
			},
		],
		'server-version'             => [ null, static fn () => tachyon_server_version() ],

		// image_downsize.
		'downsize/thumbnail'         => [ null, static fn () => wp_get_attachment_image_src( $photo, 'thumbnail' ) ],
		'downsize/medium'            => [ null, static fn () => wp_get_attachment_image_src( $photo, 'medium' ) ],
		'downsize/large'             => [ null, static fn () => wp_get_attachment_image_src( $photo, 'large' ) ],
		'downsize/full'              => [ null, static fn () => wp_get_attachment_image_src( $photo, 'full' ) ],
		'downsize/hero-crop'         => [ null, static fn () => wp_get_attachment_image_src( $photo, 'hero' ) ],
		'downsize/focal-crop'        => [ null, static fn () => wp_get_attachment_image_src( $photo, 'focal' ) ],
		'downsize/array'             => [ null, static fn () => wp_get_attachment_image_src( $photo, [ 500, 400 ] ) ],
		'downsize/unknown-size'      => [ null, static fn () => wp_get_attachment_image_src( $photo, 'nope' ) ],
		'downsize/small-png'         => [ null, static fn () => wp_get_attachment_image_src( $small, 'large' ) ],
		'downsize/parens'            => [ null, static fn () => wp_get_attachment_image_src( $parens, 'medium' ) ],

		// srcset.
		'image/large'                => [ null, static fn () => wp_get_attachment_image( $photo, 'large' ) ],
		'image/medium'               => [ null, static fn () => wp_get_attachment_image( $photo, 'medium' ) ],
		'image/parens'               => [ null, static fn () => wp_get_attachment_image( $parens, 'large' ) ],

		// the_content.
		'content/attachment-large'   => [ null, $content( $img( $photo, 'photo-1024x768.jpg', 'large', ' width="1024" height="768"' ) ) ],
		'content/attachment-thumb'   => [ null, $content( $img( $photo, 'photo-150x150.jpg', 'thumbnail' ) ) ],
		'content/attachment-full'    => [ null, $content( $img( $photo, 'photo.jpg', 'full' ) ) ],
		'content/filename-dims'      => [ 2, $content( "<img src=\"$uploads/2026/05/photo-300x225.jpg\" alt=\"\">" ) ],
		'content/width-attrs'        => [ null, $content( "<img src=\"$uploads/2026/05/photo.jpg\" width=\"640\" height=\"480\" alt=\"\">" ) ],
		'content/foreign'            => [ null, $content( '<img src="https://cdn.example.org/photo.jpg" width="10" height="10">' ) ],
		'content/linked'             => [ null, $content( "<a href=\"$uploads/2026/05/photo.jpg\">" . $img( $photo, 'photo-1024x768.jpg', 'large' ) . '</a>' ) ],
		'content/linked-parens'      => [ 1, $content( "<a href=\"$uploads/2026/05/photo(1).jpg\">" . $img( $parens, 'photo(1)-1024x683.jpg', 'large' ) . '</a>' ) ],
		'content/two-images'         => [
			2,
			$content(
				"<img src=\"$uploads/2026/05/photo.jpg\" width=\"150\" height=\"150\" alt=\"\">\n" .
				"<img src=\"$uploads/2026/05/photo.jpg\" width=\"300\" alt=\"\">"
			),
		],
		'content/size-class-no-id'   => [ 3, $content( "<img src=\"$uploads/2026/05/photo.jpg\" class=\"size-medium\" alt=\"\">" ) ],
		'content/srcset-present'     => [ null, $content( $img( $photo, 'photo-300x225.jpg', 'medium', " srcset=\"$uploads/2026/05/photo-300x225.jpg 300w\"" ) ) ],
		'content/existing-tachyon'   => [ null, $content( '<img src="' . TACHYON_URL . '/2026/05/photo.jpg?w=10" alt="">' ) ],
		'content/galleries'          => [ null, static fn () => Tachyon::filter_the_galleries( [ [ 'src' => [ "$uploads/2026/05/photo-300x225.jpg" ] ] ] ) ],

		// Static helpers.
		'parse-images'               => [ null, static fn () => Tachyon::parse_images_from_html( $img( $photo, 'photo.jpg', 'large' ) ) ],
		'parse-dimensions'           => [ null, static fn () => Tachyon::parse_dimensions_from_filename( "$uploads/2026/05/photo-300x225.jpg" ) ],
		'validate-url'               => [ null, static fn () => [ Tachyon::validate_image_url( "$uploads/a.jpg" ), Tachyon::validate_image_url( "$uploads/a.txt" ), Tachyon::validate_image_url( 'https://' ) ] ],

		// REST.
		'rest/media-view'            => [ null, $rest( $photo, 'view', false ) ],
		'rest/media-edit'            => [ null, $rest( $photo, 'edit', false ) ],
		'rest/media-view-editor'     => [ 7, $rest( $photo, 'view', true ) ],
	];
} else {
	$cases = [
		'unconfigured/symbols' => [ null, static fn () => [ function_exists( 'tachyon_url' ), function_exists( 'tachyon_server_version' ), class_exists( 'Tachyon', false ) ] ],
		'unconfigured/url'     => [ 3, static fn () => tachyon_url( "$uploads/2026/05/photo.jpg", [ 'w' => 300 ] ) ],
		'unconfigured/foreign' => [ null, static fn () => tachyon_url( 'https://cdn.example.org/photo.jpg' ) ],
	];
}

wp_set_current_user( 1 );

$results = [];
foreach ( $cases as $name => [ $deviation, $case ] ) {
	$trace = [];
	try {
		$value = $case();
	} catch ( Throwable $e ) {
		$value = 'Throwable: ' . get_class( $e );
	}
	$results[ $name ] = [
		'deviation' => $deviation,
		'value'     => $value,
		'filters'   => $trace,
	];
}

$surface = [ 'functions' => [], 'class' => null, 'hooks' => [] ];
foreach ( [ 'tachyon_url', 'tachyon_server_version' ] as $fn ) {
	if ( function_exists( $fn ) ) {
		$surface['functions'][ $fn ] = array_map(
			static fn ( ReflectionParameter $p ) => $p->getName() . ( $p->isOptional() ? '=' . var_export( $p->getDefaultValue(), true ) : '' ),
			( new ReflectionFunction( $fn ) )->getParameters()
		);
	}
}

if ( class_exists( 'Tachyon', false ) ) {
	$class   = new ReflectionClass( 'Tachyon' );
	$members = [];
	foreach ( $class->getMethods() as $m ) {
		$params                         = array_map( static fn ( $p ) => $p->getName(), $m->getParameters() );
		$members[ 'method ' . $m->getName() ] = implode( ' ', Reflection::getModifierNames( $m->getModifiers() ) ) . ' (' . implode( ', ', $params ) . ')';
	}
	foreach ( $class->getProperties() as $p ) {
		$members[ 'property ' . $p->getName() ] = implode( ' ', Reflection::getModifierNames( $p->getModifiers() ) );
	}
	$members['default $extensions'] = ( new ReflectionProperty( 'Tachyon', 'extensions' ) )->getValue();
	ksort( $members );
	$surface['class'] = $members;

	foreach ( $GLOBALS['wp_filter'] as $hook => $wp_hook ) {
		foreach ( $wp_hook->callbacks as $priority => $callbacks ) {
			foreach ( $callbacks as $cb ) {
				$fn = $cb['function'];
				if ( is_array( $fn ) && ( $fn[0] instanceof Tachyon || 'Tachyon' === $fn[0] ) ) {
					$surface['hooks'][] = sprintf( '%s @%d %s%s%s/%d', $hook, $priority, 'Tachyon', is_object( $fn[0] ) ? '->' : '::', $fn[1], $cb['accepted_args'] );
				}
			}
		}
	}
	sort( $surface['hooks'] );
}

$results['surface'] = [ 'deviation' => null, 'value' => $surface, 'filters' => [] ];

echo wp_json_encode( $results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
