<?php
/**
 * Breadcrumb - direct translation of components/navigation/Breadcrumb.jsx.
 *
 * "Derived hierarchy trail - Home -> hub -> current. Never hand-authored." (the component's own
 * docblock). So the PHP API takes a trail ARRAY and the callers derive it from real WordPress
 * hierarchy; the [tmk_breadcrumb] shortcode exists only for the hand-authored case Elementor
 * needs, and is deliberately awkward to use for anything longer than a short static trail.
 *
 * Beyond the JSX: a BreadcrumbList JSON-LD graph is emitted alongside the markup. The trail is
 * already a machine-readable hierarchy at this point, so publishing it costs nothing and is the
 * same treatment the Menu Hub gives its own structured data (PROJECT_CONTEXT 19.6). Suppressible
 * with schema="0" for any instance that is decorative rather than a real position in the site.
 *
 * @param array $trail Ordered list of array( 'label' => string, 'href' => string ). The LAST
 *                      item is the current page and is rendered as text, never a link, whatever
 *                      href it carries.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function tmk_render_breadcrumb( $trail = array(), $schema = true ) {
	$trail = array_values( array_filter( $trail, function ( $item ) {
		return ! empty( $item['label'] );
	} ) );
	if ( count( $trail ) < 2 ) {
		return '';
	}
	$last = count( $trail ) - 1;

	ob_start();
	?>
	<nav aria-label="Breadcrumb" class="tmk-breadcrumb">
		<ol class="tmk-breadcrumb__list">
			<?php foreach ( $trail as $i => $item ) : ?>
				<li class="tmk-breadcrumb__item">
					<?php if ( $i === $last || empty( $item['href'] ) ) : ?>
						<span class="tmk-breadcrumb__current" aria-current="page"><?php echo esc_html( $item['label'] ); ?></span>
					<?php else : ?>
						<a href="<?php echo esc_url( $item['href'] ); ?>"><?php echo esc_html( $item['label'] ); ?></a>
					<?php endif; ?>
					<?php if ( $i !== $last ) : ?><span class="tmk-breadcrumb__sep" aria-hidden="true">/</span><?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ol>
	</nav>
	<?php
	if ( $schema ) {
		$items = array();
		foreach ( $trail as $i => $item ) {
			$entry = array(
				'@type'    => 'ListItem',
				'position' => $i + 1,
				'name'     => $item['label'],
			);
			if ( ! empty( $item['href'] ) ) {
				$entry['item'] = esc_url_raw( $item['href'] );
			}
			$items[] = $entry;
		}
		printf(
			'<script type="application/ld+json">%s</script>',
			wp_json_encode( array( '@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items ) )
		);
	}

	return ob_get_clean();
}

/**
 * [tmk_breadcrumb trail="Home|/, Ristoranti|/ristoranti/, Rimini"]
 * Comma-separated crumbs, each "Label|href"; the href is optional on the last one.
 */
add_shortcode( 'tmk_breadcrumb', function ( $atts ) {
	$atts  = shortcode_atts( array( 'trail' => '', 'schema' => '1' ), $atts );
	$trail = array();
	foreach ( array_filter( array_map( 'trim', explode( ',', $atts['trail'] ) ) ) as $crumb ) {
		$parts   = array_map( 'trim', explode( '|', $crumb, 2 ) );
		$trail[] = array( 'label' => $parts[0], 'href' => isset( $parts[1] ) ? $parts[1] : '' );
	}
	return tmk_render_breadcrumb( $trail, '1' === (string) $atts['schema'] );
} );
