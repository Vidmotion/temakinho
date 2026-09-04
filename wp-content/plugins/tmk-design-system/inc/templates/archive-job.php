<?php
/** Full-width template for the Careers Hub (/lavora-con-noi/ archive). Renders [tmk_careers_hub]. */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();
?>
<main id="main">
	<?php echo do_shortcode( '[tmk_careers_hub]' ); ?>
</main>
<?php
get_footer();
