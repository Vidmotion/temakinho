<?php
/**
 * Careers — the Careers Hub (/lavora-con-noi/) + the Job Detail (/lavora-con-noi/{slug}/). Direct
 * translation of pages/CareersHub.jsx and pages/JobDetail.jsx (Blueprint §5.9 family): people &
 * culture first, roles second, the application form last (a conversion, not an HR portal).
 *
 * COMPOSITION from Claude Design, DATASET from the real `job` CPT. The culture intro copy is the
 * live temakinho.com "Lavora con noi" text (migrated, not invented). Fixed section orders:
 *   HUB     Hero -> Culture -> Pillars -> Trust -> Open roles -> Conversion.
 *   DETAIL  Hero -> Breadcrumb -> Intro -> Role narrative -> Trust -> Application -> Related roles.
 * Jobs with application_method="external" link to their ATS instead of showing the first-party form.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/** All published jobs (newest first). */
function tmk_get_jobs() {
	return get_posts( array( 'post_type' => 'job', 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'date', 'order' => 'DESC' ) );
}

/**
 * The job's category term ("Head Quarter" / "Ristoranti"), or null when the editor has not set
 * one. Never guessed from the presence of a location: an unset category is unset, and the card
 * simply carries one fewer beat.
 */
function tmk_job_area( $job ) {
	$terms = get_the_terms( $job, 'job_area' );
	return ( $terms && ! is_wp_error( $terms ) ) ? $terms[0] : null;
}

/**
 * The human "where" for a job — DERIVED from the linked location, not retyped.
 *
 * The brief is explicit: "evitando di creare manualmente testo duplicato". `location_id` is a real
 * post reference (an ACF Post Object picker scoped to the `location` CPT, see
 * _BUILD/181-careers-fields.php), so the label is composed from the location's own city term and
 * title — "Milano · Aqua" — and follows the restaurant automatically if it is ever renamed.
 *
 * `job_where`, the free-text field the four seeded jobs already carry, is kept as an explicit
 * OVERRIDE rather than removed: a Head Quarter role has no location post to point at and still
 * needs to say "Milano", and deleting a populated field to prove a point would have destroyed
 * real content. Precedence is location first, text second — the derived value is the one that
 * cannot drift.
 */
function tmk_job_where( $job ) {
	$job_id = is_object( $job ) ? $job->ID : (int) $job;
	$loc_id = (int) get_post_meta( $job_id, 'location_id', true );
	if ( $loc_id && 'location' === get_post_type( $loc_id ) && 'publish' === get_post_status( $loc_id ) ) {
		$parts = array();
		$city  = get_the_terms( $loc_id, 'city' );
		if ( $city && ! is_wp_error( $city ) ) {
			$parts[] = $city[0]->name;
		}
		$parts[] = get_the_title( $loc_id );
		return implode( ' · ', array_filter( $parts ) );
	}
	return (string) get_post_meta( $job_id, 'job_where', true );
}

/** The card kicker every job card and the Job Detail hero share: "Categoria · Dove · Contratto". */
function tmk_job_kicker( $job ) {
	$area = tmk_job_area( $job );
	return trim( implode( ' · ', array_filter( array(
		$area ? $area->name : '',
		tmk_job_where( $job ),
		(string) get_post_meta( is_object( $job ) ? $job->ID : (int) $job, 'employment_type', true ),
	) ) ) );
}

/**
 * A job card, bound to a real job post.
 *
 * VARIANT CHANGED 'city' -> 'location' (2026-09-03). The city variant is a 21:8 poster banner,
 * built for a full-width or one-up placement; the Open Roles grid renders three-up, where 21:8
 * resolves to roughly 460x175 and leaves a Londrina 900 role title almost no room. --location is
 * the 4:3 crop (--ratio-card), which is the shape this grid actually needs and the one the
 * "Altri ristoranti" row already uses at the same density. Same component, same visual language,
 * correct crop for the context - which is exactly the distinction the brief draws between layout
 * (may vary) and visual component (may not).
 */
function tmk_job_card( $job, $sizes = '' ) {
	$area = tmk_job_area( $job );
	return tmk_render_card( array(
		'variant'  => 'location',
		'media_id' => (int) get_post_thumbnail_id( $job ),
		'eyebrow'  => tmk_job_kicker( $job ),
		'title'    => get_the_title( $job ),
		'action'   => 'Scopri',
		'href'     => get_permalink( $job ),
		'sizes'    => $sizes,
		'data'     => array( 'data-tmk-job-area' => $area ? $area->slug : '' ),
	) );
}

/**
 * OPEN ROLES — the live, editable listing, with the Head Quarter / Ristoranti filter.
 *
 * WHY THIS EXISTS. _BUILD/104 baked the four seeded jobs into the Careers Hub template as four
 * hand-built Elementor card containers. That was correct for the build it shipped and fatal for
 * the requirement here: a job the client creates in wp-admin could never appear on the page, and a
 * job they deleted would keep showing. The template now holds ONE shortcode widget and this
 * function is the listing, so "il cliente deve poter gestire le opportunita' senza dover
 * modificare codice" is literally true — create, edit, unpublish, delete, all from wp-admin.
 *
 * The cards are the design system's own Card component (the same .tmk-card--overlay.tmk-card--city
 * the city bands and the Location page's "Altri ristoranti" row use). No careers-only card exists.
 *
 * FILTERING is progressive enhancement, not a query. Every published job is rendered server-side,
 * so the page is complete and indexable with JavaScript off and the filter bar is simply absent
 * (js/pages/careers-filter.js reveals it and does the hiding). A server-side filter would have
 * meant a query var on a CPT archive whose main query the Theme Builder template already owns,
 * for a list of four to a few dozen rows.
 *
 * The bar shows only the categories that actually have a published job in them: a filter that
 * leads to an empty result is worse than no filter.
 */
