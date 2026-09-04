<?php
/**
 * TMK Elementor widget classes. Each is a thin control-surface whose render() delegates to the
 * existing tmk_render_*() renderer, so the markup/CSS/JS are the approved design verbatim.
 * Registered by inc/elementor-widgets.php. See that file's header for the architecture.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! class_exists( '\Elementor\Widget_Base' ) ) return;

use Elementor\Controls_Manager as CM;

/** The registry consumed by inc/elementor-widgets.php. */
function tmk_elementor_widget_classes() {
	return array(
		'TMK_Hero_Widget', 'TMK_Narrative_Widget', 'TMK_City_Bands_Widget', 'TMK_Trust_Widget',
		'TMK_CTA_Banner_Widget', 'TMK_Newsletter_Widget', 'TMK_Breadcrumb_Widget', 'TMK_Gallery_Widget',
		'TMK_Practical_Widget', 'TMK_Menu_Preview_Widget', 'TMK_Booking_Widget', 'TMK_Nearby_Widget',
	);
}

abstract class TMK_Widget_Base extends \Elementor\Widget_Base {
	public function get_categories() { return array( 'tmk' ); }
	public function get_icon() { return 'eicon-food'; }
	/** Adds the manual|current_post source switch used by data-driven widgets. */
	protected function add_source_control( $note = 'In un template dinamico usa “Post corrente”.' ) {
		$this->add_control( 'source', array(
			'label'   => 'Sorgente dati',
			'type'    => CM::SELECT,
			'default' => 'manual',
			'options' => array( 'manual' => 'Manuale', 'current_post' => 'Post corrente (Ristorante)' ),
			'description' => $note,
		) );
	}
}

class TMK_Hero_Widget extends TMK_Widget_Base {
	public function get_name() { return 'tmk-hero'; }
	public function get_title() { return 'TMK Hero'; }
	public function get_icon() { return 'eicon-banner'; }
	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => 'Hero' ) );
		$this->add_source_control();
		$this->add_control( 'variant', array( 'label' => 'Variante', 'type' => CM::SELECT, 'default' => 'full-bleed',
			'options' => array( 'full-bleed' => 'Full-bleed', 'contained' => 'Contained', 'minimal' => 'Minimal' ),
			'condition' => array( 'source' => 'manual' ) ) );
		$this->add_control( 'media', array( 'label' => 'Immagine', 'type' => CM::MEDIA, 'condition' => array( 'source' => 'manual' ) ) );
		$this->add_control( 'media_alt', array( 'label' => 'Alt immagine', 'type' => CM::TEXT, 'condition' => array( 'source' => 'manual' ) ) );
		$this->add_control( 'eyebrow', array( 'label' => 'Eyebrow', 'type' => CM::TEXT, 'condition' => array( 'source' => 'manual' ) ) );
		$this->add_control( 'headline', array( 'label' => 'Titolo (H1)', 'type' => CM::TEXT, 'condition' => array( 'source' => 'manual' ) ) );
		$this->add_control( 'subheadline', array( 'label' => 'Sottotitolo', 'type' => CM::TEXTAREA, 'condition' => array( 'source' => 'manual' ) ) );
		$this->add_control( 'cta_label', array( 'label' => 'CTA — etichetta', 'type' => CM::TEXT, 'default' => 'Prenota ora', 'condition' => array( 'source' => 'manual' ) ) );
		$this->add_control( 'cta_href', array( 'label' => 'CTA — link', 'type' => CM::URL, 'condition' => array( 'source' => 'manual' ) ) );
		$this->add_control( 'secondary_label', array( 'label' => 'CTA secondaria — etichetta', 'type' => CM::TEXT, 'condition' => array( 'source' => 'manual' ) ) );
		$this->add_control( 'secondary_href', array( 'label' => 'CTA secondaria — link', 'type' => CM::URL, 'condition' => array( 'source' => 'manual' ) ) );
		$this->add_control( 'height', array( 'label' => 'Altezza (es. min(58vh,520px))', 'type' => CM::TEXT, 'condition' => array( 'source' => 'manual' ) ) );
		$this->end_controls_section();
	}
	protected function render() {
		$s = $this->get_settings_for_display();
		if ( 'current_post' === ( $s['source'] ?? 'manual' ) && ( $loc = tmk_current_location() ) ) {
			$f = tmk_location_fields( $loc );
			echo tmk_render_hero( array( 'variant' => 'full-bleed', 'media_id' => $f['media_id'], 'media_alt' => $f['title'],
				'eyebrow' => $f['city'] ? $f['city']->name : '', 'headline' => $f['title'], 'subheadline' => $f['tagline'],
				'concept' => $f['concept'], 'cta_label' => 'Prenota', 'cta_href' => $f['booking'],
				'secondary_label' => 'Scopri il menu', 'secondary_href' => '#menu' ) );
			return;
		}
		echo tmk_render_hero( array(
			'variant' => $s['variant'] ?? 'full-bleed', 'media_id' => (int) ( $s['media']['id'] ?? 0 ), 'media_alt' => $s['media_alt'] ?? '',
			'eyebrow' => $s['eyebrow'] ?? '', 'headline' => $s['headline'] ?? '', 'subheadline' => $s['subheadline'] ?? '',
			'cta_label' => $s['cta_label'] ?? '', 'cta_href' => $s['cta_href']['url'] ?? '',
			'secondary_label' => $s['secondary_label'] ?? '', 'secondary_href' => $s['secondary_href']['url'] ?? '#', 'height' => $s['height'] ?? '',
		) );
	}
}

