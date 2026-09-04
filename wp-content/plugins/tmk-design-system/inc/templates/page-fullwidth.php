<?php
/**
 * Generic full-width, shell-wrapped template for TMK content Pages (Heritage, Contact, Shop, …).
 * A page opts in with the meta `_tmk_template = fullwidth` (set at seed time); its own content is a
 * single TMK shortcode, rendered here inside <main id="main"> with no theme container so heroes are
 * full-bleed. Selected by inc/pages-routing.php. Keeps Hello Elementor untouched, same as the
 * Location/City templates — one generic template instead of one per static page.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

get_header();
?>
<main id="main">
	<?php
	while ( have_posts() ) :
		the_post();
		echo do_shortcode( get_the_content() );
	endwhile;
	?>
</main>
<?php
get_footer();
