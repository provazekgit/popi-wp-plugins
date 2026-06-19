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
					'placeholder'  => '09:00',
				),
				array(
					'key'          => 'field_popi_event_time_end',
					'label'        => 'Čas do',
					'name'         => 'popi_event_time_end',
					'type'         => 'text',
					'placeholder'  => '15:00',
				),
				array(
					'key'          => 'field_popi_event_hours_total',
					'label'        => 'Celkový počet hodin',
					'name'         => 'popi_event_hours_total',
					'type'         => 'number',
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
					'default_value' => 0,
					'instructions' => 'Doporučeno zapnout u většiny termínů — SEO hodnotu sbírá stránka kurzu, termín jen odkazuje canonical.',
				),
			),
			'location'        => array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => Cpt_Event::POST_TYPE ) ) ),
			'position'        => 'normal',
			'label_placement' => 'top',
			'menu_order'      => 0,
		) );
	}
}
