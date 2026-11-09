<?php
/**
 * Compare differential results. Untagged cases must be identical; cases tagged
 * with a Deviation must differ. Exits non-zero on any violation.
 */

$work     = $argv[1] ?? __DIR__ . '/.work';
$failures = 0;
$checked  = 0;

foreach ( [ 'configured', 'unconfigured' ] as $mode ) {
	$reference = json_decode( (string) file_get_contents( "$work/reference-$mode.json" ), true, 512, JSON_THROW_ON_ERROR );
	$edge      = json_decode( (string) file_get_contents( "$work/edge-$mode.json" ), true, 512, JSON_THROW_ON_ERROR );

	foreach ( $reference as $name => $ref ) {
		++$checked;
		$ours = $edge[ $name ] ?? null;
		if ( null === $ours ) {
			printf( "FAIL %s/%s: missing from Tachyon Edge Images\n", $mode, $name );
			++$failures;
			continue;
		}

		$same      = $ref['value'] === $ours['value'] && $ref['filters'] === $ours['filters'];
		$deviation = $ref['deviation'];

		if ( null === $deviation && ! $same ) {
			printf( "FAIL %s/%s: differs\n  reference: %s\n  edge:      %s\n", $mode, $name, json_encode( $ref, JSON_UNESCAPED_SLASHES ), json_encode( $ours, JSON_UNESCAPED_SLASHES ) );
			++$failures;
		} elseif ( null !== $deviation && $same ) {
			printf( "FAIL %s/%s: expected Deviation %d to change the result, but it is identical\n  %s\n", $mode, $name, $deviation, json_encode( $ref, JSON_UNESCAPED_SLASHES ) );
			++$failures;
		} elseif ( null !== $deviation ) {
			printf( "ok   %s/%s (Deviation %d)\n  reference: %s\n  edge:      %s\n", $mode, $name, $deviation, json_encode( $ref['value'], JSON_UNESCAPED_SLASHES ), json_encode( $ours['value'], JSON_UNESCAPED_SLASHES ) );
		}
	}
}

printf( "\n%d cases, %d failures\n", $checked, $failures );
exit( $failures > 0 ? 1 : 0 );
