/**
 * TMK motion utilities — IntersectionObserver-driven reveal, shared across every page.
 * Respects prefers-reduced-motion by skipping the observer entirely (CSS already shows
 * content immediately in that case; this avoids any pointless animation-frame work too).
 */
(function () {
	'use strict';

	var prefersReduced = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	document.addEventListener( 'DOMContentLoaded', function () {
		var targets = document.querySelectorAll( '.tmk-reveal, .tmk-reveal-stagger' );
		if ( ! targets.length ) return;

		if ( prefersReduced || ! ( 'IntersectionObserver' in window ) ) {
			targets.forEach( function ( el ) { el.classList.add( 'is-visible' ); } );
			return;
		}

		// Stagger index for children, consumed by the --tmk-stagger-i custom property in motion.css.
		document.querySelectorAll( '.tmk-reveal-stagger' ).forEach( function ( group ) {
			Array.prototype.forEach.call( group.children, function ( child, i ) {
				child.style.setProperty( '--tmk-stagger-i', i );
			} );
		} );

		var observer = new IntersectionObserver( function ( entries, obs ) {
			entries.forEach( function ( entry ) {
				if ( entry.isIntersecting ) {
					entry.target.classList.add( 'is-visible' );
					obs.unobserve( entry.target );
				}
			} );
		}, {
		// threshold 0 (not 0.15) plus a deeper bottom inset, because a percentage threshold can be
		// SKIPPED. IntersectionObserver reports the state it observes per frame, so a discrete jump -
		// a mouse-wheel burst, Page Down, a restored scroll position, an in-page anchor - can take an
		// element from below the viewport to only ~10% visible at the top edge without it ever having
		// been 15% visible, and the callback then never fires. Reproduced on the Homepage: Roma's four
		// restaurant cards sat on screen at opacity 0 after a two-step scroll.
		// With threshold 0 any intersection at all reveals, so the trigger cannot be jumped over, and
		// the -80px bottom inset preserves the original intent (reveal once the element has genuinely
		// entered the viewport, not the instant its first pixel appears) in absolute px instead of a
		// fraction of an element whose height varies with the breakpoint.
		threshold: 0,
		rootMargin: '0px 0px -80px 0px'
	} );

		targets.forEach( function ( el ) { observer.observe( el ); } );
	} );
})();
