<?php
/**
 * Full-width template for the Story Hub Page (/storie/). Renders only the shell header/footer
 * locations and the [tmk_story_hub] assembler inside <main id="main">. Selected by the
 * template_include filter in inc/story-routing.php. Mirrors inc/templates/single-location.php.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

get_header();
?>
<main id="main">
	<?php echo do_shortcode( '[tmk_story_hub]' ); ?>
</main>
<?php
get_footer();
