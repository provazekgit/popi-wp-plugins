<?php

defined( 'ABSPATH' ) || exit;

final class POPI_Connector_Storage {

	public static function tables() {
		global $wpdb;
		$prefix = $wpdb->prefix . 'popi_connector_';
		return array(
			'bindings'    => $prefix . 'bindings',
			'keys'        => $prefix . 'keys',
			'nonces'      => $prefix . 'nonces',
			'rate_limits' => $prefix . 'rate_limits',
			'audit'       => $prefix . 'audit',
			'outbox'      => $prefix . 'outbox',
		);
	}

	public static function utc_now() {
		return gmdate( 'Y-m-d H:i:s' );
	}

	public static function get_binding( $binding_id ) {
		global $wpdb;
		$table = self::tables()['bindings'];
		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM $table WHERE binding_id = %s LIMIT 1", $binding_id ),
			ARRAY_A
		);
	}

	public static function get_binding_by_installation( $installation_id ) {
		global $wpdb;
		$table = self::tables()['bindings'];
		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM $table WHERE installation_id = %s LIMIT 1", $installation_id ),
			ARRAY_A
		);
	}

	public static function get_binding_by_key( $key_id ) {
		global $wpdb;
		$tables = self::tables();
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT b.*, k.key_id, k.secret_cipher, k.status AS key_status, k.not_before, k.expires_at
				 FROM {$tables['keys']} k
				 INNER JOIN {$tables['bindings']} b ON b.binding_id = k.binding_id
				 WHERE k.key_id = %s LIMIT 1",
				$key_id
			),
			ARRAY_A
		);
	}

	public static function active_key_for_binding( $binding_id ) {
		global $wpdb;
		$table = self::tables()['keys'];
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE binding_id = %s AND status = 'active' ORDER BY id DESC LIMIT 1",
				$binding_id
			),
			ARRAY_A
		);
	}

	public static function list_bindings( $status = null ) {
		global $wpdb;
		$table = self::tables()['bindings'];
		if ( null !== $status ) {
			return $wpdb->get_results(
				$wpdb->prepare( "SELECT * FROM $table WHERE status = %s ORDER BY id ASC", $status ),
				ARRAY_A
			);
		}
		return $wpdb->get_results( "SELECT * FROM $table ORDER BY id ASC", ARRAY_A );
	}

	public static function save_binding( $binding, $key_id, $secret ) {
		global $wpdb;
		$tables = self::tables();
		$now    = self::utc_now();

		$required = array( 'binding_id', 'connection_id', 'tenant_id', 'project_id', 'installation_id', 'module', 'scopes' );
		foreach ( $required as $field ) {
			if ( ! isset( $binding[ $field ] ) || '' === $binding[ $field ] ) {
				return new WP_Error( 'popi_binding_invalid', 'POPIsite vrátil neúplné údaje připojení.' );
			}
		}

		$cipher = POPI_Connector_Crypto::encrypt_secret( $secret );
		if ( is_wp_error( $cipher ) ) {
			return $cipher;
		}

		$existing = self::get_binding( $binding['binding_id'] );
		if ( ! $existing ) {
			$existing = self::get_binding_by_installation( $binding['installation_id'] );
		}
		$data     = array(
			'binding_id'     => sanitize_text_field( $binding['binding_id'] ),
			'connection_id'  => sanitize_text_field( $binding['connection_id'] ),
			'tenant_id'      => sanitize_text_field( $binding['tenant_id'] ),
			'project_id'     => sanitize_text_field( $binding['project_id'] ),
			'installation_id'=> sanitize_text_field( $binding['installation_id'] ),
			'module'         => sanitize_key( $binding['module'] ),
			'status'         => 'active',
			'scopes'         => wp_json_encode( array_values( array_unique( array_map( 'sanitize_text_field', (array) $binding['scopes'] ) ) ) ),
			'config'         => wp_json_encode( isset( $binding['config'] ) && is_array( $binding['config'] ) ? $binding['config'] : array() ),
			'updated_at'     => $now,
		);

		$wpdb->query( 'START TRANSACTION' );
		if ( $existing ) {
			$binding_saved = $wpdb->update( $tables['bindings'], $data, array( 'id' => $existing['id'] ) );
		} else {
			$data['created_at'] = $now;
			$binding_saved = $wpdb->insert( $tables['bindings'], $data );
		}
		if ( false === $binding_saved ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'popi_binding_store_failed', 'Připojení se nepodařilo uložit do databáze.' );
		}

		if ( $existing && $existing['binding_id'] !== $binding['binding_id'] ) {
			$wpdb->query( $wpdb->prepare( "UPDATE {$tables['keys']} SET status = 'revoked', updated_at = %s WHERE binding_id = %s", $now, $existing['binding_id'] ) );
		}
		$wpdb->query( $wpdb->prepare( "UPDATE {$tables['keys']} SET status = 'retiring', updated_at = %s WHERE binding_id = %s AND status = 'active'", $now, $binding['binding_id'] ) );
		$key_data = array(
			'binding_id'   => sanitize_text_field( $binding['binding_id'] ),
			'key_id'       => sanitize_text_field( $key_id ),
			'secret_cipher'=> $cipher,
			'status'       => 'active',
			'created_at'   => $now,
			'updated_at'   => $now,
		);
		$existing_key = self::get_binding_by_key( $key_id );
		if ( $existing_key ) {
			$key_saved = $wpdb->update( $tables['keys'], $key_data, array( 'key_id' => $key_id ) );
		} else {
			$key_saved = $wpdb->insert( $tables['keys'], $key_data );
		}
		if ( false === $key_saved ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'popi_key_store_failed', 'Klíč připojení se nepodařilo uložit do databáze.' );
		}

		$wpdb->query( 'COMMIT' );
		return true;
	}

	public static function mark_seen( $binding_id ) {
		global $wpdb;
		$wpdb->update(
			self::tables()['bindings'],
			array( 'last_seen_at' => self::utc_now(), 'updated_at' => self::utc_now() ),
			array( 'binding_id' => $binding_id )
		);
	}

	public static function revoke_binding( $binding_id ) {
		global $wpdb;
		$tables = self::tables();
		$now    = self::utc_now();
		$wpdb->update( $tables['bindings'], array( 'status' => 'revoked', 'updated_at' => $now ), array( 'binding_id' => $binding_id ) );
		$wpdb->query( $wpdb->prepare( "UPDATE {$tables['keys']} SET status = 'revoked', updated_at = %s WHERE binding_id = %s", $now, $binding_id ) );
	}

	public static function store_pending_key( $binding_id, $key_id, $secret ) {
		global $wpdb;
		$cipher = POPI_Connector_Crypto::encrypt_secret( $secret );
		if ( is_wp_error( $cipher ) ) {
			return $cipher;
		}
		$table = self::tables()['keys'];
		$now   = self::utc_now();
		$ok    = $wpdb->insert(
			$table,
			array(
				'binding_id'    => sanitize_text_field( $binding_id ),
				'key_id'        => sanitize_text_field( $key_id ),
				'secret_cipher' => $cipher,
				'status'        => 'pending',
				'created_at'    => $now,
				'updated_at'    => $now,
			)
		);
		return false === $ok ? new WP_Error( 'popi_key_store_failed', 'Nový klíč se nepodařilo uložit.' ) : true;
	}

	public static function activate_key( $binding_id, $key_id, $grace_seconds = 300 ) {
		global $wpdb;
		$table   = self::tables()['keys'];
		$now     = self::utc_now();
		$expires = gmdate( 'Y-m-d H:i:s', time() + max( 60, min( 3600, (int) $grace_seconds ) ) );
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE $table SET status = 'retiring', expires_at = %s, updated_at = %s WHERE binding_id = %s AND status = 'active'",
				$expires,
				$now,
				$binding_id
			)
		);
		$updated = $wpdb->update(
			$table,
			array( 'status' => 'active', 'not_before' => $now, 'expires_at' => null, 'updated_at' => $now ),
			array( 'binding_id' => $binding_id, 'key_id' => $key_id, 'status' => 'pending' )
		);
		return false !== $updated && $updated > 0;
	}

	public static function binding_scopes( $binding ) {
		$scopes = isset( $binding['scopes'] ) ? json_decode( $binding['scopes'], true ) : array();
		return is_array( $scopes ) ? $scopes : array();
	}

	public static function binding_config( $binding ) {
		$config = isset( $binding['config'] ) ? json_decode( $binding['config'], true ) : array();
		return is_array( $config ) ? $config : array();
	}

	public static function consume_nonce( $key_id, $nonce, $request_id ) {
		global $wpdb;
		$table = self::tables()['nonces'];
		$now   = self::utc_now();
		$ok    = $wpdb->insert(
			$table,
			array(
				'key_id'     => $key_id,
				'nonce'      => $nonce,
				'request_id' => $request_id,
				'created_at' => $now,
				'expires_at' => gmdate( 'Y-m-d H:i:s', time() + 600 ),
			)
		);
		return false !== $ok;
	}

	public static function cleanup() {
		global $wpdb;
		$tables = self::tables();
		$now    = self::utc_now();
		$audit_cutoff = gmdate( 'Y-m-d H:i:s', time() - 90 * DAY_IN_SECONDS );
		$outbox_cutoff = gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS );

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$tables['nonces']} WHERE expires_at < %s", $now ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$tables['rate_limits']} WHERE updated_at < %s", gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ) ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$tables['audit']} WHERE created_at < %s", $audit_cutoff ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$tables['outbox']} WHERE status = 'sent' AND updated_at < %s", $outbox_cutoff ) );
	}
}
