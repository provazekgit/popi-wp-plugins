<?php

defined( 'ABSPATH' ) || exit;

final class POPI_Connector_Remote {

	public static function api_base() {
		$base = untrailingslashit( (string) get_option( 'popi_connector_api_base', 'https://api.popisite.cz' ) );
		$host = strtolower( (string) wp_parse_url( $base, PHP_URL_HOST ) );
		if ( 0 !== strpos( $base, 'https://' ) || ( 'api.popisite.cz' !== $host && ( ! defined( 'POPI_CONNECTOR_ALLOW_CUSTOM_API_BASE' ) || ! POPI_CONNECTOR_ALLOW_CUSTOM_API_BASE ) ) ) {
			return 'https://api.popisite.cz';
		}
		return $base;
	}

	public static function post_json( $path, $payload, $timeout = 15 ) {
		$url = self::api_base() . '/' . ltrim( $path, '/' );
		$response = wp_safe_remote_post(
			$url,
			array(
				'timeout'     => max( 5, min( 30, (int) $timeout ) ),
				'redirection' => 0,
				'sslverify'   => true,
				'headers'     => array( 'Content-Type' => 'application/json', 'Accept' => 'application/json' ),
				'user-agent'  => 'POPI-Connector/' . POPI_CONNECTOR_VERSION . '; WordPress/' . get_bloginfo( 'version' ),
				'body'        => wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'popi_remote_unavailable', 'POPIsite není dostupný: ' . $response->get_error_message() );
		}
		$status = (int) wp_remote_retrieve_response_code( $response );
		$body   = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) ) {
			return new WP_Error( 'popi_remote_invalid', 'POPIsite vrátil neplatnou odpověď.', array( 'status' => $status ) );
		}
		if ( $status < 200 || $status >= 300 ) {
			$message = isset( $body['message'] ) ? sanitize_text_field( $body['message'] ) : 'POPIsite požadavek odmítl.';
			return new WP_Error( isset( $body['code'] ) ? sanitize_key( $body['code'] ) : 'popi_remote_rejected', $message, array( 'status' => $status ) );
		}
		return $body;
	}

	public static function signed_post( $binding, $path, $payload, $key_row = null ) {
		if ( ! is_array( $binding ) ) {
			return new WP_Error( 'popi_binding_invalid', 'Připojení není platné.' );
		}
		if ( null === $key_row ) {
			$key_row = POPI_Connector_Storage::active_key_for_binding( $binding['binding_id'] );
		}
		if ( ! $key_row ) {
			return new WP_Error( 'popi_key_missing', 'Připojení nemá aktivní klíč.' );
		}
		$secret = POPI_Connector_Crypto::decrypt_secret( $key_row['secret_cipher'] );
		if ( is_wp_error( $secret ) ) {
			return $secret;
		}
		$nonce      = POPI_Connector_Crypto::random_token( 24 );
		$request_id = wp_generate_uuid4();
		if ( is_wp_error( $nonce ) ) {
			return $nonce;
		}
		$payload_json = wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		$envelope     = array(
			'protocol'               => POPI_Connector_Crypto::PROTOCOL,
			'key_id'                 => $key_row['key_id'],
			'timestamp'              => time(),
			'nonce'                  => $nonce,
			'request_id'             => $request_id,
			'tenant_id'              => $binding['tenant_id'],
			'project_id'             => $binding['project_id'],
			'module_installation_id' => $binding['installation_id'],
			'connection_id'          => $binding['connection_id'],
			'payload_b64'            => POPI_Connector_Crypto::base64url_encode( $payload_json ),
		);
		$canonical_path       = '/' . ltrim( $path, '/' );
		$envelope['signature'] = POPI_Connector_Crypto::sign_request( $secret, 'POST', $canonical_path, $envelope, POPI_Connector_Crypto::RESPONSE_INFO );
		$response              = self::post_json( $canonical_path, $envelope );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		return POPI_Connector_Crypto::verify_response( $secret, $response, $request_id, POPI_Connector_Crypto::REQUEST_INFO );
	}
}
