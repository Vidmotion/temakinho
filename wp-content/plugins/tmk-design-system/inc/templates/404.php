<?php
/** Full-width, shell-wrapped 404 template. Renders [tmk_404] inside the shell. Selected by
 *  inc/system-routing.php; WordPress has already set the 404 status. */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();
?>
<main id="main">
	<?php echo do_shortcode( '[tmk_404]' ); ?>
</main>
<?php
get_footer();