class TMK_Narrative_Widget extends TMK_Widget_Base {
	public function get_name() { return 'tmk-narrative'; }
	public function get_title() { return 'TMK Narrative Band'; }
	public function get_icon() { return 'eicon-text-area'; }
	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => 'Narrative Band' ) );
		$this->add_source_control();
		$this->add_control( 'eyebrow', array( 'label' => 'Eyebrow', 'type' => CM::TEXT, 'condition' => array( 'source' => 'manual' ) ) );
		$this->add_control( 'title', array( 'label' => 'Titolo', 'type' => CM::TEXT, 'condition' => array( 'source' => 'manual' ) ) );
		$this->add_control( 'align', array( 'label' => 'Allineamento', 'type' => CM::SELECT, 'default' => 'center',
			'options' => array( 'center' => 'Centrato', 'start' => 'A sinistra' ), 'condition' => array( 'source' => 'manual' ) ) );
		$this->add_control( 'body', array( 'label' => 'Testo', 'type' => CM::WYSIWYG, 'condition' => array( 'source' => 'manual' ) ) );
		$this->add_control( 'motif', array( 'label' => 'Illustrazione (motif)', 'type' => CM::MEDIA, 'condition' => array( 'source' => 'manual' ) ) );
		$this->add_control( 'motif_alt', array( 'label' => 'Alt illustrazione', 'type' => CM::TEXT, 'condition' => array( 'source' => 'manual' ) ) );
		$this->end_controls_section();
	}
	protected function render() {
		$s = $this->get_settings_for_display();
		if ( 'current_post' === ( $s['source'] ?? 'manual' ) && ( $loc = tmk_current_location() ) ) {
			$f = tmk_location_fields( $loc );
			$body = $f['short'] ? $f['short'] : wp_strip_all_tags( $loc->post_content );
			if ( $body ) echo tmk_render_narrative_band( array( 'eyebrow' => 'L’atmosfera', 'align' => 'start', 'motif_id' => $f['logotype_id'], 'motif_alt' => $f['logotype_alt'] ), $body );
			return;
		}
		echo tmk_render_narrative_band( array(
			'eyebrow' => $s['eyebrow'] ?? '', 'title' => $s['title'] ?? '', 'align' => $s['align'] ?? 'center',
			'motif_id' => (int) ( $s['motif']['id'] ?? 0 ), 'motif_alt' => $s['motif_alt'] ?? '',
		), (string) ( $s['body'] ?? '' ) );
	}
}