function tmk_render_jobs_grid( $atts = array() ) {
	/* 2 colonne, non 3 (QA visiva 2026-09-04). Con le 4 posizioni aperte attuali una griglia a 3
	   lasciava una card sola su una riga da tre, cioe' due caselle vuote a fianco: e' il "grande
	   spazio vuoto senza una ragione compositiva" del brief. A 2 colonne la griglia e' piena e
	   le card raddoppiano di larghezza. Resta un attributo dello shortcode: [tmk_jobs columns="3"]
	   lo riporta a tre senza toccare il codice. */
	$atts = shortcode_atts( array( 'columns' => 2, 'columns_tablet' => 2 ), $atts, 'tmk_jobs' );
	$jobs = tmk_get_jobs();

	if ( ! $jobs ) {
		return tmk_render_system_state( array(
			'kind'         => 'empty',
			'title'        => 'Nessuna posizione aperta in questo momento',
			'copy'         => 'Torna a trovarci: apriamo nuove ricerche durante tutto l’anno. Nel frattempo puoi mandarci una candidatura spontanea.',
			'action_label' => 'Scrivici',
			'action_href'  => home_url( '/contattaci/' ),
		) );
	}

	$counts = array();
	foreach ( $jobs as $job ) {
		$area = tmk_job_area( $job );
		if ( $area ) {
			$counts[ $area->slug ] = isset( $counts[ $area->slug ] ) ? $counts[ $area->slug ] + 1 : 1;
		}
	}
	$areas = array();
	foreach ( get_terms( array( 'taxonomy' => 'job_area', 'hide_empty' => false ) ) as $term ) {
		if ( ! is_wp_error( $term ) && ! empty( $counts[ $term->slug ] ) ) {
			$areas[] = $term;
		}
	}

	$sizes = '(min-width:1024px) 460px, calc(100vw - 64px)';
	$cards = '';
	foreach ( $jobs as $job ) {
		$cards .= tmk_job_card( $job, $sizes );
	}

	ob_start();
	?>
	<div class="tmk-jobs" data-tmk-job-filter>
		<?php if ( count( $areas ) > 1 ) : ?>
			<div class="tmk-jobs__filter" role="group" aria-label="Filtra le posizioni per categoria" data-tmk-job-filter-bar hidden>
				<button type="button" class="tmk-jobs__filter-btn is-active" data-tmk-job-filter-value="" aria-pressed="true">
					Tutte <span class="tmk-jobs__filter-count"><?php echo (int) count( $jobs ); ?></span>
				</button>
				<?php foreach ( $areas as $term ) : ?>
					<button type="button" class="tmk-jobs__filter-btn" data-tmk-job-filter-value="<?php echo esc_attr( $term->slug ); ?>" aria-pressed="false">
						<?php echo esc_html( $term->name ); ?> <span class="tmk-jobs__filter-count"><?php echo (int) $counts[ $term->slug ]; ?></span>
					</button>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
		<?php echo tmk_render_card_grid( $cards, (int) $atts['columns'], (int) $atts['columns_tablet'] ); ?>
		<p class="tmk-jobs__none" role="status" data-tmk-job-filter-empty hidden>Nessuna posizione aperta in questa categoria.</p>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'tmk_jobs', 'tmk_render_jobs_grid' );

/**
 * CAREERS HUB — Hero -> Culture -> Pillars -> Trust -> Open roles -> Conversion.
 */
function tmk_render_careers_hub() {
	$jobs = tmk_get_jobs();

	// Approved "why work here" pillars (careers-data.js) — brand content with no live equivalent.
	$pillars = array(
		array( 'eyebrow' => 'Crescita', 'title' => 'Percorsi veri', 'supporting' => 'Formazione continua e avanzamenti basati sul merito, non sull’anzianità.', 'media' => 31 ),
		array( 'eyebrow' => 'Squadra', 'title' => 'Si vince insieme', 'supporting' => 'Turni sostenibili, mance condivise, una brigata che si guarda le spalle.', 'media' => 30 ),
		array( 'eyebrow' => 'Cultura', 'title' => 'Due mondi, una tavola', 'supporting' => 'Rigore giapponese e calore brasiliano: qui li vivi ogni giorno.', 'media' => 227 ),
	);

	ob_start();

	echo tmk_render_hero( array(
		'variant'     => 'full-bleed',
		'media_id'    => function_exists( 'tmk_asset_id' ) ? tmk_asset_id( 'interior-milano' ) : 0,
		'media_alt'   => 'La brigata di sala di Temakinho tra il verde e il turchese della sala',
		'eyebrow'     => 'Lavora con noi',
		'headline'    => 'Qui si lavora con alegria',
		'subheadline' => 'Cerchiamo persone, non curriculum. Se ami la sala, la cucina e stare tra la gente, questa è casa tua.',
		'cta_label'   => '',
		'height'      => 'min(58vh, 520px)',
	) );
	?>

	<div class="tmk-stack">

		<?php /* CULTURE — people first; the body is the live temakinho.com careers copy. */ ?>
		<section aria-label="La nostra cultura">
			<?php echo tmk_render_narrative_band(
				array( 'eyebrow' => 'La nostra cultura', 'title' => 'Prima le persone, poi il resto', 'align' => 'start' ),
				'Cerchiamo persone che sappiano stare al passo con il ritmo, mettere passione in quello che fanno e che credano di poter portare valore a un ambiente dinamico, divertente ed emozionante con il loro lavoro ed entusiasmo, anche lavorando sotto pressione.'
			); ?>
		</section>

		<?php /* PILLARS — what it's like to work here (photo-led). */ ?>
		<section aria-label="Perché Temakinho">
			<h2 class="tmk-section-eyebrow">Cosa significa lavorare qui</h2>
			<?php
			$cards = '';
			foreach ( $pillars as $p ) {
				$cards .= tmk_render_card( array(
					'variant'    => 'story',
					'media_id'   => $p['media'],
					'eyebrow'    => $p['eyebrow'],
					'title'      => $p['title'],
					'supporting' => $p['supporting'],
					'action'     => '',
					'href'       => '#ruoli',
					'sizes'      => '(min-width:1024px) 320px, calc(100vw - 48px)',
				) );
			}
			echo tmk_render_card_grid( $cards, 3 );
			?>
		</section>

		<?php /* TRUST — a voice from the team. */ ?>
		<section aria-label="Le nostre persone">
			<?php echo tmk_render_trust_band( array( 'rotating' => false ) ); ?>
		</section>

		<?php /* OPEN ROLES — recruitment after culture. */ ?>
		<section id="ruoli" aria-label="Posizioni aperte">
			<h2 class="tmk-section-eyebrow">Posizioni aperte</h2>
			<?php if ( $jobs ) :
				$cards = '';
				foreach ( $jobs as $j ) $cards .= tmk_job_card( $j, '(min-width:1024px) 500px, calc(100vw - 48px)' );
				echo tmk_render_card_grid( $cards, 2 );
			else :
				echo tmk_render_system_state( array(
					'kind'         => 'empty',
					'title'        => 'Nessuna posizione aperta al momento',
					'copy'         => 'Non trovi il tuo ruolo? Mandaci lo stesso una candidatura: le persone giuste le troviamo sempre un posto.',
					'action_label' => 'Scrivici',
					'action_href'  => home_url( '/contattaci/' ),
				) );
			endif; ?>
		</section>

		<?php /* CONVERSION — open application, single primary action. */ ?>
		<section aria-label="Candidatura spontanea">
			<?php echo tmk_render_cta_banner( array(
				'variant'   => 'booking',
				'title'     => 'Non trovi il tuo ruolo?',
				'copy'      => 'Mandaci lo stesso una candidatura: le persone giuste le troviamo sempre un posto.',
				'cta_label' => 'Scrivici',
				'cta_href'  => home_url( '/contattaci/' ),
			) ); ?>
		</section>

	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'tmk_careers_hub', 'tmk_render_careers_hub' );

