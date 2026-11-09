const assert = require( 'node:assert/strict' );
const fs = require( 'node:fs' );
const path = require( 'node:path' );
const test = require( 'node:test' );
const vm = require( 'node:vm' );

const script = fs.readFileSync( path.join( __dirname, '../../assets/js/editor.js' ), 'utf8' );
const editorUrl = 'https://example.test/wp-admin/post.php?post=1';

function createMiddleware( restUrl = 'https://example.test/wp-json/' ) {
	let middleware;
	const errors = [];
	const window = {
		location: new URL( editorUrl ),
		tachyonEdgeImagesRestUrl: restUrl,
		wp: {
			apiFetch: {
				use( callback ) {
					middleware = callback;
				},
			},
		},
	};

	vm.runInNewContext( script, { window, URL, Headers, console: { error: ( message ) => errors.push( message ) } } );
	assert.equal( typeof middleware, 'function' );
	middleware.errors = errors;
	return middleware;
}

function forward( middleware, options ) {
	return middleware( options, ( forwarded ) => forwarded );
}

test( 'marks local REST paths and preserves existing options and headers', () => {
	const middleware = createMiddleware();
	const options = {
		path: '/wp/v2/media?context=view',
		headers: { 'X-WP-Nonce': 'nonce', 'X-Wp-Api-Fetch-From-Editor': 'false' },
		method: 'GET',
	};
	const result = forward( middleware, options );

	assert.notEqual( result, options );
	assert.equal( result.path, options.path );
	assert.equal( result.method, 'GET' );
	assert.equal( result.headers['X-WP-Nonce'], 'nonce' );
	assert.equal( new Headers( result.headers ).get( 'X-WP-API-Fetch-From-Editor' ), 'true' );
	assert.equal( options.headers['X-Wp-Api-Fetch-From-Editor'], 'false' );
} );

test( 'preserves native Headers and tuple headers', () => {
	const middleware = createMiddleware();
	for ( const headers of [
		new Headers( { Authorization: 'Bearer test', 'X-WP-API-Fetch-From-Editor': 'false' } ),
		[ [ 'Authorization', 'Bearer test' ], [ 'X-WP-API-Fetch-From-Editor', 'false' ] ],
	] ) {
		const result = forward( middleware, { path: '/wp/v2/media', headers } );
		const forwarded = new Headers( result.headers );
		assert.equal( forwarded.get( 'Authorization' ), 'Bearer test' );
		assert.equal( forwarded.get( 'X-WP-API-Fetch-From-Editor' ), 'true' );
	}
} );

test( 'uses the same header key as other editor middleware', () => {
	const result = forward( createMiddleware(), { path: '/wp/v2/media' } );
	result.headers['x-wp-api-fetch-from-editor'] = 'true';

	assert.equal( new Headers( result.headers ).get( 'X-WP-API-Fetch-From-Editor' ), 'true' );
} );

test( 'marks same-origin URLs and namespace requests', () => {
	const middleware = createMiddleware();
	for ( const options of [
		{ url: 'https://example.test:443/wp-json/wp/v2/media' },
		{ url: '/wp-json/wp/v2/media' },
		{ namespace: 'wp/v2', endpoint: 'media' },
	] ) {
		const result = forward( middleware, options );
		assert.equal( new Headers( result.headers ).get( 'X-WP-API-Fetch-From-Editor' ), 'true' );
	}
} );

test( 'leaves cross-origin, invalid, and explicit CORS requests unchanged', () => {
	const middleware = createMiddleware();
	for ( const options of [
		{ url: 'https://other.test/wp-json/wp/v2/media' },
		{ url: '//other.test/wp-json/wp/v2/media' },
		{ url: 'http://example.test/wp-json/wp/v2/media' },
		{ url: 'https://example.test:8443/wp-json/wp/v2/media' },
		{ url: 'http://[' },
		{ path: '/wp/v2/media', url: 'https://other.test/wp-json/wp/v2/media' },
		{ path: '/wp/v2/media', mode: 'cors' },
		{ path: '/wp/v2/media', mode: 'no-cors' },
	] ) {
		assert.equal( forward( middleware, options ), options );
	}
} );

test( 'uses the REST root for paths and namespace endpoints', () => {
	const middleware = createMiddleware( 'https://api.other.test/wp-json/' );
	for ( const options of [
		{ path: '/wp/v2/media' },
		{ namespace: 'wp/v2', endpoint: 'media' },
		{ path: '/wp/v2/media', url: 'https://example.test/wp-json/wp/v2/media' },
	] ) {
		assert.equal( forward( middleware, options ), options );
	}

	const result = forward( middleware, { url: 'https://example.test/wp-json/wp/v2/media' } );
	assert.equal( new Headers( result.headers ).get( 'X-WP-API-Fetch-From-Editor' ), 'true' );
} );

test( 'does not mark REST paths when the REST root is unavailable', () => {
	for ( const restUrl of [ null, '' ] ) {
		const middleware = createMiddleware( restUrl );
		const options = { path: '/wp/v2/media' };
		assert.equal( forward( middleware, options ), options );
		assert.equal( middleware.errors.length, 1 );
	}
} );

test( 'propagates errors from the next middleware', () => {
	const middleware = createMiddleware();
	const error = new Error( 'request failed' );
	for ( const options of [ { url: 'http://[' }, { url: 'https://other.test/' } ] ) {
		let calls = 0;
		assert.throws(
			() => middleware( options, () => { calls++; throw error; } ),
			( actual ) => actual === error
		);
		assert.equal( calls, 1 );
	}
} );
