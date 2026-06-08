<?php
defined( 'ABSPATH' ) || exit;

class Popi_Landing_CPT {

	public static function register(): void {
		register_post_type( 'popi_landing', array(
			'labels' => array(
				'name'               => 'Landing Pages',
				'singular_name'      => 'Landing Page',
				'add_new'            => 'Přidat novou',
				'add_new_item'       => 'Přidat landing page',
				'edit_item'          => 'Upravit landing page',
				'new_item'           => 'Nová landing page',
				'view_item'          => 'Zobrazit landing page',
				'search_items'       => 'Hledat landing pages',
				'not_found'          => 'Žádné landing pages',
				'not_found_in_trash' => 'Žádné landing pages v koši',
				'menu_name'          => 'Landing Pages',
			),
			'public'             => true,
			'has_archive'        => false,
			'show_in_rest'       => true,
			'menu_icon'          => 'dashicons-flag',
			'menu_position'      => 25,
			'supports'           => array( 'title', 'editor', 'thumbnail', 'custom-fields' ),
			'rewrite'            => array( 'slug' => 'lp', 'with_front' => false ),
		) );
	}

	public static function register_taxonomy(): void {
		register_taxonomy( 'popi_cilova_skupina', 'popi_landing', array(
			'labels' => array(
				'name'              => 'Cílové skupiny',
				'singular_name'     => 'Cílová skupina',
				'search_items'      => 'Hledat skupiny',
				'all_items'         => 'Všechny skupiny',
				'edit_item'         => 'Upravit skupinu',
				'add_new_item'      => 'Přidat skupinu',
				'not_found'         => 'Žádné skupiny',
				'menu_name'         => 'Cílové skupiny',
			),
			'hierarchical'      => true,
			'show_ui'           => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'rewrite'           => false,
		) );
	}

	public static function seed_terms(): void {
		if ( get_option( 'popi_landing_terms_seeded' ) ) {
			return;
		}
		$terms = array( 'Rodiny s dětmi', 'Turisté', 'Ústecký kraj', 'Deštivé dny' );
		foreach ( $terms as $term ) {
			if ( ! term_exists( $term, 'popi_cilova_skupina' ) ) {
				wp_insert_term( $term, 'popi_cilova_skupina' );
			}
		}
		update_option( 'popi_landing_terms_seeded', true );
	}
}
