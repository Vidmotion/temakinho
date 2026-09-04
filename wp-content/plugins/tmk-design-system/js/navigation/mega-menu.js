/**
 * TMK mega menu — navigation layer, header-specific (MegaMenu.jsx only ever appears inside
 * Header). Click-toggle (never hover-only), Escape to close, click-outside to close.
 */
(function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var root = document.querySelector( '.tmk-header-root' );
		if ( ! root ) return;

		var trigger = root.querySelector( '[data-tmk-mega-trigger]' );
		var mega = root.querySelector( '[data-tmk-mega]' );
		if ( ! trigger || ! mega ) return;

		/* Focus-return on close. Closes one half of the disclosed accessibility gap in
		   PROJECT_CONTEXT Section 10 ("no focus trap or focus-return on close for Drawer /
		   Accordion / mega-menu overlays"); the Drawer half was closed during the Homepage audit.
		   Escape and click-outside can both fire while focus sits on a city link INSIDE the panel.
		   Hiding the panel then orphans focus on a hidden node, and the browser silently resets it
		   to <body> - the keyboard user loses their place and has to Tab in from the top of the
		   document again. Focus is moved back to the trigger only when it was genuinely inside the
		   panel, so a click-outside somewhere else on the page never yanks focus away from whatever
		   the user just clicked. */
		function close() {
			var focusWasInside = mega.contains( document.activeElement );
			mega.hidden = true;
			trigger.setAttribute( 'aria-expanded', 'false' );
			if ( focusWasInside ) trigger.focus();
		}
		function open() {
			mega.hidden = false;
			trigger.setAttribute( 'aria-expanded', 'true' );
		}

		trigger.addEventListener( 'click', function () {
			mega.hidden ? open() : close();
		} );
		document.addEventListener( 'click', function ( e ) {
			if ( ! mega.hidden && ! root.contains( e.target ) ) close();
		} );
		document.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Escape' && ! mega.hidden ) close();
		} );
	} );
})();
