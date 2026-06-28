<?php
defined( 'ABSPATH' ) || exit;

/**
 * Schema.org strukturovana data (JSON-LD) pro Google rich results clanku.
 *
 * Typ Article/BlogPosting — na rozdil od popi-events (kde schema musela
 * resit noindex terminy), clanek je vzdy plne indexovana stranka, takze
 * schema se vypisuje primo na nem, zadne obchazeni.
 */
class Popi_Clanky_Schema {

	public static function init(): void {
		add_action( 'wp_head', array( self::class, 'output' ) );
	}

	public static function output(): void {
		if ( ! is_singular( 'popi_clanky' ) ) {
			return;
		}

		$post_id = get_the_ID();

		$article = array(
			'@context'      => 'https://schema.org',
			'@type'         => 'BlogPosting',
			'headline'      => get_the_title( $post_id ),
			'datePublished' => get_the_date( 'c', $post_id ),
			'dateModified'  => get_the_modified_date( 'c', $post_id ),
			'mainEntityOfPage' => array(
				'@type' => 'WebPage',
				'@id'   => get_permalink( $post_id ),
			),
		);

		$image_url = self::image_url( $post_id );
		if ( $image_url ) {
			$article['image'] = array( $image_url );
		}

		$meta_desc = popi_clanky_get_field( 'popi_clanek_meta_desc', $post_id );
		if ( $meta_desc ) {
			$article['description'] = wp_strip_all_tags( $meta_desc );
		}

		$author_name = get_the_author_meta( 'display_name', (int) get_post_field( 'post_author', $post_id ) );
		if ( $author_name ) {
			$article['author'] = array(
				'@type' => 'Person',
				'name'  => $author_name,
			);
		}

		$publisher = self::publisher();
		if ( $publisher ) {
			$article['publisher'] = $publisher;
		}

		$json = wp_json_encode( $article, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		echo '<script type="application/ld+json">' . $json . '</script>' . "\n";
	}

	private static function image_url( int $post_id ): ?string {
		$og_image = popi_clanky_get_field( 'popi_clanek_og_image', $post_id );
		if ( is_array( $og_image ) && ! empty( $og_image['url'] ) ) {
			return $og_image['url'];
		}
		$thumbnail = get_the_post_thumbnail_url( $post_id, 'large' );
		return $thumbnail ?: null;
	}

	private static function publisher(): ?array {
		$name = Popi_Clanky_Settings::get( 'publisher_name', get_bloginfo( 'name' ) );
		if ( ! $name ) {
			return null;
		}

		$publisher = array(
			'@type' => 'Organization',
			'name'  => $name,
		);

		$logo_id  = Popi_Clanky_Settings::get_int( 'publisher_logo' );
		$logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : get_site_icon_url();
		if ( $logo_url ) {
			$publisher['logo'] = array(
				'@type' => 'ImageObject',
				'url'   => $logo_url,
			);
		}

		return $publisher;
	}
}
