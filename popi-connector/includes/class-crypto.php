<?php

defined( 'ABSPATH' ) || exit;

final class POPI_Connector_Crypto {

	const PROTOCOL = 'popi-hmac-v1';
	const REQUEST_INFO = 'popisite-to-wordpress';
	const RESPONSE_INFO = 'wordpress-to-popisite';

	public static function available() {
		return function_exists( 'openssl_encrypt' )
			&& function_exists( 'openssl_decrypt' )
			&& function_exists( 'hash_hmac' )
			&& function_exists( 'hash_hkdf' )
			&& function_exists( 'random_bytes' );
	}

	public static function random_token( $bytes = 32 ) {
		try {
			return self::base64url_encode( random_bytes( max( 16, (int) $bytes ) ) );
		} catch ( Exception $error ) {
			return new WP_Error( 'popi_random_unavailable', 'Hosting nedokáže bezpečně generovat náhodné klíče.' );
		}
	}

	public static function encrypt_secret( $secret ) {
		if ( ! self::available() ) {
			return new WP_Error( 'popi_crypto_unavailable', 'Hosting nemá potřebné OpenSSL a hash funkce.' );
		}
		if ( ! is_string( $secret ) || strlen( $secret ) < 32 ) {
			return new WP_Error( 'popi_secret_invalid', 'Klíč připojení je příliš krátký.' );
		}

		try {
			$iv = random_bytes( 12 );
		} catch ( Exception $error ) {
			return new WP_Error( 'popi_random_unavailable', 'Hosting nedokáže bezpečně generovat náhodné hodnoty.' );
		}
		$tag    = '';
		$cipher = openssl_encrypt( $secret, 'aes-256-gcm', self::storage_key(), OPENSSL_RAW_DATA, $iv, $tag, self::PROTOCOL );
		if ( false === $cipher || 16 !== strlen( $tag ) ) {
			return new WP_Error( 'popi_encrypt_failed', 'Klíč připojení se nepodařilo bezpečně uložit.' );
		}

		return wp_json_encode(
			array(
				'v'    => 1,
				'iv'   => self::base64url_encode( $iv ),
				'tag'  => self::base64url_encode( $tag ),
				'data' => self::base64url_encode( $cipher ),
			)
		);
	}

	public static function decrypt_secret( $encoded ) {
		if ( ! self::available() ) {
			return new WP_Error( 'popi_crypto_unavailable', 'Hosting nemá potřebné kryptografické funkce.' );
		}
		$envelope = json_decode( (string) $encoded, true );
		if ( ! is_array( $envelope ) || 1 !== (int) ( isset( $envelope['v'] ) ? $envelope['v'] : 0 ) ) {
			return new WP_Error( 'popi_cipher_invalid', 'Uložený klíč má neplatný formát.' );
		}
		foreach ( array( 'iv', 'tag', 'data' ) as $field ) {
			if ( ! isset( $envelope[ $field ] ) || ! is_string( $envelope[ $field ] ) ) {
				return new WP_Error( 'popi_cipher_invalid', 'Uložený klíč má neplatný formát.' );
			}
		}
		$iv     = self::base64url_decode( $envelope['iv'] );
		$tag    = self::base64url_decode( $envelope['tag'] );
		$cipher = self::base64url_decode( $envelope['data'] );
		if ( false === $iv || false === $tag || false === $cipher || 12 !== strlen( $iv ) || 16 !== strlen( $tag ) ) {
			return new WP_Error( 'popi_cipher_invalid', 'Uložený klíč má neplatný formát.' );
		}
		$plain = openssl_decrypt( $cipher, 'aes-256-gcm', self::storage_key(), OPENSSL_RAW_DATA, $iv, $tag, self::PROTOCOL );
		if ( false === $plain || strlen( $plain ) < 32 ) {
			return new WP_Error( 'popi_decrypt_failed', 'Uložený klíč nelze odemknout. Připojení je nutné obnovit.' );
		}
		return $plain;
	}

	public static function sign_request( $master_secret, $method, $route, $envelope, $direction = self::REQUEST_INFO ) {
		$key = self::direction_key( $master_secret, $direction );
		return self::base64url_encode( hash_hmac( 'sha256', self::canonical_request( $method, $route, $envelope ), $key, true ) );
	}

	public static function verify_request_signature( $master_secret, $method, $route, $envelope, $direction = self::REQUEST_INFO ) {
		if ( ! isset( $envelope['signature'] ) || ! is_string( $envelope['signature'] ) ) {
			return false;
		}
		$expected = self::sign_request( $master_secret, $method, $route, $envelope, $direction );
		return hash_equals( $expected, $envelope['signature'] );
	}

	public static function signed_response( $context, $status, $payload ) {
		$payload_json = wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		$envelope     = array(
			'protocol'    => self::PROTOCOL,
			'key_id'      => $context['key_id'],
			'timestamp'   => time(),
			'nonce'       => self::random_token( 24 ),
			'request_id'  => $context['request_id'],
			'status'      => (int) $status,
			'payload_b64' => self::base64url_encode( $payload_json ),
		);
		if ( is_wp_error( $envelope['nonce'] ) ) {
			return $envelope['nonce'];
		}
		$key                   = self::direction_key( $context['master_secret'], self::RESPONSE_INFO );
		$envelope['signature'] = self::base64url_encode( hash_hmac( 'sha256', self::canonical_response( $envelope ), $key, true ) );
		return $envelope;
	}

