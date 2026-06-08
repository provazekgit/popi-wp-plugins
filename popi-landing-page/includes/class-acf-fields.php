<?php
defined( 'ABSPATH' ) || exit;

class Popi_Landing_ACF {

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
		self::group_zaklad();
		self::group_seo();
		self::group_sea();
	}

	// ── 1. ZÁKLAD ──────────────────────────────────────────────────────────────

	private static function group_zaklad(): void {
		acf_add_local_field_group( array(
			'key'             => 'group_popi_zaklad',
			'title'           => 'Landing Page — Základ',
			'fields'          => array(
				array_merge( self::d_text(), array(
					'key'           => 'field_popi_hlavni_nadpis',
					'label'         => 'Hlavní nadpis (H1)',
					'name'          => 'popi_hlavni_nadpis',
					'required'      => 1,
					'default_value' => Popi_Landing_Settings::get( 'hlavni_nadpis' ),
					'placeholder'   => 'Barevný výlet pro děti v Olympia Teplice',
					'instructions'  => 'Musí odpovídat textu v reklamě (message match).',
				) ),
				array_merge( self::d_textarea(), array(
					'key'           => 'field_popi_hlavni_popis',
					'label'         => 'Hlavní popis (perex)',
					'name'          => 'popi_hlavni_popis',
					'required'      => 1,
					'default_value' => Popi_Landing_Settings::get( 'hlavni_popis' ),
					'placeholder'   => 'Krátký podnadpis nebo perex — 1–2 věty.',
				) ),
				array_merge( self::d_text(), array(
					'key'           => 'field_popi_cta_text',
					'label'         => 'CTA — text tlačítka',
					'name'          => 'popi_cta_text',
					'required'      => 1,
					'default_value' => Popi_Landing_Settings::get( 'cta_text', 'Koupit vstupenku' ),
					'placeholder'   => 'Koupit vstupenku',
				) ),
				array_merge( self::d_url(), array(
					'key'           => 'field_popi_cta_url',
					'label'         => 'CTA — URL',
					'name'          => 'popi_cta_url',
					'required'      => 1,
					'default_value' => Popi_Landing_Settings::get( 'cta_url' ),
				) ),
			),
			'location'        => array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'popi_landing' ) ) ),
			'position'        => 'normal',
			'label_placement' => 'top',
			'menu_order'      => 0,
		) );
	}

	// ── 2. SEO & SDÍLENÍ ───────────────────────────────────────────────────────

	private static function group_seo(): void {
		acf_add_local_field_group( array(
			'key'             => 'group_popi_seo',
			'title'           => 'SEO & Sdílení',
			'fields'          => array(
				array_merge( self::d_text(), array(
					'key'           => 'field_popi_meta_title',
					'label'         => 'Meta title',
					'name'          => 'popi_meta_title',
					'default_value' => Popi_Landing_Settings::get( 'meta_title' ),
					'placeholder'   => 'Motýlí dům Papilonia Teplice | Výlet pro děti',
					'instructions'  => '50–60 znaků. Ovlivňuje Quality Score v Google Ads.',
				) ),
				array_merge( self::d_textarea(), array(
					'key'           => 'field_popi_meta_desc',
					'label'         => 'Meta description',
					'name'          => 'popi_meta_desc',
					'default_value' => Popi_Landing_Settings::get( 'meta_desc' ),
					'instructions'  => '140–160 znaků.',
				) ),
				array_merge( self::d_image(), array(
					'key'           => 'field_popi_og_image',
					'label'         => 'OG obrázek (soc. sítě)',
					'name'          => 'popi_og_image',
					'default_value' => Popi_Landing_Settings::get_int( 'og_image' ),
					'instructions'  => 'Doporučeno 1200 × 630 px.',
				) ),
			),
			'location'        => array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'popi_landing' ) ) ),
			'position'        => 'side',
			'label_placement' => 'top',
			'menu_order'      => 10,
		) );
	}

	// ── 3. SEA ─────────────────────────────────────────────────────────────────

	private static function group_sea(): void {
		acf_add_local_field_group( array(
			'key'             => 'group_popi_sea',
			'title'           => 'SEA — Kampaň',
			'fields'          => array(
				array_merge( self::d_text(), array(
					'key'           => 'field_popi_kw_kampan',
					'label'         => 'Klíčové slovo kampaně',
					'name'          => 'popi_kw_kampan',
					'default_value' => Popi_Landing_Settings::get( 'kw_kampan' ),
					'placeholder'   => 'kam s dětmi Teplice',
					'instructions'  => 'Interní přehled — na jaký dotaz tato LP cílí.',
				) ),
				array_merge( self::d_text(), array(
					'key'           => 'field_popi_utm_source',
					'label'         => 'UTM source',
					'name'          => 'popi_utm_source',
					'default_value' => Popi_Landing_Settings::get( 'utm_source', 'sklik' ),
					'placeholder'   => 'sklik',
					'instructions'  => 'Zdroj návštěvnosti — sklik, google-ads, facebook...',
				) ),
				array_merge( self::d_text(), array(
					'key'           => 'field_popi_utm_medium',
					'label'         => 'UTM medium',
					'name'          => 'popi_utm_medium',
					'default_value' => Popi_Landing_Settings::get( 'utm_medium', 'cpc' ),
					'placeholder'   => 'cpc',
					'instructions'  => 'Typ reklamy — cpc, email, social...',
				) ),
				array_merge( self::d_text(), array(
					'key'          => 'field_popi_utm_kampan',
					'label'        => 'UTM campaign',
					'name'         => 'popi_utm_kampan',
					'placeholder'  => 'deti-teplice',
					'instructions' => 'Název konkrétní kampaně pro tuto LP.',
				) ),
			),
			'location'        => array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'popi_landing' ) ) ),
			'position'        => 'side',
			'label_placement' => 'top',
			'menu_order'      => 20,
		) );
	}
}
