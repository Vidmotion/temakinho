<?php
/**
 * TMK content model — native registration (no ACF dependency).
 * Source of truth: 03_INFORMATION_ARCHITECTURE.md §24-27.
 * Engineering decision (Priority 3 — IA §36 says "ACF Pro or equivalent" and leaves the
 * mechanism open): implemented with core register_post_type/register_taxonomy/register_post_meta
 * instead of adding a new third-party plugin dependency. Fields are exposed via show_in_rest so
 * Elementor Pro's Dynamic Tags / Custom Fields controls and the block editor can both read them.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'init', 'tmk_register_post_types' );
function tmk_register_post_types() {

	/*
	 * `editor` RIMOSSO il 2026-09-04, e non e' una semplificazione: era una trappola.
	 *
	 * Nessuna delle 14 sedi ha `post_content` (verificato: 0/14) e il template che le rende
	 * davvero — Theme Builder #255 — non contiene alcun widget theme-post-content (verificato).
	 * Quindi l'editor di contenuto su una scheda ristorante era un campo grande e invitante che
	 * non compare da nessuna parte: un cliente che ci avesse scritto il racconto della sala lo
	 * avrebbe visto sparire. Quel testo ha il proprio campo, "L'atmosfera" (`short_description`),
	 * che il template rende davvero.
	 *
	 * Effetto collaterale voluto: senza `editor` WordPress usa l'editor classico per questo tipo
	 * di contenuto, quindi i pannelli dei campi stanno sotto al titolo invece che in fondo a una
	 * schermata di scrittura a tutto schermo. Per un record — e una sede e' un record, non un
	 * articolo — e' il posto giusto.
	 *
	 * `excerpt` rimosso per la stessa ragione e con la stessa verifica: 0/14 sedi ne hanno uno,
	 * il template #255 non contiene alcun widget post-excerpt e nessun renderer del plugin chiama
	 * get_the_excerpt() su una `location`. Era il secondo campo che non andava in nessun posto.
	 * Il ruolo "una riga sotto il nome" ce l'ha `tagline`, che il front end rende davvero.
	 */
	register_post_type( 'location', array(
		'labels' => array( 'name' => 'Locations', 'singular_name' => 'Location', 'add_new_item' => 'Add New Location' ),
		'public' => true,
		'show_in_rest' => true,
		'menu_icon' => 'dashicons-store',
		'supports' => array( 'title', 'thumbnail' ),
		'has_archive' => 'ristoranti',
		'rewrite' => array( 'slug' => 'ristoranti', 'with_front' => false ),
		'taxonomies' => array( 'city', 'concept' ),
	) );

	register_post_type( 'menu_item', array(
		'labels' => array( 'name' => 'Menu Items', 'singular_name' => 'Menu Item', 'add_new_item' => 'Add New Menu Item' ),
		'public' => true,
		'publicly_queryable' => false, // IA §21 — no individual URLs, embedded/hub display only
		'show_in_rest' => true,
		'menu_icon' => 'dashicons-food',
		'supports' => array( 'title', 'thumbnail', 'custom-fields' ),
		'has_archive' => false,
		'rewrite' => false,
		'taxonomies' => array( 'menu_category', 'dietary' ),
	) );

	/*
	 * `thumbnail` and `excerpt` added 2026-09-03 (client review, task 4: "l'amministratore deve
	 * poter creare una nuova opportunita' indicando ... immagine, descrizione").
	 *
	 * They were missing, not merely unused: _BUILD/104 set featured images on the four seeded jobs
	 * PROGRAMMATICALLY (set_post_thumbnail works whether or not the CPT declares support) and both
	 * the hub cards and the Job Detail template READ get_post_thumbnail_id(), so the images were
	 * already load-bearing on the front end while the editor screen offered no way to set one.
	 * Same for the excerpt: the Job Detail hero subheadline is get_the_excerpt(). Declaring the
	 * supports adds the two metaboxes and changes nothing about the stored data.
	 *
	 * `job_area` is the client-facing category axis ("Head Quarter" / "Ristoranti"). It is a NEW
	 * taxonomy rather than a reuse of `department`, deliberately: `department` already holds real
	 * data on a different axis (Bar / Cucina / Sala, assigned to all four published jobs and
	 * rendered in the Job Detail kicker). Repurposing it would have destroyed live content to
	 * avoid a second dropdown. They are two questions - WHERE in the company, and WHICH craft -
	 * and the brief asks for the first one by name.
	 */
	register_post_type( 'job', array(
		'labels' => array( 'name' => 'Jobs', 'singular_name' => 'Job', 'add_new_item' => 'Add New Job' ),
		'public' => true,
		'show_in_rest' => true,
		'menu_icon' => 'dashicons-businessperson',
		'supports' => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
		'has_archive' => 'lavora-con-noi',
		'rewrite' => array( 'slug' => 'lavora-con-noi', 'with_front' => false ),
		'taxonomies' => array( 'department', 'job_area' ),
	) );

	/*
	 * `excerpt` added 2026-09-04 (client review, task 6). It was MISSING, not unused: Theme Builder
	 * template #562 renders `theme-post-excerpt` as the event standfirst and the hub cards call
	 * get_the_excerpt(), so the value was already load-bearing on the front end while the editor
	 * screen offered no field to write it — the five seeded events only have one because a build
	 * script wrote it. Declaring the support adds the metabox and changes no stored data.
	 */
	register_post_type( 'event', array(
		'labels' => array( 'name' => 'Events', 'singular_name' => 'Event', 'add_new_item' => 'Add New Event' ),
		'public' => true,
		'show_in_rest' => true,
		'menu_icon' => 'dashicons-calendar-alt',
		'supports' => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
		'has_archive' => 'eventi',
		'rewrite' => array( 'slug' => 'eventi', 'with_front' => false ),
	) );

	register_post_type( 'testimonial', array(
		'labels' => array( 'name' => 'Testimonials', 'singular_name' => 'Testimonial' ),
		'public' => false,
		'publicly_queryable' => false, // IA §25 — embedded only, no standalone URL
		'show_ui' => true,
		'show_in_rest' => true,
		'menu_icon' => 'dashicons-format-quote',
		'supports' => array( 'title' ),
		'has_archive' => false,
		'rewrite' => false,
	) );

	// Newsletter Capture submissions (components/conversion/NewsletterCapture.jsx). Storage-only —
	// title is the email address. Not public/queryable; this is a backend record, not content.
	// Placeholder for the CRM integration IA §39 leaves open (e.g. Brevo); swap the storage call
	// in inc/newsletter-render.php's AJAX handler for a real ESP API call when that's provisioned.
	register_post_type( 'newsletter_sub', array(
		'labels' => array( 'name' => 'Newsletter Subscribers', 'singular_name' => 'Subscriber' ),
		'public' => false,
		'publicly_queryable' => false,
		'show_ui' => true,
		'show_in_rest' => false,
		'menu_icon' => 'dashicons-email-alt',
		'supports' => array( 'title' ),
		'has_archive' => false,
		'rewrite' => false,
		'capabilities' => array( 'create_posts' => 'do_not_allow' ),
		'map_meta_cap' => true,
	) );

	/*
	 * Form submissions — backend records, never public content (same pattern as newsletter_sub).
	 * `contact_message` stores Contact IntentForm submissions; `job_application` stores Job Detail
	 * applications. Both are storage placeholders for the CRM/ATS integrations the real site runs
	 * externally (temakinho.com routes applications to /pages/jobs); swap the storage call in the
	 * AJAX handlers (inc/contact-render.php / inc/careers-render.php) for the real API when wired.
	 */
	foreach ( array(
		'contact_message' => array( 'name' => 'Contact Messages', 'singular' => 'Message', 'icon' => 'dashicons-email' ),
		'job_application' => array( 'name' => 'Job Applications', 'singular' => 'Application', 'icon' => 'dashicons-id' ),
	) as $slug => $l ) {
		register_post_type( $slug, array(
			'labels' => array( 'name' => $l['name'], 'singular_name' => $l['singular'] ),
			'public' => false,
			'publicly_queryable' => false,
			'show_ui' => true,
			'show_in_rest' => false,
			'menu_icon' => $l['icon'],
			'supports' => array( 'title', 'editor' ),
			'has_archive' => false,
			'rewrite' => false,
			'capabilities' => array( 'create_posts' => 'do_not_allow' ),
			'map_meta_cap' => true,
		) );
	}
}

