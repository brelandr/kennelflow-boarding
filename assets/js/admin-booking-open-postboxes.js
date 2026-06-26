/**
 * Keep booking meta boxes expanded on the post editor screen.
 *
 * @package KennelFlow_Boarding
 */
/* global jQuery */
( function ( $ ) {
	'use strict';

	function kennelflowBoardingOpenBookingPostboxes() {
		$( '.postbox' ).removeClass( 'closed' );
		$( '.postbox .inside' ).stop( true, true ).show();
	}

	$( function () {
		kennelflowBoardingOpenBookingPostboxes();
		window.setTimeout( kennelflowBoardingOpenBookingPostboxes, 50 );
		window.setTimeout( kennelflowBoardingOpenBookingPostboxes, 300 );
	} );
}( jQuery ) );
