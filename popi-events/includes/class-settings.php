<?php

namespace Popi\Events;

defined( 'ABSPATH' ) || exit;

/**
 * Vychozi hodnoty pro casto opakovana pole terminu (misto, cena), aby se
 * nemusely vyplnovat rucne u kazdeho noveho terminu.
 */
class Settings {

	const OPTION_KEY = 'popi_events_defaults';

	public static function init(): void {
		add_action( 'admin_menu', array( self::class, 'add_page' ) );
		add_action( 'admin_init', array( self::class, 'register_settings' ) );
	}

	public static function add_page(): void {
		add_submenu_page(
			'edit.php?post_type=' . Cpt_Course::POST_TYPE,
			'Výchozí hodnoty',
			'Výchozí hodnoty',
			'manage_options',
			'popi-events-settings',
			array( self::class, 'render_page' )
		);
	}

	public static function register_settings(): void {
		register_setting(
			'popi_events_settings_group',
			self::OPTION_KEY,
			array( 'sanitize_callback' => array( self::class, 'sanitize' ) )
		);

		add_settings_section( 'popi_events_section', 'Výchozí hodnoty termínu', null, 'popi-events-settings' );

		add_settings_field( 'location', 'Místo', array( self::class, 'field_input' ), 'popi-events-settings', 'popi_events_section', array( 'field' => 'location', 'placeholder' => 'Praha, učebna 1' ) );
		add_settings_field( 'price', 'Cena', array( self::class, 'field_input' ), 'popi-events-settings', 'popi_events_section', array( 'field' => 'price', 'placeholder' => '2 990 Kč' ) );
	}

	public static function field_input( array $args ): void {
		$value = self::get( $args['field'] );
		printf(
			'<input type="text" name="%s[%s]" value="%s" placeholder="%s" class="regular-text">',
			esc_attr( self::OPTION_KEY ),
			esc_attr( $args['field'] ),
			esc_attr( $value ),
			esc_attr( $args['placeholder'] )
		);
	}

	public static function sanitize( array $input ): array {
		return array(
			'location' => sanitize_text_field( $input['location'] ?? '' ),
			'price'    => sanitize_text_field( $input['price'] ?? '' ),
		);
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1>Termíny kurzů — Výchozí hodnoty</h1>
			<p>Hodnoty se předvyplní při vytvoření nového termínu. Na konkrétním termínu je lze přepsat.</p>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'popi_events_settings_group' );
				do_settings_sections( 'popi-events-settings' );
				submit_button( 'Uložit výchozí hodnoty' );
				?>
			</form>
		</div>
		<?php
	}

	public static function get( string $key, string $fallback = '' ): string {
		$values = get_option( self::OPTION_KEY, array() );
		return ! empty( $values[ $key ] ) ? (string) $values[ $key ] : $fallback;
	}
}
