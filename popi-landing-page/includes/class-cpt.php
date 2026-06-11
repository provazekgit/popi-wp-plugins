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
			'has_archive'        => true,
			'show_in_rest'       => true,
			'menu_icon'          => 'dashicons-flag',
			'menu_position'      => 25,
			'supports'           => array( 'title', 'editor', 'thumbnail', 'custom-fields' ),
			'taxonomies'         => array( 'category' ),
			'rewrite'            => array( 'slug' => 'lp', 'with_front' => false ),
		) );
	}

	public static function seed_terms(): void {
		if ( get_option( 'popi_landing_terms_seeded_v2' ) ) {
			return;
		}
		$terms = array( 'Rodiny s dětmi', 'Turisté', 'Ústecký kraj', 'Deštivé dny' );
		foreach ( $terms as $term ) {
			if ( ! term_exists( $term, 'category' ) ) {
				wp_insert_term( $term, 'category' );
			}
		}
		update_option( 'popi_landing_terms_seeded_v2', true );
	}
}
