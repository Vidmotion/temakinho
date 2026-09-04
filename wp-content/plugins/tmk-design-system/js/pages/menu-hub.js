/* TMK Menu Hub — page behaviour (js/pages/, mirroring css/pages/).
 *
 * Why a js/pages bucket: enqueue.php's map is animations | interactions | navigation |
 * components, and this is none of those — it is one page's own behaviour, exactly what
 * css/pages/ already means for CSS. Calling it a "component" would be a lie about its scope.
 *
 * Everything here is enhancement. With JS off the page is complete: 19 anchored spreads, a
 * working quick-nav, every dish, every price, both languages on their own URLs. The category bar
 * is sticky in CSS alone, so even the second navigation level survives with JS off — it simply
 * stays boxed instead of bleeding to the viewport. This file adds four things on top:
 *   1. it replaces the CSS fallback shell measurements with the real, LIVE ones;
 *   2. it reveals and wires the text filter, which is why the field ships hidden;
 *   3. it marks which category you are currently reading, and keeps it in view;
 *   4. it gives the quick-nav a direction-aware landing offset.
 *
 * There is deliberately not one scroll listener in this file. Everything is observer-driven:
 * ResizeObserver for the measurements, IntersectionObserver for both the reading position and
 * the docked state, MutationObserver for the header's own hide/reveal. Scrolling the page costs
 * nothing until a boundary is actually crossed.
 */
