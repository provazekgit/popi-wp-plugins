<?php

defined( 'ABSPATH' ) || exit;

final class POPI_Connector_Outbox {

	public static function init() {
		add_action( 'save_post', array( __CLASS__, 'capture_post_change' ), 20, 3 );
		add_action( POPI_Connector_Installer::CRON_HOOK, array( __CLASS__, 'run_maintenance' ) );
		add_action( 'popi_connector_dispatch_outbox', array( __CLASS__, 'dispatch' ) );
	}

	public static function capture_post_change( $post_id, $post, $update ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || ! $post instanceof WP_Post ) {
			return;
		}
		foreach ( POPI_Connector_Storage::list_bindings( 'active' ) as $binding ) {
			$config = POPI_Connector_Storage::binding_config( $binding );
			$types  = isset( $config['allowed_post_types'] ) && is_array( $config['allowed_post_types'] ) ? array_map( 'sanitize_key', $config['allowed_post_types'] ) : ( 'popicast' === $binding['module'] ? array( 'podcast', 'episode' ) : array( 'post', 'page' ) );
			if ( ! in_array( $post->post_type, $types, true ) ) {
				continue;
			}
			$scopes = POPI_Connector_Storage::binding_scopes( $binding );
			$required_scope = 'popicast' === $binding['module'] ? 'popicast.events:emit' : 'popiweb.events:emit';
			if ( ! in_array( $required_scope, $scopes, true ) ) {
				continue;
			}
			self::enqueue(
				$binding['binding_id'],
				'content.changed',
				array(
					'post_id'      => (int) $post_id,
					'post_type'    => $post->post_type,
					'status'       => $post->post_status,
					'modified_gmt' => $post->post_modified_gmt,
					'change'       => $update ? 'updated' : 'created',
				)
			);
		}
		if ( ! wp_next_scheduled( 'popi_connector_dispatch_outbox' ) ) {
			wp_schedule_single_event( time() + 30, 'popi_connector_dispatch_outbox' );
		}
	}

	public static function enqueue( $binding_id, $event_name, $payload ) {
		global $wpdb;
		$table = POPI_Connector_Storage::tables()['outbox'];
		$now   = POPI_Connector_Storage::utc_now();
		$wpdb->insert(
			$table,
			array(
				'event_id'       => wp_generate_uuid4(),
				'binding_id'     => sanitize_text_field( $binding_id ),
				'event_name'     => sanitize_key( $event_name ),
				'payload'        => wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
				'status'         => 'pending',
				'attempts'       => 0,
				'next_attempt_at'=> $now,
				'created_at'     => $now,
				'updated_at'     => $now,
			)
		);
	}

	public static function dispatch() {
		global $wpdb;
		$table = POPI_Connector_Storage::tables()['outbox'];
		$rows  = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM $table WHERE status IN ('pending','retry') AND next_attempt_at <= %s ORDER BY id ASC LIMIT 20", POPI_Connector_Storage::utc_now() ),
			ARRAY_A
		);
		foreach ( $rows as $row ) {
			$binding = POPI_Connector_Storage::get_binding( $row['binding_id'] );
			if ( ! $binding || 'active' !== $binding['status'] ) {
				$wpdb->update( $table, array( 'status' => 'dead', 'last_error' => 'Binding is not active', 'updated_at' => POPI_Connector_Storage::utc_now() ), array( 'id' => $row['id'] ) );
				continue;
			}
			$payload = json_decode( $row['payload'], true );
			$result  = POPI_Connector_Remote::signed_post(
				$binding,
				'/api/v1/connectors/wordpress/events',
				array( 'event_id' => $row['event_id'], 'event_name' => $row['event_name'], 'occurred_at' => $row['created_at'] . 'Z', 'data' => is_array( $payload ) ? $payload : array() )
			);
			if ( ! is_wp_error( $result ) ) {
				$wpdb->update( $table, array( 'status' => 'sent', 'attempts' => (int) $row['attempts'] + 1, 'last_error' => null, 'updated_at' => POPI_Connector_Storage::utc_now() ), array( 'id' => $row['id'] ) );
				continue;
			}
			$attempts = (int) $row['attempts'] + 1;
			$status   = $attempts >= 10 ? 'dead' : 'retry';
			$delay    = min( DAY_IN_SECONDS, 60 * ( 2 ** min( 8, $attempts ) ) );
			$wpdb->update(
				$table,
				array(
					'status'          => $status,
					'attempts'        => $attempts,
					'next_attempt_at' => gmdate( 'Y-m-d H:i:s', time() + $delay ),
					'last_error'      => substr( $result->get_error_code() . ': ' . $result->get_error_message(), 0, 500 ),
					'updated_at'      => POPI_Connector_Storage::utc_now(),
				),
				array( 'id' => $row['id'] )
			);
		}
	}

	public static function run_maintenance() {
		self::dispatch();
		foreach ( POPI_Connector_Storage::list_bindings( 'active' ) as $binding ) {
			POPI_Connector_Remote::report_health( $binding );
		}
		POPI_Connector_Storage::cleanup();
	}
}
