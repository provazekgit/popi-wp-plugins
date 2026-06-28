<?php
defined( 'ABSPATH' ) || exit;

/**
 * Bezpecny wrapper kolem ACF get_field() — bez nej by kterekoliv volani
 * get_field() spadlo s "Call to undefined function", pokud ACF neni na
 * webu aktivni. Misto Fatal Error vrati null — pole se jen nezobrazi.
 *
 * @param string   $selector
 * @param int|bool $post_id
 * @return mixed
 */
function popi_lp_get_field( string $selector, $post_id = false ) {
	return function_exists( 'get_field' ) ? get_field( $selector, $post_id ) : null;
}

/**
 * Vrátí CTA URL s automaticky přidanými UTM parametry.
 *
 * UTM source a medium se čtou z nastavení pluginu (Landing Pages → Výchozí hodnoty).
 * UTM campaign se čte z ACF pole `popi_lp_utm_kampan` na konkrétní LP.
 *
 * Použití v šabloně Bricks (PHP snippet nebo Custom Code element):
 *   echo popi_lp_cta_url();
 *
 * Nebo jako Dynamic Data tag přes Bricks Code element:
 *   <?php echo popi_lp_cta_url( get_the_ID() ); ?>
 */
// ── BRICKS DYNAMIC TAG ────────────────────────────────────────────────────────

add_filter( 'bricks/dynamic_tags_list', function ( $tags ) {
	$tags[] = array(
		'name'  => '{popi_lp_cta_url_utm}',
		'label' => 'LP CTA URL s UTM (popi-landing-page)',
		'group' => 'Popi',
	);
	return $tags;
} );

add_filter( 'bricks/dynamic_data/render_tag', function ( $tag, $post, $context ) {
	$clean = trim( str_replace( array( '{', '}' ), '', $tag ) );
	if ( $clean === 'popi_lp_cta_url_utm' ) {
		$post_id = is_object( $post ) && ! empty( $post->ID ) ? (int) $post->ID : get_the_ID();
		return popi_lp_cta_url( $post_id );
	}
	return $tag;
}, 10, 3 );

// ── CTA URL ────────────────────────────────────────────────────────────────────

function popi_lp_cta_url( int $post_id = 0 ): string {
	if ( ! $post_id ) {
		$post_id = get_the_ID();
	}

	$base_url = popi_lp_get_field( 'popi_lp_cta_url', $post_id );
	if ( ! $base_url ) {
		return '';
	}

	$utm_source   = popi_lp_get_field( 'popi_lp_utm_source', $post_id );
	$utm_medium   = popi_lp_get_field( 'popi_lp_utm_medium', $post_id );
	$utm_campaign = popi_lp_get_field( 'popi_lp_utm_kampan', $post_id );

	$params = array_filter( array(
		'utm_source'   => $utm_source,
		'utm_medium'   => $utm_medium,
		'utm_campaign' => $utm_campaign,
	) );

	if ( empty( $params ) ) {
		return $base_url;
	}

	$separator = strpos( $base_url, '?' ) !== false ? '&' : '?';
	return $base_url . $separator . http_build_query( $params );
}

// ── CENÍK ──────────────────────────────────────────────────────────────────────

/**
 * Vrati vyplnene ceny LP jako asociativni pole label => cena.
 * Prazdne kategorie (nevyplnene na teto LP) se vynechaji.
 */
function popi_lp_pricing( int $post_id = 0 ): array {
	if ( ! $post_id ) {
		$post_id = get_the_ID();
	}

	$categories = array(
		'popi_lp_price_individual' => 'Jednotlivec',
		'popi_lp_price_child'      => 'Dítě',
		'popi_lp_price_family'     => 'Rodina',
		'popi_lp_price_senior'     => 'Senior',
		'popi_lp_price_group'      => 'Skupina',
	);

	$pricing = array();
	foreach ( $categories as $field => $label ) {
		$value = popi_lp_get_field( $field, $post_id );
		if ( $value ) {
			$pricing[ $label ] = $value;
		}
	}

	return $pricing;
}

/**
 * [popi_lp_pricing] — jednoducha HTML tabulka vyplnenych cen. Bez stylovani,
 * jen struktura (.popi-pricing-table) — vzhled si dodelej v Bricks/CSS.
 */
add_shortcode( 'popi_lp_pricing', function ( $atts ) {
	$atts    = shortcode_atts( array( 'id' => 0 ), $atts );
	$post_id = (int) $atts['id'] ?: get_the_ID();
	$pricing = popi_lp_pricing( $post_id );

	if ( ! $pricing ) {
		return '';
	}

	ob_start();
	?>
	<table class="popi-pricing-table">
		<tbody>
			<?php foreach ( $pricing as $label => $price ) : ?>
				<tr>
					<th><?php echo esc_html( $label ); ?></th>
					<td><?php echo esc_html( $price ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php
	return (string) ob_get_clean();
} );
