<?php
defined( 'ABSPATH' ) || exit;

class Popi_Clanky_Settings {

	const OPTION_KEY = 'popi_clanky_defaults';

	public static function init(): void {
		add_action( 'admin_menu', array( self::class, 'add_page' ) );
		add_action( 'admin_init', array( self::class, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_media' ) );
	}

	public static function add_page(): void {
		add_submenu_page(
			'edit.php?post_type=popi_clanky',
			'Výchozí hodnoty',
			'Výchozí hodnoty',
			'manage_options',
			'popi-clanky-settings',
			array( self::class, 'render_page' )
		);
	}

	public static function enqueue_media( $hook ): void {
		if ( 'popi_clanky_page_popi-clanky-settings' !== $hook ) {
			return;
		}
		wp_enqueue_media();
	}

	public static function register_settings(): void {
		register_setting(
			'popi_clanky_settings_group',
			self::OPTION_KEY,
			array( 'sanitize_callback' => array( self::class, 'sanitize' ) )
		);

		// ── Sekce: SEO & Sdílení ───────────────────────────────────────────────
		add_settings_section( 'popi_clanky_section_seo', 'SEO & Sdílení', null, 'popi-clanky-settings' );

		self::add_field( 'meta_title',        'Meta title',             'text',     'popi_clanky_section_seo', 'Název článku | Vyletustecko.cz' );
		self::add_field( 'meta_title_suffix', 'Přípona meta title',     'text',     'popi_clanky_section_seo', '| Vyletustecko.cz' );
		self::add_field( 'meta_desc',         'Meta description',       'textarea', 'popi_clanky_section_seo', '' );
		self::add_field( 'og_image',          'OG obrázek (soc. sítě)', 'image',    'popi_clanky_section_seo', '' );

		// ── Sekce: CTA ─────────────────────────────────────────────────────────
		add_settings_section( 'popi_clanky_section_cta', 'Výchozí CTA tlačítko', null, 'popi-clanky-settings' );

		self::add_field( 'cta_text', 'Text tlačítka', 'text', 'popi_clanky_section_cta', 'Koupit vstupenky online' );
		self::add_field( 'cta_url',  'URL',           'url',  'popi_clanky_section_cta', 'https://' );

		// ── Sekce: UTM ─────────────────────────────────────────────────────────
		add_settings_section(
			'popi_clanky_section_utm',
			'UTM parametry',
			function() { echo '<p>Výchozí UTM parametry přidávané automaticky ke každé CTA URL.</p>'; },
			'popi-clanky-settings'
		);

		self::add_field( 'utm_source', 'UTM source', 'text', 'popi_clanky_section_utm', 'vyletustecko' );
		self::add_field( 'utm_medium', 'UTM medium', 'text', 'popi_clanky_section_utm', 'clanek' );
	}

	private static function add_field( string $field, string $label, string $type, string $section, string $placeholder ): void {
		$callback = 'image' === $type ? array( self::class, 'field_image' ) : array( self::class, 'field_input' );
		add_settings_field(
			$field, $label, $callback,
			'popi-clanky-settings', $section,
			array( 'field' => $field, 'type' => $type, 'placeholder' => $placeholder )
		);
	}

	// ── Výstup polí ────────────────────────────────────────────────────────────

	public static function field_input( array $args ): void {
		$values = get_option( self::OPTION_KEY, array() );
		$value  = $values[ $args['field'] ] ?? '';
		$type   = 'textarea' === $args['type'] ? 'textarea' : ( 'url' === $args['type'] ? 'url' : 'text' );

		if ( 'textarea' === $type ) {
			printf(
				'<textarea name="%s[%s]" placeholder="%s" class="regular-text" rows="3">%s</textarea>',
				esc_attr( self::OPTION_KEY ),
				esc_attr( $args['field'] ),
				esc_attr( $args['placeholder'] ),
				esc_textarea( $value )
			);
		} else {
			printf(
				'<input type="%s" name="%s[%s]" value="%s" placeholder="%s" class="regular-text">',
				esc_attr( $type ),
				esc_attr( self::OPTION_KEY ),
				esc_attr( $args['field'] ),
				esc_attr( $value ),
				esc_attr( $args['placeholder'] )
			);
		}
	}

	public static function field_image( array $args ): void {
		$values    = get_option( self::OPTION_KEY, array() );
		$image_id  = intval( $values[ $args['field'] ] ?? 0 );
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
		<?php
	}

	// ── Sanitizace ─────────────────────────────────────────────────────────────

	public static function sanitize( array $input ): array {
		return array(
			'meta_title'        => sanitize_text_field( $input['meta_title'] ?? '' ),
			'meta_title_suffix' => sanitize_text_field( $input['meta_title_suffix'] ?? '' ),
			'meta_desc'         => sanitize_textarea_field( $input['meta_desc'] ?? '' ),
			'og_image'          => absint( $input['og_image'] ?? 0 ),
			'cta_text'          => sanitize_text_field( $input['cta_text'] ?? '' ),
			'cta_url'           => esc_url_raw( $input['cta_url'] ?? '' ),
			'utm_source'        => sanitize_text_field( $input['utm_source'] ?? '' ),
			'utm_medium'        => sanitize_text_field( $input['utm_medium'] ?? '' ),
		);
	}

	// ── Stránka ────────────────────────────────────────────────────────────────

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1>Články blog — Výchozí hodnoty</h1>
			<p>Hodnoty se předvyplní při vytvoření každého nového článku. Na konkrétním článku je lze přepsat.</p>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'popi_clanky_settings_group' );
				do_settings_sections( 'popi-clanky-settings' );
				submit_button( 'Uložit výchozí hodnoty' );
				?>
			</form>
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

	// ── Gettery ────────────────────────────────────────────────────────────────

	public static function get( string $key, string $fallback = '' ): string {
		$values = get_option( self::OPTION_KEY, array() );
		return ! empty( $values[ $key ] ) ? (string) $values[ $key ] : $fallback;
	}

	public static function get_int( string $key, int $fallback = 0 ): int {
		$values = get_option( self::OPTION_KEY, array() );
		return ! empty( $values[ $key ] ) ? (int) $values[ $key ] : $fallback;
	}
}
