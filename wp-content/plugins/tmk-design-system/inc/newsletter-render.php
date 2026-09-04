<?php
/**
 * Newsletter Capture — direct translation of components/conversion/NewsletterCapture.jsx
 * (variants: inline/footer) and components/forms/NewsletterForm.jsx (optional city preference).
 * Shared: used inside the global Footer and available standalone via [tmk_newsletter] wherever
 * a page needs its own capture block (Component Inventory lists it as reusable, not footer-only).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function tmk_render_newsletter_capture( $atts = array() ) {
	$atts = shortcode_atts( array(
		'variant' => 'inline', // inline | footer
		'title' => 'Resta nel nostro mondo',
		'copy' => 'Una mail al mese: storie, aperture, inviti. La tua email non lascia la nostra tavola.',
		'city_preference' => false,
	), $atts );

	$dark = ( 'footer' === $atts['variant'] );
	$uid = 'tmk-newsletter-' . wp_unique_id();

	ob_start();
	?>
	<div class="tmk-newsletter<?php echo $dark ? ' tmk-newsletter--footer' : ''; ?>" data-tmk-newsletter>
		<?php if ( 'inline' === $atts['variant'] ) : ?>
			<h3 class="tmk-newsletter__title"><?php echo esc_html( $atts['title'] ); ?></h3>
			<p class="tmk-newsletter__copy"><?php echo esc_html( $atts['copy'] ); ?></p>
		<?php endif; ?>

		<?php if ( $atts['city_preference'] ) :
			$cities = wp_list_pluck( get_terms( array( 'taxonomy' => 'city', 'hide_empty' => false, 'orderby' => 'name' ) ), 'name' ); ?>
			<label class="tmk-newsletter-form__label">
				<span>La tua città</span>
				<select data-tmk-newsletter-city>
					<option value="">Tutte le città</option>
					<?php foreach ( $cities as $city ) : ?>
						<option value="<?php echo esc_attr( $city ); ?>"><?php echo esc_html( $city ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
		<?php endif; ?>

		<form class="tmk-newsletter__form" data-tmk-newsletter-form action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" method="post">
			<input type="hidden" name="action" value="tmk_newsletter_subscribe" />
			<?php /*
			 * Hand-rolled instead of wp_nonce_field(), which gives the hidden input an id equal to
			 * its name. NewsletterCapture renders twice on the Homepage — once as its own section,
			 * once inside the Footer — so that id was duplicated in the document (Session 6
			 * accessibility review). The instance-unique $uid already used by the email input is
			 * reused here. The referer field wp_nonce_field() would also add is dropped: the AJAX
			 * handler validates with check_ajax_referer() on this field alone.
			 */ ?>
			<input type="hidden" id="<?php echo esc_attr( $uid ); ?>-nonce" name="tmk_newsletter_nonce" value="<?php echo esc_attr( wp_create_nonce( 'tmk_newsletter_subscribe' ) ); ?>" />
			<label class="tmk-newsletter__label" for="<?php echo esc_attr( $uid ); ?>">
				<span class="tmk-newsletter__sr-label">La tua email</span>
				<input type="email" required placeholder="La tua email" id="<?php echo esc_attr( $uid ); ?>" name="email" class="tmk-newsletter__input" data-tmk-newsletter-input />
			</label>
			<button type="submit" class="tmk-newsletter__submit" data-tmk-newsletter-submit><?php esc_html_e( 'Iscriviti', 'tmk-design-system' ); ?></button>
		</form>
		<p role="status" class="tmk-newsletter__msg tmk-newsletter__msg--success" data-tmk-newsletter-success hidden>Benvenuto. La prima storia arriva a inizio mese.</p>
		<p role="alert" class="tmk-newsletter__msg tmk-newsletter__msg--error" data-tmk-newsletter-error hidden>Controlla il formato dell'email e riprova.</p>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'tmk_newsletter', function ( $atts ) {
	$atts = (array) $atts;
	if ( isset( $atts['city_preference'] ) ) $atts['city_preference'] = filter_var( $atts['city_preference'], FILTER_VALIDATE_BOOLEAN );
	return tmk_render_newsletter_capture( $atts );
} );

function tmk_handle_newsletter_subscribe() {
	check_ajax_referer( 'tmk_newsletter_subscribe', 'tmk_newsletter_nonce' );

	$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
	if ( ! is_email( $email ) ) {
		wp_send_json_error( array( 'message' => "Controlla il formato dell'email e riprova." ), 400 );
	}

	$city = isset( $_POST['city'] ) ? sanitize_text_field( wp_unslash( $_POST['city'] ) ) : '';

	$existing = get_posts( array(
		'post_type' => 'newsletter_sub',
		'title' => $email,
		'posts_per_page' => 1,
		'post_status' => 'private',
	) );
	if ( empty( $existing ) ) {
		$post_id = wp_insert_post( array(
			'post_type' => 'newsletter_sub',
			'post_title' => $email,
			'post_status' => 'private',
		), true );
		if ( ! is_wp_error( $post_id ) && $city ) {
			update_post_meta( $post_id, 'city_preference', $city );
		}
	}

	wp_send_json_success( array( 'message' => 'Benvenuto. La prima storia arriva a inizio mese.' ) );
}
add_action( 'wp_ajax_tmk_newsletter_subscribe', 'tmk_handle_newsletter_subscribe' );
add_action( 'wp_ajax_nopriv_tmk_newsletter_subscribe', 'tmk_handle_newsletter_subscribe' );