/*
 * RESTORED VERBATIM (bar the kicker, which now goes through tmk_job_kicker() like every other
 * careers surface). This function backs inc/templates/single-job.php's [tmk_job] shortcode. That
 * template is currently unreachable - PATCH 1 disabled careers-routing.php and the live Job
 * Detail is Elementor template #563 - but the shortcode is still registered and the template file
 * still calls it, so deleting it would turn a dormant path into a fatal one. */
/**
 * JOB DETAIL — Hero -> Breadcrumb -> Intro -> Role narrative -> Trust -> Application -> Related roles.
 */
function tmk_render_job( $post = null ) {
	$post = $post ? get_post( $post ) : get_queried_object();
	if ( ! $post instanceof WP_Post || 'job' !== $post->post_type ) {
		return '';
	}
	$id    = $post->ID;
	$intro = get_the_excerpt( $post );
	$media = (int) get_post_thumbnail_id( $id );

	ob_start();

	echo tmk_render_hero( array(
		'variant'     => 'full-bleed',
		'media_id'    => $media,
		'media_alt'   => get_the_title( $post ),
		'eyebrow'     => tmk_job_kicker( $post ),
		'headline'    => get_the_title( $post ),
		'subheadline' => $intro,
		'cta_label'   => 'Candidati',
		'cta_href'    => '#candidati',
	) );
	?>

	<article class="tmk-job">

		<div class="tmk-job__column">
			<?php echo tmk_render_breadcrumb( array(
				array( 'label' => 'Home', 'href' => home_url( '/' ) ),
				array( 'label' => 'Lavora con noi', 'href' => home_url( '/lavora-con-noi/' ) ),
				array( 'label' => get_the_title( $post ) ),
			) ); ?>
		</div>

		<?php if ( $post->post_content ) : ?>
			<div class="tmk-job__column tmk-job__body">
				<?php echo apply_filters( 'the_content', $post->post_content ); ?>
			</div>
		<?php endif; ?>

		<div class="tmk-job__column tmk-job__wide">
			<section aria-labelledby="tmk-job-trust">
				<h2 id="tmk-job-trust" class="tmk-sr-only">Le nostre persone</h2>
				<?php echo tmk_render_trust_band( array( 'rotating' => false ) ); ?>
			</section>
		</div>

		<div class="tmk-job__column" id="candidati">
			<h2 class="tmk-section-eyebrow">Candidati per questa posizione</h2>
			<p class="tmk-job__form-title">Raccontaci chi sei</p>
			<?php echo tmk_render_job_application_form( $id ); ?>
		</div>

		<?php
		$others = array();
		foreach ( tmk_get_jobs() as $j ) if ( $j->ID !== $id ) $others[] = $j;
		$others = array_slice( $others, 0, 2 );
		if ( $others ) : ?>
			<div class="tmk-job__wide">
				<section aria-labelledby="tmk-job-related">
					<h2 id="tmk-job-related" class="tmk-section-eyebrow">Altre posizioni</h2>
					<?php
					$cards = '';
					foreach ( $others as $j ) $cards .= tmk_job_card( $j, '(min-width:1024px) 500px, calc(100vw - 48px)' );
					echo tmk_render_card_grid( $cards, 2 );
					?>
				</section>
			</div>
		<?php endif; ?>

	</article>
	<?php
	return ob_get_clean();
}
add_shortcode( 'tmk_job', function () {
	return tmk_render_job();
} );