	public static function verify_response( $master_secret, $envelope, $request_id, $direction = self::REQUEST_INFO ) {
		if ( ! is_array( $envelope ) || ! isset( $envelope['signature'], $envelope['request_id'], $envelope['timestamp'], $envelope['payload_b64'] ) ) {
			return new WP_Error( 'popi_response_invalid', 'POPIsite vrátil neplatnou odpověď.' );
		}
		if ( ! hash_equals( (string) $request_id, (string) $envelope['request_id'] ) || abs( time() - (int) $envelope['timestamp'] ) > 300 ) {
			return new WP_Error( 'popi_response_invalid', 'Odpověď POPIsite neodpovídá požadavku nebo je příliš stará.' );
		}
		$key      = self::direction_key( $master_secret, $direction );
		$expected = self::base64url_encode( hash_hmac( 'sha256', self::canonical_response( $envelope ), $key, true ) );
		if ( ! hash_equals( $expected, (string) $envelope['signature'] ) ) {
			return new WP_Error( 'popi_response_signature_invalid', 'Podpis odpovědi POPIsite není platný.' );
		}
		$decoded = self::base64url_decode( (string) $envelope['payload_b64'] );
		$payload = false === $decoded ? null : json_decode( $decoded, true );
		return is_array( $payload ) ? $payload : new WP_Error( 'popi_response_payload_invalid', 'Odpověď POPIsite nemá platný obsah.' );
	}

	public static function decode_payload( $payload_b64 ) {
		$decoded = self::base64url_decode( (string) $payload_b64 );
		if ( false === $decoded || strlen( $decoded ) > 1024 * 1024 ) {
			return new WP_Error( 'popi_payload_invalid', 'Payload má neplatný formát nebo velikost.' );
		}
		$payload = json_decode( $decoded, true );
		return is_array( $payload ) ? $payload : new WP_Error( 'popi_payload_invalid', 'Payload není platný JSON objekt.' );
	}

	public static function canonical_request( $method, $route, $envelope ) {
		$fields = array(
			self::PROTOCOL,
			strtoupper( (string) $method ),
			(string) $route,
			isset( $envelope['key_id'] ) ? (string) $envelope['key_id'] : '',
			isset( $envelope['timestamp'] ) ? (string) (int) $envelope['timestamp'] : '',
			isset( $envelope['nonce'] ) ? (string) $envelope['nonce'] : '',
			isset( $envelope['request_id'] ) ? (string) $envelope['request_id'] : '',
			isset( $envelope['tenant_id'] ) ? (string) $envelope['tenant_id'] : '',
			isset( $envelope['project_id'] ) ? (string) $envelope['project_id'] : '',
			isset( $envelope['module_installation_id'] ) ? (string) $envelope['module_installation_id'] : '',
			isset( $envelope['connection_id'] ) ? (string) $envelope['connection_id'] : '',
			hash( 'sha256', (string) self::base64url_decode( isset( $envelope['payload_b64'] ) ? $envelope['payload_b64'] : '' ) ),
		);
		return implode( "\n", $fields );
	}

	public static function canonical_response( $envelope ) {
		return implode(
			"\n",
			array(
				self::PROTOCOL,
				isset( $envelope['key_id'] ) ? (string) $envelope['key_id'] : '',
				isset( $envelope['timestamp'] ) ? (string) (int) $envelope['timestamp'] : '',
				isset( $envelope['nonce'] ) ? (string) $envelope['nonce'] : '',
				isset( $envelope['request_id'] ) ? (string) $envelope['request_id'] : '',
				isset( $envelope['status'] ) ? (string) (int) $envelope['status'] : '',
				hash( 'sha256', (string) self::base64url_decode( isset( $envelope['payload_b64'] ) ? $envelope['payload_b64'] : '' ) ),
			)
		);
	}

	public static function base64url_encode( $value ) {
		return rtrim( strtr( base64_encode( $value ), '+/', '-_' ), '=' );
	}

	public static function base64url_decode( $value ) {
		if ( ! is_string( $value ) || '' === $value || ! preg_match( '/^[A-Za-z0-9_-]+$/', $value ) ) {
			return false;
		}
		$padding = strlen( $value ) % 4;
		if ( $padding > 0 ) {
			$value .= str_repeat( '=', 4 - $padding );
		}
		return base64_decode( strtr( $value, '-_', '+/' ), true );
	}

	private static function direction_key( $master_secret, $direction ) {
		return hash_hkdf( 'sha256', $master_secret, 32, $direction, self::PROTOCOL );
	}

	private static function storage_key() {
		$material = '';
		foreach ( array( 'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY' ) as $constant ) {
			$material .= defined( $constant ) ? constant( $constant ) : $constant;
		}
		return hash( 'sha256', self::PROTOCOL . '|' . $material, true );
	}
}
