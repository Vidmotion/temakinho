<?php
/** Full-width, shell-wrapped template for an on-site legal page (meta _tmk_template=legal). Renders
 *  the legal reading layout via tmk_render_legal_page(). Selected by inc/pages-routing.php. */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();
?>
<main id="main">
	<?php
	while ( have_posts() ) :
		the_post();
		echo tmk_render_legal_page( get_post() );
	endwhile;
	?>
</main>
<?php
get_footer();
