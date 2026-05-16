/**
 * [unordw_withdrawal_lookup] shortcode: order-lookup form handler.
 *
 * Submits order number + billing email via AJAX, shows the matched order on success,
 * then the customer continues to the withdrawal endpoint.
 */
( function () {
	'use strict';

	const root = document.getElementById( 'un-order-lookup' );
	const form = document.getElementById( 'un-order-lookup-form' );
	if ( ! form || ! root ) {
		return;
	}

	const l10n = window.unOrderLookup || {
		ajaxUrl: '',
		ajaxAction: '',
		nonce: '',
		fillAllFields: '',
		genericError: 'Error',
		orderMatchedHeading: '',
		orderNumberLabel: '',
		placedOnLabel: '',
		statusLabel: '',
		itemsHeading: '',
		quantityAbbrev: '',
		continueToWithdrawal: '',
		lookupAnother: '',
	};

	const errorBox   = document.getElementById( 'un-order-lookup-error' );
	const resultsBox = document.getElementById( 'un-order-lookup-results' );
	const submitBtn  = document.getElementById( 'un-order-lookup-submit' );

	/**
	 * @param {string} s
	 */
	function escapeHtml( s ) {
		const d = document.createElement( 'div' );
		d.textContent = s;
		return d.innerHTML;
	}

	/**
	 * @param {string} s
	 */
	function escapeAttr( s ) {
		return String( s )
			.replace( /&/g, '&amp;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#39;' )
			.replace( /</g, '&lt;' );
	}

	/**
	 * @param {string} msg
	 */
	function showError( msg ) {
		if ( errorBox ) {
			errorBox.textContent = msg;
			errorBox.hidden = false;
		}
	}

	function clearError() {
		if ( errorBox ) {
			errorBox.textContent = '';
			errorBox.hidden = true;
		}
	}

	function showForm() {
		form.hidden = false;
		if ( resultsBox ) {
			resultsBox.hidden = true;
			resultsBox.innerHTML = '';
		}
	}

	/**
	 * @param {object} order
	 * @param {string} redirect
	 */
	function showOrderResults( order, redirect ) {
		form.hidden = true;
		if ( ! resultsBox ) {
			window.location.assign( redirect );
			return;
		}

		const items = Array.isArray( order.items ) ? order.items : [];
		const rows = items
			.map( ( row ) => {
				const name = row && row.name ? String( row.name ) : '';
				const qty  = row && row.quantity ? String( row.quantity ) : '';
				return (
					'<tr><td>' +
					escapeHtml( name ) +
					'</td><td class="un-order-lookup__qty">' +
					escapeHtml( qty ) +
					'</td></tr>'
				);
			} )
			.join( '' );

		const num   = order.number != null ? String( order.number ) : '';
		const date  = order.date_formatted != null ? String( order.date_formatted ) : '';
		const stat  = order.status != null ? String( order.status ) : '';

		resultsBox.innerHTML =
			'<div class="un-order-lookup__results-inner">' +
			'<h3 class="un-order-lookup__results-title">' +
			escapeHtml( l10n.orderMatchedHeading || '' ) +
			'</h3>' +
			'<dl class="un-order-lookup__meta">' +
			'<div><dt>' +
			escapeHtml( l10n.orderNumberLabel || '' ) +
			'</dt><dd>' +
			escapeHtml( num ) +
			'</dd></div>' +
			'<div><dt>' +
			escapeHtml( l10n.placedOnLabel || '' ) +
			'</dt><dd>' +
			escapeHtml( date ) +
			'</dd></div>' +
			'<div><dt>' +
			escapeHtml( l10n.statusLabel || '' ) +
			'</dt><dd>' +
			escapeHtml( stat ) +
			'</dd></div>' +
			'</dl>' +
			'<h4 class="un-order-lookup__items-heading">' +
			escapeHtml( l10n.itemsHeading || '' ) +
			'</h4>' +
			'<table class="un-order-lookup__items shop_table">' +
			'<thead><tr><th>' +
			escapeHtml( l10n.itemsHeading || 'Items' ) +
			'</th><th class="un-order-lookup__qty-head">' +
			escapeHtml( l10n.quantityAbbrev || 'Qty' ) +
			'</th></tr></thead><tbody>' +
			( rows || '<tr><td colspan="2">—</td></tr>' ) +
			'</tbody></table>' +
			'<p class="un-order-lookup__results-actions">' +
			'<a href="' +
			escapeAttr( redirect ) +
			'" class="button woocommerce-button un-order-lookup__btn">' +
			escapeHtml( l10n.continueToWithdrawal || '' ) +
			'</a>' +
			' <button type="button" class="button un-order-lookup__btn-secondary" id="un-order-lookup-reset">' +
			escapeHtml( l10n.lookupAnother || '' ) +
			'</button>' +
			'</p>' +
			'</div>';

		resultsBox.hidden = false;

		const resetBtn = document.getElementById( 'un-order-lookup-reset' );
		if ( resetBtn ) {
			resetBtn.addEventListener( 'click', showForm );
		}
	}

	form.addEventListener( 'submit', ( e ) => {
		e.preventDefault();
		clearError();

		const numberInput = /** @type {HTMLInputElement|null} */ (
			form.querySelector( '[name="unordw_lookup_order_number"]' )
		);
		const emailInput = /** @type {HTMLInputElement|null} */ (
			form.querySelector( '[name="unordw_lookup_email"]' )
		);

		const orderNumber = numberInput ? numberInput.value.trim() : '';
		const email       = emailInput ? emailInput.value.trim() : '';

		if ( ! orderNumber || ! email ) {
			showError( l10n.fillAllFields || '' );
			return;
		}

		if ( submitBtn ) {
			submitBtn.disabled = true;
			submitBtn.setAttribute( 'aria-busy', 'true' );
		}

		const fd = new FormData();
		fd.set( 'action', l10n.ajaxAction );
		fd.set( 'nonce', l10n.nonce );
		fd.set( 'order_number', orderNumber );
		fd.set( 'billing_email', email );

		fetch( l10n.ajaxUrl, {
			method: 'POST',
			body: fd,
			credentials: 'same-origin',
		} )
			.then( ( r ) => r.json() )
			.then( ( data ) => {
				if ( data.success && data.data && data.data.redirect ) {
					const redirect = data.data.redirect;
					if ( data.data.order ) {
						showOrderResults( data.data.order, redirect );
					} else {
						window.location.assign( redirect );
					}
					return;
				}
				const msg =
					data.data && data.data.message
						? data.data.message
						: l10n.genericError;
				showError( msg );
			} )
			.catch( () => {
				showError( l10n.genericError || 'Error' );
			} )
			.finally( () => {
				if ( submitBtn ) {
					submitBtn.disabled = false;
					submitBtn.removeAttribute( 'aria-busy' );
				}
			} );
	} );
} )();
