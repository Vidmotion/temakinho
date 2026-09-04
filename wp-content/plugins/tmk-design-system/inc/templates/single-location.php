<?php
/**
 * Full-width template for a single `location` post (the Restaurant Detail / master Location template).
 * Renders only the shell header/footer locations and the [tmk_location] assembler inside the page's
 * <main id="main"> landmark — no theme container, so the Hero is full-bleed and the shell (sticky
 * header, footer, page decor, overlays) comes from the same locations every page uses. Selected by
 * the template_include filter in inc/location-routing.php.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

get_header();
?>
<main id="main">
	<?php
	while ( have_posts() ) :
		the_post();
		echo do_shortcode( '[tmk_location]' );
	endwhile;
	?>
</main>
<?php
get_footer();