/**
 * ============================================================================================
 * APPLICATION — the first-party form and its handler.
 * ============================================================================================
 *
 * WHAT WAS HERE BEFORE, and why it needed replacing rather than extending:
 *   - the form collected name / email / phone / free-text message and told the candidate to
 *     "allega il CV rispondendo all'email di conferma" — there was no CV upload at all, and no
 *     confirmation email was ever sent, so that instruction pointed at nothing;
 *   - "Raccontaci chi sei" was one textarea doing duty as both cover letter and notes;
 *   - the record stored the job's TITLE as a string, so an application lost its link the moment
 *     a job was renamed;
 *   - nothing was ever notified: the record sat in a private CPT nobody is told about;
 *   - and none of it was reachable, because the whole file is on the [tmk_job] shortcode path,
 *     which is used by no post on this site (the live Job Detail is Elementor template #563).
 *     Session 150 documented that shape for the Trust Band; it is true here too.
 *
 * The form is now exposed as its own shortcode so template #563 can hold it directly, and it
 * posts a real multipart body.
 */

/**
 * The address applications are notified to.
 *
 * NOT INVENTED. recruiting@temakinho.com is the address the archived /pages/jobs page publishes
 * and the one _BUILD/104 already renders as the Careers Hub's apply mailto (its archived href is
 * malformed — a leading space and a trailing full stop — and was corrected there; the address
 * itself is real archived data). It is stored as a WordPress OPTION so the client can change it
 * without touching code, and filterable so an ATS integration can redirect it.
 *
 * SITE EMAIL INFRASTRUCTURE — reported, not invented: there is NO SMTP plugin on this install
 * (the only active plugins are ACF, Elementor, Elementor Pro, this one and WordPress Importer)
 * and no mail configuration of any kind, so wp_mail() falls through to PHP mail(). On Local that
 * is captured locally and never leaves the machine; in production it will need a real transport.
 * The handler therefore treats a failed send as NON-FATAL: the application is stored first and
 * the candidate is thanked whatever the mailer does, because losing a real application to a
 * missing SMTP config would be the worse failure. See the report.
 */
function tmk_applications_recipient() {
	$stored = get_option( 'tmk_applications_email', '' );
	if ( ! is_email( $stored ) ) {
		$stored = 'recruiting@temakinho.com';
	}
	return apply_filters( 'tmk_applications_recipient', $stored );
}

/**
 * The privacy consent sentence, stored once so the Careers form and the Contact form can never
 * drift apart.
 *
 * VERBATIM from the `privacy` acceptance field of the Contact form (Elementor page #251), which is
 * the informativa this site actually publishes. Filterable so the client can point it at a real
 * on-site privacy page the day one exists — see the report's open decisions.
 */
function tmk_privacy_consent_text() {
	$url  = apply_filters( 'tmk_privacy_policy_url', 'https://cdn.shopify.com/s/files/1/0016/0831/9029/files/Informativa_privacy_website.PDF?v=1713187395' );
	$text = 'Acconsento al trattamento, conservazione e comunicazione dei miei dati personali con le modalit&agrave; e per le finalit&agrave; specificate nella <a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">Privacy Policy</a> di cui dichiaro di essere a conoscenza.';
	return apply_filters( 'tmk_privacy_consent_text', $text, $url );
}

/** Accepted CV formats and the ceiling, in one place so the form copy and the handler agree. */
function tmk_cv_upload_rules() {
	return apply_filters( 'tmk_cv_upload_rules', array(
		'max_bytes' => 5 * 1024 * 1024,
		// Keyed the way wp_check_filetype_and_ext() wants them: extension pattern => real MIME.
		'mimes'     => array(
			'pdf'      => 'application/pdf',
			'doc'      => 'application/msword',
			'docx'     => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'odt'      => 'application/vnd.oasis.opendocument.text',
		),
	) );
}

