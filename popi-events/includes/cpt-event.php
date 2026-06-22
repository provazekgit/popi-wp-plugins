<?php

namespace Popi\Events;

defined( 'ABSPATH' ) || exit;

/**
 * CPT "event" — samostatny termin kurzu. Vlastni URL, ale SEO canonical
 * smeruje na rodicovsky kurz (viz Seo). Archiv vsech terminu je na /terminy/.
 */
class Cpt_Event {

	const POST_TYPE = 'popi_event';

	public static function init(): void {
		add_action( 'acf/save_post', array( self::class, 'maybe_set_title' ), 20 );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( self::class, 'admin_columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( self::class, 'admin_column_content' ), 10, 2 );
	}

	public static function register(): void {
		register_post_type( self::POST_TYPE, array(
			'labels' => array(
				'name'               => 'Termíny',
				'singular_name'      => 'Termín',
				'add_new'            => 'Přidat nový',
				'add_new_item'       => 'Přidat termín',
				'edit_item'          => 'Upravit termín',
				'new_item'           => 'Nový termín',
				'view_item'          => 'Zobrazit termín',
				'search_items'       => 'Hledat termíny',
				'not_found'          => 'Žádné termíny',
				'not_found_in_trash' => 'Žádné termíny v koši',
				'menu_name'          => 'Termíny',
				'all_items'          => 'Všechny termíny',
			),
			'public'             => true,
			'has_archive'        => 'terminy',
			'show_in_rest'       => true,
			'show_in_menu'       => 'edit.php?post_type=' . Cpt_Course::POST_TYPE,
			'supports'           => array( 'title', 'custom-fields' ),
			'rewrite'            => array( 'slug' => 'terminy', 'with_front' => false ),
		) );
	}

	/**
	 * Titulek terminu se generuje automaticky z nazvu kurzu a data — admin
	 * editor nemusi mit pole Title viditelne vyplnovane rucne.
	 */
	public static function maybe_set_title( $post_id ): void {
		static $running = false;
		if ( $running || get_post_type( $post_id ) !== self::POST_TYPE ) {
			return;
		}

		$course_id  = (int) get_field( 'popi_event_course_id', $post_id );
		$date_start = get_field( 'popi_event_date_start', $post_id );
		if ( ! $course_id || ! $date_start ) {
			return;
		}

		$new_title = trim( get_the_title( $course_id ) . ' – ' . \popi_events_format_date( $date_start ) );
		if ( get_the_title( $post_id ) === $new_title ) {
			return;
		}

		$running = true;
		wp_update_post( array( 'ID' => $post_id, 'post_title' => $new_title ) );
		$running = false;
	}

	public static function admin_columns( array $columns ): array {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['popi_course']  = 'Kurz';
				$new['popi_date']    = 'Datum';
				$new['popi_location'] = 'Místo';
				$new['popi_price']   = 'Cena';
			}
		}
		return $new;
	}

	public static function admin_column_content( string $column, int $post_id ): void {
		switch ( $column ) {
			case 'popi_course':
				$course_id = (int) get_field( 'popi_event_course_id', $post_id );
				echo $course_id ? esc_html( get_the_title( $course_id ) ) : '—';
				break;
			case 'popi_date':
				$date = get_field( 'popi_event_date_start', $post_id );
				echo $date ? esc_html( \popi_events_format_date( $date ) ) : '—';
				break;
			case 'popi_location':
				echo esc_html( get_field( 'popi_event_location', $post_id ) ?: '—' );
				break;
			case 'popi_price':
				echo esc_html( get_field( 'popi_event_price', $post_id ) ?: '—' );
				break;
		}
	}
}
