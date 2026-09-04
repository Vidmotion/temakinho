/**
 * TMK Drawer — interactions layer, generic and reusable (Drawer.jsx is used by more than just
 * the header's mobile nav — e.g. Filters). Supports any number of independent drawer instances
 * on one page via matching id attributes, rather than assuming a single global drawer:
 *   <aside data-tmk-drawer id="X">...<button data-tmk-drawer-close></button></aside>
 *   <button data-tmk-drawer-trigger="X">Open</button>
 *   <div data-tmk-drawer-scrim="X"></div>
 * Escape closes whichever drawer(s) are currently open.
 */
(function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var drawers = document.querySelectorAll( '[data-tmk-drawer]' );
		if ( ! drawers.length ) return;

		/*
		 * Every trigger that points at this drawer is a disclosure control, so it must expose its
		 * state (PROJECT_CONTEXT.md accessibility strategy: aria-expanded on interactive nav
		 * elements). The mobile-nav trigger was missing it entirely - found in the Homepage audit,
		 * 2026-07-29. Kept here in the generic layer so every future Drawer instance (Filters, a
		 * mobile cart) gets it for free instead of re-implementing it per component.
		 */
		function triggersFor( drawer ) {
			return document.querySelectorAll( '[data-tmk-drawer-trigger="' + drawer.id + '"]' );
		}
		function setExpanded( drawer, state ) {
			triggersFor( drawer ).forEach( function ( trigger ) {
				trigger.setAttribute( 'aria-expanded', state ? 'true' : 'false' );
			} );
		}
		function open( drawer ) {
			var scrim = document.querySelector( '[data-tmk-drawer-scrim="' + drawer.id + '"]' );
			drawer.hidden = false;
			if ( scrim ) scrim.hidden = false;
			document.body.style.overflow = 'hidden';
			setExpanded( drawer, true );
			// Move focus into the overlay so keyboard and screen-reader users land inside it.
			var first = drawer.querySelector( '[data-tmk-drawer-close]' ) || drawer;
			if ( first === drawer && ! drawer.hasAttribute( 'tabindex' ) ) drawer.setAttribute( 'tabindex', '-1' );
			first.focus();
		}
		function close( drawer ) {
			var scrim = document.querySelector( '[data-tmk-drawer-scrim="' + drawer.id + '"]' );
			drawer.hidden = true;
			if ( scrim ) scrim.hidden = true;
			document.body.style.overflow = '';
			setExpanded( drawer, false );
			// Return focus to the control that opened it, never to the top of the document.
			var trigger = triggersFor( drawer )[0];
			if ( trigger ) trigger.focus();
		}

		document.querySelectorAll( '[data-tmk-drawer-trigger]' ).forEach( function ( trigger ) {
			trigger.addEventListener( 'click', function () {
				var drawer = document.getElementById( trigger.getAttribute( 'data-tmk-drawer-trigger' ) );
				if ( drawer ) open( drawer );
			} );
		} );
		document.querySelectorAll( '[data-tmk-drawer-scrim]' ).forEach( function ( scrim ) {
			scrim.addEventListener( 'click', function () {
				var drawer = document.getElementById( scrim.getAttribute( 'data-tmk-drawer-scrim' ) );
				if ( drawer ) close( drawer );
			} );
		} );
		document.querySelectorAll( '[data-tmk-drawer-close]' ).forEach( function ( closeBtn ) {
			closeBtn.addEventListener( 'click', function () {
				var drawer = closeBtn.closest( '[data-tmk-drawer]' );
				if ( drawer ) close( drawer );
			} );
		} );
		document.addEventListener( 'keydown', function ( e ) {
			if ( e.key !== 'Escape' ) return;
			drawers.forEach( function ( drawer ) { if ( ! drawer.hidden ) close( drawer ); } );
		} );
	} );
})();
