<?php
/**
 * Contact — direct translation of pages/Contact.jsx (welcoming, not bureaucratic): a warm cream
 * header (no photo hero), then a two-column body of an IntentForm + a "how to reach us" aside.
 * Mounted on the /contattaci Page via the generic full-width template.
 *
 * CONTENT is migrated from the live temakinho.com contact page (the real site publishes a form +
 * restaurant routing, its socials and the company P.IVA, and routes bookings/orders to the real
 * systems — no public email/phone), so the aside lists the real channels rather than inventing
 * addresses. The form is first-party (stored as a contact_message record, the CRM placeholder).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/** The 3-path IntentForm (Contact.jsx IntentForm). Reuses the shared AJAX form infra (forms.js). */
function tmk_render_intent_form( $intents = array() ) {
	$intents = $intents ? $intents : array( 'Informazioni generali', 'Eventi privati', 'Stampa' );
	$uid = 'tmk-contact-' . wp_unique_id();
	ob_start();
	?>
	<form class="tmk-form" data-tmk-ajax-form action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" method="post">
		<input type="hidden" name="action" value="tmk_contact_submit" />
		<input type="hidden" name="tmk_contact_nonce" value="<?php echo esc_attr( wp_create_nonce( 'tmk_contact_submit' ) ); ?>" />
		<fieldset class="tmk-form__intents">
			<legend>Di cosa vuoi parlarci?</legend>
			<?php foreach ( $intents as $i => $intent ) : ?>
				<label class="tmk-form__chip">
					<input type="radio" name="intent" value="<?php echo esc_attr( $intent ); ?>" <?php checked( 0, $i ); ?> />
					<span><?php echo esc_html( $intent ); ?></span>
				</label>
			<?php endforeach; ?>
		</fieldset>
		<div class="tmk-form__field">
			<label class="tmk-form__label" for="<?php echo esc_attr( $uid ); ?>-name">Nome <span class="tmk-form__req" aria-hidden="true">*</span></label>
			<input class="tmk-form__input" id="<?php echo esc_attr( $uid ); ?>-name" name="name" type="text" required />
		</div>
		<div class="tmk-form__field">
			<label class="tmk-form__label" for="<?php echo esc_attr( $uid ); ?>-email">Email <span class="tmk-form__req" aria-hidden="true">*</span></label>
			<input class="tmk-form__input" id="<?php echo esc_attr( $uid ); ?>-email" name="email" type="email" required />
		</div>
		<div class="tmk-form__field">
			<label class="tmk-form__label" for="<?php echo esc_attr( $uid ); ?>-msg">Messaggio <span class="tmk-form__req" aria-hidden="true">*</span></label>
			<textarea class="tmk-form__textarea" id="<?php echo esc_attr( $uid ); ?>-msg" name="message" required></textarea>
		</div>
		<button type="submit" class="tmk-cta tmk-cta--primary tmk-form__submit">Invia</button>
		<p class="tmk-form__msg tmk-form__msg--success" role="status" data-tmk-form-success hidden tabindex="-1">Grazie! Ti rispondiamo entro un giorno.</p>
		<p class="tmk-form__msg tmk-form__msg--error" role="alert" data-tmk-form-error hidden>Qualcosa è andato storto. Controlla i campi e riprova.</p>
	</form>
	<?php
	return ob_get_clean();
}

function tmk_render_contact() {
	$booking = defined( 'TMK_BOOKING_URL' ) ? TMK_BOOKING_URL : home_url( '/#prenota' );
	$order   = defined( 'TMK_ORDER_URL' ) ? TMK_ORDER_URL : home_url( '/#ordina' );
	$channels = array(
		array( 'label' => 'Prenotazioni', 'value' => 'Prenota online, in meno di un minuto', 'href' => $booking ),
		array( 'label' => 'Ordina online', 'value' => 'La stessa cucina, a casa tua', 'href' => $order ),
	);
	$social = array(
		array( 'label' => 'Instagram', 'href' => 'https://instagram.com/temakinho/' ),
		array( 'label' => 'Facebook', 'href' => 'https://facebook.com/temakinho' ),
	);

	ob_start();
	?>
	<div class="tmk-contact">
		<?php echo tmk_render_breadcrumb( array(
			array( 'label' => 'Home', 'href' => home_url( '/' ) ),
			array( 'label' => 'Scrivici' ),
		) ); ?>

		<header class="tmk-contact__header">
			<p class="tmk-contact__eyebrow">Scrivici</p>
			<h1 class="tmk-contact__title">Parliamone a tavola</h1>
			<p class="tmk-contact__lead">Una domanda, una festa da organizzare, due righe per la stampa: siamo qui. Ti rispondiamo entro un giorno.</p>
		</header>

		<div class="tmk-contact__grid">
			<section class="tmk-contact__form" aria-label="Modulo di contatto">
				<?php echo tmk_render_intent_form(); ?>
			</section>

			<aside class="tmk-contact__aside" aria-label="Come raggiungerci">
				<h2 class="tmk-contact__aside-title">Come raggiungerci</h2>
				<dl class="tmk-contact__channels">
					<?php foreach ( $channels as $c ) : ?>
						<div>
							<dt><?php echo esc_html( $c['label'] ); ?></dt>
							<dd><a href="<?php echo esc_url( $c['href'] ); ?>"><?php echo esc_html( $c['value'] ); ?></a></dd>
						</div>
					<?php endforeach; ?>
				</dl>
				<div class="tmk-contact__social">
					<?php foreach ( $social as $s ) : ?>
						<a href="<?php echo esc_url( $s['href'] ); ?>" rel="noopener"><?php echo esc_html( $s['label'] ); ?></a>
					<?php endforeach; ?>
				</div>
				<p class="tmk-contact__note">Per prenotare un tavolo è più veloce il sistema di prenotazione online — questo modulo è per tutto il resto.</p>
			</aside>
		</div>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'tmk_contact', 'tmk_render_contact' );

/** AJAX — store a contact message as a private record (CRM placeholder, cf. newsletter). */
function tmk_handle_contact_submit() {
	check_ajax_referer( 'tmk_contact_submit', 'tmk_contact_nonce' );
	$name   = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
	$email  = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
	$msg    = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
	$intent = isset( $_POST['intent'] ) ? sanitize_text_field( wp_unslash( $_POST['intent'] ) ) : '';
	if ( '' === $name || ! is_email( $email ) || '' === $msg ) {
		wp_send_json_error( array( 'message' => 'Controlla i campi obbligatori e riprova.' ), 400 );
	}
	wp_insert_post( array(
		'post_type'    => 'contact_message',
		'post_status'  => 'private',
		'post_title'   => $name . ( $intent ? ' — ' . $intent : '' ),
		'post_content' => "Email: {$email}\nMotivo: {$intent}\n\n{$msg}",
	) );
	wp_send_json_success( array( 'message' => 'Grazie! Ti rispondiamo entro un giorno.' ) );
}
add_action( 'wp_ajax_tmk_contact_submit', 'tmk_handle_contact_submit' );
add_action( 'wp_ajax_nopriv_tmk_contact_submit', 'tmk_handle_contact_submit' );
