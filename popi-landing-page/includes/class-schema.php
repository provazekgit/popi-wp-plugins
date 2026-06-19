<?php
defined( 'ABSPATH' ) || exit;

/**
 * Schema.org strukturovana data (JSON-LD) — typ Product s vice cenovymi
 * variantami v poli offers (jednotlivec/dite/rodina/skupina).
 *
 * Vypisuje se jen pokud je na LP vyplnena alespon jedna cena — bez ceny
 * by Product schema bylo neuplne/neplatne, takze se v tom pripade necha
 * stranku zcela bez schema (lepsi nic, nez spatne strukturovana data).
 */
class Popi_Landing_Schema {

	public static function init(): void {
		add_action( 'wp_head', array( self::class, 'output' ) );
	}

	public static function output(): void {
		if ( ! is_singular( 'popi_landing' ) ) {
			return;
		}

		$post_id = get_the_ID();
		$offers  = self::build_offers( $post_id );
		if ( ! $offers ) {
			return;
		}

		$product = array(
			'@context' => 'https://schema.org',
			'@type'    => 'Product',
			'name'     => get_the_title( $post_id ),
			'offers'   => $offers,
		);

		$description = get_field( 'popi_lp_meta_desc', $post_id ) ?: get_field( 'popi_lp_hlavni_popis', $post_id );
		if ( $description ) {
			$product['description'] = wp_strip_all_tags( $description );
		}

		$image_url = self::image_url( $post_id );
		if ( $image_url ) {
			$product['image'] = array( $image_url );
		}

		$json = wp_json_encode( $product, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		echo '<script type="application/ld+json">' . $json . '</script>' . "\n";
	}

	private static function build_offers( int $post_id ): array {
		$currency = Popi_Landing_Settings::get( 'currency', 'CZK' );
		$url      = get_permalink( $post_id );

		$offers = array();
		foreach ( popi_lp_pricing( $post_id ) as $label => $price_raw ) {
			$price = self::parse_price( (string) $price_raw );
			if ( null === $price ) {
				continue;
			}
			$offers[] = array(
				'@type'         => 'Offer',
				'name'          => $label,
				'price'         => $price,
				'priceCurrency' => $currency,
				'availability'  => 'https://schema.org/InStock',
				'url'           => $url,
			);
		}

		return $offers;
	}

	/**
	 * Vytahne prvni ciselnou hodnotu z volneho textu ceny (napr. "250 Kč" -> "250").
	 * Vraci null pokud cena neobsahuje zadne cislo.
	 */
	private static function parse_price( string $price_raw ): ?string {
		$digits = preg_replace( '/[^0-9]/', '', $price_raw );
		return '' !== $digits ? $digits : null;
	}

	private static function image_url( int $post_id ): ?string {
		$og_image = get_field( 'popi_lp_og_image', $post_id );
		if ( is_array( $og_image ) && ! empty( $og_image['url'] ) ) {
			return $og_image['url'];
		}
		$thumbnail = get_the_post_thumbnail_url( $post_id, 'large' );
		return $thumbnail ?: null;
	}
}
