/**
 * TMK Refinements — the terminal layer's runtime.
 *
 * Shell-level behaviour that ShellFrame.jsx owns in the design source and that no single
 * component can own here, because each piece spans the whole document: the header's condensed
 * state, the search overlay, and the ambient parallax on the page decor.
 *
 * Deliberately NOT here: scroll-reveal. js/animations/reveal.js already owns that, and the
 * elements that opt in are tagged server-side (inc/card-render.php, and _css_classes on the
 * Elementor containers) rather than being discovered by this script. That ordering matters:
 * .tmk-reveal starts at opacity 0, so a footer script that adds the class after the browser has
 * already painted the top of the page would produce a visible flash of content that then hides
 * itself. Markup that ships with the class has no such window.
 *
 * Every block below is independent and guards its own targets, so a page without a header, or
 * without decor, costs nothing and throws nothing.
 */
( function () {
	'use strict';

	var reduceMotion = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	/**
	 * rAF-throttled scroll subscription. One listener per behaviour, but never more than one
	 * layout read per frame — scroll handlers that touch geometry synchronously are the classic
	 * way to turn a 60fps page into a 20fps one.
	 */
	function onScroll( handler ) {
		var ticking = false;
		function tick() {
			if ( ticking ) {
				return;
			}
			ticking = true;
			window.requestAnimationFrame( function () {
				handler( window.pageYOffset || document.documentElement.scrollTop );
				ticking = false;
			} );
		}
		window.addEventListener( 'scroll', tick, { passive: true } );
		tick();
	}

	/* 1 — CONDENSED HEADER ---------------------------------------------------
	 * Past 40px of scroll the header trades height for content (ShellFrame.jsx's StickyChrome).
	 * The whole visual result already lives in CSS (.tmk-header.is-condensed in
	 * css/layout/header.css and css/refinements/shell.css); this only owns the boolean. */
	function initCondensedHeader() {
		var header = document.getElementById( 'tmk-header' );
		if ( ! header ) {
			return;
		}
		onScroll( function ( y ) {
			var condensed = y > 40;
			if ( condensed !== header.classList.contains( 'is-condensed' ) ) {
				header.classList.toggle( 'is-condensed', condensed );
			}
		} );
	}

	/* 2 — SEARCH OVERLAY ----------------------------------------------------
	 * Answers the header's existing search control. Focus is moved into the dialog, trapped
	 * while it is open, and returned to the control that opened it — the part of a modal that
	 * is invisible to sighted mouse users and the entire experience for everyone else. */
	function initSearchOverlay() {
		var overlay = document.querySelector( '[data-tmk-search-overlay]' );
		var triggers = document.querySelectorAll( '[data-tmk-search-trigger]' );
		if ( ! overlay || ! triggers.length ) {
			return;
		}

		var panel = overlay.querySelector( '.tmk-search-overlay__panel' );
		var field = overlay.querySelector( '#tmk-search-field' );
		var lastFocused = null;

		function focusable() {
			return Array.prototype.filter.call(
				panel.querySelectorAll( 'a[href], button, input, select, textarea, [tabindex]:not([tabindex="-1"])' ),
				function ( el ) {
					return ! el.disabled && el.offsetParent !== null;
				}
			);
		}

		function open() {
			lastFocused = document.activeElement;
			overlay.hidden = false;
			document.body.classList.add( 'tmk-scroll-locked' );
			Array.prototype.forEach.call( triggers, function ( t ) {
				t.setAttribute( 'aria-expanded', 'true' );
			} );
			if ( field ) {
				field.focus();
				field.select();
			}
		}

		function close() {
			if ( overlay.hidden ) {
				return;
			}
			overlay.hidden = true;
			document.body.classList.remove( 'tmk-scroll-locked' );
			Array.prototype.forEach.call( triggers, function ( t ) {
				t.setAttribute( 'aria-expanded', 'false' );
			} );
			if ( lastFocused && lastFocused.focus ) {
				lastFocused.focus();
			}
		}

		Array.prototype.forEach.call( triggers, function ( trigger ) {
			trigger.setAttribute( 'aria-haspopup', 'dialog' );
			trigger.setAttribute( 'aria-expanded', 'false' );
			trigger.addEventListener( 'click', function ( event ) {
				event.preventDefault();
				open();
			} );
		} );

		overlay.addEventListener( 'click', function ( event ) {
			if ( event.target.closest( '[data-tmk-search-close]' ) ) {
				close();
			}
		} );

		document.addEventListener( 'keydown', function ( event ) {
			if ( overlay.hidden ) {
				return;
			}
			if ( 'Escape' === event.key ) {
				close();
				return;
			}
			if ( 'Tab' !== event.key ) {
				return;
			}
			var items = focusable();
			if ( ! items.length ) {
				return;
			}
			var first = items[ 0 ];
			var last = items[ items.length - 1 ];
			if ( event.shiftKey && document.activeElement === first ) {
				event.preventDefault();
				last.focus();
			} else if ( ! event.shiftKey && document.activeElement === last ) {
				event.preventDefault();
				first.focus();
			}
		} );

		// An empty query would land on the site's "everything" results page, which is not an
		// answer to anything. Keep the dialog open and put the caret back instead.
		var form = overlay.querySelector( '.tmk-search-overlay__form' );
		if ( form && field ) {
			form.addEventListener( 'submit', function ( event ) {
				if ( ! field.value.trim() ) {
					event.preventDefault();
					field.focus();
				}
			} );
		}
	}

	/* 3 — DECOR PARALLAX ----------------------------------------------------
	 * The botanicals drift against the scroll at a fraction of its speed, which is what makes
	 * them read as a background plane rather than as content. Transform only (no layout, no
	 * paint), opposite directions per piece, and skipped outright under reduced motion — where
	 * the illustrations simply stay put, which is a complete experience, not a degraded one. */
	function initDecorParallax() {
		if ( reduceMotion ) {
			return;
		}
		var pieces = document.querySelectorAll( '.tmk-page-decor img' );
		if ( ! pieces.length ) {
			return;
		}
		onScroll( function ( y ) {
			Array.prototype.forEach.call( pieces, function ( img, i ) {
				var rate = i % 2 ? -0.06 : 0.05;
				img.style.transform = 'translate3d(0,' + ( y * rate ).toFixed( 1 ) + 'px,0)';
			} );
		} );
	}

	function init() {
		initCondensedHeader();
		initSearchOverlay();
		initDecorParallax();
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
