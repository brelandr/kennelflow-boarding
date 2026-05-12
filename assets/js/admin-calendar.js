/**
 * Kennel Press admin: load bookings for a UTC range via REST.
 */
(function () {
	'use strict';

	function qs(id) {
		return document.getElementById( id );
	}

	function esc(s) {
		var d         = document.createElement( 'div' );
		d.textContent = s;
		return d.innerHTML;
	}

	function loadCalendar() {
		var root  = qs( 'kennelflow-boarding-calendar-root' );
		var start = qs( 'kennelflow-boarding-cal-start' );
		var end   = qs( 'kennelflow-boarding-cal-end' );
		if ( ! root || ! start || ! end || ! window.kennelflowBoardingCalendar) {
			return;
		}
		root.innerHTML = '<p>' + esc( '…' ) + '</p>';

		var url =
			window.kennelflowBoardingCalendar.restUrl +
			'?start=' +
			encodeURIComponent( start.value.trim() ) +
			'&end=' +
			encodeURIComponent( end.value.trim() );

		fetch(
			url,
			{
				credentials: 'same-origin',
				headers: {
					'X-WP-Nonce': window.kennelflowBoardingCalendar.nonce,
				},
			}
		)
			.then(
				function (r) {
					if ( ! r.ok) {
						throw new Error( 'HTTP ' + r.status );
					}
					return r.json();
				}
			)
			.then(
				function (data) {
					var rows = data.bookings || [];
					if ( ! rows.length) {
						root.innerHTML = '<p><em>No bookings in range.</em></p>';
						return;
					}
					var html = '<table class="widefat striped"><thead><tr>';
					html    += '<th>ID</th><th>Pet</th><th>Kennel</th><th>Start (UTC)</th><th>End (UTC)</th><th>Status</th>';
					html    += '</tr></thead><tbody>';
					rows.forEach(
						function (b) {
							html += '<tr>';
							html += '<td>' + esc( String( b.id ) ) + '</td>';
							html += '<td>' + esc( b.pet_title || '' ) + '</td>';
							html += '<td>' + esc( b.kennel_title || '' ) + '</td>';
							html += '<td>' + esc( b.start_gmt || '' ) + '</td>';
							html += '<td>' + esc( b.end_gmt || '' ) + '</td>';
							html += '<td>' + esc( b.status || '' ) + '</td>';
							html += '</tr>';
						}
					);
					html          += '</tbody></table>';
					root.innerHTML = html;
				}
			)
			.catch(
				function () {
					root.innerHTML = '<p class="notice notice-error"><span>Could not load bookings.</span></p>';
				}
			);
	}

	document.addEventListener(
		'DOMContentLoaded',
		function () {
			var btn = qs( 'kennelflow-boarding-cal-load' );
			if (btn) {
				btn.addEventListener( 'click', loadCalendar );
			}
			loadCalendar();
		}
	);
})();
