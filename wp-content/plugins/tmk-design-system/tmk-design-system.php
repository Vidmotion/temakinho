<?php
/**
 * Plugin Name: TMK Design System
 * Description: The single implementation layer for the Temakinho site — design tokens, base styles, utilities, shared components, and layout regions, all verbatim from the approved Claude Design system, enqueued in one enforced layer order (tokens -> base -> utilities -> animations -> components -> layout -> pages; see inc/enqueue.php) and mirrored into Elementor's Global Colors/Fonts. Source of truth: TMK/10_DESIGN_SYSTEM_SPECIFICATION.md. Never hand-edit token values here — update via the Claude Design project and re-sync.
 * Version: 1.23.1
 * Author: TMK Technical Lead
 * Text Domain: tmk-design-system
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'TMK_DS_VERSION', '1.23.1' );
// Production booking + ordering destinations. Used as the governed fallback for every Prenota
// action that has no per-instance destination yet, so CTAs point at the real system instead of the
// /#prenota placeholder.
//
// 2026-09-07 - TMK_BOOKING_URL ripuntato da https://temakinho.it/prenota/ a
// https://temakinho.com/prenota/ su richiesta del cliente: il dominio canonico e' .com e la
// pagina /prenota/ (page #13752) e' li'. Prima di questa modifica ogni pagina ristorante
// pubblicava DUE link a temakinho.it (CTA dell'hero + fascia "Il tuo tavolo ti aspetta") mentre il
// pulsante PRENOTA ORA della testata puntava a .com: e' esattamente l'incoerenza segnalata.
// Verificato prima di cambiare: https://temakinho.com/prenota/ risponde 200.
// D-32 resta rispettato - qui cambia una DESTINAZIONE, non il sistema di prenotazione: nessuna
// rotta, nessun provider, nessun contenuto delle pagine /prenota-*\/ e' toccato.
if ( ! defined( 'TMK_BOOKING_URL' ) ) define( 'TMK_BOOKING_URL', 'https://temakinho.com/prenota/' );
// ATTENZIONE - NON allineare questa al .com senza prima creare la pagina: verificato il
// 2026-09-07, https://temakinho.com/ordina-online/ risponde 404, mentre
// https://temakinho.it/ordina-online/ risponde 200. Ripuntarla romperebbe il link "Delivery".
if ( ! defined( 'TMK_ORDER_URL' ) )   define( 'TMK_ORDER_URL', 'https://temakinho.it/ordina-online/' );
// Real external shop (temakinho.com Shopify storefront). The Shop page is an on-brand showcase; the
// actual purchase happens on the real store, so product actions route here rather than faking a cart.
if ( ! defined( 'TMK_SHOP_URL' ) )    define( 'TMK_SHOP_URL', 'https://www.temakinho.com/pages/shop' );
define( 'TMK_DS_PATH', plugin_dir_path( __FILE__ ) );
define( 'TMK_DS_URL', plugin_dir_url( __FILE__ ) );

require_once TMK_DS_PATH . 'inc/content-model.php';
/* 2026-09-04 — la superficie di redazione in wp-admin. Il modello dei contenuti registrava i
   campi ma nessuno li poteva compilare: ACF free non ha Repeater ne' Gallery, quindi `hours` e
   `gallery_ids` non avevano interfaccia, e gli Eventi non ne avevano affatto.
   Solo admin: nessun hook front-end qui dentro. Vedi inc/admin/fields.php. */
if ( is_admin() ) {
	require_once TMK_DS_PATH . 'inc/admin/fields.php';
}
require_once TMK_DS_PATH . 'inc/enqueue.php';
require_once TMK_DS_PATH . 'inc/elementor-kit.php';
// Elementor-first presentation layer (Session 9): the TMK design-system components as native
// Elementor widgets. Thin control-surfaces over the tmk_render_* engine; markup/CSS/JS unchanged.
require_once TMK_DS_PATH . 'inc/elementor-widgets.php';
require_once TMK_DS_PATH . 'inc/media.php';
require_once TMK_DS_PATH . 'inc/logo.php';
require_once TMK_DS_PATH . 'inc/newsletter-render.php';
require_once TMK_DS_PATH . 'inc/header-render.php';
require_once TMK_DS_PATH . 'inc/footer-render.php';
require_once TMK_DS_PATH . 'inc/hero-render.php';
require_once TMK_DS_PATH . 'inc/narrative-band-render.php';
require_once TMK_DS_PATH . 'inc/card-render.php';
require_once TMK_DS_PATH . 'inc/trust-band-render.php';
require_once TMK_DS_PATH . 'inc/cta-banner-render.php';
require_once TMK_DS_PATH . 'inc/booking-widget-render.php';
require_once TMK_DS_PATH . 'inc/accessibility-landmarks.php';
require_once TMK_DS_PATH . 'inc/menu-render.php';
require_once TMK_DS_PATH . 'inc/refinements.php';

