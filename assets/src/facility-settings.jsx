import React from 'react';
import { createRoot } from 'react-dom/client';

import './facility-settings.css';

const DAY_KEYS = [ 'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun' ];
const DAY_LABELS = {
	mon: 'Monday',
	tue: 'Tuesday',
	wed: 'Wednesday',
	thu: 'Thursday',
	fri: 'Friday',
	sat: 'Saturday',
	sun: 'Sunday',
};

function localToUtcMysql( localDatetimeValue ) {
	if ( ! localDatetimeValue ) {
		return '';
	}
	const d = new Date( localDatetimeValue );
	if ( isNaN( d.getTime() ) ) {
		return '';
	}
	return d.toISOString().slice( 0, 19 ).replace( 'T', ' ' );
}

function utcMysqlToDatetimeLocal( utcMysql ) {
	if ( ! utcMysql || typeof utcMysql !== 'string' ) {
		return '';
	}
	const d = new Date( utcMysql.replace( ' ', 'T' ) + 'Z' );
	if ( isNaN( d.getTime() ) ) {
		return '';
	}
	return d.toISOString().slice( 0, 16 );
}

function FacilitySettingsApp() {
	// Localized in PHP as `kennelflowBoardingFacilitySettings` (see KennelFlow_Boarding_Admin::enqueue_admin_scripts).
	const cfg =
		typeof window !== 'undefined'
			? window.kennelflowBoardingFacilitySettings || window.kennelpressFacilitySettings || {}
			: {};
	const canEdit = !! cfg.canEdit;
	const [ loading, setLoading ] = React.useState( true );
	const [ saving, setSaving ] = React.useState( false );
	const [ err, setErr ] = React.useState( '' );
	const [ ok, setOk ] = React.useState( '' );
	const [ locations, setLocations ] = React.useState( [] );
	const [ tzChoices, setTzChoices ] = React.useState( [] );

	const load = React.useCallback( async () => {
		setErr( '' );
		setLoading( true );
		try {
			const r = await fetch( cfg.restUrl, {
				credentials: 'same-origin',
				headers: {
					Accept: 'application/json',
					'X-WP-Nonce': cfg.nonce,
				},
			} );
			if ( ! r.ok ) {
				throw new Error( 'HTTP ' + r.status );
			}
			const data = await r.json();
			setLocations( data.locations || [] );
			setTzChoices( data.timezone_choices || [] );
		} catch ( e ) {
			setErr( e.message || String( e ) );
		} finally {
			setLoading( false );
		}
	}, [ cfg.nonce, cfg.restUrl ] );

	React.useEffect( () => {
		load();
	}, [ load ] );

	const updateSettings = ( locId, patch ) => {
		setLocations( ( prev ) =>
			prev.map( ( loc ) =>
				loc.id === locId ? { ...loc, settings: { ...loc.settings, ...patch } } : loc
			)
		);
	};

	const updateWeekly = ( locId, day, patch ) => {
		setLocations( ( prev ) =>
			prev.map( ( loc ) => {
				if ( loc.id !== locId ) {
					return loc;
				}
				const w = { ...loc.settings.weekly };
				w[ day ] = { ...w[ day ], ...patch };
				return { ...loc, settings: { ...loc.settings, weekly: w } };
			} )
		);
	};

	const addHoliday = ( locId ) => {
		setLocations( ( prev ) =>
			prev.map( ( loc ) => {
				if ( loc.id !== locId ) {
					return loc;
				}
				const h = [ ...loc.settings.holidays, { start: '', end: '', label: '' } ];
				return { ...loc, settings: { ...loc.settings, holidays: h } };
			} )
		);
	};

	const setHoliday = ( locId, index, patch ) => {
		setLocations( ( prev ) =>
			prev.map( ( loc ) => {
				if ( loc.id !== locId ) {
					return loc;
				}
				const h = loc.settings.holidays.map( ( row, i ) =>
					i === index ? { ...row, ...patch } : row
				);
				return { ...loc, settings: { ...loc.settings, holidays: h } };
			} )
		);
	};

	const removeHoliday = ( locId, index ) => {
		setLocations( ( prev ) =>
			prev.map( ( loc ) => {
				if ( loc.id !== locId ) {
					return loc;
				}
				const h = loc.settings.holidays.filter( ( _, i ) => i !== index );
				return { ...loc, settings: { ...loc.settings, holidays: h } };
			} )
		);
	};

	const setBlackout = ( locId, index, patch ) => {
		setLocations( ( prev ) =>
			prev.map( ( loc ) => {
				if ( loc.id !== locId ) {
					return loc;
				}
				const b = loc.settings.blackouts.map( ( row, i ) =>
					i === index ? { ...row, ...patch } : row
				);
				return { ...loc, settings: { ...loc.settings, blackouts: b } };
			} )
		);
	};

	const addBlackout = ( locId ) => {
		setLocations( ( prev ) =>
			prev.map( ( loc ) => {
				if ( loc.id !== locId ) {
					return loc;
				}
				const b = [ ...loc.settings.blackouts, { start_gmt: '', end_gmt: '', label: '' } ];
				return { ...loc, settings: { ...loc.settings, blackouts: b } };
			} )
		);
	};

	const removeBlackout = ( locId, index ) => {
		setLocations( ( prev ) =>
			prev.map( ( loc ) => {
				if ( loc.id !== locId ) {
					return loc;
				}
				const b = loc.settings.blackouts.filter( ( _, i ) => i !== index );
				return { ...loc, settings: { ...loc.settings, blackouts: b } };
			} )
		);
	};

	const boardingDayDefaults = () => ( {
		closed: false,
		drop_start: '07:00',
		drop_end: '10:00',
		pick_start: '12:00',
		pick_end: '18:00',
		ext_end: '',
	} );

	const updateBoardingDaily = ( locId, day, patch ) => {
		setLocations( ( prev ) =>
			prev.map( ( loc ) => {
				if ( loc.id !== locId ) {
					return loc;
				}
				const raw = loc.settings.boarding_daily && loc.settings.boarding_daily[ day ]
					? loc.settings.boarding_daily[ day ]
					: boardingDayDefaults();
				const b = { ...( loc.settings.boarding_daily || {} ) };
				b[ day ] = { ...raw, ...patch };
				return { ...loc, settings: { ...loc.settings, boarding_daily: b } };
			} )
		);
	};

	const setBoardingTier = ( locId, index, patch ) => {
		setLocations( ( prev ) =>
			prev.map( ( loc ) => {
				if ( loc.id !== locId ) {
					return loc;
				}
				const tiers = Array.isArray( loc.settings.boarding_discount_tiers )
					? [ ...loc.settings.boarding_discount_tiers ]
					: [];
				tiers[ index ] = { ...tiers[ index ], ...patch };
				return { ...loc, settings: { ...loc.settings, boarding_discount_tiers: tiers } };
			} )
		);
	};

	const addBoardingTier = ( locId ) => {
		setLocations( ( prev ) =>
			prev.map( ( loc ) => {
				if ( loc.id !== locId ) {
					return loc;
				}
				const tiers = Array.isArray( loc.settings.boarding_discount_tiers )
					? [ ...loc.settings.boarding_discount_tiers ]
					: [];
				tiers.push( { min_nights: 7, type: 'percent', value: 10 } );
				return { ...loc, settings: { ...loc.settings, boarding_discount_tiers: tiers } };
			} )
		);
	};

	const removeBoardingTier = ( locId, index ) => {
		setLocations( ( prev ) =>
			prev.map( ( loc ) => {
				if ( loc.id !== locId ) {
					return loc;
				}
				const tiers = Array.isArray( loc.settings.boarding_discount_tiers )
					? loc.settings.boarding_discount_tiers.filter( ( _, i ) => i !== index )
					: [];
				return { ...loc, settings: { ...loc.settings, boarding_discount_tiers: tiers } };
			} )
		);
	};

	const save = async () => {
		setErr( '' );
		setOk( '' );
		setSaving( true );
		const payload = { locations: {} };
		locations.forEach( ( loc ) => {
			payload.locations[ String( loc.id ) ] = loc.settings;
		} );
		try {
			const r = await fetch( cfg.restUrl, {
				method: 'PUT',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/json',
					Accept: 'application/json',
					'X-WP-Nonce': cfg.nonce,
				},
				body: JSON.stringify( payload ),
			} );
			const raw = await r.text();
			let data;
			try {
				data = JSON.parse( raw );
			} catch ( e ) {
				data = null;
			}
			if ( ! r.ok ) {
				throw new Error( ( data && data.message ) || raw || 'HTTP ' + r.status );
			}
			if ( data && data.locations ) {
				setLocations( data.locations );
			}
			setOk( 'Saved.' );
		} catch ( e ) {
			setErr( e.message || String( e ) );
		} finally {
			setSaving( false );
		}
	};

	if ( loading ) {
		return <p className="kennelpress-fs-muted">Loading…</p>;
	}

	return (
		<div className="kennelpress-fs-root">
			{ ! canEdit ? (
				<div className="notice notice-info inline">
					<p>
						You can view kennel rules but not change them. Users who can edit posts
						or manage options can edit.
					</p>
				</div>
			) : null }
			{ err ? (
				<div className="kennelpress-fs-msg error" role="alert">
					{ err }
				</div>
			) : null }
			{ ok ? (
				<div className="kennelpress-fs-msg ok" role="status">
					{ ok }
				</div>
			) : null }

			{ locations.length === 0 && ! err ? (
				<div className="notice notice-warning inline">
					<p>
						<strong>No locations yet.</strong> Rules are saved per location. Add at least one
						KennelFlow Location (post), then assign kennels to that location when editing kennels.{ ' ' }
						{ cfg.locationsAdminUrl ? (
							<a href={ cfg.locationsAdminUrl }>Manage locations</a>
						) : null }
					</p>
				</div>
			) : null }

			{ locations.map( ( loc ) => (
				<details key={ loc.id } className="kennelpress-fs-loc" open>
					<summary>
						{ loc.name } <span className="kennelpress-fs-muted">(ID { loc.id })</span>
					</summary>
					<div className="kennelpress-fs-body">
						<p>
							<label>
								<input
									type="checkbox"
									checked={ !! loc.settings.enabled }
									disabled={ ! canEdit }
									onChange={ ( e ) => updateSettings( loc.id, { enabled: e.target.checked } ) }
								/>{ ' ' }
								Enforce rules for this location
							</label>
						</p>
						<p>
							<label>
								Timezone{' '}
								<select
									value={ loc.settings.timezone || 'UTC' }
									disabled={ ! canEdit }
									onChange={ ( e ) => updateSettings( loc.id, { timezone: e.target.value } ) }
								>
									{ tzChoices.map( ( tz ) => (
										<option key={ tz } value={ tz }>
											{ tz }
										</option>
									) ) }
								</select>
							</label>
						</p>

						<h4 className="kennelpress-fs-row-head">Weekly hours (drop-off / pick-up)</h4>
						<table className="kennelpress-fs-table">
							<thead>
								<tr>
									<th>Day</th>
									<th>Closed</th>
									<th>Open</th>
									<th>Close</th>
								</tr>
							</thead>
							<tbody>
								{ DAY_KEYS.map( ( day ) => {
									const wd = loc.settings.weekly[ day ] || {
										closed: false,
										open: '09:00',
										close: '17:00',
									};
									return (
										<tr key={ day }>
											<td>{ DAY_LABELS[ day ] }</td>
											<td>
												<input
													type="checkbox"
													checked={ !! wd.closed }
													disabled={ ! canEdit }
													onChange={ ( e ) =>
														updateWeekly( loc.id, day, { closed: e.target.checked } )
													}
												/>
											</td>
											<td>
												<input
													type="time"
													value={ wd.open || '09:00' }
													onChange={ ( e ) =>
														updateWeekly( loc.id, day, { open: e.target.value } )
													}
													disabled={ ! canEdit || !! wd.closed }
												/>
											</td>
											<td>
												<input
													type="time"
													value={ wd.close || '17:00' }
													onChange={ ( e ) =>
														updateWeekly( loc.id, day, { close: e.target.value } )
													}
													disabled={ ! canEdit || !! wd.closed }
												/>
											</td>
										</tr>
									);
								} ) }
							</tbody>
						</table>

						<h4 className="kennelpress-fs-row-head">Boarding rules &amp; pricing</h4>
						<p className="kennelpress-fs-muted">
							When <strong>boarding windows</strong> are enabled, drop-off and pick-up times use the table
							below instead of the weekly open/close column. Legacy weekly hours still apply when boarding rules
							are off.
						</p>
						<p>
							<label>
								<input
									type="checkbox"
									checked={ !! loc.settings.boarding_rules_enabled }
									disabled={ ! canEdit }
									onChange={ ( e ) => updateSettings( loc.id, { boarding_rules_enabled: e.target.checked } ) }
								/>{ ' ' }
								Use boarding drop-off / pick-up windows (above weekly hours)
							</label>
						</p>

						<h5>Boarding windows (local time)</h5>
						<table className="kennelpress-fs-table kennelpress-fs-table-boarding">
							<thead>
								<tr>
									<th>Day</th>
									<th>Closed</th>
									<th>Drop start</th>
									<th>Drop end</th>
									<th>Pick start</th>
									<th>Pick end</th>
									<th>Ext. end</th>
								</tr>
							</thead>
							<tbody>
								{ DAY_KEYS.map( ( day ) => {
									const bd =
										loc.settings.boarding_daily && loc.settings.boarding_daily[ day ]
											? loc.settings.boarding_daily[ day ]
											: boardingDayDefaults();
									return (
										<tr key={ day }>
											<td>{ DAY_LABELS[ day ] }</td>
											<td>
												<input
													type="checkbox"
													checked={ !! bd.closed }
													disabled={ ! canEdit }
													onChange={ ( e ) =>
														updateBoardingDaily( loc.id, day, { closed: e.target.checked } )
													}
												/>
											</td>
											<td>
												<input
													type="time"
													value={ bd.drop_start || '07:00' }
													onChange={ ( e ) => updateBoardingDaily( loc.id, day, { drop_start: e.target.value } ) }
													disabled={ ! canEdit || !! bd.closed }
												/>
											</td>
											<td>
												<input
													type="time"
													value={ bd.drop_end || '10:00' }
													onChange={ ( e ) => updateBoardingDaily( loc.id, day, { drop_end: e.target.value } ) }
													disabled={ ! canEdit || !! bd.closed }
												/>
											</td>
											<td>
												<input
													type="time"
													value={ bd.pick_start || '12:00' }
													onChange={ ( e ) => updateBoardingDaily( loc.id, day, { pick_start: e.target.value } ) }
													disabled={ ! canEdit || !! bd.closed }
												/>
											</td>
											<td>
												<input
													type="time"
													value={ bd.pick_end || '18:00' }
													onChange={ ( e ) => updateBoardingDaily( loc.id, day, { pick_end: e.target.value } ) }
													disabled={ ! canEdit || !! bd.closed }
												/>
											</td>
											<td>
												<input
													type="time"
													value={ bd.ext_end || '' }
													onChange={ ( e ) =>
														updateBoardingDaily( loc.id, day, { ext_end: e.target.value } )
													}
													disabled={ ! canEdit || !! bd.closed }
												/>
											</td>
										</tr>
									);
								} ) }
							</tbody>
						</table>

						<div className="kennelpress-fs-boarding-grid">
							<p>
								<label>
									<input
										type="checkbox"
										checked={ !! loc.settings.boarding_extended_hours_enabled }
										disabled={ ! canEdit }
										onChange={ ( e ) =>
											updateSettings( loc.id, { boarding_extended_hours_enabled: e.target.checked } )
										}
									/>{ ' ' }
									Extended pick-up (fee)
								</label>
							</p>
							<label>
								Extended fee
								<input
									type="number"
									min="0"
									step="0.01"
									value={ loc.settings.boarding_extended_fee ?? 0 }
									disabled={ ! canEdit }
									onChange={ ( e ) =>
										updateSettings( loc.id, { boarding_extended_fee: parseFloat( e.target.value ) || 0 } )
									}
								/>
							</label>
							<p>
								<label>
									<input
										type="checkbox"
										checked={ !! loc.settings.boarding_charge_extra_day_after_extended }
										disabled={ ! canEdit }
										onChange={ ( e ) =>
											updateSettings( loc.id, {
												boarding_charge_extra_day_after_extended: e.target.checked,
											} )
										}
									/>{ ' ' }
									Charge an extra day after extended pick-up
								</label>
							</p>
							<p>
								<label>
									<input
										type="checkbox"
										checked={ !! loc.settings.boarding_emergency_drop_enabled }
										disabled={ ! canEdit }
										onChange={ ( e ) =>
											updateSettings( loc.id, { boarding_emergency_drop_enabled: e.target.checked } )
										}
									/>{ ' ' }
									Allow emergency drop-off (before window opens, fee)
								</label>
							</p>
							<label>
								Emergency drop fee
								<input
									type="number"
									min="0"
									step="0.01"
									value={ loc.settings.boarding_emergency_drop_fee ?? 0 }
									disabled={ ! canEdit }
									onChange={ ( e ) =>
										updateSettings( loc.id, { boarding_emergency_drop_fee: parseFloat( e.target.value ) || 0 } )
									}
								/>
							</label>
							<p>
								<label>
									<input
										type="checkbox"
										checked={ !! loc.settings.boarding_food_enabled }
										disabled={ ! canEdit }
										onChange={ ( e ) => updateSettings( loc.id, { boarding_food_enabled: e.target.checked } ) }
									/>{ ' ' }
									Kennel food add-on
								</label>
							</p>
							<label>
								Food fee (per pet / night)
								<input
									type="number"
									min="0"
									step="0.01"
									value={ loc.settings.boarding_food_fee ?? 0 }
									disabled={ ! canEdit }
									onChange={ ( e ) =>
										updateSettings( loc.id, { boarding_food_fee: parseFloat( e.target.value ) || 0 } )
									}
								/>
							</label>
						</div>

						<h5>Daily rates</h5>
						<div className="kennelpress-fs-boarding-grid">
							<label>
								Fallback daily (if size fees empty)
								<input
									type="number"
									min="0"
									step="0.01"
									value={ loc.settings.boarding_daily_fee ?? 0 }
									disabled={ ! canEdit }
									onChange={ ( e ) =>
										updateSettings( loc.id, { boarding_daily_fee: parseFloat( e.target.value ) || 0 } )
									}
								/>
							</label>
							<label>
								Small
								<input
									type="number"
									min="0"
									step="0.01"
									value={ loc.settings.boarding_fee_small ?? 0 }
									disabled={ ! canEdit }
									onChange={ ( e ) =>
										updateSettings( loc.id, { boarding_fee_small: parseFloat( e.target.value ) || 0 } )
									}
								/>
							</label>
							<label>
								Medium
								<input
									type="number"
									min="0"
									step="0.01"
									value={ loc.settings.boarding_fee_medium ?? 0 }
									disabled={ ! canEdit }
									onChange={ ( e ) =>
										updateSettings( loc.id, { boarding_fee_medium: parseFloat( e.target.value ) || 0 } )
									}
								/>
							</label>
							<label>
								Large
								<input
									type="number"
									min="0"
									step="0.01"
									value={ loc.settings.boarding_fee_large ?? 0 }
									disabled={ ! canEdit }
									onChange={ ( e ) =>
										updateSettings( loc.id, { boarding_fee_large: parseFloat( e.target.value ) || 0 } )
									}
								/>
							</label>
							<label>
								Additional pet (per night)
								<input
									type="number"
									min="0"
									step="0.01"
									value={ loc.settings.boarding_additional_pet_fee ?? 0 }
									disabled={ ! canEdit }
									onChange={ ( e ) =>
										updateSettings( loc.id, {
											boarding_additional_pet_fee: parseFloat( e.target.value ) || 0,
										} )
									}
								/>
							</label>
						</div>

						<h5>Stay discounts (tiers)</h5>
						<p className="kennelpress-fs-muted">First matching tier (highest min nights) wins.</p>
						{ ( Array.isArray( loc.settings.boarding_discount_tiers ) ? loc.settings.boarding_discount_tiers : [] ).map(
							( tier, i ) => (
								<div key={ i } className="kennelpress-fs-add-row">
									<label>
										Min nights
										<input
											type="number"
											min="1"
											value={ tier.min_nights ?? 1 }
											disabled={ ! canEdit }
											onChange={ ( e ) =>
												setBoardingTier( loc.id, i, { min_nights: parseInt( e.target.value, 10 ) || 1 } )
											}
										/>
									</label>
									<label>
										Type
										<select
											value={ tier.type === 'flat' ? 'flat' : 'percent' }
											disabled={ ! canEdit }
											onChange={ ( e ) => setBoardingTier( loc.id, i, { type: e.target.value } ) }
										>
											<option value="percent">Percent off</option>
											<option value="flat">Flat amount off</option>
										</select>
									</label>
									<label>
										Value
										<input
											type="number"
											min="0"
											step="0.01"
											value={ tier.value ?? 0 }
											disabled={ ! canEdit }
											onChange={ ( e ) =>
												setBoardingTier( loc.id, i, { value: parseFloat( e.target.value ) || 0 } )
											}
										/>
									</label>
									<button
										type="button"
										className="button"
										onClick={ () => removeBoardingTier( loc.id, i ) }
										disabled={ ! canEdit }
									>
										Remove
									</button>
								</div>
							)
						) }
						<button type="button" className="button" onClick={ () => addBoardingTier( loc.id ) } disabled={ ! canEdit }>
							Add tier
						</button>

						<h5>Pricing output &amp; WooCommerce</h5>
						<p>
							<label>
								How prices apply
								<select
									value={ loc.settings.boarding_price_application || 'quote_only' }
									disabled={ ! canEdit }
									onChange={ ( e ) =>
										updateSettings( loc.id, { boarding_price_application: e.target.value } )
									}
								>
									<option value="quote_only">Quote on booking only</option>
									<option value="woocommerce">WooCommerce checkout</option>
									<option value="both">Both (quote + checkout)</option>
								</select>
							</label>
						</p>
						<div className="kennelpress-fs-boarding-grid">
							<label>
								WC product ID
								<input
									type="number"
									min="0"
									value={ loc.settings.boarding_wc_product_id ?? 0 }
									disabled={ ! canEdit }
									onChange={ ( e ) =>
										updateSettings( loc.id, { boarding_wc_product_id: parseInt( e.target.value, 10 ) || 0 } )
									}
								/>
							</label>
							<label>
								Variation: small
								<input
									type="number"
									min="0"
									value={ loc.settings.boarding_wc_variation_small ?? 0 }
									disabled={ ! canEdit }
									onChange={ ( e ) =>
										updateSettings( loc.id, {
											boarding_wc_variation_small: parseInt( e.target.value, 10 ) || 0,
										} )
									}
								/>
							</label>
							<label>
								Variation: medium
								<input
									type="number"
									min="0"
									value={ loc.settings.boarding_wc_variation_medium ?? 0 }
									disabled={ ! canEdit }
									onChange={ ( e ) =>
										updateSettings( loc.id, {
											boarding_wc_variation_medium: parseInt( e.target.value, 10 ) || 0,
										} )
									}
								/>
							</label>
							<label>
								Variation: large
								<input
									type="number"
									min="0"
									value={ loc.settings.boarding_wc_variation_large ?? 0 }
									disabled={ ! canEdit }
									onChange={ ( e ) =>
										updateSettings( loc.id, {
											boarding_wc_variation_large: parseInt( e.target.value, 10 ) || 0,
										} )
									}
								/>
							</label>
						</div>

						<h5>Intake &amp; interview</h5>
						<p>
							<label>
								<input
									type="checkbox"
									checked={ !! loc.settings.boarding_intake_form_enabled }
									disabled={ ! canEdit }
									onChange={ ( e ) =>
										updateSettings( loc.id, { boarding_intake_form_enabled: e.target.checked } )
									}
								/>{ ' ' }
								Show intake form in booking wizard
							</label>
						</p>
						<p>
							<label>
								<input
									type="checkbox"
									checked={ !! loc.settings.boarding_interview_enabled }
									disabled={ ! canEdit }
									onChange={ ( e ) =>
										updateSettings( loc.id, { boarding_interview_enabled: e.target.checked } )
									}
								/>{ ' ' }
								Offer pre-boarding interview request
							</label>
						</p>
						<p>
							<label>
								Interview instructions (shown to owners)
								<textarea
									rows={ 3 }
									className="large-text"
									value={ loc.settings.boarding_interview_instructions || '' }
									disabled={ ! canEdit }
									onChange={ ( e ) =>
										updateSettings( loc.id, { boarding_interview_instructions: e.target.value } )
									}
								/>
							</label>
						</p>

						<h4>Holiday closures (local dates)</h4>
						<p className="kennelpress-fs-muted">Stays that touch any day in these ranges are blocked.</p>
						{ loc.settings.holidays.map( ( h, i ) => (
							<div key={ i } className="kennelpress-fs-add-row">
								<label>
									Start
									<input
										type="date"
										value={ h.start }
										disabled={ ! canEdit }
										onChange={ ( e ) => setHoliday( loc.id, i, { start: e.target.value } ) }
									/>
								</label>
								<label>
									End
									<input
										type="date"
										value={ h.end }
										disabled={ ! canEdit }
										onChange={ ( e ) => setHoliday( loc.id, i, { end: e.target.value } ) }
									/>
								</label>
								<label>
									Label
									<input
										type="text"
										value={ h.label }
										disabled={ ! canEdit }
										onChange={ ( e ) => setHoliday( loc.id, i, { label: e.target.value } ) }
									/>
								</label>
								<button type="button" className="button" onClick={ () => removeHoliday( loc.id, i ) } disabled={ ! canEdit }>
									Remove
								</button>
							</div>
						) ) }
						<button type="button" className="button" onClick={ () => addHoliday( loc.id ) } disabled={ ! canEdit }>
							Add holiday range
						</button>

						<h4>Blackout windows (UTC)</h4>
						<p className="kennelpress-fs-muted">Use your local time; stored as UTC.</p>
						{ loc.settings.blackouts.map( ( b, i ) => (
							<div key={ i } className="kennelpress-fs-add-row">
								<label>
									Start (local)
									<input
										type="datetime-local"
										value={ utcMysqlToDatetimeLocal( b.start_gmt ) }
										disabled={ ! canEdit }
										onChange={ ( e ) => {
											const s = localToUtcMysql( e.target.value );
											setBlackout( loc.id, i, { start_gmt: s } );
										} }
									/>
								</label>
								<label>
									End (local)
									<input
										type="datetime-local"
										value={ utcMysqlToDatetimeLocal( b.end_gmt ) }
										disabled={ ! canEdit }
										onChange={ ( e ) => {
											const s = localToUtcMysql( e.target.value );
											setBlackout( loc.id, i, { end_gmt: s } );
										} }
									/>
								</label>
								<label>
									Label
									<input
										type="text"
										value={ b.label }
										disabled={ ! canEdit }
										onChange={ ( e ) => setBlackout( loc.id, i, { label: e.target.value } ) }
									/>
								</label>
								<button type="button" className="button" onClick={ () => removeBlackout( loc.id, i ) } disabled={ ! canEdit }>
									Remove
								</button>
							</div>
						) ) }
						<button type="button" className="button" onClick={ () => addBlackout( loc.id ) } disabled={ ! canEdit }>
							Add blackout
						</button>
					</div>
				</details>
			) ) }

			<p className="kennelpress-fs-actions">
				<button type="button" className="button button-primary" onClick={ save } disabled={ ! canEdit || saving }>
					{ saving ? 'Saving…' : 'Save all' }
				</button>
			</p>
		</div>
	);
}

const mount = document.getElementById( 'kennelflow-boarding-facility-settings-root' );
if ( mount ) {
	createRoot( mount ).render( <FacilitySettingsApp /> );
}