/**
 * Editor classico per Eventi e Posizioni.
 *
 * Entrambi usano davvero `post_content` (i template #562 e #563 rendono theme-post-content), quindi
 * l'editor resta. Ma con l'editor a blocchi i pannelli dei campi finiscono TUTTI in fondo, sotto
 * una tela di scrittura a tutto schermo: su un evento — dove le informazioni che contano sono
 * data, sede, prezzo e disponibilita' — vuol dire scorrere oltre il contenuto per arrivare al
 * contenuto. Con l'editor classico i campi stanno sotto al titolo e le tassonomie nella colonna
 * di destra, che e' la forma di una scheda.
 *
 * C'e' anche una ragione di robustezza: l'editor a blocchi salva via REST e poi invia i metabox
 * con una SECONDA richiesta separata. Funziona, ma e' il punto in cui nascono le segnalazioni
 * "ho salvato e non e' rimasto". Una sola richiesta, un solo salvataggio.
 *
 * Reversibile in una riga: togliere questo filtro riporta l'editor a blocchi.
 */
add_filter( 'use_block_editor_for_post_type', 'tmk_classic_editor_for_records', 10, 2 );
function tmk_classic_editor_for_records( $use, $post_type ) {
	return in_array( $post_type, array( 'event', 'job' ), true ) ? false : $use;
}

