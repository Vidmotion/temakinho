<?php
/** Full-width template for the Event Hub (/eventi/ archive). Renders [tmk_event_hub] inside the
 *  shell. Selected by inc/event-routing.php. Mirrors inc/templates/single-location.php. */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();
?>
<main id="main">
	<?php echo do_shortcode( '[tmk_event_hub]' ); ?>
</main>
<?php
get_footer();
