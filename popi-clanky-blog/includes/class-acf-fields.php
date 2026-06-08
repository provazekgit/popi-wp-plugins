<?php
defined( 'ABSPATH' ) || exit;

class Popi_Clanky_ACF {

	// ── VÝCHOZÍ PARAMETRY POLÍ ─────────────────────────────────────────────────

	private static function d_text(): array {
		return array( 'type' => 'text', 'required' => 0, 'placeholder' => '', 'instructions' => '' );
	}

	private static function d_textarea(): array {
		return array( 'type' => 'textarea', 'required' => 0, 'rows' => 3, 'placeholder' => '', 'instructions' => '' );
	}

	private static function d_url(): array {
		return array( 'type' => 'url', 'required' => 0, 'placeholder' => 'https://', 'instructions' => '' );
	}

	private static function d_image(): array {
		return array( 'type' => 'image', 'required' => 0, 'return_format' => 'array', 'preview_size' => 'medium', 'instructions' => '' );
	}

	// ── REGISTRACE ─────────────────────────────────────────────────────────────

	public static function init(): void {
		add_action( 'acf/init', array( self::class, 'register_fields' ) );
	}

	public static function register_fields(): void {
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}
		self::group_seo();
		self::group_cta();
	}

	// ── SEO & SDÍLENÍ (sidebar) ────────────────────────────────────────────────

	private static function group_seo(): void {
		acf_add_local_field_group( array(
			'key'             => 'group_popi_clanky_seo',
			'title'           => 'SEO & Sdílení',
			'fields'          => array(
				array_merge( self::d_text(), array(
					'key'           => 'field_clanky_meta_title',
					'label'         => 'Meta title',
					'name'          => 'popi_meta_title',
					'default_value' => Popi_Clanky_Settings::get( 'meta_title' ),
					'placeholder'   => 'Název článku | Vyletustecko.cz',
					'instructions'  => '50–60 znaků.',
				) ),
				array_merge( self::d_textarea(), array(
					'key'           => 'field_clanky_meta_desc',
					'label'         => 'Meta description',
					'name'          => 'popi_meta_desc',
					'default_value' => Popi_Clanky_Settings::get( 'meta_desc' ),
					'instructions'  => '140–160 znaků.',
				) ),
				array_merge( self::d_image(), array(
					'key'           => 'field_clanky_og_image',
					'label'         => 'OG obrázek (soc. sítě)',
					'name'          => 'popi_og_image',
					'default_value' => Popi_Clanky_Settings::get_int( 'og_image' ),
					'instructions'  => 'Doporučeno 1200 × 630 px.',
				) ),
			),
			'location'        => array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'popi_clanky' ) ) ),
			'position'        => 'side',
			'label_placement' => 'top',
			'menu_order'      => 0,
		) );
	}

	// ── CTA & UTM (sidebar) ────────────────────────────────────────────────────

	private static function group_cta(): void {
		acf_add_local_field_group( array(
			'key'             => 'group_popi_clanky_cta',
			'title'           => 'CTA & Tracking',
			'fields'          => array(
				array_merge( self::d_text(), array(
					'key'           => 'field_clanky_cta_text',
					'label'         => 'CTA — text tlačítka',
					'name'          => 'popi_cta_text',
					'default_value' => Popi_Clanky_Settings::get( 'cta_text', 'Koupit vstupenky online' ),
					'placeholder'   => 'Koupit vstupenky online',
				) ),
				array_merge( self::d_url(), array(
					'key'           => 'field_clanky_cta_url',
					'label'         => 'CTA — URL',
					'name'          => 'popi_cta_url',
					'default_value' => Popi_Clanky_Settings::get( 'cta_url' ),
				) ),
				array_merge( self::d_text(), array(
					'key'           => 'field_clanky_utm_source',
					'label'         => 'UTM source',
					'name'          => 'popi_utm_source',
					'default_value' => Popi_Clanky_Settings::get( 'utm_source', 'vyletustecko' ),
					'placeholder'   => 'vyletustecko',
					'instructions'  => 'Zdroj — vyletustecko, newsletter, facebook...',
				) ),
				array_merge( self::d_text(), array(
					'key'           => 'field_clanky_utm_medium',
					'label'         => 'UTM medium',
					'name'          => 'popi_utm_medium',
					'default_value' => Popi_Clanky_Settings::get( 'utm_medium', 'clanek' ),
					'placeholder'   => 'clanek',
					'instructions'  => 'Typ obsahu — clanek, email, social...',
				) ),
				array_merge( self::d_text(), array(
					'key'          => 'field_clanky_utm_kampan',
					'label'        => 'UTM campaign',
					'name'         => 'popi_utm_kampan',
					'placeholder'  => 'letni-vylet-2026',
					'instructions' => 'Název kampaně nebo tématu článku.',
				) ),
			),
			'location'        => array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'popi_clanky' ) ) ),
			'position'        => 'side',
			'label_placement' => 'top',
			'menu_order'      => 10,
		) );
	}
}