add_action( 'init', 'tmk_register_taxonomies' );
function tmk_register_taxonomies() {

	register_taxonomy( 'city', array( 'location' ), array(
		'labels' => array( 'name' => 'Cities', 'singular_name' => 'City' ),
		'public' => true,
		'show_in_rest' => true,
		'hierarchical' => false,
		'rewrite' => array( 'slug' => 'ristoranti', 'with_front' => false ),
	) );

	register_taxonomy( 'concept', array( 'location' ), array(
		'labels' => array( 'name' => 'Concepts', 'singular_name' => 'Concept' ),
		'public' => true,
		'show_in_rest' => true,
		'hierarchical' => false,
	) );

	register_taxonomy( 'menu_category', array( 'menu_item' ), array(
		'labels' => array( 'name' => 'Menu Categories', 'singular_name' => 'Menu Category' ),
		'public' => true,
		'show_in_rest' => true,
		'hierarchical' => true,
	) );

	register_taxonomy( 'dietary', array( 'menu_item' ), array(
		'labels' => array( 'name' => 'Dietary Tags', 'singular_name' => 'Dietary Tag' ),
		'public' => true,
		'show_in_rest' => true,
		'hierarchical' => false,
	) );

	register_taxonomy( 'department', array( 'job' ), array(
		'labels' => array( 'name' => 'Reparti', 'singular_name' => 'Reparto' ),
		'public' => true,
		'show_in_rest' => true,
		'hierarchical' => false,
		'show_admin_column' => true,
	) );

	/*
	 * Job area — the client-managed category, with EXACTLY the two terms the brief names.
	 *
	 * hierarchical => true is not about nesting: it is what makes WordPress render the metabox as
	 * a CHECKBOX list of existing terms instead of a free-text tag field. A free-text field would
	 * let an editor type "Ristorante", "ristoranti " or "HQ" and quietly create a third, fourth
	 * and fifth category — exactly what "le categorie devono essere ESATTAMENTE" forbids. The two
	 * terms are seeded by tmk_seed_job_areas() below and the "add new term" control is removed
	 * from the metabox for anyone who is not an administrator (tmk_lock_job_area_terms()).
	 */
	register_taxonomy( 'job_area', array( 'job' ), array(
		'labels' => array(
			'name'          => 'Categorie',
			'singular_name' => 'Categoria',
			'menu_name'     => 'Categorie',
			'all_items'     => 'Tutte le categorie',
		),
		'public'            => true,
		'show_in_rest'      => true,
		'hierarchical'      => true,
		'show_admin_column' => true,
		'rewrite'           => array( 'slug' => 'area-lavoro' ),
	) );
	// `category` (native, applies to post) is extended, not replaced — IA §25.
}

