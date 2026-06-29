/**
 * Session photos on booking edit screens — camera + Media Library + REST.
 *
 * @package KennelFlow_Boarding
 */
/* global jQuery, wp, kennelflowBoardingBookingMedia, ltkfOpenDeviceCamera */
( function ( $ ) {
	'use strict';

	var cfg = window.kennelflowBoardingBookingMedia || {};
	var bookingId = parseInt( cfg.bookingId, 10 ) || 0;
	var restBase = cfg.restBase || 'kennelflow-boarding/v1';
	var nonce = cfg.nonce || '';
	var i18n = cfg.i18n || {};
	var mediaFrame = null;
	var pendingKind = 'check_in';

	function restPath( suffix ) {
		return '/wp-json/' + String( restBase ).replace( /^\/+|\/+$/g, '' ) + suffix;
	}

	function setStatus( msg, isError ) {
		$( '#kf-boarding-media-status' ).text( msg || '' ).toggleClass( 'kf-boarding-media-status--error', !! isError );
	}

	function kindFromButton( el, prefix ) {
		var cls = String( el.className || '' ).split( /\s+/ );
		for ( var i = 0; i < cls.length; i++ ) {
			if ( cls[ i ].indexOf( prefix ) === 0 ) {
				return cls[ i ].slice( prefix.length );
			}
		}
		return 'check_in';
	}

	function renderRows( media ) {
		var $tbody = $( '#kf-boarding-media-rows' );
		if ( ! $tbody.length ) {
			return;
		}
		if ( ! media || ! media.length ) {
			$tbody.html( '<tr><td colspan="3">' + ( i18n.empty || '' ) + '</td></tr>' );
			return;
		}
		var html = '';
		media.forEach( function ( row ) {
			var thumb = row.thumbnail_url
				? '<img src="' + row.thumbnail_url + '" alt="" width="80" height="80" style="object-fit:cover;" />'
				: '—';
			html += '<tr data-media-id="' + row.id + '"><td>' + ( row.media_kind || '' ) + '</td><td>' + thumb +
				'</td><td><button type="button" class="button-link-delete kf-boarding-remove-media" data-media-id="' +
				row.id + '">' + ( i18n.remove || 'Remove' ) + '</button></td></tr>';
		} );
		$tbody.html( html );
	}

	function loadMedia() {
		if ( bookingId < 1 ) {
			return;
		}
		setStatus( i18n.loading || 'Loading…', false );
		$.ajax( {
			url: restPath( '/bookings/' + bookingId + '/media' ),
			method: 'GET',
			beforeSend: function ( xhr ) {
				if ( nonce ) {
					xhr.setRequestHeader( 'X-WP-Nonce', nonce );
				}
			},
		} )
			.done( function ( resp ) {
				renderRows( resp && resp.media ? resp.media : [] );
				setStatus( '', false );
			} )
			.fail( function () {
				setStatus( i18n.loadError || 'Could not load photos.', true );
			} );
	}

	function linkAttachment( attachmentId, kind ) {
		setStatus( i18n.saving || 'Saving…', false );
		$.ajax( {
			url: restPath( '/bookings/' + bookingId + '/media' ),
			method: 'POST',
			contentType: 'application/json',
			data: JSON.stringify( { media_kind: kind, attachment_id: attachmentId } ),
			beforeSend: function ( xhr ) {
				if ( nonce ) {
					xhr.setRequestHeader( 'X-WP-Nonce', nonce );
				}
			},
		} )
			.done( function () {
				setStatus( i18n.saved || 'Photo saved.', false );
				loadMedia();
			} )
			.fail( function () {
				setStatus( i18n.saveError || 'Could not save photo.', true );
			} );
	}

	function uploadFileAndLink( file, kind ) {
		if ( ! file || ! file.type || file.type.indexOf( 'image/' ) !== 0 ) {
			setStatus( i18n.invalidImage || 'Please choose an image file.', true );
			return;
		}
		var formData = new FormData();
		formData.append( 'file', file, file.name || 'photo.jpg' );
		setStatus( i18n.uploading || 'Uploading…', false );
		$.ajax( {
			url: '/wp-json/wp/v2/media',
			method: 'POST',
			data: formData,
			processData: false,
			contentType: false,
			beforeSend: function ( xhr ) {
				if ( nonce ) {
					xhr.setRequestHeader( 'X-WP-Nonce', nonce );
				}
			},
		} )
			.done( function ( resp ) {
				var id = parseInt( resp && resp.id, 10 ) || 0;
				if ( id < 1 ) {
					setStatus( i18n.uploadError || 'Could not upload photo.', true );
					return;
				}
				linkAttachment( id, kind );
			} )
			.fail( function () {
				setStatus( i18n.uploadError || 'Could not upload photo.', true );
			} );
	}

	function openMediaFrame( kind ) {
		pendingKind = kind;
		if ( ! mediaFrame ) {
			mediaFrame = wp.media( {
				title: i18n.pickTitle || 'Choose photo',
				button: { text: i18n.usePhoto || 'Use photo' },
				library: { type: 'image' },
				multiple: false,
			} );
			mediaFrame.on( 'select', function () {
				var attachment = mediaFrame.state().get( 'selection' ).first().toJSON();
				var id = parseInt( attachment.id, 10 ) || 0;
				if ( id > 0 ) {
					linkAttachment( id, pendingKind );
				}
			} );
		}
		mediaFrame.open();
	}

	function openDeviceCameraForKind( kind ) {
		if ( typeof window.ltkfOpenDeviceCamera !== 'function' ) {
			setStatus( i18n.cameraUnsupported || 'Camera is not available.', true );
			return;
		}
		var title = 'check_out' === kind ? ( i18n.takeCheckOut || 'Take check-out photo' ) : ( i18n.takeCheckIn || 'Take check-in photo' );
		window.ltkfOpenDeviceCamera( {
			title: title,
			i18n: i18n,
			onCapture: function ( file ) {
				uploadFileAndLink( file, kind );
			},
		} );
	}

	$( function () {
		if ( bookingId < 1 ) {
			return;
		}
		loadMedia();
		$( document ).on( 'click', '[class*="kf-boarding-camera-"]', function ( e ) {
			e.preventDefault();
			openDeviceCameraForKind( kindFromButton( this, 'kf-boarding-camera-' ) );
		} );
		$( document ).on( 'click', '[class*="kf-boarding-pick-"]', function ( e ) {
			e.preventDefault();
			openMediaFrame( kindFromButton( this, 'kf-boarding-pick-' ) );
		} );
		$( document ).on( 'click', '.kf-boarding-remove-media', function ( e ) {
			e.preventDefault();
			var mediaId = parseInt( $( this ).data( 'media-id' ), 10 ) || 0;
			if ( mediaId < 1 ) {
				return;
			}
			if ( i18n.confirmRemove && ! window.confirm( i18n.confirmRemove ) ) {
				return;
			}
			$.ajax( {
				url: restPath( '/bookings/' + bookingId + '/media/' + mediaId ),
				method: 'DELETE',
				beforeSend: function ( xhr ) {
					if ( nonce ) {
						xhr.setRequestHeader( 'X-WP-Nonce', nonce );
					}
				},
			} ).done( loadMedia );
		} );
	} );
}( jQuery ) );
