<?php

namespace Popi\Events;

defined( 'ABSPATH' ) || exit;

/**
 * SEO logika terminu: canonical vzdy smeruje na rodicovsky kurz (zabranuje
 * duplicitnimu obsahu), noindex je volitelny prepinac na konkretnim terminu.
 *
 * Kompatibilni s Yoast SEO a Rank Math (pokud je nektery aktivni, prebira
 * jejich filtr). Bez SEO pluginu pouzije vlastni <link rel="canonical">
 * a <meta name="robots"> ve wp_head.
 */
class Seo {

	public static function init(): void {
		// Yoast SEO
		add_filter( 'wpseo_canonical', array( self::class, 'filter_canonical' ) );
		add_filter( 'wpseo_robots', array( self::class, 'filter_yoast_robots' ) );

		// Rank Math
		add_filter( 'rank_math/frontend/canonical', array( self::class, 'filter_canonical' ) );
		add_filter( 'rank_math/frontend/robots', array( self::class, 'filter_rank_math_robots' ) );

		// Fallback bez SEO pluginu
		add_action( 'wp_head', array( self::class, 'output_fallback_tags' ), 1 );
	}

	private static function get_course_permalink(): ?string {
		if ( ! is_singular( Cpt_Event::POST_TYPE ) ) {
			return null;
		}
		$course_id = (int) get_field( 'popi_event_course_id', get_the_ID() );
		if ( ! $course_id ) {
			return null;
		}
		$permalink = get_permalink( $course_id );
		return $permalink ?: null;
	}

	private static function is_noindex(): bool {
		return is_singular( Cpt_Event::POST_TYPE ) && (bool) get_field( 'popi_event_noindex', get_the_ID() );
	}

	public static function filter_canonical( $canonical ) {
		$course_url = self::get_course_permalink();
		return $course_url ?: $canonical;
	}

	public static function filter_yoast_robots( array $robots ): array {
		if ( self::is_noindex() ) {
			$robots['index'] = 'noindex';
		}
		return $robots;
	}

	public static function filter_rank_math_robots( array $robots ): array {
		if ( self::is_noindex() ) {
			$robots['noindex'] = 'noindex';
		}
		return $robots;
	}

	public static function output_fallback_tags(): void {
		if ( self::seo_plugin_active() ) {
			return;
		}

		$course_url = self::get_course_permalink();
		if ( $course_url ) {
			printf( '<link rel="canonical" href="%s" />' . "\n", esc_url( $course_url ) );
		}

		if ( self::is_noindex() ) {
			echo '<meta name="robots" content="noindex, follow" />' . "\n";
		}
	}

	private static function seo_plugin_active(): bool {
		return defined( 'WPSEO_VERSION' ) || class_exists( 'RankMath' );
	}
}
