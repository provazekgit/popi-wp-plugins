<?php

defined( 'ABSPATH' ) || exit;

final class POPI_Connector_Audit {

	public static function record( $event, $result, $args = array() ) {
		global $wpdb;
		$table = POPI_Connector_Storage::tables()['audit'];
		$data  = array(
			'binding_id' => isset( $args['binding_id'] ) ? sanitize_text_field( $args['binding_id'] ) : null,
			'request_id' => isset( $args['request_id'] ) ? sanitize_text_field( $args['request_id'] ) : null,
			'event'      => sanitize_key( $event ),
			'result'     => sanitize_key( $result ),
			'actor_type' => isset( $args['actor_type'] ) ? sanitize_key( $args['actor_type'] ) : 'system',
			'actor_id'   => isset( $args['actor_id'] ) ? sanitize_text_field( $args['actor_id'] ) : null,
			'metadata'   => wp_json_encode( self::sanitize_metadata( isset( $args['metadata'] ) ? $args['metadata'] : array() ) ),
			'created_at' => POPI_Connector_Storage::utc_now(),
		);
		$wpdb->insert( $table, $data );
	}

	public static function recent( $limit = 100, $binding_id = null ) {
		global $wpdb;
		$table = POPI_Connector_Storage::tables()['audit'];
		$limit = max( 1, min( 500, (int) $limit ) );
		if ( $binding_id ) {
			return $wpdb->get_results(
				$wpdb->prepare( "SELECT * FROM $table WHERE binding_id = %s ORDER BY id DESC LIMIT %d", $binding_id, $limit ),
				ARRAY_A
			);
		}
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table ORDER BY id DESC LIMIT %d", $limit ), ARRAY_A );
	}

	private static function sanitize_metadata( $value, $depth = 0 ) {
		if ( $depth > 3 ) {
			return '[truncated]';
		}
		if ( is_array( $value ) ) {
			$output = array();
			foreach ( array_slice( $value, 0, 30, true ) as $key => $item ) {
				$safe_key = is_string( $key ) ? sanitize_key( $key ) : $key;
				if ( in_array( $safe_key, array( 'secret', 'signature', 'payload', 'content', 'password', 'token' ), true ) ) {
					$output[ $safe_key ] = '[redacted]';
					continue;
				}
				$output[ $safe_key ] = self::sanitize_metadata( $item, $depth + 1 );
			}
			return $output;
		}
		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) || null === $value ) {
			return $value;
		}
		return substr( sanitize_text_field( (string) $value ), 0, 500 );
	}
}

