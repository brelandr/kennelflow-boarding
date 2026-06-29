/**
 * Vanilla in-browser camera capture for admin session photos.
 *
 * @package KennelFlow_Groom_Pro
 */
( function ( window ) {
	'use strict';

	var activeStream = null;
	var overlayEl = null;

	function stopStream() {
		if ( ! activeStream ) {
			return;
		}
		activeStream.getTracks().forEach( function ( track ) {
			track.stop();
		} );
		activeStream = null;
	}

	function removeOverlay() {
		stopStream();
		if ( overlayEl && overlayEl.parentNode ) {
			overlayEl.parentNode.removeChild( overlayEl );
		}
		overlayEl = null;
	}

	function supported() {
		return (
			'undefined' !== typeof navigator &&
			navigator.mediaDevices &&
			'function' === typeof navigator.mediaDevices.getUserMedia
		);
	}

	/**
	 * Open a live camera modal and return a JPEG File via callback.
	 *
	 * @param {object}   options
	 * @param {string}   [options.title] Modal title.
	 * @param {Function} options.onCapture Called with File.
	 * @param {Function} [options.onClose] Called when cancelled.
	 * @param {object}   [options.i18n]    Translated strings.
	 */
	function openDeviceCamera( options ) {
		options = options || {};
		var i18n = options.i18n || {};
		var onCapture = options.onCapture;
		var onClose = options.onClose;

		if ( 'function' !== typeof onCapture ) {
			return;
		}

		if ( ! supported() ) {
			window.alert(
				i18n.unsupported ||
					'Camera is not available in this browser.'
			);
			return;
		}

		removeOverlay();

		overlayEl = document.createElement( 'div' );
		overlayEl.className = 'ltkf-device-camera';
		overlayEl.setAttribute( 'role', 'dialog' );
		overlayEl.setAttribute( 'aria-modal', 'true' );

		var backdrop = document.createElement( 'div' );
		backdrop.className = 'ltkf-device-camera__backdrop';

		var panel = document.createElement( 'div' );
		panel.className = 'ltkf-device-camera__panel';

		var title = document.createElement( 'h4' );
		title.className = 'ltkf-device-camera__title';
		title.textContent = options.title || i18n.takePhoto || 'Take photo';

		var errorEl = document.createElement( 'p' );
		errorEl.className = 'ltkf-device-camera__error';
		errorEl.hidden = true;

		var video = document.createElement( 'video' );
		video.className = 'ltkf-device-camera__video';
		video.setAttribute( 'playsinline', '' );
		video.setAttribute( 'muted', '' );
		video.autoplay = true;

		var actions = document.createElement( 'div' );
		actions.className = 'ltkf-device-camera__actions';

		var cancelBtn = document.createElement( 'button' );
		cancelBtn.type = 'button';
		cancelBtn.className = 'button';
		cancelBtn.textContent = i18n.cancel || 'Cancel';

		var captureBtn = document.createElement( 'button' );
		captureBtn.type = 'button';
		captureBtn.className = 'button button-primary';
		captureBtn.textContent = i18n.capture || 'Capture photo';
		captureBtn.disabled = true;

		function closeModal() {
			removeOverlay();
			if ( 'function' === typeof onClose ) {
				onClose();
			}
		}

		cancelBtn.addEventListener( 'click', closeModal );
		backdrop.addEventListener( 'click', closeModal );

		captureBtn.addEventListener( 'click', function () {
			if (
				! video.videoWidth ||
				! video.videoHeight ||
				captureBtn.disabled
			) {
				return;
			}

			var canvas = document.createElement( 'canvas' );
			canvas.width = video.videoWidth;
			canvas.height = video.videoHeight;
			var ctx = canvas.getContext( '2d' );
			if ( ! ctx ) {
				return;
			}
			ctx.drawImage( video, 0, 0 );

			canvas.toBlob(
				function ( blob ) {
					if ( ! blob ) {
						errorEl.textContent =
							i18n.captureError || 'Could not capture photo.';
						errorEl.hidden = false;
						return;
					}
					var file = new File(
						[ blob ],
						'groom-session-' + Date.now() + '.jpg',
						{ type: 'image/jpeg' }
					);
					removeOverlay();
					onCapture( file );
				},
				'image/jpeg',
				0.92
			);
		} );

		actions.appendChild( cancelBtn );
		actions.appendChild( captureBtn );
		panel.appendChild( title );
		panel.appendChild( errorEl );
		panel.appendChild( video );
		panel.appendChild( actions );
		overlayEl.appendChild( backdrop );
		overlayEl.appendChild( panel );
		document.body.appendChild( overlayEl );

		navigator.mediaDevices
			.getUserMedia( {
				video: {
					facingMode: { ideal: 'environment' },
					width: { ideal: 1920 },
					height: { ideal: 1080 },
				},
				audio: false,
			} )
			.then( function ( stream ) {
				activeStream = stream;
				video.srcObject = stream;
				return video.play();
			} )
			.then( function () {
				captureBtn.disabled = false;
			} )
			.catch( function () {
				video.hidden = true;
				errorEl.textContent =
					i18n.accessError ||
					'Could not access the camera. Check browser permissions and try again.';
				errorEl.hidden = false;
			} );
	}

	window.ltkfOpenDeviceCamera = openDeviceCamera;
	window.ltkfDeviceCameraSupported = supported;
}( window ) );
