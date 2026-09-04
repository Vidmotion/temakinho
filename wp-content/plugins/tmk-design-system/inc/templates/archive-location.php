<?php
/**
 * Full-width template for the `location` post-type archive (/ristoranti/) — the Restaurants index.
 * Same shape and same contract as inc/templates/taxonomy-city.php: shell header/footer locations,
 * the assembler inside the page's <main id="main"> landmark, and no theme container, so the Hero
 * renders correctly. Selected by the template_include filter in inc/city-routing.php.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

get_header();
?>
<main id="main">
	<?php echo do_shortcode( '[tmk_restaurants_index]' ); ?>
</main>
<?php
get_footer();
