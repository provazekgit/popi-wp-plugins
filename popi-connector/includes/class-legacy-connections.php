<?php

defined( 'ABSPATH' ) || exit;

/**
 * Read-only inventory and operator declarations for legacy WP Application Password access.
 *
 * Application Password values, UUIDs, names, usernames and last IP addresses are never
 * stored by this class or sent to POPIsite. A declaration describes only the intended
 * use of an already existing credential; it does not create, rotate or revoke one.
 */
final class POPI_Connector_Legacy_Connections {

	const OPTION = 'popi_connector_legacy_connections';
	const MAX_NOTE_LENGTH = 500;
	const MAX_USERS_SCANNED = 200;

	public static function detection_summary() {
		$supported = function_exists( 'wp_is_application_passwords_supported' )
			? (bool) wp_is_application_passwords_supported()
			: class_exists( 'WP_Application_Passwords' );
		$summary = array(
			'supported'        => $supported,
			'configured'       => false,
			'credential_count' => 0,
			'last_used_at'     => null,
			'scan_limited'     => false,
		);
		if ( ! class_exists( 'WP_Application_Passwords' ) || ! function_exists( 'get_users' ) ) {
			return $summary;
		}

		$user_ids = get_users(
			array(
				'fields'     => 'ID',
				'meta_key'   => '_application_passwords', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- bounded admin/health inventory.
				'number'     => self::MAX_USERS_SCANNED + 1,
				'count_total'=> false,
			)
		);
		$summary['scan_limited'] = count( $user_ids ) > self::MAX_USERS_SCANNED;
		$user_ids = array_slice( $user_ids, 0, self::MAX_USERS_SCANNED );
		$last_used = 0;
		foreach ( $user_ids as $user_id ) {
			$passwords = WP_Application_Passwords::get_user_application_passwords( (int) $user_id );
			if ( ! is_array( $passwords ) ) {
				continue;
			}
			$summary['credential_count'] += count( $passwords );
			foreach ( $passwords as $password ) {
				if ( isset( $password['last_used'] ) ) {
					$last_used = max( $last_used, (int) $password['last_used'] );
				}
			}
		}
		$summary['configured'] = $summary['credential_count'] > 0;
		$summary['last_used_at'] = $last_used > 0 ? gmdate( 'Y-m-d\\TH:i:s\\Z', $last_used ) : null;
		return $summary;
	}

	public static function get( $binding_id ) {
		$all = get_option( self::OPTION, array() );
		$saved = is_array( $all ) && isset( $all[ $binding_id ] ) && is_array( $all[ $binding_id ] ) ? $all[ $binding_id ] : array();
		return array(
			'declared' => ! empty( $saved['declared'] ),
			'purpose'  => isset( $saved['purpose'] ) && in_array( $saved['purpose'], self::purposes(), true ) ? $saved['purpose'] : 'content_sync',
			'note'     => isset( $saved['note'] ) ? (string) $saved['note'] : '',
		);
	}

	public static function save( $binding_id, $input, $actor_id ) {
		$binding = POPI_Connector_Storage::get_binding( $binding_id );
		if ( ! $binding ) {
			return new WP_Error( 'popi_binding_not_found', 'Připojení nebylo nalezeno.' );
		}
		$purpose = isset( $input['purpose'] ) ? sanitize_key( $input['purpose'] ) : 'content_sync';
		if ( ! in_array( $purpose, self::purposes(), true ) ) {
			return new WP_Error( 'popi_legacy_purpose_invalid', 'Účel legacy připojení není platný.' );
		}
		$note = isset( $input['note'] ) ? sanitize_textarea_field( $input['note'] ) : '';
		if ( function_exists( 'mb_substr' ) ) {
			$note = mb_substr( $note, 0, self::MAX_NOTE_LENGTH );
		} else {
			$note = substr( $note, 0, self::MAX_NOTE_LENGTH );
		}
		$all = get_option( self::OPTION, array() );
		$all = is_array( $all ) ? $all : array();
		$all[ $binding_id ] = array(
			'declared'  => ! empty( $input['declared'] ),
			'purpose'   => $purpose,
			'note'      => $note,
			'updated_at'=> gmdate( DATE_ATOM ),
			'updated_by'=> (int) $actor_id,
		);
		update_option( self::OPTION, $all, false );
		POPI_Connector_Audit::record(
			'legacy_connection.updated',
			'success',
			array(
				'binding_id' => $binding_id,
				'actor_type' => 'user',
				'actor_id'   => (int) $actor_id,
				'metadata'   => array( 'declared' => ! empty( $input['declared'] ), 'purpose' => $purpose, 'has_note' => '' !== $note ),
			)
		);
		return true;
	}

	public static function health_payload( $binding ) {
		$declaration = self::get( $binding['binding_id'] );
		return array(
			'auth_mode' => 'wp_application_password',
			'product'   => sanitize_key( $binding['module'] ),
			'declared'  => (bool) $declaration['declared'],
			'purpose'   => $declaration['purpose'],
			'note'      => $declaration['note'],
			'detection' => self::detection_summary(),
		);
	}

	private static function purposes() {
		return array( 'content_sync', 'admin_access', 'migration', 'other' );
	}
}
