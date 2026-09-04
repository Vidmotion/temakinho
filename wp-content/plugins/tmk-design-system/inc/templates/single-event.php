<?php
/** Full-width template for a single Event (/eventi/{slug}/). Renders [tmk_event] inside the shell.
 *  Selected by inc/event-routing.php. */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();
?>
<main id="main">
	<?php
	while ( have_posts() ) :
		the_post();
		echo do_shortcode( '[tmk_event]' );
	endwhile;
	?>
</main>
<?php
get_footer();
