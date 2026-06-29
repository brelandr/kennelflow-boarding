/**
 * Kennel Press admin: load bookings for a UTC range via REST; quick confirm from calendar.
 */
(function () {
	'use strict';

	function qs(id) {
		return document.getElementById(id);
	}

	function esc(s) {
		var d         = document.createElement('div');
		d.textContent = s;
		return d.innerHTML;
	}

	function patchStatus(bookingId, status, onDone) {
		if (!window.kennelflowBoardingCalendar) {
			onDone(new Error('Missing config'));
			return;
		}
		var url = window.kennelflowBoardingCalendar.restUrl.replace(/\/?$/, '/') + encodeURIComponent(String(bookingId));
		fetch(url, {
			method: 'PATCH',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': window.kennelflowBoardingCalendar.nonce,
			},
			body: JSON.stringify({ status: status }),
		})
			.then(function (r) {
				if (!r.ok) {
					throw new Error('HTTP ' + r.status);
				}
				return r.json();
			})
			.then(function () {
				onDone(null);
			})
			.catch(function (err) {
				onDone(err instanceof Error ? err : new Error(String(err)));
			});
	}

	function actionButtons(b) {
		var status = String(b.status || 'pending');
		var html   = '';
		if (status === 'pending' || status === 'pending_payment') {
			html +=
				'<button type="button" class="button button-primary button-small kennelflow-boarding-cal-confirm" data-id="' +
				esc(String(b.id)) +
				'">Confirm</button> ';
		} else if (status === 'confirmed') {
			html +=
				'<button type="button" class="button button-small kennelflow-boarding-cal-checkin" data-id="' +
				esc(String(b.id)) +
				'">Check in</button> ';
		}
		if (b.edit_url) {
			html +=
				'<a class="button button-small" href="' +
				esc(String(b.edit_url)) +
				'">Open booking</a>';
		}
		return html || '—';
	}

	function bindActionButtons(root) {
		root.querySelectorAll('.kennelflow-boarding-cal-confirm').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var id = btn.getAttribute('data-id');
				btn.disabled = true;
				patchStatus(id, 'confirmed', function (err) {
					if (err) {
						btn.disabled = false;
						window.alert('Could not confirm booking.');
						return;
					}
					loadCalendar();
				});
			});
		});
		root.querySelectorAll('.kennelflow-boarding-cal-checkin').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var id = btn.getAttribute('data-id');
				btn.disabled = true;
				patchStatus(id, 'checked_in', function (err) {
					if (err) {
						btn.disabled = false;
						window.alert('Could not check in booking.');
						return;
					}
					loadCalendar();
				});
			});
		});
	}

	function loadCalendar() {
		var root  = qs('kennelflow-boarding-calendar-root');
		var start = qs('kennelflow-boarding-cal-start');
		var end   = qs('kennelflow-boarding-cal-end');
		if (!root || !start || !end || !window.kennelflowBoardingCalendar) {
			return;
		}
		root.innerHTML = '<p>' + esc('…') + '</p>';

		var url =
			window.kennelflowBoardingCalendar.restUrl +
			'?start=' +
			encodeURIComponent(start.value.trim()) +
			'&end=' +
			encodeURIComponent(end.value.trim());

		fetch(url, {
			credentials: 'same-origin',
			headers: {
				'X-WP-Nonce': window.kennelflowBoardingCalendar.nonce,
			},
		})
			.then(function (r) {
				if (!r.ok) {
					throw new Error('HTTP ' + r.status);
				}
				return r.json();
			})
			.then(function (data) {
				var rows = data.bookings || [];
				if (!rows.length) {
					root.innerHTML = '<p><em>No bookings in range.</em></p>';
					return;
				}
				var html = '<table class="widefat striped"><thead><tr>';
				html += '<th>ID</th><th>Pet</th><th>Kennel</th><th>Start (UTC)</th><th>End (UTC)</th><th>Status</th><th>Actions</th>';
				html += '</tr></thead><tbody>';
				rows.forEach(function (b) {
					html += '<tr>';
					html += '<td>' + esc(String(b.id)) + '</td>';
					html += '<td>' + esc(b.pet_title || '') + '</td>';
					html += '<td>' + esc(b.kennel_title || '') + '</td>';
					html += '<td>' + esc(b.start_gmt || '') + '</td>';
					html += '<td>' + esc(b.end_gmt || '') + '</td>';
					html += '<td>' + esc(b.status || '') + '</td>';
					html += '<td>' + actionButtons(b) + '</td>';
					html += '</tr>';
				});
				html += '</tbody></table>';
				root.innerHTML = html;
				bindActionButtons(root);
			})
			.catch(function () {
				root.innerHTML = '<p class="notice notice-error"><span>Could not load bookings.</span></p>';
			});
	}

	document.addEventListener('DOMContentLoaded', function () {
		var btn = qs('kennelflow-boarding-cal-load');
		if (btn) {
			btn.addEventListener('click', loadCalendar);
		}
		loadCalendar();
	});
})();
