<?php

defined( 'ABSPATH' ) || exit;

final class POPI_Connector_Authentication {

	const MAX_BODY_BYTES = 1048576;
	const CLOCK_SKEW_SECONDS = 300;

	public static function authorize( $request, $scope, $module, $rate_bucket = 'read', $rate_limit = 60, $allowed_key_statuses = array( 'active', 'retiring' ) ) {
		$body = $request->get_body();
		if ( ! is_string( $body ) || strlen( $body ) > self::MAX_BODY_BYTES ) {
			return self::error( 'popi_request_too_large', 'Požadavek je příliš velký.', 413 );
		}
		$envelope = $request->get_json_params();
		if ( ! is_array( $envelope ) ) {
			return self::error( 'popi_envelope_invalid', 'Chybí platná podepsaná obálka.', 400 );
		}

		$required = array( 'protocol', 'key_id', 'timestamp', 'nonce', 'request_id', 'tenant_id', 'project_id', 'module_installation_id', 'connection_id', 'payload_b64', 'signature' );
		foreach ( $required as $field ) {
			if ( ! isset( $envelope[ $field ] ) || ( is_string( $envelope[ $field ] ) && '' === $envelope[ $field ] ) ) {
				return self::error( 'popi_envelope_invalid', 'Podepsaná obálka je neúplná.', 400 );
			}
		}
		if ( POPI_Connector_Crypto::PROTOCOL !== $envelope['protocol'] ) {
			return self::error( 'popi_protocol_unsupported', 'Verze podpisového protokolu není podporovaná.', 400 );
		}
		if ( abs( time() - (int) $envelope['timestamp'] ) > self::CLOCK_SKEW_SECONDS ) {
			return self::error( 'popi_timestamp_invalid', 'Čas požadavku je mimo povolené okno.', 401 );
		}
		if ( ! self::valid_identifier( $envelope['key_id'] )
			|| ! self::valid_identifier( $envelope['request_id'] )
			|| ! preg_match( '/^[A-Za-z0-9_-]{22,96}$/', (string) $envelope['nonce'] )
			|| ! preg_match( '/^[A-Za-z0-9_-]{2,1398102}$/', (string) $envelope['payload_b64'] )
			|| ! preg_match( '/^[A-Za-z0-9_-]{40,96}$/', (string) $envelope['signature'] ) ) {
			return self::error( 'popi_envelope_invalid', 'Podepsaná obálka obsahuje neplatné identifikátory.', 400 );
		}

		$binding = POPI_Connector_Storage::get_binding_by_key( sanitize_text_field( $envelope['key_id'] ) );
		if ( ! $binding || 'active' !== $binding['status'] || ! in_array( $binding['key_status'], $allowed_key_statuses, true ) ) {
			return self::error( 'popi_key_unknown', 'Připojení nebo klíč není aktivní.', 401 );
		}
		if ( $binding['not_before'] && strtotime( $binding['not_before'] . ' UTC' ) > time() ) {
			return self::error( 'popi_key_not_active', 'Klíč ještě není aktivní.', 401 );
		}
		if ( $binding['expires_at'] && strtotime( $binding['expires_at'] . ' UTC' ) <= time() ) {
			return self::error( 'popi_key_expired', 'Platnost klíče vypršela.', 401 );
		}

		$tuple = array(
			'tenant_id'              => 'tenant_id',
			'project_id'             => 'project_id',
			'module_installation_id' => 'installation_id',
			'connection_id'          => 'connection_id',
		);
		foreach ( $tuple as $request_field => $stored_field ) {
			if ( ! hash_equals( (string) $binding[ $stored_field ], (string) $envelope[ $request_field ] ) ) {
				self::audit_failure( 'auth.binding_mismatch', $envelope, $binding );
				return self::error( 'popi_binding_mismatch', 'Požadavek nepatří k tomuto připojení.', 403 );
			}
		}
		if ( $module && ! hash_equals( (string) $binding['module'], (string) $module ) ) {
			return self::error( 'popi_module_mismatch', 'Připojení nepatří požadovanému modulu.', 403 );
		}
		$scopes = POPI_Connector_Storage::binding_scopes( $binding );
		if ( ! in_array( $scope, $scopes, true ) ) {
			return self::error( 'popi_scope_denied', 'Připojení nemá oprávnění pro tuto operaci.', 403 );
		}

		$secret = POPI_Connector_Crypto::decrypt_secret( $binding['secret_cipher'] );
		if ( is_wp_error( $secret ) ) {
			POPI_Connector_Audit::record( 'auth.secret_unavailable', 'error', array( 'binding_id' => $binding['binding_id'] ) );
			return self::error( 'popi_secret_unavailable', 'Klíč připojení nelze načíst. Je nutné nové párování.', 503 );
		}

		$route = '/wp-json' . $request->get_route();
		if ( ! POPI_Connector_Crypto::verify_request_signature( $secret, $request->get_method(), $route, $envelope ) ) {
			self::audit_failure( 'auth.signature_invalid', $envelope, $binding );
			return self::error( 'popi_signature_invalid', 'Podpis požadavku není platný.', 401 );
		}
		if ( ! POPI_Connector_Storage::consume_nonce( $binding['key_id'], sanitize_text_field( $envelope['nonce'] ), sanitize_text_field( $envelope['request_id'] ) ) ) {
			self::audit_failure( 'auth.replay_detected', $envelope, $binding );
			return self::error( 'popi_replay_detected', 'Požadavek již byl použit.', 409 );
		}

		$rate = POPI_Connector_Rate_Limiter::consume( $binding['binding_id'], $rate_bucket, $rate_limit, 'keys' === $rate_bucket ? 3600 : 60 );
		if ( is_wp_error( $rate ) ) {
			POPI_Connector_Audit::record( 'auth.rate_limited', 'denied', array( 'binding_id' => $binding['binding_id'], 'request_id' => $envelope['request_id'] ) );
			return $rate;
		}

		$payload = POPI_Connector_Crypto::decode_payload( $envelope['payload_b64'] );
		if ( is_wp_error( $payload ) ) {
			return self::error( 'popi_payload_invalid', $payload->get_error_message(), 400 );
		}

		POPI_Connector_Storage::mark_seen( $binding['binding_id'] );
		$context = array(
			'binding'      => $binding,
			'key_id'       => $binding['key_id'],
			'master_secret'=> $secret,
			'request_id'   => sanitize_text_field( $envelope['request_id'] ),
			'payload'      => $payload,
			'scope'        => $scope,
		);
		$request->set_param( '_popi_context', $context );
		return true;
	}

	private static function valid_identifier( $value ) {
		return is_string( $value ) && (bool) preg_match( '/^[A-Za-z0-9._:-]{3,191}$/', $value );
	}

	private static function audit_failure( $event, $envelope, $binding ) {
		POPI_Connector_Audit::record(
			$event,
			'denied',
			array(
				'binding_id' => isset( $binding['binding_id'] ) ? $binding['binding_id'] : null,
				'request_id' => isset( $envelope['request_id'] ) ? $envelope['request_id'] : null,
				'actor_type' => 'service',
				'actor_id'   => isset( $envelope['key_id'] ) ? $envelope['key_id'] : null,
			)
		);
	}

	private static function error( $code, $message, $status ) {
		return new WP_Error( $code, $message, array( 'status' => $status ) );
	}
}
