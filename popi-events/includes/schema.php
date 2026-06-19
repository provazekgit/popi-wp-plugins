<?php

namespace Popi\Events;

defined( 'ABSPATH' ) || exit;

/**
 * Schema.org strukturovana data (JSON-LD) pro Google rich results.
 *
 * Dulezite architektonicke rozhodnuti: schema se vypisuje na strance KURZU,
 * ne na jednotlivem terminu. Duvod: terminy jsou typicky noindex (viz Seo) —
 * Google rich results se nezobrazi pro neindexovanou stranku, takze schema
 * na terminu by bylo k nicemu. Kurz je indexovana stranka, takze na ni
 * vypiseme vsechny nadchazejici terminy jako pole Event objektu (@graph).
 */
class Schema {

	public static function init(): void {
		add_action( 'wp_head', array( self::class, 'output' ) );
	}

	public static function output(): void {
		if ( ! is_singular( Cpt_Course::POST_TYPE ) ) {
			return;
		}

		$course_id = get_the_ID();
		$events    = \popi_events_get_course_events( $course_id, true );
		if ( ! $events ) {
			return;
		}

		$graph = array();
		foreach ( $events as $event ) {
			$entry = self::build_event( $course_id, $event->ID );
			if ( $entry ) {
				$graph[] = $entry;
			}
		}

		if ( ! $graph ) {
			return;
		}

		$json = wp_json_encode(
			array(
				'@context' => 'https://schema.org',
				'@graph'   => $graph,
			),
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		);

		echo '<script type="application/ld+json">' . $json . '</script>' . "\n";
	}

	private static function build_event( int $course_id, int $event_id ): ?array {
		$date_start = get_field( 'popi_event_date_start', $event_id );
		if ( ! $date_start ) {
			return null;
		}

		$location = get_field( 'popi_event_location', $event_id );

		$event = array(
			'@type'    => 'Event',
			'name'     => get_the_title( $course_id ),
			'startDate' => self::iso_datetime( $date_start, get_field( 'popi_event_time_start', $event_id ) ),
			'url'      => get_permalink( $event_id ),
			'eventAttendanceMode' => self::attendance_mode_url( get_field( 'popi_event_attendance_mode', $event_id ) ?: 'offline' ),
			'eventStatus' => 'https://schema.org/EventScheduled',
		);

		$time_end = get_field( 'popi_event_time_end', $event_id );
		if ( $time_end ) {
			$event['endDate'] = self::iso_datetime( $date_start, $time_end );
		}

		if ( $location ) {
			$event['location'] = array(
				'@type' => 'Place',
				'name'  => $location,
			);
		}

		$description = get_field( 'popi_event_note', $event_id ) ?: get_field( 'popi_course_meta_desc', $course_id );
		if ( $description ) {
			$event['description'] = wp_strip_all_tags( $description );
		}

		$image_url = self::course_image_url( $course_id );
		if ( $image_url ) {
			$event['image'] = $image_url;
		}

		$offers = self::build_offers( $event_id );
		if ( $offers ) {
			$event['offers'] = $offers;
		}

		$event['organizer'] = array(
			'@type' => 'Organization',
			'name'  => Settings::get( 'organizer_name', get_bloginfo( 'name' ) ),
			'url'   => Settings::get( 'organizer_url', home_url( '/' ) ),
		);

		return $event;
	}

	private static function build_offers( int $event_id ): ?array {
		$price_raw = get_field( 'popi_event_price', $event_id );
		$price     = self::parse_price( (string) $price_raw );
		if ( null === $price ) {
			return null;
		}

		return array(
			'@type'         => 'Offer',
			'price'         => $price,
			'priceCurrency' => Settings::get( 'currency', 'CZK' ),
			'availability'  => 'https://schema.org/InStock',
			'url'           => get_permalink( $event_id ),
		);
	}

	/**
	 * Vytahne prvni ciselnou hodnotu z volneho textu ceny (napr. "2 990 Kč" -> "2990").
	 * Vraci null pokud cena neobsahuje zadne cislo (napr. "na dotaz") — schema pak
	 * offers vubec nevypise, protoze necisene "price" by bylo neplatne strukturovane data.
	 */
	private static function parse_price( string $price_raw ): ?string {
		$digits = preg_replace( '/[^0-9]/', '', $price_raw );
		return '' !== $digits ? $digits : null;
	}

	private static function attendance_mode_url( string $mode ): string {
		$map = array(
			'offline' => 'https://schema.org/OfflineEventAttendanceMode',
			'online'  => 'https://schema.org/OnlineEventAttendanceMode',
			'mixed'   => 'https://schema.org/MixedEventAttendanceMode',
		);
		return $map[ $mode ] ?? $map['offline'];
	}

	private static function iso_datetime( string $date, ?string $time ): string {
		$time = $time && preg_match( '/^\d{1,2}:\d{2}/', $time ) ? $time : '00:00';
		$dt   = date_create( $date . ' ' . $time, wp_timezone() );
		return $dt ? $dt->format( 'c' ) : $date;
	}

	private static function course_image_url( int $course_id ): ?string {
		$og_image = get_field( 'popi_course_og_image', $course_id );
		if ( is_array( $og_image ) && ! empty( $og_image['url'] ) ) {
			return $og_image['url'];
		}
		$thumbnail = get_the_post_thumbnail_url( $course_id, 'large' );
		return $thumbnail ?: null;
	}
}
