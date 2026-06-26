/**
 * Print trigger for standalone run-card HTML (no inline onclick).
 *
 * @package KennelFlow_Boarding
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '.kprc-print-trigger' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				window.print();
			} );
		} );
	} );
}() );
