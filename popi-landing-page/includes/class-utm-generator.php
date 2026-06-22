<?php
defined( 'ABSPATH' ) || exit;

/**
 * Generuje UTM odkazy na landing page pro jednotlivé reklamní/distribuční kanály.
 *
 * Na rozdíl od CTA URL (viz functions.php popi_lp_cta_url) tyto odkazy vedou
 * NA samotnou landing page — používají se jako cílové URL v Google Ads, Sklik,
 * Meta Ads apod., tedy tam, kam se vkládá odkaz na inzerát/příspěvek.
 */
class Popi_Landing_Utm_Generator {

	const CHANNELS = array(
		'sklik'             => array( 'label' => 'Sklik (Seznam)',         'utm_source' => 'sklik',      'utm_medium' => 'cpc' ),
		'google_ads'        => array( 'label' => 'Google Ads',             'utm_source' => 'google',     'utm_medium' => 'cpc' ),
		'meta_ads'          => array( 'label' => 'Meta Ads (Facebook)',    'utm_source' => 'facebook',   'utm_medium' => 'cpc' ),
		'instagram_organic' => array( 'label' => 'Instagram (organicky)',  'utm_source' => 'instagram',  'utm_medium' => 'social' ),
		'linkedin_organic'  => array( 'label' => 'LinkedIn (organicky)',   'utm_source' => 'linkedin',   'utm_medium' => 'social' ),
		'linkedin_ads'      => array( 'label' => 'LinkedIn Ads',          'utm_source' => 'linkedin',   'utm_medium' => 'cpc' ),
		'newsletter'        => array( 'label' => 'Newsletter',             'utm_source' => 'newsletter', 'utm_medium' => 'email' ),
	);

	const UTM_KAMPAN_FIELD_KEY = 'field_popi_utm_kampan';

	public static function init(): void {
		add_action( 'add_meta_boxes', array( self::class, 'add_meta_box' ) );
	}

	public static function add_meta_box(): void {
		add_meta_box(
			'popi_landing_utm_generator',
			'UTM odkazy pro kampaně (popi-landing-page)',
			array( self::class, 'render_meta_box' ),
			'popi_landing',
			'normal',
			'high'
		);
	}

	/**
	 * Vrátí trvalou adresu LP — pro koncepty dopočítá adresu ze slugu,
	 * protože get_permalink() u auto-draftu/konceptu ještě nevrací finální URL.
	 */
	private static function permalink_for( WP_Post $post ): string {
		$permalink = get_permalink( $post->ID );
		if ( in_array( $post->post_status, array( 'draft', 'pending', 'auto-draft' ), true ) ) {
			list( $permalink_sample, $post_name ) = get_sample_permalink( $post->ID );
			$slug = $post_name ?: $post->post_name;
			if ( $slug && strpos( $permalink_sample, '%postname%' ) !== false ) {
				$permalink = str_replace( '%postname%', $slug, $permalink_sample );
			}
		}
		return $permalink ?: '';
	}

	/**
	 * Vrátí UTM odkazy na landing page pro všechny kanály.
	 *
	 * @return array<string,string> klíč kanálu => kompletní URL
	 */
	public static function urls( int $post_id ): array {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return array();
		}

		$base_url = self::permalink_for( $post );
		if ( ! $base_url ) {
			return array();
		}

		$campaign = get_field( 'popi_lp_utm_kampan', $post_id ) ?: $post->post_name;

		$urls = array();
		foreach ( self::CHANNELS as $key => $channel ) {
			$params = array_filter( array(
				'utm_source'   => $channel['utm_source'],
				'utm_medium'   => $channel['utm_medium'],
				'utm_campaign' => $campaign,
			) );
			$separator   = strpos( $base_url, '?' ) !== false ? '&' : '?';
			$urls[ $key ] = $base_url . $separator . http_build_query( $params );
		}

