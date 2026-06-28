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

	/**
	 * Yoast SEO menilo format teto hodnoty mezi verzemi — starsi Yoast predava
	 * asociativni array (napr. ['index' => 'index, follow']), novejsi (od
	 * prechodu na WP wp_robots API) predava uz slozeny string (napr.
	 * "index, follow, max-snippet:-1"). Musime zvladnout obe varianty, jinak
	 * na aktualnim Yoastu spadne cely web s TypeError (wpseo_robots bezi na
	 * wp_head, tedy na kazde strance).
	 *
	 * @param array|string $robots
	 * @return array|string
	 */
	public static function filter_yoast_robots( $robots ) {
		if ( ! self::is_noindex() ) {
			return $robots;
		}

		if ( is_array( $robots ) ) {
			$robots['index'] = 'noindex';
			return $robots;
		}

		if ( ! is_string( $robots ) ) {
			// Neznamy format (budouci verze SEO pluginu) — nic neupravujeme,
			// at se web nikdy nezastavi na neceka vstupu, ktery nekontrolujeme.
			return $robots;
		}

		if ( false !== strpos( $robots, 'noindex' ) ) {
			return $robots;
		}
		if ( false !== strpos( $robots, 'index' ) ) {
			return preg_replace( '/\bindex\b/', 'noindex', $robots, 1 );
		}
		return 'noindex, ' . $robots;
	}

	/**
	 * @param array|string $robots
	 * @return array|string
	 */
	public static function filter_rank_math_robots( $robots ) {
		if ( ! self::is_noindex() ) {
			return $robots;
		}

		if ( is_array( $robots ) ) {
			$robots['noindex'] = 'noindex';
			return $robots;
		}

		if ( ! is_string( $robots ) ) {
			return $robots;
		}

		return false !== strpos( $robots, 'noindex' ) ? $robots : 'noindex, ' . $robots;
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

	/**
	 * Sem patri jakykoliv SEO plugin, ktery uz sam vypisuje canonical/robots
	 * meta tagy — jinak by se nas fallback vypsal soubezne s nim a vznikly
	 * by duplicitni/konfliktni tagy ve <head>.
	 */
	private static function seo_plugin_active(): bool {
		return defined( 'WPSEO_VERSION' )                  // Yoast SEO
			|| class_exists( 'RankMath' )                   // Rank Math
			|| defined( 'AIOSEO_VERSION' )                  // All in One SEO
			|| class_exists( '\AIOSEO\Plugin\AIOSEO' )       // All in One SEO (novejsi)
			|| defined( 'WPSEOPRESS_VERSION' )               // SEOPress
			|| class_exists( 'SEOPress' );
	}
}
