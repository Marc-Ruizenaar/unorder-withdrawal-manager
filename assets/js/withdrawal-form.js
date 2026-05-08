/**
 * Withdrawal: per-line quantity → review → submit via admin-ajax (eu_withdrawal_submit).
 */
(function () {
	'use strict';

	const form = document.getElementById( 'un-order-withdrawal-form' );
	if ( ! form ) {
		return;
	}

	const root = form.closest( '.un-order-withdrawal' );
	const selectStep = document.getElementById( 'un-order-step-select' );
	const confirmStep = document.getElementById( 'un-order-step-confirm' );
	const continueBtn = document.getElementById( 'un-order-continue-confirm' );
	const backLink = document.getElementById( 'un-order-back-to-edit' );
	const confirmBtn = document.getElementById( 'un-order-confirm-withdrawal' );
	const errorBox = document.getElementById( 'un-order-continue-error' );
	const submitError = document.getElementById( 'un-order-submit-error' );
	const listEl = document.getElementById( 'un-order-confirm-list' );
	const reasonOut = document.getElementById( 'un-order-confirm-reason' );
	const reasonIn = document.getElementById( 'un_order_withdrawal_reason' );
	const l10n = window.unOrderWithdrawal || {
		ajaxAction: 'eu_withdrawal_submit',
		ajaxUrl: '/wp-admin/admin-ajax.php',
		nonce: '',
		selectItemError: '',
		itemQtySymbol: '\u00d7',
		noReason: '—',
		submitError: 'Error',
	};

	if ( ! selectStep || ! confirmStep || ! continueBtn || ! backLink || ! root ) {
		return;
	}

	form.addEventListener( 'submit', ( e ) => e.preventDefault() );

	backLink.addEventListener( 'click', ( e ) => {
		e.preventDefault();
		if ( submitError ) {
			submitError.textContent = '';
			submitError.hidden = true;
		}
		confirmStep.hidden = true;
		selectStep.hidden = false;
	} );

	/**
	 * @param {HTMLInputElement} input
	 * @param {string|number|undefined} raw
	 * @return {number}
	 */
	function normalizeQty( input, raw ) {
		const maxS = input.getAttribute( 'data-un-order-item-max' );
		const max = maxS == null || maxS === '' ? 0 : parseFloat( String( maxS ).replace( ',', '.' ) );
		const step = ( input.getAttribute( 'data-un-order-item-step' ) || '1' ).toLowerCase();
		let v = parseFloat( String( raw == null ? input.value : raw ).replace( ',', '.' ) );
		if ( ! Number.isFinite( v ) || Number.isNaN( v ) ) {
			v = 0;
		}
		if ( ! Number.isFinite( max ) || max < 0 ) {
			return 0;
		}
		v = Math.min( max, Math.max( 0, v ) );
		if ( step === '1' ) {
			v = Math.min( max, Math.max( 0, Math.round( v ) ) );
		} else {
			v = Math.round( v * 10000 ) / 10000;
		}
		if ( v > max + 0.00001 ) {
			v = max;
		}
		return v;
	}

	/**
	 * @param {number} v
	 * @return {string}
	 */
	function formatQty( v ) {
		if ( ! Number.isFinite( v ) || v < 0.0000001 ) {
			return '0';
		}
		if ( Math.abs( v - Math.round( v ) ) < 0.0000001 ) {
			return String( Math.round( v ) );
		}
		const r = Math.round( v * 10000 ) / 10000;
		return String( r );
	}

	continueBtn.addEventListener( 'click', () => {
		const inputs = form.querySelectorAll( 'input.un-order-withdrawal__item-qty' );
		/** @type { Array<{ label: string, qty: number }> } */
		const lines = [];
		inputs.forEach( ( el ) => {
			const n = normalizeQty( el, el.value );
			const st = ( el.getAttribute( 'data-un-order-item-step' ) || '1' ).toLowerCase();
			if ( st === '1' ) {
				el.value = n > 0 ? String( Math.round( n ) ) : '0';
			} else {
				el.value = n > 0 ? formatQty( n ) : '0';
			}
			const label = el.getAttribute( 'data-un-order-item-label' ) || '';
			if ( n > 0.0000001 ) {
				lines.push( { label, qty: n } );
			}
		} );
		if ( lines.length < 1 ) {
			if ( errorBox ) {
				errorBox.textContent = l10n.selectItemError || '';
				errorBox.hidden = false;
			}
			return;
		}
		if ( errorBox ) {
			errorBox.textContent = '';
			errorBox.hidden = true;
		}
		if ( listEl ) {
			const sym = l10n.itemQtySymbol != null && l10n.itemQtySymbol !== '' ? l10n.itemQtySymbol : '\u00d7';
			listEl.innerHTML = '';
			lines.forEach( ( { label, qty } ) => {
				const li = document.createElement( 'li' );
				const t = ( label && label.length > 0 ? label : '—' ) + ' ' + sym + ' ' + formatQty( qty );
				li.textContent = t;
				listEl.appendChild( li );
			} );
		}
		if ( reasonOut && reasonIn ) {
			const t = ( reasonIn.value || '' ).trim();
			reasonOut.textContent = t.length > 0 ? t : l10n.noReason;
		}
		if ( submitError ) {
			submitError.textContent = '';
			submitError.hidden = true;
		}
		selectStep.hidden = true;
		confirmStep.hidden = false;
	} );

	if ( ! confirmBtn ) {
		return;
	}

	confirmBtn.addEventListener( 'click', () => {
		if ( ! l10n.nonce || ! l10n.ajaxAction ) {
			return;
		}
		const orderId = root.getAttribute( 'data-order-id' ) || '';
		if ( ! orderId ) {
			if ( submitError ) {
				submitError.textContent = l10n.submitError;
				submitError.hidden = false;
			}
			return;
		}
		const fd = new FormData( form );
		fd.set( 'action', l10n.ajaxAction );
		fd.set( 'nonce', l10n.nonce );
		fd.set( 'order_id', orderId );
		confirmBtn.disabled = true;
		confirmBtn.setAttribute( 'aria-busy', 'true' );
		if ( submitError ) {
			submitError.textContent = '';
			submitError.hidden = true;
		}
		fetch( l10n.ajaxUrl, {
			method: 'POST',
			body: fd,
			credentials: 'same-origin',
		} )
			.then( ( r ) => r.json() )
			.then( ( data ) => {
				if ( data.success && data.data && data.data.redirect ) {
					window.location.assign( data.data.redirect );
					return;
				}
				const err =
					data.data && data.data.message
						? data.data.message
						: l10n.submitError;
				if ( submitError ) {
					submitError.textContent = err;
					submitError.hidden = false;
				}
			} )
			.catch( () => {
				if ( submitError ) {
					submitError.textContent = l10n.submitError;
					submitError.hidden = false;
				}
			} )
			.finally( () => {
				confirmBtn.disabled = false;
				confirmBtn.removeAttribute( 'aria-busy' );
			} );
	} );
} )();
