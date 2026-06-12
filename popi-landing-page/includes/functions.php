<?php
defined( 'ABSPATH' ) || exit;

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

	$base_url = get_field( 'popi_lp_cta_url', $post_id );
	if ( ! $base_url ) {
		return '';
	}

	$utm_source   = get_field( 'popi_lp_utm_source', $post_id );
	$utm_medium   = get_field( 'popi_lp_utm_medium', $post_id );
	$utm_campaign = get_field( 'popi_lp_utm_kampan', $post_id );

	$params = array_filter( array(
		'utm_source'   => $utm_source,
		'utm_medium'   => $utm_medium,
		'utm_campaign' => $utm_campaign,
	) );

	if ( empty( $params ) ) {
		return $base_url;
	}

	$separator = str_contains( $base_url, '?' ) ? '&' : '?';
	return $base_url . $separator . http_build_query( $params );
}