add_action( 'init', 'tmk_register_meta_fields' );
function tmk_register_meta_fields() {
	$text = array( 'show_in_rest' => true, 'single' => true, 'type' => 'string' );
	$bool = array( 'show_in_rest' => true, 'single' => true, 'type' => 'boolean' );

	// Location — IA §27
	foreach ( array( 'tagline', 'short_description', 'address', 'phone', 'email', 'map_embed', 'booking_url', 'accessibility_notes', 'tmk_location_neighbourhood_note' ) as $f ) {
		register_post_meta( 'location', $f, $text );
	}
	register_post_meta( 'location', 'delivery_available', $bool );
	register_post_meta( 'location', 'tmk_location_has_live_music', $bool );
	register_post_meta( 'location', 'tmk_location_logotype_id', array( 'show_in_rest' => true, 'single' => true, 'type' => 'integer' ) );
	register_post_meta( 'location', 'hours', array( 'show_in_rest' => true, 'single' => true, 'type' => 'array', 'schema' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ) ) );
	register_post_meta( 'location', 'gallery_ids', array( 'show_in_rest' => true, 'single' => true, 'type' => 'array', 'schema' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ) ) );
	/*
	 * 2026-09-04. `map_url`, `map_coords` and `phone_display` were WRITTEN by the import scripts and
	 * READ by inc/elementor-widgets.php and inc/map-block-render.php from the day they were added,
	 * but never registered — so they were invisible to the REST API, to Elementor's Dynamic Tags and
	 * to any editing UI. Registering them is additive and changes no value.
	 */
	register_post_meta( 'location', 'map_url', $text );
	register_post_meta( 'location', 'map_coords', $text );
	register_post_meta( 'location', 'phone_display', $text );
	/*
	 * Per-restaurant booking DESTINATION, as a page reference rather than a URL string.
	 *
	 * Authorised on 2026-09-04 as the one editorial booking integration ("nel backend di ogni
	 * location, rendi selezionabile la relativa pagina WordPress di prenotazione"). It is a page ID
	 * resolved through get_permalink() at render time, so re-slugging a /prenota-*\/ page can never
	 * break the link — which a stored URL (the existing `booking_url`) would. `booking_url` is kept,
	 * backed up (_BUILD/backup-200-*\/200-booking-url-backup.json) and still honoured as the second
	 * fallback, so nothing that was configured before changes behaviour.
	 *
	 * D-32 perimeter: this stores and resolves a DESTINATION only. No routing, no provider, no
	 * iframe, no TheFork logic and no /prenota-*\/ page content is touched anywhere in this plugin.
	 */
	register_post_meta( 'location', 'tmk_location_booking_page_id', array( 'show_in_rest' => true, 'single' => true, 'type' => 'integer' ) );

	// Menu Item — IA §27
	register_post_meta( 'menu_item', 'price', $text );
	register_post_meta( 'menu_item', 'short_description', $text );
	register_post_meta( 'menu_item', 'channel', $text ); // dine-in | delivery | both

	/*
	 * Menu Item — bilingual + published-source fields (Session 6, Menu Hub).
	 *
	 * The live production menu (temakinho.it/menu/) publishes every dish twice, in Italian and in
	 * English, from one list. The site has no multilingual plugin and adding one (WPML/Polylang)
	 * would duplicate 168 posts, duplicate the taxonomy, and hand the client two content trees to
	 * keep in sync for a page whose two versions differ only by two strings. So the second language
	 * lives on the SAME post as two extra meta fields. One dish, one price, one allergen set, one
	 * editorial position — exactly the shape of the source. See PROJECT_CONTEXT 19.
	 *
	 * `allergens` is the comma-separated list of EU allergen numbers exactly as published in the
	 * dish description, e.g. '1,3,6,10'. It is stored as data instead of being left inside the
	 * description string so the numbers can be expanded into real allergen names for assistive
	 * technology and for the printed legend, without an editor having to retype them.
	 *
	 * `labels` is the comma-separated list of published dish markers, e.g. 'star' (the live menu's
	 * highlight star) or 'alcohol-free'. On the source page both are typographic hacks — a ⭐ glyph
	 * inside the dish name and the words ALCOHOL FREE written in Mathematical-Italic codepoints.
	 * Neither survives a screen reader, so the marker is lifted out of the name into data.
	 *
	 * `allergens_en` exists because the published EN cards do not always carry the same allergen
	 * codes as their IT counterparts. Silently reusing the IT list would publish an allergen
	 * declaration that contradicts the source, so the divergence is modelled instead of averaged.
	 *
	 * `sort_order` preserves the published order inside a category. A menu is a composed sequence,
	 * not an alphabet, so it cannot be recovered from the title.
	 */
	register_post_meta( 'menu_item', 'title_en', $text );
	register_post_meta( 'menu_item', 'short_description_en', $text );
	register_post_meta( 'menu_item', 'allergens', $text );
	register_post_meta( 'menu_item', 'allergens_en', $text );
	register_post_meta( 'menu_item', 'labels', $text );
	register_post_meta( 'menu_item', 'sort_order', array( 'show_in_rest' => true, 'single' => true, 'type' => 'integer' ) );

	// Job — IA §27
	register_post_meta( 'job', 'requirements', $text );
	register_post_meta( 'job', 'application_method', $text ); // native | external
	register_post_meta( 'job', 'application_url', $text );
	register_post_meta( 'job', 'location_id', array( 'show_in_rest' => true, 'single' => true, 'type' => 'integer' ) );
	// Careers presentation fields (CareersHub.jsx / JobDetail.jsx): the human labels the cards + hero
	// show. `job_where` is the display location ("Milano · Aqua"); `employment_type` the contract
	// ("Full-time", "Stagionale"). The role narrative (Cosa farai / Chi cerchiamo / Cosa offriamo)
	// lives in post_content as headed lists, composed in the normal editor.
	register_post_meta( 'job', 'job_where', $text );
	register_post_meta( 'job', 'employment_type', $text );
	/*
	 * 2026-09-04 (client review, task 7). The role narrative used to live only inside post_content
	 * as hand-typed <h3> + <ul> blocks — which is why only ONE of the four published jobs has it,
	 * and that one is verbatim placeholder copy from careers-data.js. As three separate line-per-item
	 * fields the client fills them in without knowing any markup, and each section disappears from
	 * the page when its field is empty. `requirements` already worked this way.
	 */
	register_post_meta( 'job', 'tmk_job_responsibilities', $text );
	register_post_meta( 'job', 'tmk_job_offer', $text );
	register_post_meta( 'job', 'tmk_job_deadline', $text ); // 'Y-m-d'

	// Event — IA §27
	register_post_meta( 'event', 'inquiry_target', $text );
	register_post_meta( 'event', 'location_ids', array( 'show_in_rest' => true, 'single' => true, 'type' => 'array', 'schema' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ) ) );
	/*
	 * Event Hub + Event Detail presentation fields (EventHub.jsx / EventDetail.jsx). An event is
	 * experiential, not a calendar row, but it still has a WHEN, a KIND and a VENUE, and its Terminal
	 * page carries the same practical facts a Location does (address/phone/accessibility) plus a
	 * degustazione formula and its own photo set. `event_start` is a 'Y-m-d H:i:s' string ordered on
	 * for the hub's soonest-first sort. Every field degrades to nothing when empty.
	 */
	register_post_meta( 'event', 'event_kind', $text );          // "Serata speciale", "Degustazione"…
	register_post_meta( 'event', 'event_venue', $text );         // "Milano · Aqua"
	register_post_meta( 'event', 'event_start', $text );         // 'Y-m-d H:i:s'
	register_post_meta( 'event', 'event_formula', $text );       // "Menu degustazione 6 portate"
	register_post_meta( 'event', 'event_address', $text );
	register_post_meta( 'event', 'event_phone', $text );
	register_post_meta( 'event', 'event_accessibility', $text );
	register_post_meta( 'event', 'booking_url', $text );
	register_post_meta( 'event', 'event_gallery_ids', array( 'show_in_rest' => true, 'single' => true, 'type' => 'array', 'schema' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ) ) );
	/*
	 * 2026-09-04 (client review, task 6) — the event fields the brief names, none of which existed.
	 *
	 * `tmk_event_location_id` is the important one: with a Temakinho venue selected, the event's
	 * displayed place, address and map are DERIVED from that restaurant's own verified data
	 * (tmk_event_place()), so an event can never publish an address that contradicts the
	 * restaurant page. `event_venue` / `event_address` stay as the manual fallback for an event
	 * that genuinely is not in one of our rooms.
	 *
	 * `tmk_event_booking_page_id` mirrors the location field above: a page reference, resolved at
	 * render time. Same D-32 perimeter — a destination, never the booking mechanism.
	 */
	register_post_meta( 'event', 'tmk_event_end', $text );          // 'Y-m-d H:i:s'
	register_post_meta( 'event', 'tmk_event_price', $text );        // "€ 65 a persona" | "Su richiesta"
	register_post_meta( 'event', 'tmk_event_capacity', $text );     // "Posti limitati"
	register_post_meta( 'event', 'tmk_event_notes', $text );
	register_post_meta( 'event', 'tmk_event_cta_label', $text );
	register_post_meta( 'event', 'tmk_event_contact', $text );
	register_post_meta( 'event', 'tmk_event_location_id', array( 'show_in_rest' => true, 'single' => true, 'type' => 'integer' ) );
	register_post_meta( 'event', 'tmk_event_booking_page_id', array( 'show_in_rest' => true, 'single' => true, 'type' => 'integer' ) );

	// Testimonial — IA §27
	register_post_meta( 'testimonial', 'quote', $text );
	register_post_meta( 'testimonial', 'author', $text );
	register_post_meta( 'testimonial', 'rating', array( 'show_in_rest' => true, 'single' => true, 'type' => 'integer' ) );
	register_post_meta( 'testimonial', 'location_id', array( 'show_in_rest' => true, 'single' => true, 'type' => 'integer' ) );
	// Targeting (client review "wire the testimonial targeting system"), checked by
	// inc/trust-band-render.php. tmk_* convention per this project's own naming rule; the four
	// keys above predate that rule and are left as-is - renaming a live meta key would silently
	// break every already-authored value.
	register_post_meta( 'testimonial', 'tmk_testimonial_homepage', array( 'show_in_rest' => true, 'single' => true, 'type' => 'boolean', 'default' => false ) );
	register_post_meta( 'testimonial', 'tmk_testimonial_scope', array( 'show_in_rest' => true, 'single' => true, 'type' => 'string', 'default' => '' ) );

	// Post extension — IA §10, §27 (Related Location)
	register_post_meta( 'post', 'related_location_id', array( 'show_in_rest' => true, 'single' => true, 'type' => 'integer' ) );
	/*
	 * Editorial article "Related" bridge — the storytelling→conversion beat (EditorialArticle.jsx /
	 * StoryHub.jsx render a CITY card, not a location, in "Dove vivere questa storia"). Stored as the
	 * `city` term slug so an article can point at the collection page a story belongs to; empty ->
	 * the article simply omits the bridge (graceful degrade). Kept distinct from related_location_id,
	 * which points at a single restaurant.
	 */
	register_post_meta( 'post', 'related_city', $text );
	/*
	 * Editorial byline — the article's author line ("di Beatriz Costa"). An editorial piece names its
	 * writer, which is not always the WordPress `post_author` user (guest contributors, agency
	 * pieces), so it is a first-class string on the post; the renderer falls back to the author's
	 * display name when this is empty.
	 */
	register_post_meta( 'post', 'byline', $text );

	// City / Concept term fields — IA §27
	register_term_meta( 'city', 'intro_copy', $text );
	register_term_meta( 'city', 'hero_image_id', array( 'show_in_rest' => true, 'single' => true, 'type' => 'integer' ) );
	/*
	 * City (Collection) page fields — the master City template (inc/city-render.php) needs a hero
	 * subheadline, a hero eyebrow and an orientation-beat title that IA §27 does not separately
	 * model (it lists only intro_copy + hero_image_id). Rather than overload intro_copy or invent
	 * per-city meaning in code, these three optional strings carry the design's Collection content
	 * (pages/milano-data.js) on the term itself; every one degrades gracefully when empty, so a
	 * city with none simply renders a leaner page and the client can fill them in wp-admin. `region`
	 * is the hero eyebrow (e.g. "Milano · Lombardia"); `tagline` the hero subheadline; and
	 * `narrative_title` the orientation beat's title above intro_copy. Mirrors the Location CPT's
	 * own tagline/short_description split.
	 */
	register_term_meta( 'city', 'region', $text );
	register_term_meta( 'city', 'tagline', $text );
	register_term_meta( 'city', 'narrative_title', $text );
	register_term_meta( 'concept', 'narrative_copy', $text );
	register_term_meta( 'concept', 'accent_token', $text ); // e.g. 'aqua' | 'arya' | 'tierra' | 'fuego' | '' (Standard)

	// Menu Category term fields — Session 6. `name_en` mirrors the second language of the source
	// menu (see the menu_item note above); `sort_order` preserves the published category sequence,
	// which is editorial (Starters -> Crudo -> ... -> Info) and not alphabetical.
	register_term_meta( 'menu_category', 'name_en', $text );
	register_term_meta( 'menu_category', 'sort_order', array( 'show_in_rest' => true, 'single' => true, 'type' => 'integer' ) );
}

