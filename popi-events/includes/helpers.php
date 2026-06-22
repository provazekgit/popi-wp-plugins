<?php

defined( 'ABSPATH' ) || exit;

/**
 * Globalni helper funkce — bez namespace, aby se daly snadno volat
 * z Bricks Code elementu nebo sablony tematu, stejne jako u sourozeneckych
 * pluginu (popi_lp_cta_url, popi_clanky_toc...).
 */

/**
 * Formatuje datum 'Y-m-d' na cesky tvar "12. 6. 2026".
 */
function popi_events_format_date( string $date_start ): string {
	$timestamp = strtotime( $date_start );
	if ( ! $timestamp ) {
		return $date_start;
	}
	return date_i18n( 'j. n. Y', $timestamp );
}

/**
 * Vrati terminy konkretniho kurzu, serazene podle data od nejblizsiho.
 *
 * @return WP_Post[]
 */
function popi_events_get_course_events( int $course_id, bool $upcoming_only = false ): array {
	$meta_query = array(
		array(
			'key'   => 'popi_event_course_id',
			'value' => $course_id,
		),
	);

	if ( $upcoming_only ) {
		$meta_query[] = array(
			'key'     => 'popi_event_date_start',
			'value'   => current_time( 'Y-m-d' ),
			'compare' => '>=',
			'type'    => 'DATE',
		);
	}

	return get_posts( array(
		'post_type'      => 'event',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'meta_key'       => 'popi_event_date_start',
		'orderby'        => 'meta_value',
		'order'          => 'ASC',
		'meta_query'     => $meta_query,
	) );
}

/**
 * Vrati vsechny terminy (libovolneho kurzu) v rozsahu nasledujicich $days dnu.
 *
 * @return WP_Post[]
 */
function popi_events_get_upcoming_events( int $days = 30 ): array {
	$today = current_time( 'Y-m-d' );
	$until = date( 'Y-m-d', strtotime( "+{$days} days", current_time( 'timestamp' ) ) );

	return get_posts( array(
		'post_type'      => 'event',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'meta_key'       => 'popi_event_date_start',
		'orderby'        => 'meta_value',
		'order'          => 'ASC',
		'meta_query'     => array(
			array(
				'key'     => 'popi_event_date_start',
				'value'   => array( $today, $until ),
				'compare' => 'BETWEEN',
				'type'    => 'DATE',
			),
		),
	) );
}

/**
 * Vrati terminy v danem mesici (pro kalendarni grid).
 *
 * @param string $year_month formát "Y-m", napr. "2026-06"
 * @return WP_Post[]
 */
function popi_events_get_month_events( string $year_month ): array {
	$start = $year_month . '-01';
	$end   = date( 'Y-m-t', strtotime( $start ) );

	return get_posts( array(
		'post_type'      => 'event',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'meta_key'       => 'popi_event_date_start',
		'orderby'        => 'meta_value',
		'order'          => 'ASC',
		'meta_query'     => array(
			array(
				'key'     => 'popi_event_date_start',
				'value'   => array( $start, $end ),
				'compare' => 'BETWEEN',
				'type'    => 'DATE',
			),
		),
	) );
}

// ── CTA URL (kurz) ───────────────────────────────────────────────────────────

/**
 * Vrati CTA URL kurzu s automaticky pridanymi UTM parametry (pro GA4).
 *
 * UTM source/medium se ctou z poli kurzu (nebo z vychozich hodnot v Nastaveni),
 * UTM campaign z pole popi_course_utm_kampan.
 *
 * Pouziti v Bricks: Dynamicka data -> skupina Popi -> Kurz CTA URL s UTM
 * (tag {popi_course_cta_url_utm}), nebo primo: echo popi_events_course_cta_url( get_the_ID() );
 */
function popi_events_course_cta_url( int $course_id = 0 ): string {
	if ( ! $course_id ) {
		$course_id = get_the_ID();
	}

	$base_url = get_field( 'popi_course_cta_url', $course_id );
	if ( ! $base_url ) {
		return '';
	}

	$params = array_filter( array(
		'utm_source'   => get_field( 'popi_course_utm_source', $course_id ),
		'utm_medium'   => get_field( 'popi_course_utm_medium', $course_id ),
		'utm_campaign' => get_field( 'popi_course_utm_kampan', $course_id ),
	) );

	if ( empty( $params ) ) {
		return $base_url;
	}

	$separator = strpos( $base_url, '?' ) !== false ? '&' : '?';
	return $base_url . $separator . http_build_query( $params );
}

add_filter( 'bricks/dynamic_tags_list', function ( $tags ) {
	$tags[] = array(
		'name'  => '{popi_course_cta_url_utm}',
		'label' => 'Kurz CTA URL s UTM (popi-events)',
		'group' => 'Popi',
	);
	return $tags;
} );

add_filter( 'bricks/dynamic_data/render_tag', function ( $tag, $post, $context ) {
	$clean = trim( str_replace( array( '{', '}' ), '', $tag ) );
	if ( $clean === 'popi_course_cta_url_utm' ) {
		$post_id = is_object( $post ) && ! empty( $post->ID ) ? (int) $post->ID : get_the_ID();
		return popi_events_course_cta_url( $post_id );
	}
	return $tag;
}, 10, 3 );

/**
 * Vrati kurzy (CPT course), volitelne filtrovane podle slugu kategorie.
 *
 * @return WP_Post[]
 */
function popi_events_get_courses( string $category_slug = '' ): array {
	$args = array(
		'post_type'      => 'course',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
	);

	if ( $category_slug ) {
		$args['tax_query'] = array(
			array(
				'taxonomy' => 'category',
				'field'    => 'slug',
				'terms'    => $category_slug,
			),
		);
	}

	return get_posts( $args );
}
