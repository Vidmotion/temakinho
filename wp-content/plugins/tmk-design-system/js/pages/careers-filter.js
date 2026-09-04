/**
 * TMK Careers — Head Quarter / Ristoranti filter for the Open Roles grid.
 *
 * PROGRESSIVE ENHANCEMENT, deliberately. inc/careers-render.php renders EVERY published job
 * server-side and marks the filter bar `hidden`; this script is what un-hides it. So with
 * JavaScript off, or before this file runs, the page is complete: every role is visible and
 * reachable, and there is no dead control advertising a feature that cannot work. The same
 * reasoning the shell's search overlay already follows in this codebase.
 *
 * State lives on the buttons (aria-pressed) rather than in a variable, so assistive technology
 * reads the current filter without a live region, and the empty state is announced by the
 * role="status" paragraph the renderer already ships.
 *
 * Cards are hidden with the `hidden` attribute rather than a class: a hidden card must leave the
 * tab order and the accessibility tree, not merely disappear, or a keyboard user tabs through
 * roles that are not on screen.
 */
(function () {
	function init(root) {
		var bar = root.querySelector('[data-tmk-job-filter-bar]');
		if (!bar) { return; }
		var buttons = bar.querySelectorAll('[data-tmk-job-filter-value]');
		var cards = root.querySelectorAll('[data-tmk-job-area]');
		var empty = root.querySelector('[data-tmk-job-filter-empty]');
		if (!buttons.length || !cards.length) { return; }

		bar.hidden = false;

		function apply(value) {
			var shown = 0;
			for (var i = 0; i < cards.length; i++) {
				var match = !value || cards[i].getAttribute('data-tmk-job-area') === value;
				cards[i].hidden = !match;
				if (match) { shown++; }
			}
			for (var j = 0; j < buttons.length; j++) {
				var on = buttons[j].getAttribute('data-tmk-job-filter-value') === value;
				buttons[j].setAttribute('aria-pressed', on ? 'true' : 'false');
				buttons[j].classList.toggle('is-active', on);
			}
			if (empty) { empty.hidden = shown !== 0; }
		}

		for (var k = 0; k < buttons.length; k++) {
			buttons[k].addEventListener('click', function (e) {
				apply(e.currentTarget.getAttribute('data-tmk-job-filter-value') || '');
			});
		}
	}

	document.addEventListener('DOMContentLoaded', function () {
		var roots = document.querySelectorAll('[data-tmk-job-filter]');
		for (var i = 0; i < roots.length; i++) { init(roots[i]); }
	});
})();
