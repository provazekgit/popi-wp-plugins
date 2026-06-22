<?php

namespace Popi\Events;

defined( 'ABSPATH' ) || exit;

/**
 * CPT "course" — hlavni stranka kurzu/akce. Pevna URL (/kurzy/nazev/),
 * sbira SEO autoritu. Terminy (CPT event) na ni odkazuji canonical odkazem.
 *
 * Typ (webinar/kurz/workshop...) se zadava standardni WP rubrikou (category),
 * ne vlastni taxonomii — sdili se s ostatnimi popi pluginy (stejny vzor jako
 * popi-landing-page) a funguje rovnou s WP widgety a menu.
 */
class Cpt_Course {

	const POST_TYPE = 'popi_course';

	public static function register(): void {
		register_post_type( self::POST_TYPE, array(
			'labels' => array(
				'name'               => 'Kurzy / Akce',
				'singular_name'      => 'Kurz / Akce',
				'add_new'            => 'Přidat nový',
				'add_new_item'       => 'Přidat kurz / akci',
				'edit_item'          => 'Upravit kurz / akci',
				'new_item'           => 'Nový kurz / akce',
				'view_item'          => 'Zobrazit kurz / akci',
				'search_items'       => 'Hledat kurzy / akce',
				'not_found'          => 'Žádné kurzy / akce',
				'not_found_in_trash' => 'Žádné kurzy / akce v koši',
				'menu_name'          => 'Kurzy / Akce',
			),
			'public'             => true,
			'has_archive'        => 'kurzy',
			'show_in_rest'       => true,
			'menu_icon'          => 'dashicons-welcome-learn-more',
			'menu_position'      => 25,
			'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ),
			'taxonomies'         => array( 'category' ),
			'rewrite'            => array( 'slug' => 'kurzy', 'with_front' => false ),
		) );
	}
}