/** The first-party application form (or an external ATS link for application_method="external"). */
function tmk_render_job_application_form( $job_id = 0 ) {
	$job_id = (int) $job_id;
	if ( ! $job_id ) {
		$job_id = (int) get_the_ID();
	}
	if ( ! $job_id || 'job' !== get_post_type( $job_id ) ) {
		return '';
	}
	$method = (string) get_post_meta( $job_id, 'application_method', true );
	$url    = (string) get_post_meta( $job_id, 'application_url', true );
	if ( 'external' === $method && $url ) {
		return '<a class="tmk-cta tmk-cta--primary" href="' . esc_url( $url ) . '" rel="noopener">Candidati sul portale</a>';
	}
	$rules = tmk_cv_upload_rules();
	$exts  = implode( ', ', array_map( 'strtoupper', array_keys( $rules['mimes'] ) ) );
	$accept = '.' . implode( ',.', array_keys( $rules['mimes'] ) );
	$max_mb = (int) round( $rules['max_bytes'] / 1048576 );
	$uid   = 'tmk-apply-' . wp_unique_id();
	ob_start();
	?>
	<form class="tmk-form tmk-form--apply" data-tmk-ajax-form action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" method="post" enctype="multipart/form-data">
		<input type="hidden" name="action" value="tmk_job_apply" />
		<input type="hidden" name="tmk_apply_nonce" value="<?php echo esc_attr( wp_create_nonce( 'tmk_job_apply' ) ); ?>" />
		<input type="hidden" name="job_id" value="<?php echo (int) $job_id; ?>" />
		<?php /* Honeypot: a real field, visually and aurally hidden, that only a bot fills in. Cheaper
		         and more accessible than a CAPTCHA, and it costs the candidate nothing. */ ?>
		<div class="tmk-form__trap" aria-hidden="true">
			<label for="<?php echo esc_attr( $uid ); ?>-url">Non compilare questo campo</label>
			<input id="<?php echo esc_attr( $uid ); ?>-url" name="website" type="text" tabindex="-1" autocomplete="off" />
		</div>

		<div class="tmk-form__row">
			<div class="tmk-form__field">
				<label class="tmk-form__label" for="<?php echo esc_attr( $uid ); ?>-first">Nome <span class="tmk-form__req" aria-hidden="true">*</span></label>
				<input class="tmk-form__input" id="<?php echo esc_attr( $uid ); ?>-first" name="first_name" type="text" autocomplete="given-name" required />
			</div>
			<div class="tmk-form__field">
				<label class="tmk-form__label" for="<?php echo esc_attr( $uid ); ?>-last">Cognome <span class="tmk-form__req" aria-hidden="true">*</span></label>
				<input class="tmk-form__input" id="<?php echo esc_attr( $uid ); ?>-last" name="last_name" type="text" autocomplete="family-name" required />
			</div>
		</div>

		<div class="tmk-form__row">
			<div class="tmk-form__field">
				<label class="tmk-form__label" for="<?php echo esc_attr( $uid ); ?>-email">Email <span class="tmk-form__req" aria-hidden="true">*</span></label>
				<input class="tmk-form__input" id="<?php echo esc_attr( $uid ); ?>-email" name="email" type="email" autocomplete="email" required />
			</div>
			<div class="tmk-form__field">
				<label class="tmk-form__label" for="<?php echo esc_attr( $uid ); ?>-phone">Telefono</label>
				<input class="tmk-form__input" id="<?php echo esc_attr( $uid ); ?>-phone" name="phone" type="tel" autocomplete="tel" />
			</div>
		</div>

		<div class="tmk-form__field">
			<label class="tmk-form__label" for="<?php echo esc_attr( $uid ); ?>-cv">Curriculum <span class="tmk-form__req" aria-hidden="true">*</span></label>
			<input class="tmk-form__file" id="<?php echo esc_attr( $uid ); ?>-cv" name="cv" type="file" accept="<?php echo esc_attr( $accept ); ?>" required aria-describedby="<?php echo esc_attr( $uid ); ?>-cv-hint" />
			<p class="tmk-form__hint" id="<?php echo esc_attr( $uid ); ?>-cv-hint"><?php echo esc_html( $exts ); ?> &middot; massimo <?php echo (int) $max_mb; ?> MB</p>
		</div>

		<div class="tmk-form__field">
			<label class="tmk-form__label" for="<?php echo esc_attr( $uid ); ?>-cover">Lettera di presentazione <span class="tmk-form__req" aria-hidden="true">*</span></label>
			<textarea class="tmk-form__textarea" id="<?php echo esc_attr( $uid ); ?>-cover" name="cover_letter" rows="7" required aria-describedby="<?php echo esc_attr( $uid ); ?>-cover-hint"></textarea>
			<p class="tmk-form__hint" id="<?php echo esc_attr( $uid ); ?>-cover-hint">Raccontaci chi sei e perch&eacute; questo ruolo.</p>
		</div>

		<div class="tmk-form__field">
			<label class="tmk-form__label" for="<?php echo esc_attr( $uid ); ?>-notes">Altre informazioni</label>
			<textarea class="tmk-form__textarea tmk-form__textarea--short" id="<?php echo esc_attr( $uid ); ?>-notes" name="notes" rows="3"></textarea>
		</div>

		<?php /*
			CONSENSO PRIVACY — obbligatorio, autorizzato dal proprietario il 2026-09-04.
			Il testo e il link NON sono nuovi: sono esattamente quelli del campo `privacy` del form
			Contatti (pagina #251) e della voce "Privacy" del footer, cioe' l'informativa che questo
			sito pubblica davvero. Non e' stata inventata copy legale e non e' stato inventato un
			URL: la pagina WordPress "Privacy Policy" (#3) esiste solo come bozza vuota di default,
			quindi linkarla avrebbe mandato il candidato su una pagina senza informativa.
			Un CV e una lettera di presentazione sono dati personali: il campo e' `required` lato
			browser E ricontrollato lato server (il browser si puo' aggirare, la richiesta no).
		*/ ?>
		<div class="tmk-form__field tmk-form__field--consent">
			<label class="tmk-form__consent" for="<?php echo esc_attr( $uid ); ?>-privacy">
				<input id="<?php echo esc_attr( $uid ); ?>-privacy" name="privacy" type="checkbox" value="1" required />
				<span><?php echo wp_kses_post( tmk_privacy_consent_text() ); ?> <span class="tmk-form__req" aria-hidden="true">*</span></span>
			</label>
		</div>

		<button type="submit" class="tmk-cta tmk-cta--primary tmk-form__submit">Invia candidatura</button>
		<p class="tmk-form__note">I dati che ci invii servono solo a valutare la tua candidatura.</p>
		<p class="tmk-form__msg tmk-form__msg--success" role="status" data-tmk-form-success hidden tabindex="-1">Grazie! Abbiamo ricevuto la tua candidatura: ti ricontattiamo presto.</p>
		<p class="tmk-form__msg tmk-form__msg--error" role="alert" data-tmk-form-error hidden>Qualcosa non ha funzionato. Controlla i campi e riprova.</p>
	</form>
	<?php
	return ob_get_clean();
}
/**
 * "Specifiche / requisiti" — the field, rendered honestly.
 *
 * It was already on the Job Detail template as a bare Post Custom Field dynamic tag inside a Text
 * Editor widget: no label, and — because a dynamic tag returns the raw meta string into a single
 * <p> — a multi-line value collapsed into one run-on paragraph. The client is now being asked to
 * fill this field from wp-admin (one requirement per line, per the ACF instructions), so it has to
 * render as what it is.
 *
 * A shortcode rather than a second dynamic tag, for one reason: the heading must be CONDITIONAL.
 * An Elementor heading widget prints whether or not the field beneath it has a value, so a job
 * with no requirements would show a "Requisiti" title over nothing. Here the whole block —
 * heading included — is absent when the field is empty, which is this codebase's own standing
 * rule that every dynamic beat degrades to nothing rather than to a placeholder.
 *
 * Lines become a real <ul> when there is more than one, and a paragraph when there is one, so the
 * editor's own line breaks survive without them having to know any markup.
 */
