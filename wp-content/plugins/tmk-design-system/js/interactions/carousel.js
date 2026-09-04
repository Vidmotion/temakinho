/**
 * TMK Gallery Carousel - interactions layer, alongside Modal, Drawer and Accordion.
 *
 * `inc/gallery-render.php::tmk_render_gallery_carousel()` renders REAL Elementor Pro
 * Media_Carousel markup (genuine swiper.js structure: `.elementor-main-swiper.swiper` for the
 * main stage, a second `.elementor-main-swiper.elementor-thumbnails-swiper.swiper` for the
 * thumbnail strip, `.elementor-swiper-button-prev/-next` for arrows), wrapped in
 * `.tmk-gallery-carousel` - but Elementor's OWN frontend.js only auto-initialises swiper for
 * elements whose `data-widget_type` matches a widget name it has a registered handler for
 * (`media-carousel.default`). Since this markup is printed by our own `tmk-gallery` widget, that
 * hook never fires: confirmed directly - `swiper-initialized` class absent, main swiper collapses
 * to 0 height, arrow clicks do nothing.
 *
 * This initialises the real swiper.js library (already enqueued - the same `swiper` script
 * handle Elementor Pro's own widget declares, via TMK_Gallery_Widget::get_script_depends())
 * directly against our markup, using swiper's own documented `thumbs` module to sync the two
 * carousels - not a hand-rolled slide-swap. Works identically for any slide count: a single
 * image has no thumbnail swiper at all (Media_Carousel's own render() already omits it below 2
 * slides), and 3 vs 9 images differ only in how many slides swiper.js loops over.
 *
 * Thumbnail sizing: Elementor's OWN frontend handler (modules/carousel/assets/js/frontend/
 * handlers/media-carousel.js::getDefaultSettings()) sets the thumbs swiper's slidesPerView to
 * {mobile:3, mobile_extra:4, tablet:4, tablet_extra:5, laptop:5, desktop:5, widescreen:5} - a
 * value that only exists inside that handler, which never runs on our markup. Without it swiper
 * falls back to its own default (slidesPerView:1), rendering every thumbnail at full carousel
 * width instead of a small strip - confirmed visually. The breakpoints below transcribe that same
 * default (3 / 4 / 5, at this project's own documented tablet/desktop widths, CLAUDE.md §"kit
 * breakpoints" - mobile <=767, tablet 768-1024, desktop >=1025) rather than inventing a value.
 *
 * Keyboard (client review §4/§6) - tested empirically, not assumed from the arrows working:
 * the prev/next arrows respond to Enter/Space, but that turned out to be swiper's OWN Navigation
 * module binding keydown handling to ITS nav elements specifically - not a general browser
 * behaviour for `role="button"` divs. Confirmed by testing a DIFFERENT custom `role="button"` div
 * (the new lightbox trigger, added by inc/gallery-render.php): focus() succeeds, a JS .click()
 * opens the modal, but a real Enter keypress does nothing - so thumbnails and lightbox triggers
 * both need their own explicit keydown shim; the arrows do not.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		if ( typeof window.Swiper === 'undefined' ) return;

		var carousels = document.querySelectorAll( '.tmk-gallery-carousel' );
		if ( ! carousels.length ) return;

		carousels.forEach( function ( carousel ) {
			var swipers = carousel.querySelectorAll( '.swiper' );
			if ( ! swipers.length ) return;

			var thumbsEl = carousel.querySelector( '.elementor-thumbnails-swiper' );
			var mainEl = null;
			for ( var i = 0; i < swipers.length; i++ ) {
				if ( swipers[ i ] !== thumbsEl ) { mainEl = swipers[ i ]; break; }
			}
			if ( ! mainEl ) return;

			var thumbsSwiper = null;
			if ( thumbsEl ) {
				thumbsSwiper = new window.Swiper( thumbsEl, {
					slidesPerView: 3,
					spaceBetween: 8,
					watchSlidesProgress: true,
					freeMode: true,
					breakpoints: {
						768: { slidesPerView: 4 },
						1025: { slidesPerView: 5 },
					},
				} );
			}

			var prevEl = mainEl.querySelector( '.elementor-swiper-button-prev' );
			var nextEl = mainEl.querySelector( '.elementor-swiper-button-next' );

			var config = {
				slidesPerView: 1,
				loop: false,
				navigation: ( prevEl && nextEl ) ? { prevEl: prevEl, nextEl: nextEl } : undefined,
			};
			if ( thumbsSwiper ) {
				config.thumbs = { swiper: thumbsSwiper };
			}

			var mainSwiper = new window.Swiper( mainEl, config );

			function activateOnEnterOrSpace( el ) {
				el.addEventListener( 'keydown', function ( e ) {
					if ( 'Enter' === e.key || ' ' === e.key ) {
						e.preventDefault();
						el.click();
					}
				} );
			}

			// Thumbnails: not focusable at all by default (confirmed - no native tabindex).
			// activateOnEnterOrSpace()'s el.click() shim does NOT work here - confirmed by
			// testing a plain JS .click() call directly (no keyboard involved) and finding the
			// main swiper never moved: swiper's Thumbs module (swiper.js's onThumbClick) listens
			// for swiper's own internal 'tap' event, not a DOM 'click', so nothing dispatches it.
			// Calling the same swiper.slideTo() the module's own handler resolves to (with loop
			// disabled on both swipers here, its branching collapses to exactly this) reaches the
			// identical end state without needing to fake swiper's internal event.
			if ( thumbsEl ) {
				var thumbSlides = thumbsEl.querySelectorAll( '.swiper-slide' );
				thumbSlides.forEach( function ( thumb, i ) {
					thumb.setAttribute( 'tabindex', '0' );
					thumb.addEventListener( 'keydown', function ( e ) {
						if ( 'Enter' === e.key || ' ' === e.key ) {
							e.preventDefault();
							mainSwiper.slideTo( i );
						}
					} );
				} );
			}

			// Lightbox triggers (inc/gallery-render.php ships tabindex="0" + role="button" on
			// every one): same keydown shim, plus tabindex kept in sync with the ACTIVE slide
			// only - otherwise a keyboard user tabs through every off-screen slide's trigger too.
			var triggers = mainEl.querySelectorAll( '[data-tmk-modal-trigger]' );
			if ( triggers.length ) {
				triggers.forEach( activateOnEnterOrSpace );

				var syncTriggerTabindex = function () {
					triggers.forEach( function ( trigger, i ) {
						trigger.setAttribute( 'tabindex', i === mainSwiper.activeIndex ? '0' : '-1' );
					} );
				};
				syncTriggerTabindex();
				mainSwiper.on( 'slideChange', syncTriggerTabindex );
			}
		} );
	} );
}() );