class TMK_City_Bands_Widget extends TMK_Widget_Base {
	public function get_name() { return 'tmk-city-bands'; }
	public function get_title() { return 'TMK Città (bands)'; }
	public function get_icon() { return 'eicon-posts-grid'; }
	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => 'Città' ) );
		$this->add_control( 'note', array( 'type' => CM::RAW_HTML, 'raw' => 'Griglia dinamica di tutte le città con ristoranti (dati reali). Nessuna configurazione.' ) );
		$this->end_controls_section();
	}
	protected function render() { echo tmk_render_city_bands(); }
}

class TMK_Trust_Widget extends TMK_Widget_Base {
	public function get_name() { return 'tmk-trust'; }
	public function get_title() { return 'TMK Trust Band'; }
	public function get_icon() { return 'eicon-testimonial'; }
	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => 'Trust Band' ) );
		$this->add_control( 'rotating', array( 'label' => 'A rotazione', 'type' => CM::SWITCHER, 'default' => '' ) );
		$this->end_controls_section();
	}
	protected function render() {
		$s = $this->get_settings_for_display();
		echo tmk_render_trust_band( array( 'rotating' => ( 'yes' === ( $s['rotating'] ?? '' ) ) ) );
	}
}

class TMK_CTA_Banner_Widget extends TMK_Widget_Base {
	public function get_name() { return 'tmk-cta-banner'; }
	public function get_title() { return 'TMK CTA Banner'; }
	public function get_icon() { return 'eicon-call-to-action'; }
	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => 'CTA Banner' ) );
		$this->add_control( 'variant', array( 'label' => 'Variante', 'type' => CM::SELECT, 'default' => 'booking',
			'options' => array( 'booking' => 'Booking', 'newsletter' => 'Newsletter', 'delivery' => 'Delivery' ) ) );
		$this->add_control( 'title', array( 'label' => 'Titolo', 'type' => CM::TEXT ) );
		$this->add_control( 'copy', array( 'label' => 'Testo', 'type' => CM::TEXTAREA ) );
		$this->add_control( 'cta_label', array( 'label' => 'CTA — etichetta', 'type' => CM::TEXT ) );
		$this->add_control( 'cta_href', array( 'label' => 'CTA — link', 'type' => CM::URL ) );
		$this->end_controls_section();
	}
	protected function render() {
		$s = $this->get_settings_for_display();
		echo tmk_render_cta_banner( array(
			'variant' => $s['variant'] ?? 'booking', 'title' => $s['title'] ?? '', 'copy' => $s['copy'] ?? '',
			'cta_label' => $s['cta_label'] ?? '', 'cta_href' => $s['cta_href']['url'] ?? '',
		) );
	}
}

class TMK_Newsletter_Widget extends TMK_Widget_Base {
	public function get_name() { return 'tmk-newsletter'; }
	public function get_title() { return 'TMK Newsletter'; }
	public function get_icon() { return 'eicon-email-field'; }
	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => 'Newsletter' ) );
		$this->add_control( 'variant', array( 'label' => 'Variante', 'type' => CM::SELECT, 'default' => 'inline',
			'options' => array( 'inline' => 'Inline', 'footer' => 'Footer' ) ) );
		$this->add_control( 'title', array( 'label' => 'Titolo', 'type' => CM::TEXT ) );
		$this->add_control( 'copy', array( 'label' => 'Testo', 'type' => CM::TEXTAREA ) );
		$this->end_controls_section();
	}
	protected function render() {
		$s = $this->get_settings_for_display();
		$a = array( 'variant' => $s['variant'] ?? 'inline' );
		if ( ! empty( $s['title'] ) ) $a['title'] = $s['title'];
		if ( ! empty( $s['copy'] ) ) $a['copy'] = $s['copy'];
		echo tmk_render_newsletter_capture( $a );
	}
}

