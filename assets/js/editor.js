/**
 * Marks same-origin REST requests from the block editor, so the server returns
 * original image URLs to the editor (Deviation 7).
 */
( function ( apiFetch, restUrl ) {
	'use strict';

	var HEADER = 'x-wp-api-fetch-from-editor';

	apiFetch.use( function ( options, next ) {
		if ( options.mode === 'cors' || options.mode === 'no-cors' ) {
			return next( options );
		}

		var usesRestRoot = typeof options.path === 'string' ||
			( typeof options.namespace === 'string' && typeof options.endpoint === 'string' );
		if ( usesRestRoot && ( typeof restUrl !== 'string' || ! restUrl ) ) {
			console.error( 'Tachyon Edge Images: cannot determine the REST API origin; this editor request was not marked.' );
			return next( options );
		}
		var target = options.url || window.location.href;

		var isSameOrigin;
		try {
			isSameOrigin = new URL( target, window.location.href ).origin === window.location.origin &&
				( ! usesRestRoot || new URL( restUrl, window.location.href ).origin === window.location.origin );
		} catch ( error ) {
			return next( options );
		}
		if ( ! isSameOrigin ) {
			return next( options );
		}

		var headers;
		if ( options.headers && typeof options.headers.forEach === 'function' ) {
			headers = {};
			new Headers( options.headers ).forEach( function ( value, name ) {
				headers[ name ] = value;
			} );
		} else {
			headers = Object.assign( {}, options.headers );
		}

		Object.keys( headers ).forEach( function ( name ) {
			if ( name.toLowerCase() === HEADER ) {
				delete headers[ name ];
			}
		} );
		headers[ HEADER ] = 'true';

		return next( Object.assign( {}, options, { headers: headers } ) );
	} );
} )( window.wp.apiFetch, window.tachyonEdgeImagesRestUrl );
