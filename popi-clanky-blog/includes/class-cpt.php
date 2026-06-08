<?php
defined( 'ABSPATH' ) || exit;

class Popi_Clanky_CPT {

	public static function register(): void {
		register_post_type( 'popi_clanky', array(
			'labels' => array(
				'name'               => 'Články',
				'singular_name'      => 'Článek',
				'add_new'            => 'Přidat nový',
				'add_new_item'       => 'Přidat nový článek',
				'edit_item'          => 'Upravit článek',
				'new_item'           => 'Nový článek',
				'view_item'          => 'Zobrazit článek',
				'search_items'       => 'Hledat články',
				'not_found'          => 'Žádné články',
				'not_found_in_trash' => 'Žádné články v koši',
				'menu_name'          => 'Články blog',
			),
			'public'              => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_nav_menus'   => true,
			'show_in_admin_bar'   => true,
			'show_in_rest'        => true,
			'menu_position'       => 5,
			'menu_icon'           => 'dashicons-admin-post',
			'has_archive'         => true,
			'exclude_from_search' => false,
			'publicly_queryable'  => true,
			'can_export'          => true,
			'capability_type'     => 'post',
			'supports'            => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments', 'custom-fields' ),
			'taxonomies'          => array( 'category', 'post_tag' ),
			'rewrite'             => array( 'slug' => 'clanky', 'with_front' => false ),
		) );
	}
}
