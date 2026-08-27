<?php

defined( 'ABSPATH' ) || exit;

final class POPI_Connector_Pairing {

	const CLAIM_OPTION = 'popi_connector_pending_claim';
	const CLAIM_PATH = '/api/v1/public/connectors/wordpress/pairings/claim';
	const STATUS_PATH = '/api/v1/public/connectors/wordpress/pairings/status';
	const ACTIVATE_PATH = '/api/v1/public/connectors/wordpress/pairings/activate';

	public static function claim( $pairing_code ) {
		$code = strtoupper( preg_replace( '/[^A-Z0-9]/', '', (string) $pairing_code ) );
		if ( ! preg_match( '/^(?:POPI)?[A-Z0-9]{12}$/', $code ) ) {
			return new WP_Error( 'popi_pairing_code_invalid', 'Párovací kód nemá očekávaný formát.' );
		}
		if ( 0 === strpos( $code, 'POPI' ) ) {
			$code = substr( $code, 4 );
		}
		$site_instance_id = (string) get_option( 'popi_connector_site_instance_id', '' );
		$response = POPI_Connector_Remote::post_json(
			self::CLAIM_PATH,
			array(
				'pairing_code'    => $code,
				'site_instance_id' => $site_instance_id,
				'canonical_url'    => home_url( '/' ),
				'admin_url'        => admin_url( '/' ),
				'callback_url'     => rest_url( 'popi-connector/v1' ),
				'plugin_version'   => POPI_CONNECTOR_VERSION,
				'wordpress_version'=> get_bloginfo( 'version' ),
				'php_version'      => PHP_VERSION,
				'multisite'        => is_multisite(),
			)
		);
		if ( is_wp_error( $response ) ) {
			POPI_Connector_Audit::record( 'pairing.claim', 'error', array( 'actor_type' => 'user', 'actor_id' => get_current_user_id(), 'metadata' => array( 'code' => $response->get_error_code() ) ) );
			return $response;
		}
		if ( empty( $response['claim_id'] ) || empty( $response['claim_token'] ) || empty( $response['expires_at'] ) ) {
			return new WP_Error( 'popi_pairing_response_invalid', 'POPIsite vrátil neúplné párování.' );
		}
		$cipher = POPI_Connector_Crypto::encrypt_secret( (string) $response['claim_token'] );
		if ( is_wp_error( $cipher ) ) {
			return $cipher;
		}
		update_option(
			self::CLAIM_OPTION,
			array(
				'claim_id'     => sanitize_text_field( $response['claim_id'] ),
				'token_cipher'  => $cipher,
				'expires_at'    => sanitize_text_field( $response['expires_at'] ),
				'reported_site' => isset( $response['reported_site'] ) && is_array( $response['reported_site'] ) ? $response['reported_site'] : array(),
			),
			false
		);
		POPI_Connector_Audit::record( 'pairing.claim', 'success', array( 'actor_type' => 'user', 'actor_id' => get_current_user_id() ) );
		return $response;
	}

	public static function refresh() {
		$claim = get_option( self::CLAIM_OPTION, array() );
		if ( ! is_array( $claim ) || empty( $claim['claim_id'] ) || empty( $claim['token_cipher'] ) ) {
			return new WP_Error( 'popi_pairing_missing', 'Žádné párování nečeká na potvrzení.' );
		}
		$claim_token = POPI_Connector_Crypto::decrypt_secret( $claim['token_cipher'] );
		if ( is_wp_error( $claim_token ) ) {
			delete_option( self::CLAIM_OPTION );
			return $claim_token;
		}
		$status = POPI_Connector_Remote::post_json(
			self::STATUS_PATH,
			array( 'claim_id' => $claim['claim_id'], 'claim_token' => $claim_token )
		);
		if ( is_wp_error( $status ) ) {
			return $status;
		}
		if ( empty( $status['status'] ) || 'approved' !== $status['status'] ) {
			return $status;
		}
		return self::activate( $claim, $claim_token );
	}

