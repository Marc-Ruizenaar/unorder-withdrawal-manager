/**
 * Admin: withdraw list — reject dialog (row + bulk).
 */
(function ( $ ) {
	'use strict';

	$( function () {
		var $form = $( '#un-order-withdrawal-requests' );
		var $dialog = $( '#un-order-reject-dialog' );
		var $single = $( '#un-order-reject-single' );
		var $bulk = $( '#un-order-reject-bulk' );
		var $requestId = $( '#un-order-reject-request-id' );
		var $singleReason = $( '#unordw_reject_reason' );
		var $bulkText = $( '#unordw_bulk_reject_textarea' );
		var $bulkReasonHidden = $( '#unordw_bulk_reject_reason' );

		function openDialog( isBulk ) {
			$dialog.prop( 'hidden', false );
			$dialog.addClass( 'is-open' );
			if ( isBulk ) {
				$single.prop( 'hidden', true );
				$bulk.prop( 'hidden', false );
				$bulkText.val( '' );
				$bulkText.trigger( 'focus' );
			} else {
				$single.prop( 'hidden', false );
				$bulk.prop( 'hidden', true );
			}
		}

		function closeDialog() {
			$dialog.prop( 'hidden', true );
			$dialog.removeClass( 'is-open' );
		}

		$( document ).on( 'click', '.un-order-open-reject', function ( e ) {
			e.preventDefault();
			var id = $( this ).data( 'request-id' );
			$requestId.val( id );
			$singleReason.val( '' );
			openDialog( false );
			$singleReason.trigger( 'focus' );
		} );

		$( document ).on( 'click', '#un-order-reject-cancel, #un-order-bulk-reject-cancel', function () {
			closeDialog();
		} );

		$dialog.on( 'click', function ( e ) {
			if ( e.target === this ) {
				closeDialog();
			}
		} );

		$( document ).on( 'click', '#un-order-bulk-reject-apply', function () {
			var t = ( $bulkText.val() || '' ).trim();
			$bulkReasonHidden.val( t );
			closeDialog();
			if ( $form.get( 0 ) ) {
				$form.get( 0 ).submit();
			}
		} );

		/* Long withdrawal reason: read-only popup */
		var $reasonDialog = $( '#un-order-reason-dialog' );
		var $reasonBody = $( '#un-order-reason-body' );
		var $reasonLastFocus = $();

		function openReasonDialog( fullText ) {
			if ( ! $reasonDialog.length || ! $reasonBody.length ) {
				return;
			}
			$reasonLastFocus = $( document.activeElement );
			$reasonBody.empty();
			$( '<pre class="un-order-reason-dialog__pre" />' )
				.text( typeof fullText === 'string' ? fullText : String( fullText ) )
				.appendTo( $reasonBody );
			$reasonDialog.removeAttr( 'hidden' );
			$reasonDialog.prop( 'hidden', false );
			$reasonDialog.addClass( 'is-open' );
			$( '#un-order-reason-close' ).trigger( 'focus' );
		}

		function closeReasonDialog() {
			$reasonDialog.removeClass( 'is-open' );
			$reasonDialog.attr( 'hidden', 'hidden' );
			$reasonDialog.prop( 'hidden', true );
			$reasonBody.empty();
			if ( $reasonLastFocus.length && $reasonLastFocus.is( ':visible' ) ) {
				$reasonLastFocus.trigger( 'focus' );
			}
			$reasonLastFocus = $();
		}

		$( document ).on( 'click', '.un-order-reason-open', function ( e ) {
			e.preventDefault();
			e.stopPropagation();
			var id = parseInt( $( this ).attr( 'data-reason-id' ) || '0', 10 );
			var map = window.unordwWithdrawalReasons || {};
			var text = map[ id ];
			if ( typeof text === 'undefined' ) {
				text = map[ String( id ) ];
			}
			if ( typeof text !== 'string' ) {
				text = '';
			}
			openReasonDialog( text );
		} );

		$reasonDialog.on( 'click', function ( e ) {
			if ( e.target === this ) {
				closeReasonDialog();
			}
		} );

		$( document ).on( 'click', '#un-order-reason-close', function () {
			closeReasonDialog();
		} );

		$( document ).on( 'keydown', function ( e ) {
			if ( e.key !== 'Escape' || ! $reasonDialog.hasClass( 'is-open' ) || $reasonDialog.prop( 'hidden' ) ) {
				return;
			}
			e.preventDefault();
			closeReasonDialog();
		} );

		$form.on( 'submit', function ( e ) {
			var $action1 = $form.find( 'select[name="action"]' );
			var $action2 = $form.find( 'select[name="action2"]' );
			var act = '-1';
			if ( $action1.length && $action1.val() !== '-1' ) {
				act = $action1.val();
			} else if ( $action2.length && $action2.val() !== '-1' ) {
				act = $action2.val();
			}
			if ( act !== 'reject' ) {
				return;
			}
			var $boxes = $form.find( 'input[name="unordw_withdrawal_requests[]"]:checked' );
			if ( ! $boxes.length ) {
				return;
			}
			// After user filled bulk modal, the hidden input is set — submit for real.
			if ( ( $bulkReasonHidden.val() || '' ).length > 0 ) {
				return;
			}
			e.preventDefault();
			$action1.val( 'reject' );
			if ( $action2.length ) {
				$action2.val( '-1' );
			}
			openDialog( true );
		} );
	} );
})( jQuery );
