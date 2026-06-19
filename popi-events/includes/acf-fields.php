<?php

namespace Popi\Events;

defined( 'ABSPATH' ) || exit;

/**
 * ACF local field group pro CPT "event". Pouziva jen pole dostupna v ACF Free
 * (Post Object, Date Picker, Text, Number, Textarea, True/False).
 */
class Acf_Fields {

	public static function init(): void {
		add_action( 'acf/init', array( self::class, 'register_fields' ) );
	}

	public static function register_fields(): void {
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}

		self::group_course_seo();
		self::group_course_cta();
		self::group_event();
	}

	// ── KURZ — SEO & SDÍLENÍ ───────────────────────────────────────────────────

	private static function group_course_seo(): void {
		acf_add_local_field_group( array(
			'key'             => 'group_popi_course_seo',
			'title'           => 'Kurz / Akce — SEO & Sdílení (popi-events)',
			'fields'          => array(
				array(
					'key'           => 'field_popi_course_meta_title',
					'label'         => 'Meta title',
					'name'          => 'popi_course_meta_title',
					'type'          => 'text',
					'default_value' => Settings::get( 'meta_title' ),
					'placeholder'   => 'Kurz první pomoci | Vaše firma',
					'instructions'  => '50–60 znaků.',
				),
				array(
					'key'           => 'field_popi_course_meta_desc',
					'label'         => 'Meta description',
					'name'          => 'popi_course_meta_desc',
					'type'          => 'textarea',
					'rows'          => 3,
					'default_value' => Settings::get( 'meta_desc' ),
					'instructions'  => '140–160 znaků.',
				),
				array(
					'key'           => 'field_popi_course_og_image',
					'label'         => 'OG obrázek (soc. sítě)',
					'name'          => 'popi_course_og_image',
					'type'          => 'image',
					'return_format' => 'array',
					'preview_size'  => 'medium',
					'default_value' => Settings::get_int( 'og_image' ),
					'instructions'  => 'Doporučeno 1200 × 630 px. Použije se i pro Schema.org obrázek (Google rich results).',
				),
				array(
					'key'          => 'field_popi_course_nahledovy_obrazek_mobil',
					'label'        => 'Náhledový obrázek — mobil',
					'name'         => 'popi_course_nahledovy_obrazek_mobil',
					'type'         => 'image',
					'return_format' => 'array',
					'preview_size'  => 'medium',
					'instructions' => 'Volitelný alternativní obrázek pro mobilní zařízení. Pokud nevyplníš, použije se standardní náhledový obrázek (WP Featured Image) — ten se zobrazuje na PC a tabletu.',
				),
			),
			'location'        => array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => Cpt_Course::POST_TYPE ) ) ),
			'position'        => 'side',
			'label_placement' => 'top',
			'menu_order'      => 10,
		) );
	}

	// ── KURZ — CTA & UTM ───────────────────────────────────────────────────────

	private static function group_course_cta(): void {
		acf_add_local_field_group( array(
			'key'             => 'group_popi_course_cta',
			'title'           => 'Kurz / Akce — CTA & UTM (popi-events)',
			'fields'          => array(
				array(
					'key'           => 'field_popi_course_cta_text',
					'label'         => 'CTA — text tlačítka',
					'name'          => 'popi_course_cta_text',
					'type'          => 'text',
					'default_value' => Settings::get( 'cta_text', 'Přihlásit se na kurz' ),
					'placeholder'   => 'Přihlásit se na kurz',
				),
				array(
					'key'           => 'field_popi_course_cta_url',
					'label'         => 'CTA — URL',
					'name'          => 'popi_course_cta_url',
					'type'          => 'url',
					'default_value' => Settings::get( 'cta_url' ),
					'placeholder'   => 'https://',
					'instructions'  => 'Základní URL přihlašovacího/rezervačního formuláře bez UTM parametrů — ty se přidají automaticky.',
				),
				array(
					'key'           => 'field_popi_course_utm_source',
					'label'         => 'UTM source',
					'name'          => 'popi_course_utm_source',
					'type'          => 'text',
					'default_value' => Settings::get( 'utm_source', 'google' ),
					'placeholder'   => 'google',
					'instructions'  => 'Zdroj návštěvnosti — google, sklik, facebook... Pro GA4.',
				),
				array(
					'key'           => 'field_popi_course_utm_medium',
					'label'         => 'UTM medium',
					'name'          => 'popi_course_utm_medium',
					'type'          => 'text',
					'default_value' => Settings::get( 'utm_medium', 'cpc' ),
					'placeholder'   => 'cpc',
				),
				array(
					'key'          => 'field_popi_course_utm_kampan',
					'label'        => 'UTM campaign',
					'name'         => 'popi_course_utm_kampan',
					'type'         => 'text',
					'placeholder'  => 'kurz-prvni-pomoci-2026',
					'instructions' => 'Název kampaně pro tento kurz. Použije se i jako výchozí hodnota v generátoru UTM odkazů níže.',
				),
			),
			'location'        => array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => Cpt_Course::POST_TYPE ) ) ),
			'position'        => 'side',
			'label_placement' => 'top',
			'menu_order'      => 20,
		) );
	}

	// ── TERMÍN ─────────────────────────────────────────────────────────────────

	private static function group_event(): void {
		acf_add_local_field_group( array(
			'key'             => 'group_popi_event',
			'title'           => 'Termín kurzu (popi-events)',
			'fields'          => array(
				array(
					'key'           => 'field_popi_event_course_id',
					'label'         => 'Kurz',
					'name'          => 'popi_event_course_id',
					'type'          => 'post_object',
					'required'      => 1,
					'post_type'     => array( Cpt_Course::POST_TYPE ),
					'return_format' => 'id',
					'ui'            => 1,
					'instructions'  => 'Ke kterému kurzu termín patří. Pokud Post Object pole ve tvé verzi ACF chybí, nahraď polem typu Number a vyplň ID kurzu ručně.',
				),
				array(
					'key'           => 'field_popi_event_date_start',
					'label'         => 'Datum termínu',
					'name'          => 'popi_event_date_start',
					'type'          => 'date_picker',
					'required'      => 1,
					'display_format' => 'd.m.Y',
					'return_format'  => 'Y-m-d',
					'first_day'      => 1,
				),
				array(
					'key'          => 'field_popi_event_time_start',
					'label'        => 'Čas od',
					'name'         => 'popi_event_time_start',
					'type'         => 'text',
					'default_value' => Settings::get( 'time_start' ),
					'placeholder'  => '09:00',
				),
				array(
					'key'          => 'field_popi_event_time_end',
					'label'        => 'Čas do',
					'name'         => 'popi_event_time_end',
					'type'         => 'text',
					'default_value' => Settings::get( 'time_end' ),
					'placeholder'  => '15:00',
				),
				array(
					'key'          => 'field_popi_event_hours_total',
					'label'        => 'Celkový počet hodin',
					'name'         => 'popi_event_hours_total',
					'type'         => 'number',
					'default_value' => Settings::get( 'hours_total' ),
					'placeholder'  => '8',
				),
				array(
					'key'          => 'field_popi_event_location',
					'label'        => 'Místo',
					'name'         => 'popi_event_location',
					'type'         => 'text',
					'default_value' => Settings::get( 'location' ),
					'placeholder'  => 'Praha, učebna 1',
				),
				array(
					'key'          => 'field_popi_event_price',
					'label'        => 'Cena',
					'name'         => 'popi_event_price',
					'type'         => 'text',
					'default_value' => Settings::get( 'price' ),
					'placeholder'  => '2 990 Kč',
				),
				array(
					'key'          => 'field_popi_event_note',
					'label'        => 'Krátká poznámka',
					'name'         => 'popi_event_note',
					'type'         => 'textarea',
					'rows'         => 3,
					'instructions' => 'Krátký popis termínu (např. změna, poslední místa...). Plný popis patří na stránku kurzu, ne sem — zabraňuje to duplicitnímu obsahu.',
				),
				array(
					'key'          => 'field_popi_event_noindex',
					'label'        => 'Skrýt z vyhledávačů (noindex)',
					'name'         => 'popi_event_noindex',
					'type'         => 'true_false',
					'ui'           => 1,
					'default_value' => Settings::get_bool( 'noindex' ),
					'instructions' => 'Doporučeno zapnout u většiny termínů — SEO hodnotu sbírá stránka kurzu, termín jen odkazuje canonical.',
				),
				array(
					'key'          => 'field_popi_event_attendance_mode',
					'label'        => 'Forma konání',
					'name'         => 'popi_event_attendance_mode',
					'type'         => 'select',
					'choices'      => array(
						'offline' => 'Prezenčně',
						'online'  => 'Online',
						'mixed'   => 'Hybridně (prezenčně i online)',
					),
					'default_value' => 'offline',
					'instructions' => 'Pro Schema.org (Google rich results) — eventAttendanceMode.',
				),
			),
			'location'        => array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => Cpt_Event::POST_TYPE ) ) ),
			'position'        => 'normal',
			'label_placement' => 'top',
			'menu_order'      => 0,
		) );
	}
}
