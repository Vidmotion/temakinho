/**
 * TMK Newsletter Capture — vanilla JS translation of NewsletterCapture.jsx's client-side
 * validation/state machine (default/submitting/success/error), wired to a real AJAX
 * submission (inc/newsletter-render.php) instead of the prototype's setTimeout stand-in.
 */
(function () {
	'use strict';

	var EMAIL_RE = /.+@.+\..+/;

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '[data-tmk-newsletter]' ).forEach( function ( root ) {
			var form = root.querySelector( '[data-tmk-newsletter-form]' );
			var input = root.querySelector( '[data-tmk-newsletter-input]' );
			var citySelect = root.querySelector( '[data-tmk-newsletter-city]' );
			var submitBtn = root.querySelector( '[data-tmk-newsletter-submit]' );
			var successMsg = root.querySelector( '[data-tmk-newsletter-success]' );
			var errorMsg = root.querySelector( '[data-tmk-newsletter-error]' );
			if ( ! form || ! input ) return;

			function setError( on ) {
				input.classList.toggle( 'is-error', on );
				errorMsg.hidden = ! on;
				if ( on ) successMsg.hidden = true;
			}

			input.addEventListener( 'input', function () {
				if ( input.classList.contains( 'is-error' ) ) setError( false );
			} );

			form.addEventListener( 'submit', function ( e ) {
				e.preventDefault();
				if ( ! EMAIL_RE.test( input.value ) ) { setError( true ); return; }
				setError( false );
				successMsg.hidden = true;
				submitBtn.disabled = true;
				submitBtn.textContent = '…';

				var data = new FormData( form );
				if ( citySelect ) data.set( 'city', citySelect.value );

				/*
				 * form.getAttribute('action'), NOT form.action — e non e' pedanteria.
				 *
				 * HTMLFormElement e' definito con [LegacyOverrideBuiltIns]: un controllo del form il cui
				 * name coincide con una proprieta' dell'interfaccia LA OSCURA. Ogni form AJAX di questo
				 * progetto contiene <input type="hidden" name="action" value="tmk_..."> perche' e' cosi'
				 * che admin-ajax.php instrada la richiesta — quindi `form.action` non restituisce l'URL
				 * ma l'elemento <input>, che stringificato diventa "[object HTMLInputElement]".
				 * fetch() chiedeva quindi un URL relativo chiamato letteralmente cosi', prendeva 404,
				 * r.json() lanciava, e il ramo catch mostrava il messaggio di errore.
				 *
				 * Trovato dalla QA pre-lancio col browser reale: un POST diretto ad admin-ajax.php (curl)
				 * riusciva sempre, perche' non passa da qui. Nessun errore in console, nessuna eccezione:
				 * il fallimento era gia' gestito dal catch e si presentava come "Qualcosa non ha funzionato".
				 */
				fetch( form.getAttribute( 'action' ), { method: 'POST', body: data, credentials: 'same-origin' } )
					.then( function ( r ) { return r.json(); } )
					.then( function ( res ) {
						submitBtn.disabled = false;
						submitBtn.textContent = 'Iscriviti';
						if ( res && res.success ) {
							successMsg.hidden = false;
							form.reset();
						} else {
							setError( true );
						}
					} )
					.catch( function () {
						submitBtn.disabled = false;
						submitBtn.textContent = 'Iscriviti';
						setError( true );
					} );
			} );
		} );
	} );
})();