class TMK_Breadcrumb_Widget extends TMK_Widget_Base {
	public function get_name() { return 'tmk-breadcrumb'; }
	public function get_title() { return 'TMK Breadcrumb'; }
	public function get_icon() { return 'eicon-navigation-horizontal'; }
	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => 'Breadcrumb' ) );
		$this->add_source_control();
		$this->add_control( 'trail', array( 'label' => 'Percorso (Etichetta|url, per riga)', 'type' => CM::TEXTAREA,
			'description' => 'Es: Home|/, Ristoranti|/ristoranti/, Rimini', 'condition' => array( 'source' => 'manual' ) ) );
		$this->end_controls_section();
	}
	protected function render() {
		$s = $this->get_settings_for_display();
		if ( 'current_post' === ( $s['source'] ?? 'manual' ) && ( $loc = tmk_current_location() ) ) {
			$f = tmk_location_fields( $loc );
			$trail = array( array( 'label' => 'Home', 'href' => home_url( '/' ) ), array( 'label' => 'Ristoranti', 'href' => home_url( '/ristoranti/' ) ) );
			if ( $f['city'] ) $trail[] = array( 'label' => $f['city']->name, 'href' => tmk_city_href( $f['city'] ) );
			$trail[] = array( 'label' => $f['title'] );
			echo tmk_render_breadcrumb( $trail );
			return;
		}
		$trail = array();
		foreach ( array_filter( array_map( 'trim', explode( ',', (string) ( $s['trail'] ?? '' ) ) ) ) as $crumb ) {
			$p = array_map( 'trim', explode( '|', $crumb, 2 ) );
			$trail[] = array( 'label' => $p[0], 'href' => $p[1] ?? '' );
		}
		echo tmk_render_breadcrumb( $trail );
	}
}

class TMK_Gallery_Widget extends TMK_Widget_Base {
	public function get_name() { return 'tmk-gallery'; }
	public function get_title() { return 'TMK Gallery'; }
	public function get_icon() { return 'eicon-gallery-grid'; }
	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => 'Gallery' ) );
		$this->add_source_control();
		$this->add_control( 'images', array( 'label' => 'Immagini', 'type' => CM::GALLERY, 'condition' => array( 'source' => 'manual' ) ) );
		$this->add_control( 'variant', array( 'label' => 'Variante', 'type' => CM::SELECT, 'default' => 'grid',
			'options' => array( 'grid' => 'Griglia', 'full-bleed-scroll' => 'Scroll a tutta larghezza', 'carousel' => 'Carosello' ) ) );
		$this->end_controls_section();
	}
	protected function render() {
		$s = $this->get_settings_for_display();
		$ids = array();
		if ( 'current_post' === ( $s['source'] ?? 'manual' ) && ( $loc = tmk_current_location() ) ) {
			$ids = tmk_location_fields( $loc )['gallery'];
		} else {
			foreach ( (array) ( $s['images'] ?? array() ) as $img ) if ( ! empty( $img['id'] ) ) $ids[] = (int) $img['id'];
		}
		if ( ! $ids ) { return; }
		$variant = $s['variant'] ?? 'grid';
		if ( 'carousel' === $variant ) {
			echo tmk_render_gallery_carousel( $ids );
			return;
		}
		echo tmk_render_gallery( array( 'ids' => $ids, 'variant' => $variant ) );
	}
	/** Elementor Pro's Media_Carousel assets (real swiper.js + its Elementor CSS) - not enqueued
	 *  sitewide, so this widget must declare them itself for the carousel variant to be
	 *  interactive rather than just static markup. */
	public function get_style_depends(): array { return array( 'e-swiper', 'widget-media-carousel', 'widget-carousel-module-base' ); }
	public function get_script_depends(): array { return array( 'swiper' ); }
}

