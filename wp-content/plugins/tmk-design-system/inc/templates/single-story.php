<?php
/**
 * Full-width template for a single editorial article (native `post`, /storie/{slug}/). Renders the
 * shell header/footer + the [tmk_article] assembler inside <main id="main">. Selected by the
 * template_include filter in inc/story-routing.php.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

get_header();
?>
<main id="main">
	<?php
	while ( have_posts() ) :
		the_post();
		echo do_shortcode( '[tmk_article]' );
	endwhile;
	?>
</main>
<?php
get_footer();
