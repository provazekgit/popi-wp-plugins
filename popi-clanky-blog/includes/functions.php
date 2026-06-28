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
function popi_clanky_get_field( string $selector, $post_id = false ) {
	return function_exists( 'get_field' ) ? get_field( $selector, $post_id ) : null;
}

// ── CTA URL ────────────────────────────────────────────────────────────────────

/**
 * Vrátí CTA URL s automaticky přidanými UTM parametry.
 * Použití v Bricks Code elementu: <?php echo popi_clanky_cta_url( get_the_ID() ); ?>
 */
function popi_clanky_cta_url( int $post_id = 0 ): string {
	if ( ! $post_id ) {
		$post_id = get_the_ID();
	}

	$base_url = popi_clanky_get_field( 'popi_clanek_cta_url', $post_id );
	if ( ! $base_url ) {
		return '';
	}

	$utm_source   = popi_clanky_get_field( 'popi_clanek_utm_source', $post_id );
	$utm_medium   = popi_clanky_get_field( 'popi_clanek_utm_medium', $post_id );
	$utm_campaign = popi_clanky_get_field( 'popi_clanek_utm_kampan', $post_id );

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

// ── BRICKS DYNAMIC TAG ────────────────────────────────────────────────────────

add_filter( 'bricks/dynamic_tags_list', function ( $tags ) {
	$tags[] = array(
		'name'  => '{popi_clanek_cta_url_utm}',
		'label' => 'Článek CTA URL s UTM (popi-clanky-blog)',
		'group' => 'Popi',
	);
	return $tags;
} );

add_filter( 'bricks/dynamic_data/render_tag', function ( $tag, $post, $context ) {
	$clean = trim( str_replace( array( '{', '}' ), '', $tag ) );
	if ( $clean === 'popi_clanek_cta_url_utm' ) {
		$post_id = is_object( $post ) && ! empty( $post->ID ) ? (int) $post->ID : get_the_ID();
		return popi_clanky_cta_url( $post_id );
	}
	return $tag;
}, 10, 3 );

// ── TABLE OF CONTENTS ──────────────────────────────────────────────────────────

/**
 * Přidá id="" na H2 a H3 nadpisy v obsahu článku popi_clanky.
 * Nutné aby kotvy v TOC navigaci fungovaly.
 */
add_filter( 'the_content', function ( string $content ): string {
	if ( ! is_singular( 'popi_clanky' ) ) {
		return $content;
	}
	return preg_replace_callback(
		'/<h([23])([^>]*)>(.*?)<\/h[23]>/is',
		function ( array $m ): string {
			if ( strpos( $m[2], 'id=' ) !== false ) {
				return $m[0];
			}
			$id = popi_clanky_heading_id( wp_strip_all_tags( $m[3] ) );
			return sprintf( '<h%1$d%2$s id="%3$s">%4$s</h%1$d>', $m[1], $m[2], esc_attr( $id ), $m[3] );
		},
		$content
	);
} );

/**
 * Vygeneruje HTML navigaci (obsah článku) ze všech H2 a H3 nadpisů.
 * Vrátí prázdný řetězec pokud článek nemá žádné nadpisy.
 *
 * Použití v Bricks Code elementu:
 *   <?php echo popi_clanky_toc( get_the_ID() ); ?>
 */
function popi_clanky_toc( int $post_id = 0 ): string {
	if ( ! $post_id ) {
		$post_id = get_the_ID();
	}

	$post = get_post( $post_id );
	if ( ! $post ) {
		return '';
	}

	$content = apply_filters( 'the_content', $post->post_content );

	preg_match_all( '/<h([23])[^>]*>(.*?)<\/h[23]>/is', $content, $matches, PREG_SET_ORDER );

	if ( empty( $matches ) ) {
		return '';
	}

	$items = '';
	foreach ( $matches as $m ) {
		$level = (int) $m[1];
		$text  = wp_strip_all_tags( $m[2] );
		$id    = popi_clanky_heading_id( $text );
		$items .= sprintf(
			'<li class="toc-h%d"><a href="#%s">%s</a></li>' . "\n",
			$level,
			esc_attr( $id ),
			esc_html( $text )
		);
	}

	return '<nav class="popi-toc" aria-label="Obsah článku"><ul>' . "\n" . $items . '</ul></nav>';
}

/**
 * Převede text nadpisu na URL-friendly ID (podporuje českou diakritiku).
 */
function popi_clanky_heading_id( string $text ): string {
	$map = array(
		'á' => 'a', 'č' => 'c', 'ď' => 'd', 'é' => 'e', 'ě' => 'e',
		'í' => 'i', 'ň' => 'n', 'ó' => 'o', 'ř' => 'r', 'š' => 's',
		'ť' => 't', 'ú' => 'u', 'ů' => 'u', 'ý' => 'y', 'ž' => 'z',
		'Á' => 'a', 'Č' => 'c', 'Ď' => 'd', 'É' => 'e', 'Ě' => 'e',
		'Í' => 'i', 'Ň' => 'n', 'Ó' => 'o', 'Ř' => 'r', 'Š' => 's',
		'Ť' => 't', 'Ú' => 'u', 'Ů' => 'u', 'Ý' => 'y', 'Ž' => 'z',
	);
	$text = strtr( $text, $map );
	$text = strtolower( $text );
	$text = preg_replace( '/[^a-z0-9]+/', '-', $text );
	return trim( $text, '-' );
}