function tmk_render_job_requirements( $atts = array() ) {
	$atts   = shortcode_atts( array( 'job_id' => 0 ), $atts, 'tmk_job_requirements' );
	$job_id = (int) $atts['job_id'] ?: (int) get_the_ID();
	if ( ! $job_id || 'job' !== get_post_type( $job_id ) ) {
		return '';
	}
	$raw = trim( (string) get_post_meta( $job_id, 'requirements', true ) );
	if ( '' === $raw ) {
		return '';
	}
	$lines = array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $raw ) ) ) );
	if ( ! $lines ) {
		return '';
	}
	$out = '<section class="tmk-job-requirements" aria-labelledby="tmk-job-req-' . $job_id . '">';
	$out .= '<h2 class="tmk-section-title" id="tmk-job-req-' . $job_id . '">Requisiti</h2>';
	if ( count( $lines ) > 1 ) {
		$out .= '<ul class="tmk-job-requirements__list">';
		foreach ( $lines as $line ) {
			$out .= '<li>' . esc_html( $line ) . '</li>';
		}
		$out .= '</ul>';
	} else {
		$out .= '<p class="tmk-job-requirements__text">' . esc_html( $lines[0] ) . '</p>';
	}
	return $out . '</section>';
}
add_shortcode( 'tmk_job_requirements', 'tmk_render_job_requirements' );

/**
 * The role narrative, as three fields instead of hand-typed markup.
 *
 * `tmk_job_responsibilities` / `requirements` / `tmk_job_offer` are one item per line. Same
 * contract as tmk_render_job_requirements() above and for the same reason: the heading has to be
 * CONDITIONAL, because an Elementor heading widget prints whether or not the field under it has a
 * value, and a job with no requirements would otherwise show a title over nothing.
 *
 * Before this, the only place this content could live was post_content as hand-written <h3> + <ul>
 * blocks — which is why exactly one of the four published jobs has any, and that one is verbatim
 * placeholder copy from the design's careers-data.js. A client filling three plain textareas
 * cannot produce broken markup and cannot forget a closing tag.
 */
function tmk_render_job_sections( $atts = array() ) {
	$atts = shortcode_atts( array( 'job_id' => 0 ), $atts, 'tmk_job_sections' );
	$id   = (int) $atts['job_id'] ?: (int) get_the_ID();
	if ( ! $id || 'job' !== get_post_type( $id ) ) {
		return '';
	}
	$blocks = array(
		'tmk_job_responsibilities' => 'Cosa farai',
		'requirements'             => 'Chi cerchiamo',
		'tmk_job_offer'            => 'Cosa offriamo',
	);
	$out = '';
	foreach ( $blocks as $key => $title ) {
		$raw = trim( (string) get_post_meta( $id, $key, true ) );
		if ( '' === $raw ) {
			continue;
		}
		$lines = array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $raw ) ) ) );
		if ( ! $lines ) {
			continue;
		}
		$slug = 'tmk-job-' . sanitize_title( $title ) . '-' . $id;
		$out .= '<section class="tmk-job-section" aria-labelledby="' . esc_attr( $slug ) . '">';
		$out .= '<h2 class="tmk-section-title" id="' . esc_attr( $slug ) . '">' . esc_html( $title ) . '</h2>';
		if ( count( $lines ) > 1 ) {
			$out .= '<ul class="tmk-job-section__list">';
			foreach ( $lines as $line ) {
				$out .= '<li>' . esc_html( $line ) . '</li>';
			}
			$out .= '</ul>';
		} else {
			$out .= '<p class="tmk-job-section__text">' . esc_html( $lines[0] ) . '</p>';
		}
		$out .= '</section>';
	}
	// The application deadline is a fact about the opening, so it sits with the role, not with the
	// form. Absent when not set — an empty date must never print "Scadenza: ".
	$deadline = trim( (string) get_post_meta( $id, 'tmk_job_deadline', true ) );
	$ts       = $deadline ? strtotime( $deadline ) : 0;
	if ( $ts ) {
		$out .= '<p class="tmk-job-deadline">Candidature aperte fino al <b>' . esc_html( gmdate( 'd/m/Y', $ts ) ) . '</b>.</p>';
	}
	return $out;
}
add_shortcode( 'tmk_job_sections', 'tmk_render_job_sections' );

/**
 * [tmk_job_kicker] — "Ristoranti · Milano · Aqua · Full-time".
 *
 * Replaces the `post-info` widget the Job Detail template used, which read `job_where` as a raw
 * Post Custom Field: a job whose place was chosen from the restaurant list instead of typed as
 * free text printed nothing there. tmk_job_where() already derives city + restaurant from the
 * linked location; this simply lets the template use it.
 */
function tmk_render_job_kicker_block( $atts = array() ) {
	$atts = shortcode_atts( array( 'job_id' => 0 ), $atts, 'tmk_job_kicker' );
	$id   = (int) $atts['job_id'] ?: (int) get_the_ID();
	if ( ! $id || 'job' !== get_post_type( $id ) ) {
		return '';
	}
	$k = tmk_job_kicker( get_post( $id ) );
	return '' === $k ? '' : '<p class="tmk-job__kicker-line">' . esc_html( $k ) . '</p>';
}
add_shortcode( 'tmk_job_kicker', 'tmk_render_job_kicker_block' );

add_shortcode( 'tmk_job_form', function ( $atts ) {
	$atts = shortcode_atts( array( 'job_id' => 0 ), $atts, 'tmk_job_form' );
	return tmk_render_job_application_form( (int) $atts['job_id'] );
} );