(function () {
	'use strict';

	var hub = document.querySelector('[data-menu-hub]');
	if (!hub) { return; }

	var sections = Array.prototype.slice.call(hub.querySelectorAll('[data-menu-section]'));
	var dishes   = Array.prototype.slice.call(hub.querySelectorAll('[data-menu-dish]'));
	var groups   = Array.prototype.slice.call(hub.querySelectorAll('[data-menu-group]'));
	var pills    = Array.prototype.slice.call(hub.querySelectorAll('[data-menu-pill]'));
	var countEl  = hub.querySelector('[data-menu-count]');
	var emptyEl  = hub.querySelector('[data-menu-empty]');
	var form     = hub.querySelector('[data-menu-search]');
	var input    = form ? form.querySelector('input[type="search"]') : null;
	var strip    = hub.querySelector('.tmk-menu-index');

	var oneTpl  = hub.getAttribute('data-menu-count-one') || '%s';
	var manyTpl = hub.getAttribute('data-menu-count-many') || '%s';

	var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	var header = document.getElementById('tmk-header')
	          || document.querySelector('.elementor-location-header')
	          || document.querySelector('.tmk-header-root');

	/* ---- 1. live shell measurements -------------------------------------------------------
	 * Three numbers the stylesheet cannot derive on its own.
	 *
	 * chrome — the header's HEIGHT. It is not a constant: #tmk-header measures 90px at rest and
	 * 140px once .tmk-scrolled applies, because that rule pins the logo to a fixed width and the
	 * logo is a square SVG, so the "condensed" navbar is the taller one (a pre-existing kit-CSS
	 * defect, reported rather than patched from here). A resize listener structurally cannot see
	 * that change; a ResizeObserver on the element sees it and every other cause. Used for the
	 * things that must NOT move while the navbar tucks itself away — anchor landings and the
	 * ScrollSpy reading line.
	 *
	 * dock — the header's live BOTTOM EDGE, and the whole answer to where the category bar sits.
	 * Sampling the edge rather than deriving it from a height and a docked/undocked flag is the
	 * point: measured through one hide, the edge travelled 90 → 99 → 0, because the navbar grows
	 * and slides away at the same time. There is no state machine that reproduces that curve, and
	 * every attempt to approximate it is what let the bar drift behind the navbar.
	 *
	 * bleed — the distance from the viewport edge to the hub's content box, which the bar spends
	 * as a negative margin and earns back as padding. Read from the hub rather than from the bar
	 * so the measurement is independent of the value it produces.
	 */
	var chrome = 0;
	var bleed  = 0;
	var dock   = -1;

	/* The whole of it. No extrapolation, no clamp against an assumed height, no test for whether
	 * the header "looks" sticky — every one of those was this file second-guessing the header
	 * instead of reading it, and a guard that can return early is a guard that can freeze the bar
	 * at a stale position forever. The header's underside is whatever the header says it is; if it
	 * ever stops being pinned, its bottom edge goes negative on its own and the bar correctly
	 * docks at the top of the viewport. One statement, no state carried between frames. */
	function trackDock() {
		if (!header) { return; }
		var d = Math.max(0, Math.round(header.getBoundingClientRect().bottom * 100) / 100);
		if (d === dock) { return; }
		dock = d;
		hub.style.setProperty('--tmk-menu-dock', d + 'px');
	}

	function measure() {
		var c = header ? Math.round(header.getBoundingClientRect().height) : 0;
		var b = Math.max(0, Math.round(hub.getBoundingClientRect().left + parseFloat(window.getComputedStyle(hub).paddingLeft) || 0));
		if (c !== chrome) { chrome = c; hub.style.setProperty('--tmk-menu-chrome', c + 'px'); }
		if (b !== bleed)  { bleed  = b; hub.style.setProperty('--tmk-menu-bleed', b + 'px'); }
		trackDock();
	}

	/* WHEN to re-read it: every frame, unconditionally, for as long as the page is on screen.
	 *
	 * This is the actual fix, and it is a deletion rather than an addition. Every previous
	 * revision made the update CONDITIONAL — on a value having changed recently, on the element
	 * still computing to sticky, on the browser reporting a running animation, on a class or a
	 * transition event having fired. Each condition is this file holding an opinion about what
	 * the header is doing, i.e. a second state machine running alongside the header's own; and
	 * every one of them was wrong in some frame. Measured in order: a "has it stopped moving"
	 * heuristic froze the bar mid-transition and left it stranded at the previous position; a
	 * getAnimations() gate reading only playState missed the window where a just-created
	 * transition is still `pending`; even with `pending` included, 10 frames per run still slipped
	 * through the gap between a class landing and the transition existing.
	 *
	 * There is no version of that gate which is correct, because the question it asks — "can the
	 * header have moved?" — cannot be answered earlier than the moment the header has moved. So
	 * stop asking. Read the edge every frame and the answer is never needed: it is not possible
	 * for the header to move in a frame the bar did not also update, because there is no frame in
	 * which the bar does not update.
	 *
	 * The cost of that guarantee is one getBoundingClientRect() per frame, and a write only when
	 * the number actually differs. Animations are ticked before frame callbacks, so the read is
	 * this frame's true value, not the last one's. requestAnimationFrame is suspended by the browser
	 * whenever the tab is backgrounded, so an idle page costs nothing at all.
	 *
	 * It also means the MutationObserver, the transition listeners and the animation-state probe
	 * are all gone. Nothing now needs to be told that the header moved. */
	function pump() {
		trackDock();
		window.requestAnimationFrame(pump);
	}

	/* ---- 2. text filter ------------------------------------------------------------------- */
	function label(n) {
		return (n === 1 ? oneTpl : manyTpl).replace('%s', String(n));
	}

	function apply(term) {
		var q = term.trim().toLowerCase();
		var visible = 0;

		dishes.forEach(function (li) {
			var hit = !q || (li.getAttribute('data-menu-find') || '').indexOf(q) > -1;
			li.hidden = !hit;
			if (hit) { visible++; }
		});

		groups.forEach(function (g) {
			g.hidden = !g.querySelector('[data-menu-dish]:not([hidden])');
		});

		sections.forEach(function (sec) {
			var has = !!sec.querySelector('[data-menu-dish]:not([hidden])');
			// The Info & allergeni spread carries no dishes and must never be filtered away:
			// it is the legend the allergen numbers point at, and it is legally required.
			var isInfo = sec.classList.contains('tmk-menu-spread--info');
			sec.hidden = !q ? false : (isInfo ? false : !has);
			var c = sec.querySelector('[data-menu-section-count]');
			if (c && !isInfo) {
				c.textContent = label(sec.querySelectorAll('[data-menu-dish]:not([hidden])').length);
			}
		});

		if (countEl) { countEl.textContent = label(visible); }
		if (emptyEl) { emptyEl.hidden = !(q && visible === 0); }

		// Filtering moves every section on the page. Nothing above this line changed; the
		// quick-nav simply has to re-read where it is now, or it keeps pointing at a category
		// the filter has just taken off screen.
		spy();
	}

	if (form && input) {
		form.hidden = false;
		form.addEventListener('submit', function (e) { e.preventDefault(); });
		var t = null;
		input.addEventListener('input', function () {
			window.clearTimeout(t);
			t = window.setTimeout(function () { apply(input.value); }, 120);
		});
		// Escape clears, which is what every search field on the site already does.
		input.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && input.value) {
				e.stopPropagation();
				input.value = '';
				apply('');
			}
		});
	}

	/* ---- 3. which category am I reading ---------------------------------------------------
	 * The reading line is the y at which a section title comes to rest under the docked bar, so
	 * that clicking a category and reading it while scrolling agree on the same answer. Everything
	 * that has to know where the bar sits derives it from here.
	 */
	function railHeight() { return strip ? Math.round(strip.getBoundingClientRect().height) : 0; }
	// Read from the same custom property the stylesheet's own offsets are built on, not from the
	// token behind it, so the two can never be changed apart.
	function lead() { return parseFloat(window.getComputedStyle(hub).getPropertyValue('--tmk-menu-lead')) || 16; }
	function readingLine() { return chrome + railHeight() + lead() + 2; } // +2 absorbs sub-pixel layout

	var activeId = null;

	function setActive(id) {
		if (id === activeId) { return; }
		activeId = id;
		var target = null;
		pills.forEach(function (p) {
			if (p.getAttribute('data-menu-pill') === id) {
				// 'location' rather than 'true': this is the reader's position within a set of section links,
				// which is exactly what aria-current=location means. 'true' would only say "this one".
				p.setAttribute('aria-current', 'location');
				target = p;
			} else {
				p.removeAttribute('aria-current');
			}
		});
		if (target) { centre(target); }
	}

	/* Keep the active category in view inside its own strip. Deliberately NOT scrollIntoView():
	 * that would also scroll the PAGE vertically to reach the pill, fighting the very scroll that
	 * triggered it. scrollTo on the strip moves one axis of one element and nothing else. */
	function centre(pill) {
		if (!strip || strip.scrollWidth <= strip.clientWidth + 1) { return; }
		var left = pill.offsetLeft - (strip.clientWidth - pill.offsetWidth) / 2;
		strip.scrollTo({ left: left, behavior: reduce ? 'auto' : 'smooth' });
	}

	/* The single decision function: the last section whose top has passed the reading line. Hidden
	 * sections are skipped so the filter and the quick-nav never disagree. Called by the observer
	 * below on boundary crossings, and directly whenever the layout changed underneath us. */
	function spy() {
		var mark = readingLine();
		var active = null;
		sections.forEach(function (sec) {
			if (sec.hidden) { return; }
			if (sec.getBoundingClientRect().top <= mark) { active = sec; }
		});
		setActive(active ? active.getAttribute('id') : null);
	}

	/* IntersectionObserver as a pure SIGNAL, not as the decision. Its root is squeezed to a 1px
	 * band sitting exactly on the reading line, so it fires when — and only when — a section
	 * enters or leaves that line, which is precisely the set of moments spy()'s answer can change.
	 * Scrolling through a 700px section costs zero callbacks. Rebuilt whenever the geometry it
	 * encodes moves, because rootMargin is fixed at construction time. */
	var spyIO = null;
	function observeSections() {
		if (!('IntersectionObserver' in window)) { return; }
		if (spyIO) { spyIO.disconnect(); }
		var top = readingLine();
		var bottom = Math.max(0, window.innerHeight - top - 1);
		spyIO = new IntersectionObserver(spy, { rootMargin: (-top) + 'px 0px ' + (-bottom) + 'px 0px', threshold: 0 });
		sections.forEach(function (sec) { spyIO.observe(sec); });
	}

	function navHidden() { return !!header && header.classList.contains('tmk-nav-hidden'); }

	/* ---- 4. quick-nav landing ---------------------------------------------------------------
	 * The navbar hides on the way down and reveals on the way up, so a jump downwards lands under
	 * a 0px header and a jump upwards under a full one. A single CSS scroll-margin cannot be right
	 * for both: sized for the header it drops a downward target ~140px too low, sized without it
	 * a upward target parks behind the navbar. Reading the direction at click time costs nothing
	 * and is exact. The CSS value stays as the safe answer for no-JS and for cold #hash loads. */
	function clearance(sec) {
		// What the navbar will be doing when the jump LANDS, which is decided by the direction of
		// the jump: it hides on the way down and reveals on the way up. Reading navHidden() for an
		// upward jump asks about the wrong moment — you are almost always scrolled down when you
		// pick a category, so it answered "hidden" and the section landed behind a navbar that was
		// on its way back. Only when the jump is too small to change the header's mind does the
		// current state become the right answer.
		var delta = sec.getBoundingClientRect().top - (railHeight() + lead());
		if (delta > 24)  { return 0; }
		if (delta < -24) { return chrome; }
		return navHidden() ? 0 : chrome;
	}

	/* The document itself can move under a smooth scroll. #tmk-header changes height the instant
	 * .tmk-scrolled applies — 90px to 140px at 1440, see the note in section 1 — and because it is
	 * sticky it occupies flow, so every section below it shifts by the difference while the scroll
	 * is still travelling. A late image does the same thing. Predicting the landing is therefore
	 * necessary but not sufficient: re-measure once the scroll has settled and correct the
	 * remainder against what is true THEN. Skipped entirely if the reader took over in the
	 * meantime, and it cannot recur — the listener is one-shot and the correction is not settled. */
	function jumpTo(sec) {
		var want = clearance(sec) + railHeight() + lead();
		var top  = Math.max(0, window.pageYOffset + sec.getBoundingClientRect().top - want);
		window.scrollTo({ top: top, behavior: reduce ? 'auto' : 'smooth' });

		var done = function () {
			if (Math.abs(window.pageYOffset - top) > 200) { return; } // the reader scrolled away
			var now = (navHidden() ? 0 : chrome) + railHeight() + lead();
			if (Math.abs(sec.getBoundingClientRect().top - now) > 2) {
				window.scrollTo({ top: Math.max(0, window.pageYOffset + sec.getBoundingClientRect().top - now), behavior: reduce ? 'auto' : 'smooth' });
			}
			spy();
		};
		// A scroll that has nothing to travel emits no scrollend, so the two cases are separated
		// rather than papered over with one timeout long enough for both — which would fire in the
		// middle of a long smooth scroll and settle against a position still moving.
		if (Math.abs(window.pageYOffset - top) <= 1) { window.setTimeout(done, 300); }
		else if ('onscrollend' in window) { window.addEventListener('scrollend', done, { once: true }); }
		else { window.setTimeout(done, 1200); }
	}

	/* A category URL should behave the same however it is reached — clicked, pasted, or arrived at
	 * with the Back button. Left to the browser, a #hash load lands on the CSS fallback offset
	 * (correct, but deliberately conservative) and lights no pill at all, so the same section
	 * looks different depending on how you got there. */
	function syncHash() {
		var id = window.location.hash.slice(1);
		if (!id) { return; }
		var sec = document.getElementById(id);
		if (!sec || sections.indexOf(sec) === -1) { return; }
		jumpTo(sec);
		setActive(id);
	}
	window.addEventListener('hashchange', syncHash);

	if (strip) {
		strip.addEventListener('click', function (e) {
			if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) { return; }
			var pill = e.target.closest ? e.target.closest('[data-menu-pill]') : null;
			if (!pill) { return; }
			var id  = pill.getAttribute('data-menu-pill');
			var sec = document.getElementById(id);
			if (!sec) { return; }
			e.preventDefault();

			jumpTo(sec);
			if (window.history && window.history.pushState) { window.history.pushState(null, '', '#' + id); }
			setActive(id);
		});
	}

	/* ---- 5. wiring ------------------------------------------------------------------------- */
	measure();
	observeSections();
	spy();
	syncHash();

	if ('ResizeObserver' in window) {
		// Only the values that are NOT read every frame need announcing: the header's height and
		// the page gutter, plus the geometry the ScrollSpy's root margin bakes in at construction.
		// Three sources — the header condensing, the strip re-flowing when the webfont lands, and
		// the viewport resizing (including the mobile URL bar).
		var ro = new ResizeObserver(function () {
			measure();
			observeSections();
			spy();
		});
		if (header) { ro.observe(header); }
		if (strip)  { ro.observe(strip); }
		ro.observe(document.documentElement);
	}

	// The one loop. Nothing starts it and nothing stops it, which is the point.
	if (header) { window.requestAnimationFrame(pump); }
}());