class TMK_Practical_Widget extends TMK_Widget_Base {
	public function get_name() { return 'tmk-practical'; }
	public function get_title() { return 'TMK Informazioni pratiche'; }
	public function get_icon() { return 'eicon-info-circle'; }
	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => 'Informazioni pratiche' ) );
		$this->add_source_control();
		$this->add_control( 'hours', array( 'label' => 'Orari (Etichetta|Valore, per riga)', 'type' => CM::TEXTAREA, 'condition' => array( 'source' => 'manual' ) ) );
		$this->add_control( 'address', array( 'label' => 'Indirizzo', 'type' => CM::TEXTAREA, 'condition' => array( 'source' => 'manual' ) ) );
		$this->add_control( 'phone', array( 'label' => 'Telefono', 'type' => CM::TEXT, 'condition' => array( 'source' => 'manual' ) ) );
		$this->add_control( 'accessibility', array( 'label' => 'Accessibilità', 'type' => CM::TEXTAREA, 'condition' => array( 'source' => 'manual' ) ) );
		$this->add_control( 'map_label', array( 'label' => 'Etichetta mappa', 'type' => CM::TEXT, 'condition' => array( 'source' => 'manual' ) ) );
		$this->end_controls_section();
	}
	protected function render() {
		$s = $this->get_settings_for_display();
		if ( 'current_post' === ( $s['source'] ?? 'manual' ) && ( $loc = tmk_current_location() ) ) {
			$f = tmk_location_fields( $loc );
			echo tmk_render_practical_info_band( array( 'hours' => $f['hours'], 'address' => $f['address'], 'phone' => $f['phone'], 'phone_display' => $f['phone_display'], 'email' => $f['email'], 'neighbourhood_note' => $f['neighbourhood_note'], 'accessibility' => $f['access'], 'map_label' => $f['title'], 'map_url' => $f['map_url'], 'map_coords' => $f['map_coords'], 'map_style' => 'embed' ) );
			if ( $f['has_music'] ) echo '<p class="tmk-location__music">Musica &amp; atmosfera</p>';
			return;
		}
		$hours = array();
		foreach ( array_filter( array_map( 'trim', explode( "\n", (string) ( $s['hours'] ?? '' ) ) ) ) as $row ) {
			$p = array_map( 'trim', explode( '|', $row, 2 ) );
			$hours[] = array( 'label' => $p[0], 'value' => $p[1] ?? '' );
		}
		echo tmk_render_practical_info_band( array( 'hours' => $hours, 'address' => $s['address'] ?? '', 'phone' => $s['phone'] ?? '', 'accessibility' => $s['accessibility'] ?? '', 'map_label' => $s['map_label'] ?? '' ) );
	}
}

class TMK_Menu_Preview_Widget extends TMK_Widget_Base {
	public function get_name() { return 'tmk-menu-preview'; }
	public function get_title() { return 'TMK Menu (anteprima)'; }
	public function get_icon() { return 'eicon-cart-medium'; }
	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => 'Menu — anteprima' ) );
		$this->add_control( 'limit', array( 'label' => 'Numero piatti', 'type' => CM::NUMBER, 'default' => 6, 'min' => 2, 'max' => 12 ) );
		$this->end_controls_section();
	}
	protected function render() {
		$s = $this->get_settings_for_display();
		if ( ! function_exists( 'tmk_location_menu_selection' ) ) return;
		$dishes = tmk_location_menu_selection( (int) ( $s['limit'] ?? 6 ) );
		if ( ! $dishes ) return;
		$menu_url = home_url( '/menu/' );
		$cards = '';
		foreach ( $dishes as $dish ) {
			$cats = wp_get_post_terms( $dish->ID, 'menu_category', array( 'number' => 1 ) );
			$eyebrow = ( ! is_wp_error( $cats ) && $cats ) ? html_entity_decode( $cats[0]->name, ENT_QUOTES ) : '';
			$cards .= tmk_render_card( array( 'variant' => 'menu', 'eyebrow' => $eyebrow, 'title' => get_the_title( $dish ),
				'supporting' => (string) get_post_meta( $dish->ID, 'short_description', true ), 'price' => (string) get_post_meta( $dish->ID, 'price', true ), 'action' => '', 'href' => $menu_url ) );
		}
		$intro = defined( 'TMK_LOCATION_MENU_INTRO' ) ? '<p class="tmk-location__menu-intro">' . esc_html( TMK_LOCATION_MENU_INTRO ) . '</p>' : '';
		echo '<section class="tmk-location__band tmk-location__menu" id="menu"><h2 class="tmk-eyebrow tmk-location__eyebrow">Il menu &middot; una selezione</h2>' . $intro
			. tmk_render_card_grid( $cards, 3 )
			. '<p class="tmk-location__menu-more"><a class="tmk-cta tmk-cta--secondary" href="' . esc_url( $menu_url ) . '">Vedi il men&ugrave; completo</a></p></section>';
	}
}

