<?php
/**
 * Full-width template for a `city` taxonomy term archive (the master City / Collection template).
 * Renders only the shell header/footer locations and the [tmk_city] assembler inside the page's
 * <main id="main"> landmark — no theme container, so the Hero is full-bleed and the shell (sticky
 * header, footer, page decor, overlays) comes from the same locations every page uses. Selected by
 * the template_include filter in inc/city-routing.php. Mirrors inc/templates/single-location.php.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

get_header();
?>
<main id="main">
	<?php echo do_shortcode( '[tmk_city]' ); ?>
</main>
<?php
get_footer();
