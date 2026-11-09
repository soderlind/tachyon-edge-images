<?php
/**
 * Differential fixtures: attachments with metadata. No image files are needed.
 */

$fixtures = [
	'photo'  => [
		'file'  => '2026/05/photo.jpg',
		'mime'  => 'image/jpeg',
		'w'     => 2000,
		'h'     => 1500,
		'sizes' => [
			'thumbnail'    => [ 150, 150 ],
			'medium'       => [ 300, 225 ],
			'medium_large' => [ 768, 576 ],
			'large'        => [ 1024, 768 ],
			'1536x1536'    => [ 1536, 1152 ],
		],
	],
	'parens' => [
		'file'  => '2026/05/photo(1).jpg',
		'mime'  => 'image/jpeg',
		'w'     => 1200,
		'h'     => 800,
		'sizes' => [
			'thumbnail' => [ 150, 150 ],
			'medium'    => [ 300, 200 ],
			'large'     => [ 1024, 683 ],
		],
	],
	'small'  => [
		'file'  => '2026/05/small.png',
		'mime'  => 'image/png',
		'w'     => 100,
		'h'     => 80,
		'sizes' => [],
	],
];

$ids = [];
foreach ( $fixtures as $key => $f ) {
	$id = wp_insert_attachment(
		[
			'post_title'     => $key,
			'post_mime_type' => $f['mime'],
			'post_status'    => 'inherit',
			'guid'           => wp_get_upload_dir()['baseurl'] . '/' . $f['file'],
		],
		$f['file']
	);

	$info  = pathinfo( $f['file'] );
	$sizes = [];
	foreach ( $f['sizes'] as $name => [ $w, $h ] ) {
		$sizes[ $name ] = [
			'file'      => "{$info['filename']}-{$w}x{$h}.{$info['extension']}",
			'width'     => $w,
			'height'    => $h,
			'mime-type' => $f['mime'],
		];
	}

	wp_update_attachment_metadata(
		$id,
		[
			'width'      => $f['w'],
			'height'     => $f['h'],
			'file'       => $f['file'],
			'sizes'      => $sizes,
			'image_meta' => [],
		]
	);
	$ids[ $key ] = $id;
}

update_option( 'differential_fixtures', $ids );
WP_CLI::log( 'Fixtures: ' . wp_json_encode( $ids ) );
