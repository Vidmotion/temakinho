/**
 * TMK Accordion — interactions layer, generic and reusable (Accordion.jsx: single-open by
 * default). Scoped per group via [data-tmk-accordion], so independent accordion instances on
 * the same page (mobile nav, a future FAQ block, job listings, etc.) never interfere with
 * each other's "close the others" behaviour.
 */
(function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '[data-tmk-accordion]' ).forEach( function ( group ) {
			var triggers = group.querySelectorAll( '[data-tmk-accordion-trigger]' );
			triggers.forEach( function ( trigger ) {
				trigger.addEventListener( 'click', function () {
					var panel = trigger.nextElementSibling;
					var isOpen = trigger.getAttribute( 'aria-expanded' ) === 'true';
					triggers.forEach( function ( other ) {
						other.setAttribute( 'aria-expanded', 'false' );
						var otherPanel = other.nextElementSibling;
						if ( otherPanel ) otherPanel.hidden = true;
					} );
					if ( ! isOpen ) {
						trigger.setAttribute( 'aria-expanded', 'true' );
						if ( panel ) panel.hidden = false;
					}
				} );
			} );
		} );
	} );
})();
