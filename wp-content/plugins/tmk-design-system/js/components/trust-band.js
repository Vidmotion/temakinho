/**
 * TMK Trust Band — rotating-testimonial prev/next, vanilla translation of TrustBand.jsx's
 * useState index. Each instance is scoped to its own [data-tmk-trust-band] wrapper so multiple
 * bands on one page (or one per city, later) never interfere with each other.
 */
(function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '[data-tmk-trust-band]' ).forEach( function ( band ) {
			var items = Array.prototype.slice.call( band.querySelectorAll( '[data-tmk-trust-band-item]' ) );
			var countEl = band.querySelector( '[data-tmk-trust-band-count]' );
			var prevBtn = band.querySelector( '[data-tmk-trust-band-prev]' );
			var nextBtn = band.querySelector( '[data-tmk-trust-band-next]' );
			if ( items.length < 2 ) return;

			var i = 0;
			function render() {
				items.forEach( function ( el, idx ) { el.classList.toggle( 'is-active', idx === i ); } );
				if ( countEl ) countEl.textContent = ( i + 1 ) + ' / ' + items.length;
			}
			if ( prevBtn ) prevBtn.addEventListener( 'click', function () { i = ( i - 1 + items.length ) % items.length; render(); } );
			if ( nextBtn ) nextBtn.addEventListener( 'click', function () { i = ( i + 1 ) % items.length; render(); } );
		} );
	} );
})();
