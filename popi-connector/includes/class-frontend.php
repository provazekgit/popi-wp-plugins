<?php

defined( 'ABSPATH' ) || exit;

final class POPI_Connector_Frontend {

	const OPTION = 'popi_connector_frontend';
	const SNAPSHOT_OPTION = 'popi_connector_frontend_snapshot';

	private static $render_mode = '';

	public static function init() {
		add_filter( 'wp_robots', array( __CLASS__, 'filter_robots' ), 99 );
		add_action( 'send_headers', array( __CLASS__, 'send_headers' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_redirect' ), 1 );
		add_filter( 'template_include', array( __CLASS__, 'template_include' ), 99 );
	}

	public static function settings() {
		$defaults = array(
			'mode'                => 'wordpress',
			'title'               => '',
			'description'         => '',
			'redirect_url'        => '',
			'redirect_status'     => 302,
			'allow_remote_manage' => 0,
		);
		$stored = get_option( self::OPTION, array() );
		return wp_parse_args( is_array( $stored ) ? $stored : array(), $defaults );
	}

	public static function apply( $input, $actor_type = 'user', $actor_id = null, $request_id = null, $binding = null ) {
		if ( ! is_array( $input ) ) {
			return new WP_Error( 'popi_frontend_invalid', 'Nastavení frontendu není platné.', array( 'status' => 400 ) );
		}
		$current = self::settings();
		$mode    = isset( $input['mode'] ) ? sanitize_key( $input['mode'] ) : $current['mode'];
		if ( ! in_array( $mode, array( 'wordpress', 'noindex', 'information', 'redirect', 'disabled' ), true ) ) {
			return new WP_Error( 'popi_frontend_mode_invalid', 'Požadovaný režim frontendu není podporovaný.', array( 'status' => 400 ) );
		}
		$redirect_url = isset( $input['redirect_url'] ) ? esc_url_raw( $input['redirect_url'], array( 'https' ) ) : $current['redirect_url'];
		if ( 'redirect' === $mode ) {
			if ( ! $redirect_url || 0 !== strpos( $redirect_url, 'https://' ) ) {
				return new WP_Error( 'popi_redirect_invalid', 'Přesměrování vyžaduje platnou HTTPS adresu.', array( 'status' => 400 ) );
			}
			if ( 'service' === $actor_type && ! self::redirect_allowed_for_binding( $redirect_url, $binding ) ) {
				return new WP_Error( 'popi_redirect_denied', 'Cílová doména není pro projekt ověřená.', array( 'status' => 403 ) );
			}
		}
		$status = isset( $input['redirect_status'] ) ? (int) $input['redirect_status'] : (int) $current['redirect_status'];
		if ( ! in_array( $status, array( 301, 302, 307, 308 ), true ) ) {
			$status = 302;
		}
		if ( in_array( $status, array( 301, 308 ), true ) && empty( $input['confirm_permanent'] ) ) {
			$status = 302;
		}

		if ( 'wordpress' !== $mode && false === get_option( self::SNAPSHOT_OPTION, false ) ) {
			add_option(
				self::SNAPSHOT_OPTION,
				array( 'blog_public' => (int) get_option( 'blog_public', 1 ), 'settings' => $current, 'created_at' => POPI_Connector_Storage::utc_now() ),
				'',
				false
			);
		}

		$settings = array(
			'mode'                => $mode,
			'title'               => isset( $input['title'] ) ? sanitize_text_field( $input['title'] ) : $current['title'],
			'description'         => isset( $input['description'] ) ? wp_kses_post( $input['description'] ) : $current['description'],
			'redirect_url'        => $redirect_url,
			'redirect_status'     => $status,
			'allow_remote_manage' => isset( $input['allow_remote_manage'] ) ? (int) ! empty( $input['allow_remote_manage'] ) : (int) $current['allow_remote_manage'],
		);
		update_option( self::OPTION, $settings, false );
		if ( 'wordpress' === $mode ) {
			$snapshot = get_option( self::SNAPSHOT_OPTION, array() );
			if ( is_array( $snapshot ) && isset( $snapshot['blog_public'] ) ) {
				update_option( 'blog_public', (int) $snapshot['blog_public'] );
			}
		} else {
			update_option( 'blog_public', 0 );
		}

		POPI_Connector_Audit::record(
			'frontend.changed',
			'success',
			array(
				'request_id' => $request_id,
				'actor_type' => $actor_type,
				'actor_id'   => $actor_id,
				'metadata'   => array( 'from' => $current['mode'], 'to' => $mode, 'redirect_host' => $redirect_url ? wp_parse_url( $redirect_url, PHP_URL_HOST ) : null ),
			)
		);
		return $settings;
	}

	public static function rollback( $actor_id = null ) {
		$snapshot = get_option( self::SNAPSHOT_OPTION, array() );
		if ( ! is_array( $snapshot ) || ! isset( $snapshot['settings'], $snapshot['blog_public'] ) || ! is_array( $snapshot['settings'] ) ) {
			return new WP_Error( 'popi_frontend_snapshot_missing', 'Původní nastavení frontendu není uložené.' );
		}
		update_option( self::OPTION, $snapshot['settings'], false );
		update_option( 'blog_public', (int) $snapshot['blog_public'] );
		delete_option( self::SNAPSHOT_OPTION );
		POPI_Connector_Audit::record( 'frontend.rolled_back', 'success', array( 'actor_type' => 'user', 'actor_id' => $actor_id ) );
		return self::settings();
	}

	public static function filter_robots( $robots ) {
		if ( ! is_array( $robots ) || ! self::should_noindex() ) {
			return $robots;
		}
		$robots['noindex']  = true;
		$robots['nofollow'] = true;
		unset( $robots['index'], $robots['follow'] );
		return $robots;
	}

	public static function send_headers() {
		if ( self::should_noindex() && ! headers_sent() ) {
			header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
		}
	}

	public static function maybe_redirect() {
		$settings = self::settings();
		if ( self::is_excluded_request() || 'redirect' !== $settings['mode'] ) {
			return;
		}
		$target = esc_url_raw( $settings['redirect_url'], array( 'https' ) );
		if ( ! $target ) {
			return;
		}
		nocache_headers();
		$host = wp_parse_url( $target, PHP_URL_HOST );
		$allow_target = function ( $hosts ) use ( $host ) {
			if ( is_string( $host ) && '' !== $host ) {
				$hosts[] = $host;
			}
			return array_unique( $hosts );
		};
		add_filter( 'allowed_redirect_hosts', $allow_target );
		wp_safe_redirect( $target, (int) $settings['redirect_status'], 'POPI Connector' );
		remove_filter( 'allowed_redirect_hosts', $allow_target );
		exit;
	}

	public static function template_include( $template ) {
		$settings = self::settings();
		if ( self::is_excluded_request() || ! in_array( $settings['mode'], array( 'information', 'disabled' ), true ) ) {
			return $template;
		}
		self::$render_mode = $settings['mode'];
		return POPI_CONNECTOR_DIR . 'templates/frontend-information.php';
	}

	public static function render_context() {
		$settings = self::settings();
		return array(
			'mode'        => self::$render_mode ? self::$render_mode : $settings['mode'],
			'title'       => $settings['title'] ? $settings['title'] : get_bloginfo( 'name' ),
			'description' => $settings['description'],
		);
	}

	private static function should_noindex() {
		return 'wordpress' !== self::settings()['mode'];
	}

	private static function is_excluded_request() {
		if ( is_admin() || ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) || ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) ) {
			return true;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}
		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
			return true;
		}
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		if ( 0 === strpos( $uri, '/wp-login.php' ) || 0 === strpos( $uri, '/.well-known/' ) ) {
			return true;
		}
		if ( is_user_logged_in() && current_user_can( 'manage_popi_connector_frontend' ) && empty( $_GET['popi_connector_preview'] ) ) {
			return true;
		}
		return false;
	}

	private static function redirect_allowed_for_binding( $url, $binding ) {
		if ( ! is_array( $binding ) ) {
			return false;
		}
		$config = POPI_Connector_Storage::binding_config( $binding );
		$hosts  = isset( $config['verified_frontend_hosts'] ) && is_array( $config['verified_frontend_hosts'] ) ? $config['verified_frontend_hosts'] : array();
		$host   = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		return $host && in_array( $host, array_map( 'strtolower', array_map( 'sanitize_text_field', $hosts ) ), true );
	}
}
