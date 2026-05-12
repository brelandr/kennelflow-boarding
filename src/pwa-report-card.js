/**
 * Mobile Report Card PWA — roster + camera form + POST report-cards.
 *
 * @package KennelFlow_Boarding
 */

import { createRoot } from 'react-dom/client';
import { useCallback, useEffect, useMemo, useState } from 'react';

import './pwa-report-card.css';

/**
 * @typedef {object} KennelpressPwaSettings
 * @property {string} restUrl
 * @property {string} nonce
 * @property {string} todayStartGmt
 * @property {string} todayEndGmt
 */

/**
 * @returns {KennelpressPwaSettings}
 */
function getSettings() {
	if ( typeof window !== 'undefined' && window.kennelflowBoardingPwaReport ) {
		return window.kennelflowBoardingPwaReport;
	}
	if ( typeof window !== 'undefined' && window.kennelpressPwaReport ) {
		return window.kennelpressPwaReport;
	}
	return {
		restUrl: '/wp-json/',
		nonce: '',
		todayStartGmt: '',
		todayEndGmt: '',
	};
}

/**
 * @param {string} baseRestUrl
 * @param {string} path
 * @param {Record<string, string>} [params]
 */
function buildRestUrl( baseRestUrl, path, params ) {
	const base = baseRestUrl.endsWith( '/' ) ? baseRestUrl : `${ baseRestUrl }/`;
	const u = new URL( path.replace( /^\//, '' ), base );
	if ( params ) {
		Object.keys( params ).forEach( ( k ) => {
			u.searchParams.set( k, params[ k ] );
		} );
	}
	return u.toString();
}

/**
 * @param {object} props
 * @param {KennelpressPwaSettings} props.settings
 */
function App( { settings } ) {
	const [ view, setView ] = useState( 'roster' );
	const [ roster, setRoster ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );
	const [ selected, setSelected ] = useState( null );

	const [ mood, setMood ] = useState( 'happy' );
	const [ ateFood, setAteFood ] = useState( true );
	const [ bathroom, setBathroom ] = useState( true );
	const [ notes, setNotes ] = useState( '' );
	const [ photoFile, setPhotoFile ] = useState( null );
	const [ sending, setSending ] = useState( false );
	const [ sendError, setSendError ] = useState( null );

	const loadRoster = useCallback( async () => {
		setLoading( true );
		setError( null );
		try {
			const url = buildRestUrl( settings.restUrl, 'kennelflow-boarding/v1/bookings', {
				start: settings.todayStartGmt,
				end: settings.todayEndGmt,
				booking_kind: 'boarding',
				status: 'checked_in',
			} );
			const res = await fetch( url, {
				credentials: 'same-origin',
				headers: {
					'X-WP-Nonce': settings.nonce,
				},
			} );
			const data = await res.json().catch( () => ( {} ) );
			if ( ! res.ok ) {
				throw new Error(
					data.message || data.code || 'Failed to load bookings'
				);
			}
			const list = Array.isArray( data.bookings ) ? data.bookings : [];
			setRoster( list );
		} catch ( e ) {
			setError( e.message || 'Network error' );
			setRoster( [] );
		} finally {
			setLoading( false );
		}
	}, [ settings.nonce, settings.restUrl, settings.todayEndGmt, settings.todayStartGmt ] );

	useEffect( () => {
		loadRoster();
	}, [ loadRoster ] );

	const openForm = useCallback( ( booking ) => {
		setSelected( booking );
		setMood( 'happy' );
		setAteFood( true );
		setBathroom( true );
		setNotes( '' );
		setPhotoFile( null );
		setSendError( null );
		setView( 'form' );
	}, [] );

	const goRoster = useCallback( () => {
		setView( 'roster' );
		setSelected( null );
		setSendError( null );
	}, [] );

	const submitReport = useCallback( async () => {
		if ( ! selected || ! photoFile ) {
			setSendError(
				'Please take or choose a photo before sending.'
			);
			return;
		}
		setSending( true );
		setSendError( null );
		try {
			const fd = new FormData();
			fd.append( 'booking_id', String( selected.id ) );
			fd.append( 'photo', photoFile );
			fd.append( 'mood', mood );
			fd.append( 'ate_food', ateFood ? 'true' : 'false' );
			fd.append( 'bathroom', bathroom ? 'true' : 'false' );
			fd.append( 'notes', notes );

			const url = buildRestUrl( settings.restUrl, 'kennelflow-boarding/v1/report-cards' );
			const res = await fetch( url, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'X-WP-Nonce': settings.nonce,
				},
				body: fd,
			} );
			const data = await res.json().catch( () => ( {} ) );
			if ( ! res.ok ) {
				throw new Error(
					data.message || data.code || 'Could not send report'
				);
			}
			setView( 'success' );
			setTimeout( () => {
				setView( 'roster' );
				setSelected( null );
				loadRoster();
			}, 2200 );
		} catch ( e ) {
			setSendError( e.message || 'Send failed' );
		} finally {
			setSending( false );
		}
	}, [
		ateFood,
		bathroom,
		loadRoster,
		mood,
		notes,
		photoFile,
		selected,
		settings.nonce,
		settings.restUrl,
	] );

	const photoLabel = useMemo( () => {
		if ( photoFile && photoFile.name ) {
			return photoFile.name;
		}
		return 'Tap to take or choose photo';
	}, [ photoFile ] );

	if ( 'success' === view ) {
		return (
			<div className="kpr-root">
				<div className="kpr-success" role="status">
					<div className="kpr-success-icon" aria-hidden="true">
						✓
					</div>
					<p className="kpr-success-msg">Report sent!</p>
				</div>
			</div>
		);
	}

	if ( 'form' === view && selected ) {
		return (
			<div className="kpr-root">
				<header className="kpr-header">
					<button type="button" className="kpr-back" onClick={ goRoster }>
						← Back
					</button>
					<h1 className="kpr-title">{ selected.pet_title || 'Pet' }</h1>
					<p className="kpr-sub">Daily report card</p>
				</header>
				<main className="kpr-main">
					{ sendError ? (
						<p className="kpr-error" role="alert">
							{ sendError }
						</p>
					) : null }

					<div className="kpr-field">
						<label className="kpr-label" htmlFor="kpr-photo-input">
							Photo
						</label>
						<input
							id="kpr-photo-input"
							className="kpr-file-input"
							type="file"
							accept="image/*"
							capture="environment"
							onChange={ ( e ) => {
								const f = e.target.files && e.target.files[ 0 ];
								setPhotoFile( f || null );
							} }
						/>
						<label
							className={
								'kpr-file-btn' + ( photoFile ? ' has-file' : '' )
							}
							htmlFor="kpr-photo-input"
						>
							{ photoLabel }
						</label>
					</div>

					<div className="kpr-field">
						<span className="kpr-label">Mood</span>
						<div className="kpr-toggle-row" role="group" aria-label="Mood">
							{ [ 'happy', 'calm', 'anxious' ].map( ( m ) => (
								<button
									key={ m }
									type="button"
									className="kpr-toggle"
									aria-pressed={ mood === m }
									onClick={ () => setMood( m ) }
								>
									{ m.charAt( 0 ).toUpperCase() + m.slice( 1 ) }
								</button>
							) ) }
						</div>
					</div>

					<div className="kpr-field">
						<span className="kpr-label">Ate breakfast</span>
						<div className="kpr-toggle-row" role="group" aria-label="Ate breakfast">
							<button
								type="button"
								className="kpr-toggle kpr-toggle--half"
								aria-pressed={ ateFood === true }
								onClick={ () => setAteFood( true ) }
							>
								Yes
							</button>
							<button
								type="button"
								className="kpr-toggle kpr-toggle--half"
								aria-pressed={ ateFood === false }
								onClick={ () => setAteFood( false ) }
							>
								No
							</button>
						</div>
					</div>

					<div className="kpr-field">
						<span className="kpr-label">Bathroom normal</span>
						<div className="kpr-toggle-row" role="group" aria-label="Bathroom normal">
							<button
								type="button"
								className="kpr-toggle kpr-toggle--half"
								aria-pressed={ bathroom === true }
								onClick={ () => setBathroom( true ) }
							>
								Yes
							</button>
							<button
								type="button"
								className="kpr-toggle kpr-toggle--half"
								aria-pressed={ bathroom === false }
								onClick={ () => setBathroom( false ) }
							>
								No
							</button>
						</div>
					</div>

					<div className="kpr-field">
						<label className="kpr-label" htmlFor="kpr-notes">
							Notes
						</label>
						<textarea
							id="kpr-notes"
							className="kpr-notes"
							value={ notes }
							onChange={ ( e ) => setNotes( e.target.value ) }
							placeholder="Anything else we should share?"
						/>
					</div>

					<button
						type="button"
						className="kpr-submit"
						disabled={ sending }
						onClick={ submitReport }
					>
						{ sending ? 'Sending…' : 'Send Report Card' }
					</button>
				</main>
			</div>
		);
	}

	return (
		<div className="kpr-root">
			<header className="kpr-header">
				<h1 className="kpr-title">In-house today</h1>
				<p className="kpr-sub">Boarding — checked in</p>
			</header>
			<main className="kpr-main">
				{ loading ? (
					<p className="kpr-loading">Loading…</p>
				) : null }
				{ error ? <p className="kpr-error">{ error }</p> : null }
				{ ! loading && ! error && roster.length === 0 ? (
					<p className="kpr-empty">No pets checked in right now.</p>
				) : null }
				{ ! loading && roster.length > 0 ? (
					<ul className="kpr-list">
						{ roster.map( ( b ) => (
							<li key={ b.id }>
								<button
									type="button"
									className="kpr-card"
									onClick={ () => openForm( b ) }
								>
									{ b.pet_title || `Pet #${ b.pet_id }` }
									{ b.kennel_title ? (
										<span className="kpr-card-meta">
											{ b.kennel_title }
										</span>
									) : null }
								</button>
							</li>
						) ) }
					</ul>
				) : null }
			</main>
		</div>
	);
}

const mount = document.getElementById( 'kf-pwa-root' );
if ( mount ) {
	const settings = getSettings();
	const root = createRoot( mount );
	root.render( <App settings={ settings } /> );
}