		return $urls;
	}

	public static function render_meta_box( WP_Post $post ): void {
		$base_url = self::permalink_for( $post );
		if ( ! $base_url ) {
			echo '<p>Nejdřív ulož landing page (vyplň název a klikni na Uložit jako koncept) — odkazy se generují podle URL adresy stránky.</p>';
			return;
		}

		$campaign = get_field( 'popi_lp_utm_kampan', $post->ID ) ?: $post->post_name;
		?>
		<div class="popi-utm-generator-wrap">
			<p>Hotové odkazy pro nastavení cílové URL v reklamních kampaních. Skládají se z trvalé adresy této LP a názvu kampaně níže.</p>

			<p>
				<label for="popi_utm_campaign_input" style="display:block;font-weight:600;margin-bottom:4px;">Název kampaně (utm_campaign):</label>
				<input type="text" id="popi_utm_campaign_input" value="<?php echo esc_attr( $campaign ); ?>" class="regular-text" placeholder="např. vylet-deti-teplice">
				<span class="description" style="display:block;margin-top:4px;">Změna se promítne do ACF pole <em>UTM campaign</em> (sekce SEA — Kampaň) a uloží se při uložení landing page.</span>
			</p>

			<table class="widefat striped popi-utm-table">
				<thead>
					<tr>
						<th style="width:170px;">Kanál</th>
						<th>Cílový odkaz pro reklamu</th>
						<th style="width:110px;text-align:right;">Akce</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( self::CHANNELS as $key => $channel ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $channel['label'] ); ?></strong></td>
							<td>
								<code class="popi-utm-link" data-source="<?php echo esc_attr( $channel['utm_source'] ); ?>" data-medium="<?php echo esc_attr( $channel['utm_medium'] ); ?>" style="word-break:break-all;"></code>
							</td>
							<td style="text-align:right;"><button type="button" class="button button-small popi-utm-copy">Kopírovat</button></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<script>
		(function($){
			var baseUrl = <?php echo wp_json_encode( $base_url ); ?>;

			function slugify( value ) {
				return value.toLowerCase()
					.normalize( 'NFD' ).replace( /[̀-ͯ]/g, '' )
					.replace( /[^a-z0-9\-_]+/g, '-' )
					.replace( /-+/g, '-' )
					.replace( /^-|-$/g, '' );
			}

			function updateLinks() {
				var campaign = slugify( $( '#popi_utm_campaign_input' ).val().trim() );

				$( '.popi-utm-link' ).each( function () {
					var params = [
						'utm_source=' + encodeURIComponent( $( this ).data( 'source' ) ),
						'utm_medium=' + encodeURIComponent( $( this ).data( 'medium' ) )
					];
					if ( campaign ) {
						params.push( 'utm_campaign=' + encodeURIComponent( campaign ) );
					}
					var separator = baseUrl.indexOf( '?' ) !== -1 ? '&' : '?';
					$( this ).text( baseUrl + separator + params.join( '&' ) );
				} );
			}

			$( '#popi_utm_campaign_input' ).on( 'input', function () {
				var $acfField = $( 'input[name="acf[<?php echo esc_js( self::UTM_KAMPAN_FIELD_KEY ); ?>]"]' );
				if ( $acfField.length ) {
					$acfField.val( $( this ).val() );
				}
				updateLinks();
			} );

			$( document ).on( 'input', 'input[name="acf[<?php echo esc_js( self::UTM_KAMPAN_FIELD_KEY ); ?>]"]', function () {
				$( '#popi_utm_campaign_input' ).val( $( this ).val() );
				updateLinks();
			} );

			updateLinks();

			$( document ).on( 'click', '.popi-utm-copy', function () {
				var $btn  = $( this );
				var text  = $btn.closest( 'tr' ).find( '.popi-utm-link' ).text();
				navigator.clipboard.writeText( text ).then( function () {
					var original = $btn.text();
					$btn.text( 'Zkopírováno!' );
					setTimeout( function () { $btn.text( original ); }, 1500 );
				} );
			} );
		})( jQuery );
		</script>
		<?php
	}
}
