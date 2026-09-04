<?php
/**
 * Timeline — the DS Timeline component (used by the Heritage page's "the meeting" spread). An ordered
 * list of narrative steps, each a marker + title + text, connected by a vertical rail. No JS.
 * Exposed via tmk_render_timeline(); composed in PHP (Elementor cannot nest widgets).
 *
 * @param array $steps Ordered list of array( 'marker' => '01', 'title' => '…', 'text' => '…' ).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function tmk_render_timeline( $steps = array() ) {
	$steps = array_values( array_filter( (array) $steps, function ( $s ) {
		return ! empty( $s['title'] ) || ! empty( $s['text'] );
	} ) );
	if ( ! $steps ) {
		return '';
	}
	ob_start();
	?>
	<ol class="tmk-timeline">
		<?php foreach ( $steps as $i => $step ) : ?>
			<li class="tmk-timeline__step">
				<span class="tmk-timeline__marker" aria-hidden="true"><?php echo esc_html( $step['marker'] ?? sprintf( '%02d', $i + 1 ) ); ?></span>
				<div class="tmk-timeline__content">
					<?php if ( ! empty( $step['title'] ) ) : ?><h3 class="tmk-timeline__title"><?php echo esc_html( $step['title'] ); ?></h3><?php endif; ?>
					<?php if ( ! empty( $step['text'] ) ) : ?><p class="tmk-timeline__text"><?php echo esc_html( $step['text'] ); ?></p><?php endif; ?>
				</div>
			</li>
		<?php endforeach; ?>
	</ol>
	<?php
	return ob_get_clean();
}
