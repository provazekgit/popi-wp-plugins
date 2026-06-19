<?php

namespace Popi\Events;

defined( 'ABSPATH' ) || exit;

/**
 * CPT "course" — hlavni stranka kurzu. Pevna URL (/kurzy/nazev-kurzu/),
 * sbira SEO autoritu. Terminy (CPT event) na ni odkazuji canonical odkazem.
 */
class Cpt_Course {

	const POST_TYPE = 'course';
	const TAXONOMY  = 'popi_course_category';

	public static function register(): void {
		register_post_type( self::POST_TYPE, array(
			'labels' => array(
				'name'               => 'Kurzy',
				'singular_name'      => 'Kurz',
				'add_new'            => 'Přidat nový',
				'add_new_item'       => 'Přidat kurz',
				'edit_item'          => 'Upravit kurz',
				'new_item'           => 'Nový kurz',
				'view_item'          => 'Zobrazit kurz',
				'search_items'       => 'Hledat kurzy',
				'not_found'          => 'Žádné kurzy',
				'not_found_in_trash' => 'Žádné kurzy v koši',
				'menu_name'          => 'Kurzy',
			),
			'public'             => true,
			'has_archive'        => 'kurzy',
			'show_in_rest'       => true,
			'menu_icon'          => 'dashicons-welcome-learn-more',
			'menu_position'      => 25,
			'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ),
			'taxonomies'         => array( self::TAXONOMY ),
			'rewrite'            => array( 'slug' => 'kurzy', 'with_front' => false ),
		) );

		register_taxonomy( self::TAXONOMY, self::POST_TYPE, array(
			'labels' => array(
				'name'          => 'Kategorie kurzu',
				'singular_name' => 'Kategorie kurzu',
				'search_items'  => 'Hledat kategorie',
				'all_items'     => 'Všechny kategorie',
				'edit_item'     => 'Upravit kategorii',
				'add_new_item'  => 'Přidat kategorii',
				'menu_name'     => 'Kategorie kurzu',
			),
			'public'            => true,
			'show_in_rest'      => true,
			'hierarchical'      => true,
			'rewrite'           => array( 'slug' => 'kategorie-kurzu' ),
		) );
	}
}