	private static function activate( $claim, $claim_token ) {
		$secret = POPI_Connector_Crypto::random_token( 32 );
		if ( is_wp_error( $secret ) ) {
			return $secret;
		}
		$key_id = 'wp_' . str_replace( '-', '', wp_generate_uuid4() );
		$response = POPI_Connector_Remote::post_json(
			self::ACTIVATE_PATH,
			array(
				'claim_id'         => $claim['claim_id'],
				'claim_token'      => $claim_token,
				'site_instance_id' => get_option( 'popi_connector_site_instance_id' ),
				'key_id'           => $key_id,
				'master_secret'    => $secret,
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$binding = isset( $response['binding'] ) && is_array( $response['binding'] ) ? $response['binding'] : array();
		$saved   = POPI_Connector_Storage::save_binding( $binding, $key_id, $secret );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}
		delete_option( self::CLAIM_OPTION );
		POPI_Connector_Audit::record(
			'pairing.activated',
			'success',
			array(
				'binding_id' => $binding['binding_id'],
				'actor_type' => 'user',
				'actor_id'   => get_current_user_id(),
				'metadata'   => array( 'module' => $binding['module'], 'key_id' => $key_id ),
			)
		);
		return array( 'status' => 'active', 'binding' => $binding );
	}

	public static function revoke( $binding_id ) {
		$binding = POPI_Connector_Storage::get_binding( $binding_id );
		if ( ! $binding ) {
			return new WP_Error( 'popi_binding_missing', 'Připojení nebylo nalezeno.' );
		}
		$remote = POPI_Connector_Remote::signed_post(
			$binding,
			'/api/v1/connectors/wordpress/revoke',
			array( 'reason' => 'revoked_by_wordpress_administrator' )
		);
		POPI_Connector_Storage::revoke_binding( $binding_id );
		if ( is_wp_error( $remote ) ) {
			POPI_Connector_Audit::record( 'binding.revoked', 'partial', array( 'binding_id' => $binding_id, 'actor_type' => 'user', 'actor_id' => get_current_user_id(), 'metadata' => array( 'remote_error' => $remote->get_error_code() ) ) );
			return new WP_Error( 'popi_revoke_remote_failed', 'Připojení bylo lokálně zablokováno, ale POPIsite potvrzení selhalo. Odvolejte binding také v POPIsite.' );
		}
		POPI_Connector_Audit::record( 'binding.revoked', 'success', array( 'binding_id' => $binding_id, 'actor_type' => 'user', 'actor_id' => get_current_user_id() ) );
		return true;
	}

	public static function rotate( $binding_id ) {
		$binding = POPI_Connector_Storage::get_binding( $binding_id );
		if ( ! $binding || 'active' !== $binding['status'] ) {
			return new WP_Error( 'popi_binding_missing', 'Aktivní připojení nebylo nalezeno.' );
		}
		$new_secret = POPI_Connector_Crypto::random_token( 32 );
		if ( is_wp_error( $new_secret ) ) {
			return $new_secret;
		}
		$new_key_id = 'wp_' . str_replace( '-', '', wp_generate_uuid4() );
		$prepare = POPI_Connector_Remote::signed_post(
			$binding,
			'/api/v1/connectors/wordpress/rotations/prepare',
			array( 'new_key_id' => $new_key_id, 'new_master_secret' => $new_secret )
		);
		if ( is_wp_error( $prepare ) ) {
			return $prepare;
		}
		$stored = POPI_Connector_Storage::store_pending_key( $binding_id, $new_key_id, $new_secret );
		if ( is_wp_error( $stored ) ) {
			return $stored;
		}
		$new_key = POPI_Connector_Storage::get_binding_by_key( $new_key_id );
		$commit  = POPI_Connector_Remote::signed_post(
			$binding,
			'/api/v1/connectors/wordpress/rotations/commit',
			array( 'new_key_id' => $new_key_id, 'proof' => hash( 'sha256', $new_secret ) ),
			$new_key
		);
		if ( is_wp_error( $commit ) ) {
			return $commit;
		}
		if ( ! POPI_Connector_Storage::activate_key( $binding_id, $new_key_id, 300 ) ) {
			return new WP_Error( 'popi_rotation_commit_failed', 'Nový klíč byl potvrzen, ale lokálně se nepodařilo dokončit rotaci.' );
		}
		POPI_Connector_Audit::record( 'key.rotated', 'success', array( 'binding_id' => $binding_id, 'actor_type' => 'user', 'actor_id' => get_current_user_id(), 'metadata' => array( 'key_id' => $new_key_id ) ) );
		return true;
	}
}
