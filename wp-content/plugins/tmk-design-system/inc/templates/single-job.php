<?php
/** Full-width template for a single Job (/lavora-con-noi/{slug}/). Renders [tmk_job]. */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();
?>
<main id="main">
	<?php
	while ( have_posts() ) :
		the_post();
		echo do_shortcode( '[tmk_job]' );
	endwhile;
	?>
</main>
<?php
get_footer();
