<?php

defined( 'ABSPATH' ) || exit;

final class POPI_Connector_REST_API {

	const NAMESPACE = 'popi-connector/v1';

	public static function register_routes() {
		self::route( '/core/health', 'core.health:read', null, 'read', 120, array( __CLASS__, 'health' ) );
		self::route( '/core/manifest', 'core.manifest:read', null, 'read', 60, array( __CLASS__, 'manifest' ) );
		self::route( '/core/site', 'core.site:read', null, 'read', 60, array( __CLASS__, 'site' ) );
		self::route( '/core/frontend/get', 'frontend:read', null, 'read', 60, array( __CLASS__, 'frontend_get' ) );
		self::route( '/core/frontend/set', 'frontend:write', null, 'write', 10, array( __CLASS__, 'frontend_set' ) );
		self::route( '/core/key/prepare', 'keys:rotate', null, 'keys', 5, array( __CLASS__, 'key_prepare' ) );
		self::route( '/core/key/commit', 'keys:rotate', null, 'keys', 5, array( __CLASS__, 'key_commit' ), array( 'pending' ) );
		self::route( '/core/revoke', 'connection:revoke', null, 'keys', 5, array( __CLASS__, 'revoke' ) );

		self::route( '/popiweb/schema/read', 'popiweb.schema:read', 'popiweb', 'read', 60, array( __CLASS__, 'popiweb_schema' ) );
		self::route( '/popiweb/entries/search', 'popiweb.entries:read', 'popiweb', 'read', 60, array( __CLASS__, 'popiweb_search' ) );
		self::route( '/popiweb/entries/get', 'popiweb.entries:read', 'popiweb', 'read', 60, array( __CLASS__, 'entry_get' ) );
		self::route( '/popiweb/entries/patch', 'popiweb.entries:write', 'popiweb', 'write', 10, array( __CLASS__, 'entry_patch' ) );

		self::route( '/popicast/show/get', 'popicast.show:read', 'popicast', 'read', 60, array( __CLASS__, 'popicast_show' ) );
		self::route( '/popicast/episodes/search', 'popicast.episodes:read', 'popicast', 'read', 60, array( __CLASS__, 'popicast_search' ) );
		self::route( '/popicast/episodes/get', 'popicast.episodes:read', 'popicast', 'read', 60, array( __CLASS__, 'entry_get' ) );
	}