// Location (Restaurant Detail) master template + its component renderers. map-block is required
// before practical-info because the band composes the map internally; location-render is last
// because it composes every component above.
require_once TMK_DS_PATH . 'inc/breadcrumb-render.php';
require_once TMK_DS_PATH . 'inc/gallery-render.php';
require_once TMK_DS_PATH . 'inc/map-block-render.php';
require_once TMK_DS_PATH . 'inc/practical-info-render.php';
require_once TMK_DS_PATH . 'inc/location-render.php';
require_once TMK_DS_PATH . 'inc/location-routing.php';

// City (Collection, Depth 1) master template + its routing. Requires the card/hero/narrative/
// cta-banner/booking/breadcrumb renderers above; renders any `city` term from its real taxonomy
// fields + the `location` posts filed under it, at /ristoranti/{city}/.
require_once TMK_DS_PATH . 'inc/city-render.php';
require_once TMK_DS_PATH . 'inc/city-routing.php';

// Shared System State partial (loading/empty/success/error) — consumed by Story Hub, Search, forms.
require_once TMK_DS_PATH . 'inc/system-state-render.php';

// Stories — the editorial magazine: Story Hub (/storie/) + Editorial Article (/storie/{slug}/).
// Native `post`s (the blog is the magazine); requires the card/hero/trust/breadcrumb renderers above.
require_once TMK_DS_PATH . 'inc/story-render.php';
// PATCH 1 (D-29): /storie/ + /storie/{slug}/ moved to Elementor. This routing's
// template_include would still win, so the Elementor pages would never appear.
// require_once TMK_DS_PATH . 'inc/story-routing.php';

// Events — Event Hub (/eventi/) + Event Detail (/eventi/{slug}/) over the real `event` CPT.
require_once TMK_DS_PATH . 'inc/event-render.php';
/* 2026-09-04 — i blocchi dinamici degli Eventi. Il hub e il dettaglio avevano i cinque eventi
   seminati SCRITTI A MANO nei template Elementor, quindi un evento creato in wp-admin non poteva
   comparire e uno cancellato non poteva sparire. Vedi inc/event-blocks.php. */
require_once TMK_DS_PATH . 'inc/event-blocks.php';
// PATCH 1 (D-29): /eventi/ + /eventi/{slug}/ moved to Elementor.
// require_once TMK_DS_PATH . 'inc/event-routing.php';

// Careers — Careers Hub (/lavora-con-noi/) + Job Detail (/lavora-con-noi/{slug}/) over the `job` CPT.
require_once TMK_DS_PATH . 'inc/careers-render.php';
// Candidature riservate: archivio CV fuori dalla webroot, ruolo "Consultazione CV", download protetto.
require_once TMK_DS_PATH . 'inc/cv-access.php';
// Convenzioni aziendali: validazione lato server del modulo Elementor (solo quel modulo), ID richiesta
// e mittente .it delle sue notifiche, limiti anti-abuso; copia automatica nel registro condiviso.
require_once TMK_DS_PATH . 'inc/conventions-form.php';
require_once TMK_DS_PATH . 'inc/conventions-registry.php';
// Plugin Brevo: nessuna identificazione degli account WordPress (login, pagine viste da collegati).
require_once TMK_DS_PATH . 'inc/brevo-login-tracking.php';
// PATCH 1 (D-29): /lavora-con-noi/ + /lavora-con-noi/{slug}/ moved to Elementor.
// require_once TMK_DS_PATH . 'inc/careers-routing.php';

// Timeline component + Heritage page (/heritage) + generic full-width Page routing (Heritage/Contact/Shop).
require_once TMK_DS_PATH . 'inc/timeline-render.php';
require_once TMK_DS_PATH . 'inc/heritage-render.php';
// PATCH 1 (D-29): Heritage (/la-nostra-storia/), Contact (/contattaci/) and Shop
// (/shop/) moved to Elementor. The renderers above stay loaded — only the routing
// that claimed those URLs via template_include is disabled.
// require_once TMK_DS_PATH . 'inc/pages-routing.php';

// Contact page (/contattaci) — IntentForm + real contact channels.
require_once TMK_DS_PATH . 'inc/contact-render.php';

// Shop page (/shop) — editorial-commerce showcase routing to the real external store.
require_once TMK_DS_PATH . 'inc/shop-render.php';

// Legal — on-site legal pages (allergens); Privacy/Cookie/Codice Etico/Whistleblowing are external.
require_once TMK_DS_PATH . 'inc/legal-render.php';
require_once TMK_DS_PATH . 'inc/legal-docs-render.php'; // PATCH 10: shortcode [tmk_legal_docs]

// System pages — 404 + Search results (replace the theme defaults with shell-wrapped templates).
require_once TMK_DS_PATH . 'inc/notfound-render.php';
require_once TMK_DS_PATH . 'inc/search-render.php';
// PATCH 1 (D-29): 404 + Search (/?s=…) moved to Elementor.
// require_once TMK_DS_PATH . 'inc/system-routing.php';

// Flush rewrite rules once whenever this file's content-model version changes, so newly
// registered CPT/taxonomy permalinks work without a manual visit to Settings > Permalinks.
add_action( 'init', function () {
	if ( get_option( 'tmk_ds_rewrite_flush_v' ) !== TMK_DS_VERSION ) {
		flush_rewrite_rules();
		update_option( 'tmk_ds_rewrite_flush_v', TMK_DS_VERSION );
	}
}, 20 );