/**
 * AJAX — validate, store the CV, store the application, notify.
 *
 * Order matters and is deliberate: the record is written BEFORE the email is attempted, and a
 * failed send is reported in the record rather than to the candidate. A real application must not
 * be lost because a mail transport is missing (see tmk_applications_recipient()).
 *
 * The CV becomes a real WordPress attachment so it is downloadable from the application's own
 * admin screen, but it is NOT attached to a public post and its record is private. Uploads go
 * through wp_handle_upload() with an explicit `mimes` allow-list, so the extension AND the sniffed
 * MIME must both match — an .exe renamed to .pdf is rejected by wp_check_filetype_and_ext(), which
 * that call performs internally. `test_form` is disabled because this is an AJAX POST, not a form
 * POST to the same URL wp_handle_upload expects; the nonce above is the real CSRF control.
 */
function tmk_handle_job_apply() {
	if ( ! check_ajax_referer( 'tmk_job_apply', 'tmk_apply_nonce', false ) ) {
		wp_send_json_error( array( 'message' => 'La pagina e\' rimasta aperta troppo a lungo. Ricaricala e riprova.' ), 403 );
	}
	// Honeypot. Answered with a success shape on purpose: a bot that is told it failed retries.
	if ( ! empty( $_POST['website'] ) ) {
		wp_send_json_success( array( 'message' => 'Grazie!' ) );
	}

	$job_id = isset( $_POST['job_id'] ) ? (int) $_POST['job_id'] : 0;
	if ( ! $job_id || 'job' !== get_post_type( $job_id ) || 'publish' !== get_post_status( $job_id ) ) {
		wp_send_json_error( array( 'message' => 'Questa posizione non e\' piu\' disponibile.' ), 400 );
	}

	$first = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
	$last  = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';
	$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
	$phone = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
	$cover = isset( $_POST['cover_letter'] ) ? sanitize_textarea_field( wp_unslash( $_POST['cover_letter'] ) ) : '';
	$notes = isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '';

	$errors = array();
	if ( '' === $first ) { $errors[] = 'il nome'; }
	if ( '' === $last )  { $errors[] = 'il cognome'; }
	if ( ! is_email( $email ) ) { $errors[] = 'un indirizzo email valido'; }
	if ( mb_strlen( $cover ) < 20 ) { $errors[] = 'una lettera di presentazione (almeno 20 caratteri)'; }
	if ( $errors ) {
		wp_send_json_error( array( 'message' => 'Manca ancora ' . implode( ', ', $errors ) . '.' ), 400 );
	}

	/*
	 * CONSENSO PRIVACY — ricontrollato lato server, non solo `required` nel markup.
	 * Un attributo HTML e' un suggerimento al browser: chiunque posti direttamente ad
	 * admin-ajax.php lo salta. Trattandosi di un CV e di una lettera di presentazione, cioe' dati
	 * personali, il consenso e' una condizione della richiesta e viene registrato con la data sul
	 * record insieme al testo esatto accettato (tmk_application_privacy_text), perche' un consenso
	 * senza la sua formulazione non e' dimostrabile.
	 */
	if ( empty( $_POST['privacy'] ) ) {
		wp_send_json_error( array( 'message' => 'Per inviare la candidatura serve il consenso al trattamento dei dati.' ), 400 );
	}

	$rules = tmk_cv_upload_rules();
	if ( empty( $_FILES['cv']['name'] ) || ! isset( $_FILES['cv']['error'] ) || UPLOAD_ERR_NO_FILE === $_FILES['cv']['error'] ) {
		wp_send_json_error( array( 'message' => 'Allega il tuo curriculum per completare la candidatura.' ), 400 );
	}
	if ( UPLOAD_ERR_OK !== (int) $_FILES['cv']['error'] ) {
		// INI_SIZE/FORM_SIZE are the two a candidate can actually act on; the rest are server-side.
		$msg = in_array( (int) $_FILES['cv']['error'], array( UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE ), true )
			? 'Il file e\' troppo grande. Il limite e\' ' . (int) round( $rules['max_bytes'] / 1048576 ) . ' MB.'
			: 'Non siamo riusciti a caricare il file. Riprova.';
		wp_send_json_error( array( 'message' => $msg ), 400 );
	}
	if ( (int) $_FILES['cv']['size'] > (int) $rules['max_bytes'] ) {
		wp_send_json_error( array( 'message' => 'Il curriculum supera ' . (int) round( $rules['max_bytes'] / 1048576 ) . ' MB. Comprimilo e riprova.' ), 400 );
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	/*
	 * The stored filename is randomised, and that is a privacy control, not tidiness.
	 * WordPress' uploads directory is served directly by the web server: an attachment marked
	 * `private` is hidden from the admin list and the REST API, but its FILE is still readable by
	 * anyone who knows the URL - there is no capability check in front of /wp-content/uploads/.
	 * A CV uploaded as "cv.pdf" or "mario-rossi-cv.pdf" therefore lands at a guessable address.
	 * A 32-hex name makes the URL unguessable, which is the strongest mitigation available from
	 * inside the plugin. It is NOT equivalent to access control - see the report: keeping
	 * candidate documents genuinely private needs the uploads path protected at the server, or
	 * the applications routed to an ATS, and that is a hosting decision.
	 */
	$upload = wp_handle_upload( $_FILES['cv'], array(
		'test_form' => false,
		'mimes'     => $rules['mimes'],
		'unique_filename_callback' => function ( $dir, $name, $ext ) {
			return wp_generate_password( 32, false, false ) . $ext;
		},
	) );
	if ( ! is_array( $upload ) || isset( $upload['error'] ) ) {
		wp_send_json_error( array( 'message' => 'Formato non accettato. Invia il CV in ' . implode( ', ', array_map( 'strtoupper', array_keys( $rules['mimes'] ) ) ) . '.' ), 400 );
	}

	$cv_id = wp_insert_attachment( array(
		'post_mime_type' => $upload['type'],
		'post_title'     => sprintf( 'CV %s %s', $first, $last ),
		'post_status'    => 'private',
	), $upload['file'] );
	if ( is_wp_error( $cv_id ) || ! $cv_id ) {
		wp_send_json_error( array( 'message' => 'Non siamo riusciti a salvare il curriculum. Riprova.' ), 500 );
	}
	wp_update_attachment_metadata( $cv_id, wp_generate_attachment_metadata( $cv_id, $upload['file'] ) );

	$job_title = get_the_title( $job_id );
	$app_id = wp_insert_post( array(
		'post_type'    => 'job_application',
		'post_status'  => 'private',
		'post_title'   => sprintf( '%s %s — %s', $first, $last, $job_title ),
		'post_content' => $cover . ( $notes ? "\n\n---\n" . $notes : '' ),
	), true );
	if ( is_wp_error( $app_id ) ) {
		wp_send_json_error( array( 'message' => 'Non siamo riusciti a registrare la candidatura. Riprova.' ), 500 );
	}

	update_post_meta( $app_id, 'tmk_application_job_id', $job_id );
	update_post_meta( $app_id, 'tmk_application_first_name', $first );
	update_post_meta( $app_id, 'tmk_application_last_name', $last );
	update_post_meta( $app_id, 'tmk_application_email', $email );
	update_post_meta( $app_id, 'tmk_application_phone', $phone );
	update_post_meta( $app_id, 'tmk_application_cover_letter', $cover );
	update_post_meta( $app_id, 'tmk_application_notes', $notes );
	update_post_meta( $app_id, 'tmk_application_cv_id', (int) $cv_id );
	// Il consenso, con la sua data e la formulazione esatta accettata.
	update_post_meta( $app_id, 'tmk_application_privacy_at', current_time( 'mysql' ) );
	update_post_meta( $app_id, 'tmk_application_privacy_text', wp_strip_all_tags( tmk_privacy_consent_text() ) );
	wp_update_post( array( 'ID' => (int) $cv_id, 'post_parent' => (int) $app_id ) );

	$to      = tmk_applications_recipient();
	$subject = sprintf( '[Temakinho] Nuova candidatura — %s', $job_title );
	$body    = implode( "\n", array(
		'Posizione: ' . $job_title,
		'Categoria: ' . ( tmk_job_area( $job_id ) ? tmk_job_area( $job_id )->name : '—' ),
		'Sede: ' . ( tmk_job_where( $job_id ) ?: '—' ),
		'',
		'Candidato: ' . $first . ' ' . $last,
		'Email: ' . $email,
		'Telefono: ' . ( $phone ?: '—' ),
		'',
		'Lettera di presentazione:',
		$cover,
		'',
		( $notes ? "Altre informazioni:\n" . $notes . "\n" : '' ),
		'CV: ' . wp_get_attachment_url( $cv_id ),
		'Scheda candidatura: ' . admin_url( 'post.php?post=' . (int) $app_id . '&action=edit' ),
	) );
	$sent = wp_mail( $to, $subject, $body, array( 'Reply-To: ' . $first . ' ' . $last . ' <' . $email . '>' ) );
	update_post_meta( $app_id, 'tmk_application_notified', $sent ? 1 : 0 );

	wp_send_json_success( array( 'message' => 'Grazie! Abbiamo ricevuto la tua candidatura.' ) );
}
add_action( 'wp_ajax_tmk_job_apply', 'tmk_handle_job_apply' );
add_action( 'wp_ajax_nopriv_tmk_job_apply', 'tmk_handle_job_apply' );

/**
 * Job Applications admin list — the columns a recruiter actually needs, and the CV as a link.
 *
 * Without this the list shows a title and a date: the email, the phone number and the CV are all
 * one click deeper, in a custom-field box, which makes triaging a batch of applications
 * needlessly slow. Read-only: nothing here can edit or create a record.
 */
add_filter( 'manage_job_application_posts_columns', function ( $cols ) {
	$new = array( 'cb' => $cols['cb'] ?? '', 'title' => 'Candidato' );
	$new['tmk_job']   = 'Posizione';
	$new['tmk_email'] = 'Contatti';
	$new['tmk_cv']    = 'CV';
	$new['date']      = $cols['date'] ?? 'Data';
	return $new;
} );
add_action( 'manage_job_application_posts_custom_column', function ( $col, $post_id ) {
	switch ( $col ) {
		case 'tmk_job':
			$job = (int) get_post_meta( $post_id, 'tmk_application_job_id', true );
			echo $job ? '<a href="' . esc_url( get_edit_post_link( $job ) ) . '">' . esc_html( get_the_title( $job ) ) . '</a>' : '&mdash;';
			break;
		case 'tmk_email':
			$mail  = (string) get_post_meta( $post_id, 'tmk_application_email', true );
			$phone = (string) get_post_meta( $post_id, 'tmk_application_phone', true );
			echo $mail ? '<a href="mailto:' . esc_attr( $mail ) . '">' . esc_html( $mail ) . '</a>' : '&mdash;';
			if ( $phone ) { echo '<br><span>' . esc_html( $phone ) . '</span>'; }
			break;
		case 'tmk_cv':
			$cv = (int) get_post_meta( $post_id, 'tmk_application_cv_id', true );
			$url = $cv ? wp_get_attachment_url( $cv ) : '';
			echo $url ? '<a href="' . esc_url( $url ) . '" download>Scarica</a>' : '&mdash;';
			break;
	}
}, 10, 2 );