/**
 * The two — and only two — job categories.
 *
 * Seeded on init rather than on plugin activation: activation hooks do not fire for a plugin that
 * is already active, so an activation-only seed would never run on this install. term_exists()
 * makes it a no-op on every request after the first, and re-creating a term an editor deleted is
 * the correct behaviour here: the brief pins the vocabulary, so the vocabulary is code, not data.
 * Slugs are stable and ASCII so a URL or a CSS hook built on them never has to change.
 */
function tmk_seed_job_areas() {
	foreach ( array( 'head-quarter' => 'Head Quarter', 'ristoranti' => 'Ristoranti' ) as $slug => $name ) {
		if ( ! term_exists( $slug, 'job_area' ) ) {
			wp_insert_term( $name, 'job_area', array( 'slug' => $slug ) );
		}
	}
}
add_action( 'init', 'tmk_seed_job_areas', 20 );

/**
 * Job Applications are records, not content: their fields are registered so they are visible in
 * the admin list, exportable, and readable by whatever ATS this is eventually wired to, instead of
 * being flattened into a post_content blob the way the previous handler wrote them.
 *
 * `tmk_application_job_id` is the association the brief asks for ("associazione della candidatura
 * alla specifica opportunita'") — a real post ID, so a renamed or re-slugged job keeps its
 * applications, which a stored job TITLE (what the old handler kept) would not.
 */
add_action( 'init', 'tmk_register_application_meta' );
function tmk_register_application_meta() {
	$text = array( 'show_in_rest' => false, 'single' => true, 'type' => 'string' );
	foreach ( array(
		'tmk_application_first_name',
		'tmk_application_last_name',
		'tmk_application_email',
		'tmk_application_phone',
		'tmk_application_cover_letter',
		'tmk_application_notes',
		// Consenso privacy: quando, e con quale testo. Un consenso senza la propria formulazione
		// non e' dimostrabile, quindi si registrano entrambi.
		'tmk_application_privacy_at',
		'tmk_application_privacy_text',
	) as $f ) {
		register_post_meta( 'job_application', $f, $text );
	}
	register_post_meta( 'job_application', 'tmk_application_job_id', array( 'show_in_rest' => false, 'single' => true, 'type' => 'integer' ) );
	register_post_meta( 'job_application', 'tmk_application_cv_id', array( 'show_in_rest' => false, 'single' => true, 'type' => 'integer' ) );
}
