/**
 * TMK forms — generic AJAX submit for any [data-tmk-ajax-form] (Contact IntentForm, Job Application).
 * Mirrors js/components/newsletter.js: progressive enhancement over a real <form> that already posts
 * to admin-ajax.php, so it still works if this script fails. Native constraint validation first,
 * then fetch → JSON → reveal the success or error message. Reduced-motion / no-JS safe by design.
 */
(function () {
	function init(form) {
		var ok = form.querySelector('[data-tmk-form-success]');
		var err = form.querySelector('[data-tmk-form-error]');
		var btn = form.querySelector('[type="submit"]');
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			if (!form.checkValidity()) { form.reportValidity(); return; }
			if (err) err.hidden = true;
			var label = btn ? btn.textContent : '';
			if (btn) { btn.disabled = true; btn.textContent = '…'; }
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
			fetch(form.getAttribute('action'), { method: 'POST', body: new FormData(form), credentials: 'same-origin' })
				.then(function (r) { return r.json(); })
				.then(function (res) {
					if (res && res.success) {
						form.hidden = true;
						if (ok) { ok.hidden = false; ok.focus && ok.focus(); }
					} else {
						if (err) { err.hidden = false; if (res && res.data && res.data.message) err.textContent = res.data.message; }
						if (btn) { btn.disabled = false; btn.textContent = label; }
					}
				})
				.catch(function () {
					if (err) err.hidden = false;
					if (btn) { btn.disabled = false; btn.textContent = label; }
				});
		});
	}
	document.addEventListener('DOMContentLoaded', function () {
		var forms = document.querySelectorAll('[data-tmk-ajax-form]');
		for (var i = 0; i < forms.length; i++) init(forms[i]);
	});
})();
