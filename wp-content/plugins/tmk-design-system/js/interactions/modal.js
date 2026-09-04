/**
 * TMK Modal - interactions layer, generic and multi-instance, alongside Drawer and Accordion.
 *
 *   <div class="tmk-modal" data-tmk-modal id="X" hidden>
 *     <div class="tmk-modal__scrim" data-tmk-modal-scrim></div>
 *     <div class="tmk-modal__panel" role="dialog" aria-modal="true" aria-label="...">
 *       <button data-tmk-modal-close></button>
 *     </div>
 *   </div>
 *   <button data-tmk-modal-trigger="X">Open</button>
 *
 * Unlike Drawer (PROJECT_CONTEXT: "no focus trap, no focus-return" is a disclosed gap there) this
 * one implements both, because Modal.jsx specifies both and because aria-modal="true" is a promise
 * to assistive technology that the rest of the page is unreachable - a promise a keyboard user can
 * otherwise walk straight out of with one Tab.
 */
( function () {
	'use strict';

	var FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

	document.addEventListener( 'DOMContentLoaded', function () {
		var modals = document.querySelectorAll( '[data-tmk-modal]' );
		if ( ! modals.length ) return;

		var returnTo = null;

		function panelOf( modal ) {
			return modal.querySelector( '[role="dialog"]' ) || modal;
		}

		function open( modal, trigger ) {
			returnTo = trigger || document.activeElement;
			modal.hidden = false;
			document.body.style.overflow = 'hidden';
			var panel = panelOf( modal );
			var first = modal.querySelector( '[data-tmk-modal-close]' ) || panel;
			if ( first === panel && ! panel.hasAttribute( 'tabindex' ) ) panel.setAttribute( 'tabindex', '-1' );
			first.focus();
		}

		function close( modal ) {
			modal.hidden = true;
			document.body.style.overflow = '';
			if ( returnTo && returnTo.focus ) returnTo.focus();
			returnTo = null;
		}

		function trapTab( modal, e ) {
			var items = panelOf( modal ).querySelectorAll( FOCUSABLE );
			if ( ! items.length ) return;
			var first = items[0];
			var last  = items[ items.length - 1 ];
			if ( e.shiftKey && document.activeElement === first ) {
				last.focus();
				e.preventDefault();
			} else if ( ! e.shiftKey && document.activeElement === last ) {
				first.focus();
				e.preventDefault();
			}
		}

		document.querySelectorAll( '[data-tmk-modal-trigger]' ).forEach( function ( trigger ) {
			trigger.addEventListener( 'click', function () {
				var modal = document.getElementById( trigger.getAttribute( 'data-tmk-modal-trigger' ) );
				if ( modal ) open( modal, trigger );
			} );
		} );

		document.querySelectorAll( '[data-tmk-modal-scrim]' ).forEach( function ( scrim ) {
			scrim.addEventListener( 'click', function () {
				var modal = scrim.closest( '[data-tmk-modal]' );
				if ( modal ) close( modal );
			} );
		} );

		document.querySelectorAll( '[data-tmk-modal-close]' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var modal = btn.closest( '[data-tmk-modal]' );
				if ( modal ) close( modal );
			} );
		} );

		document.addEventListener( 'keydown', function ( e ) {
			var i;
			for ( i = 0; i < modals.length; i++ ) {
				if ( modals[ i ].hidden ) continue;
				if ( 'Escape' === e.key ) close( modals[ i ] );
				else if ( 'Tab' === e.key ) trapTab( modals[ i ], e );
			}
		} );
	} );
} )();
