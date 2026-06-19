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
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_media' ) );
	}

	public static function enqueue_media( $hook ): void {
		if ( 'course_page_popi-events-settings' !== $hook ) {
			return;
		}
		wp_enqueue_media();
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
		add_settings_field( 'time_start', 'Čas od', array( self::class, 'field_input' ), 'popi-events-settings', 'popi_events_section', array( 'field' => 'time_start', 'placeholder' => '09:00' ) );
		add_settings_field( 'time_end', 'Čas do', array( self::class, 'field_input' ), 'popi-events-settings', 'popi_events_section', array( 'field' => 'time_end', 'placeholder' => '15:00' ) );
		add_settings_field( 'hours_total', 'Celkový počet hodin', array( self::class, 'field_input' ), 'popi-events-settings', 'popi_events_section', array( 'field' => 'hours_total', 'placeholder' => '8' ) );
		add_settings_field( 'noindex', 'Skrýt termíny z vyhledávačů (noindex)', array( self::class, 'field_checkbox' ), 'popi-events-settings', 'popi_events_section', array( 'field' => 'noindex' ) );

		// ── Sekce: SEO & Sdílení (kurz) ────────────────────────────────────────
		add_settings_section( 'popi_events_section_seo', 'SEO & Sdílení (kurz)', null, 'popi-events-settings' );

		add_settings_field( 'meta_title', 'Meta title', array( self::class, 'field_input' ), 'popi-events-settings', 'popi_events_section_seo', array( 'field' => 'meta_title', 'placeholder' => 'Kurz první pomoci | Vaše firma' ) );
		add_settings_field( 'meta_desc', 'Meta description', array( self::class, 'field_textarea' ), 'popi-events-settings', 'popi_events_section_seo', array( 'field' => 'meta_desc', 'placeholder' => '' ) );
		add_settings_field( 'og_image', 'OG obrázek (soc. sítě)', array( self::class, 'field_image' ), 'popi-events-settings', 'popi_events_section_seo', array( 'field' => 'og_image' ) );

		// ── Sekce: CTA & UTM (kurz) ─────────────────────────────────────────────
		add_settings_section( 'popi_events_section_cta', 'CTA & UTM (kurz)', null, 'popi-events-settings' );

		add_settings_field( 'cta_text', 'CTA — text tlačítka', array( self::class, 'field_input' ), 'popi-events-settings', 'popi_events_section_cta', array( 'field' => 'cta_text', 'placeholder' => 'Přihlásit se na kurz' ) );
		add_settings_field( 'cta_url', 'CTA — URL', array( self::class, 'field_input' ), 'popi-events-settings', 'popi_events_section_cta', array( 'field' => 'cta_url', 'placeholder' => 'https://' ) );
		add_settings_field( 'utm_source', 'UTM source', array( self::class, 'field_input' ), 'popi-events-settings', 'popi_events_section_cta', array( 'field' => 'utm_source', 'placeholder' => 'google' ) );
		add_settings_field( 'utm_medium', 'UTM medium', array( self::class, 'field_input' ), 'popi-events-settings', 'popi_events_section_cta', array( 'field' => 'utm_medium', 'placeholder' => 'cpc' ) );

		// ── Sekce: Schema.org (Event) ───────────────────────────────────────────
		add_settings_section(
			'popi_events_section_schema',
			'Schema.org — strukturovaná data (Event)',
			function () {
				echo '<p>Tyto hodnoty se použijí pro všechny termíny jako pořadatel a měna v Google rich results (datum, místo, cena ve výsledcích vyhledávání).</p>';
			},
			'popi-events-settings'
		);

		add_settings_field( 'organizer_name', 'Pořadatel — název', array( self::class, 'field_input' ), 'popi-events-settings', 'popi_events_section_schema', array( 'field' => 'organizer_name', 'placeholder' => get_bloginfo( 'name' ) ) );
		add_settings_field( 'organizer_url', 'Pořadatel — URL', array( self::class, 'field_input' ), 'popi-events-settings', 'popi_events_section_schema', array( 'field' => 'organizer_url', 'placeholder' => home_url( '/' ) ) );
		add_settings_field( 'currency', 'Měna (ISO 4217)', array( self::class, 'field_input' ), 'popi-events-settings', 'popi_events_section_schema', array( 'field' => 'currency', 'placeholder' => 'CZK' ) );
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

	public static function field_textarea( array $args ): void {
		$value = self::get( $args['field'] );
		printf(
			'<textarea name="%s[%s]" placeholder="%s" class="regular-text" rows="3">%s</textarea>',
			esc_attr( self::OPTION_KEY ),
			esc_attr( $args['field'] ),
			esc_attr( $args['placeholder'] ),
			esc_textarea( $value )
		);
	}

	public static function field_image( array $args ): void {
		$image_id  = self::get_int( $args['field'] );
		$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : '';
		$field_key = self::OPTION_KEY . '[' . $args['field'] . ']';
		?>
		<div class="popi-image-field" style="display:flex;align-items:flex-start;gap:12px;">
			<input type="hidden" name="<?php echo esc_attr( $field_key ); ?>" value="<?php echo esc_attr( $image_id ?: '' ); ?>" class="popi-image-id">
			<div class="popi-image-preview" style="width:120px;height:80px;background:#f0f0f0;border:1px solid #ddd;display:flex;align-items:center;justify-content:center;overflow:hidden;">
				<?php if ( $image_url ) : ?>
					<img src="<?php echo esc_url( $image_url ); ?>" style="max-width:100%;max-height:100%;object-fit:cover;">
				<?php else : ?>
					<span style="color:#999;font-size:12px;">Bez obrázku</span>
				<?php endif; ?>
			</div>
			<div>
				<button type="button" class="button popi-image-select">Vybrat obrázek</button><br><br>
				<button type="button" class="button popi-image-remove" <?php echo $image_id ? '' : 'style="display:none"'; ?>>Odebrat</button>
			</div>
		</div>
		<script>
		(function($){
			$(document).on('click', '.popi-image-select', function(e){
				e.preventDefault();
				var $wrap = $(this).closest('.popi-image-field');
				var frame = wp.media({ title: 'Vybrat obrázek', button: { text: 'Použít' }, multiple: false });
				frame.on('select', function(){
					var att = frame.state().get('selection').first().toJSON();
					$wrap.find('.popi-image-id').val(att.id);
					$wrap.find('.popi-image-preview').html('<img src="' + (att.sizes && att.sizes.medium ? att.sizes.medium.url : att.url) + '" style="max-width:100%;max-height:100%;object-fit:cover;">');
					$wrap.find('.popi-image-remove').show();
				});
				frame.open();
			});
			$(document).on('click', '.popi-image-remove', function(e){
				e.preventDefault();
				var $wrap = $(this).closest('.popi-image-field');
				$wrap.find('.popi-image-id').val('');
				$wrap.find('.popi-image-preview').html('<span style="color:#999;font-size:12px;">Bez obrázku</span>');
				$(this).hide();
			});
		})(jQuery);
		</script>
		<?php
	}

	public static function field_checkbox( array $args ): void {
		printf(
			'<label><input type="checkbox" name="%s[%s]" value="1" %s> Ano, nové termíny budou výchozí noindex</label>',
			esc_attr( self::OPTION_KEY ),
			esc_attr( $args['field'] ),
			checked( self::get_bool( $args['field'] ), true, false )
		);
	}

	public static function sanitize( array $input ): array {
		return array(
			'location'       => sanitize_text_field( $input['location'] ?? '' ),
			'price'          => sanitize_text_field( $input['price'] ?? '' ),
			'time_start'     => sanitize_text_field( $input['time_start'] ?? '' ),
			'time_end'       => sanitize_text_field( $input['time_end'] ?? '' ),
			'hours_total'    => sanitize_text_field( $input['hours_total'] ?? '' ),
			'noindex'        => ! empty( $input['noindex'] ) ? 1 : 0,
			'meta_title'     => sanitize_text_field( $input['meta_title'] ?? '' ),
			'meta_desc'      => sanitize_textarea_field( $input['meta_desc'] ?? '' ),
			'og_image'       => absint( $input['og_image'] ?? 0 ),
			'cta_text'       => sanitize_text_field( $input['cta_text'] ?? '' ),
			'cta_url'        => esc_url_raw( $input['cta_url'] ?? '' ),
			'utm_source'     => sanitize_text_field( $input['utm_source'] ?? '' ),
			'utm_medium'     => sanitize_text_field( $input['utm_medium'] ?? '' ),
			'organizer_name' => sanitize_text_field( $input['organizer_name'] ?? '' ),
			'organizer_url'  => esc_url_raw( $input['organizer_url'] ?? '' ),
			'currency'       => sanitize_text_field( $input['currency'] ?? '' ),
		);
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1>Kurzy / Akce — Výchozí hodnoty</h1>
			<p>Hodnoty se předvyplní při vytvoření nového termínu nebo kurzu. Na konkrétní položce je lze přepsat.</p>
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

	public static function get_bool( string $key ): bool {
		$values = get_option( self::OPTION_KEY, array() );
		return ! empty( $values[ $key ] );
	}

	public static function get_int( string $key, int $fallback = 0 ): int {
		$values = get_option( self::OPTION_KEY, array() );
		return ! empty( $values[ $key ] ) ? (int) $values[ $key ] : $fallback;
	}
}
