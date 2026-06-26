/**
 * Mobile Report PWA: read boot payload from #kf-pwa-root[data-boot] (base64 JSON).
 *
 * Exposes globals expected by the Webpack bundle.
 *
 * @package KennelFlow_Boarding
 */
( function () {
	'use strict';

	var root = document.getElementById( 'kf-pwa-root' );
	var data = {};
	var raw  = root ? root.getAttribute( 'data-boot' ) : '';
	if ( raw && typeof window.atob === 'function' ) {
		try {
			var txt = window.atob( raw );
			data = txt ? JSON.parse( txt ) : {};
		} catch ( e ) {
			data = {};
		}
	}
	window.kennelpressPwaReport = data;
	window.kennelflowPwaReport  = data;
}() );
