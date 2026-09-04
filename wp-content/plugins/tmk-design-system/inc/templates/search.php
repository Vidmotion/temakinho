<?php
/** Full-width, shell-wrapped search-results template. Renders [tmk_search] inside the shell.
 *  Selected by inc/system-routing.php. */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();
?>
<main id="main">
	<?php echo do_shortcode( '[tmk_search]' ); ?>
</main>
<?php
get_footer();