class TMK_Booking_Widget extends TMK_Widget_Base {
	public function get_name() { return 'tmk-booking'; }
	public function get_title() { return 'TMK Booking'; }
	public function get_icon() { return 'eicon-calendar'; }
	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => 'Booking' ) );
		$this->add_source_control();
		$this->add_control( 'location_name', array( 'label' => 'Nome sede', 'type' => CM::TEXT, 'condition' => array( 'source' => 'manual' ) ) );
		$this->add_control( 'booking_url', array( 'label' => 'URL prenotazione', 'type' => CM::URL, 'condition' => array( 'source' => 'manual' ) ) );
		$this->end_controls_section();
	}
	protected function render() {
		$s = $this->get_settings_for_display();
		if ( 'current_post' === ( $s['source'] ?? 'manual' ) && ( $loc = tmk_current_location() ) ) {
			$f = tmk_location_fields( $loc );
			$name = $f['title']; $booking = $f['booking'];
		} else {
			$name = $s['location_name'] ?? '';
			$booking = ! empty( $s['booking_url']['url'] ) ? $s['booking_url']['url'] : ( defined( 'TMK_BOOKING_URL' ) ? TMK_BOOKING_URL : home_url( '/#prenota' ) );
		}
		echo tmk_render_cta_banner( array( 'variant' => 'booking' ),
			tmk_render_booking_widget( array( 'tier' => 1, 'location_name' => $name, 'booking_url' => $booking ) ) );
	}
}

class TMK_Nearby_Widget extends TMK_Widget_Base {
	public function get_name() { return 'tmk-nearby'; }
	public function get_title() { return 'TMK Altri ristoranti'; }
	public function get_icon() { return 'eicon-nested-elements'; }
	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => 'Altri ristoranti' ) );
		$this->add_control( 'note', array( 'type' => CM::RAW_HTML, 'raw' => 'Mostra le altre città (dinamico, dal Ristorante corrente).' ) );
		$this->end_controls_section();
	}
	protected function render() {
		$loc = tmk_current_location();
		if ( ! $loc ) return;
		$f = tmk_location_fields( $loc );
		$others = array();
		if ( $f['city'] ) {
			foreach ( tmk_ordered_city_terms() as $t ) if ( $t->term_id !== $f['city']->term_id ) $others[] = $t;
		}
		$others = array_slice( $others, 0, 4 );
		if ( ! $others ) return;
		$cards = '';
		foreach ( $others as $t ) $cards .= tmk_render_city_card( $t, 'lazy', '', '(min-width: 1024px) 280px, calc(100vw - 48px)' );
		echo '<section class="tmk-location__band tmk-location__nearby"><h2 class="tmk-eyebrow tmk-location__eyebrow">Altri ristoranti Temakinho</h2>' . tmk_render_card_grid( $cards, 4, 2 ) . '</section>';
	}
}