	private static function route( $route, $scope, $module, $bucket, $limit, $callback, $key_statuses = array( 'active', 'retiring' ) ) {
		register_rest_route(
			self::NAMESPACE,
			$route,
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => $callback,
				'permission_callback' => function ( $request ) use ( $scope, $module, $bucket, $limit, $key_statuses ) {
					return POPI_Connector_Authentication::authorize( $request, $scope, $module, $bucket, $limit, $key_statuses );
				},
			)
		);
	}

	public static function health( $request ) {
		$context = self::context( $request );
		return self::respond( $context, POPI_Connector_Contracts::health( $context ) );
	}

	public static function manifest( $request ) {
		return self::respond( self::context( $request ), POPI_Connector_Contracts::manifest() );
	}

	public static function site( $request ) {
		return self::respond( self::context( $request ), POPI_Connector_Contracts::site() );
	}

	public static function frontend_get( $request ) {
		return self::respond( self::context( $request ), POPI_Connector_Frontend::settings() );
	}

	public static function frontend_set( $request ) {
		$context  = self::context( $request );
		$current  = POPI_Connector_Frontend::settings();
		if ( empty( $current['allow_remote_manage'] ) ) {
			return self::respond( $context, new WP_Error( 'popi_frontend_remote_denied', 'Vzdálená správa frontendu není lokálně povolená.', array( 'status' => 403 ) ) );
		}
		$result = POPI_Connector_Frontend::apply( $context['payload'], 'service', $context['binding']['installation_id'], $context['request_id'], $context['binding'] );
		return self::respond( $context, $result );
	}

	public static function key_prepare( $request ) {
		$context = self::context( $request );
		$payload = $context['payload'];
		if ( empty( $payload['new_key_id'] ) || empty( $payload['new_master_secret'] ) ) {
			return self::respond( $context, new WP_Error( 'popi_rotation_invalid', 'Chybí nový key ID nebo secret.', array( 'status' => 400 ) ) );
		}
		$result = POPI_Connector_Storage::store_pending_key( $context['binding']['binding_id'], sanitize_text_field( $payload['new_key_id'] ), (string) $payload['new_master_secret'] );
		return self::respond( $context, is_wp_error( $result ) ? $result : array( 'status' => 'prepared', 'key_id' => sanitize_text_field( $payload['new_key_id'] ) ) );
	}

	public static function key_commit( $request ) {
		$context = self::context( $request );
		$key_id  = $context['key_id'];
		$result  = POPI_Connector_Storage::activate_key( $context['binding']['binding_id'], $key_id, 300 );
		if ( ! $result ) {
			return self::respond( $context, new WP_Error( 'popi_rotation_failed', 'Rotaci klíče se nepodařilo dokončit.', array( 'status' => 500 ) ) );
		}
		POPI_Connector_Audit::record( 'key.rotated_remote', 'success', array( 'binding_id' => $context['binding']['binding_id'], 'request_id' => $context['request_id'], 'actor_type' => 'service', 'actor_id' => $context['binding']['installation_id'], 'metadata' => array( 'key_id' => $key_id ) ) );
		return self::respond( $context, array( 'status' => 'active', 'key_id' => $key_id ) );
	}

	public static function revoke( $request ) {
		$context = self::context( $request );
		POPI_Connector_Storage::revoke_binding( $context['binding']['binding_id'] );
		POPI_Connector_Audit::record( 'binding.revoked_remote', 'success', array( 'binding_id' => $context['binding']['binding_id'], 'request_id' => $context['request_id'], 'actor_type' => 'service', 'actor_id' => $context['binding']['installation_id'] ) );
		return self::respond( $context, array( 'status' => 'revoked' ) );
	}

	public static function popiweb_schema( $request ) {
		$context = self::context( $request );
		return self::respond( $context, POPI_Connector_Contracts::schema_read( $context ) );
	}

	public static function popiweb_search( $request ) {
		$context = self::context( $request );
		return self::respond( $context, POPI_Connector_Contracts::entries_search( $context, 'popiweb' ) );
	}

	public static function popicast_search( $request ) {
		$context = self::context( $request );
		return self::respond( $context, POPI_Connector_Contracts::entries_search( $context, 'popicast' ) );
	}

	public static function entry_get( $request ) {
		$context = self::context( $request );
		return self::respond( $context, POPI_Connector_Contracts::entry_get( $context ) );
	}

	public static function entry_patch( $request ) {
		$context = self::context( $request );
		return self::respond( $context, POPI_Connector_Contracts::entry_patch( $context ) );
	}

	public static function popicast_show( $request ) {
		return self::respond( self::context( $request ), POPI_Connector_Contracts::popicast_show() );
	}

	private static function context( $request ) {
		$context = $request->get_param( '_popi_context' );
		return is_array( $context ) ? $context : array();
	}

	private static function respond( $context, $result ) {
		$status = 200;
		if ( is_wp_error( $result ) ) {
			$data   = $result->get_error_data();
			$status = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 500;
			$result = array(
				'ok'        => false,
				'code'      => $result->get_error_code(),
				'message'   => $result->get_error_message(),
				'retryable' => $status >= 500 || 429 === $status,
			);
		} else {
			$result = array( 'ok' => true, 'data' => $result );
		}
		$envelope = POPI_Connector_Crypto::signed_response( $context, $status, $result );
		if ( is_wp_error( $envelope ) ) {
			return $envelope;
		}
		$response = new WP_REST_Response( $envelope, $status );
		$response->header( 'Cache-Control', 'no-store' );
		return $response;
	}
}
