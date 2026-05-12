/**
 * Mobile Report PWA: read boot JSON, expose globals expected by the Webpack bundle.
 *
 * The JSON must be valid UTF-8 and placed in a preceding <script type="application/json" id="kf-pwa-boot-json">.
 *
 * @package KennelFlow_Boarding
 */
( function () {
	'use strict';

	var el   = document.getElementById( 'kf-pwa-boot-json' );
	var data = {};
	if ( el && el.textContent ) {
		try {
			data = JSON.parse( el.textContent );
		} catch ( e ) {
			data = {};
		}
	}
	window.kennelpressPwaReport = data;
	window.kennelflowPwaReport  = data;
}() );
