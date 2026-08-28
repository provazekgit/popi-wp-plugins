<?php

defined( 'ABSPATH' ) || exit;

final class POPI_Connector_Plugin {

	private static $booted = false;

	public static function boot() {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;

		add_action( 'plugins_loaded', array( 'POPI_Connector_Installer', 'maybe_upgrade' ) );
		add_action( 'plugins_loaded', array( 'POPI_Connector_Admin', 'init' ) );
		add_action( 'plugins_loaded', array( 'POPI_Connector_Frontend', 'init' ) );
		add_action( 'plugins_loaded', array( 'POPI_Connector_Outbox', 'init' ) );
		add_action( 'rest_api_init', array( 'POPI_Connector_REST_API', 'register_routes' ) );

		add_action( 'plugins_loaded', array( __CLASS__, 'load_updater' ) );
	}

	public static function load_updater() {
		new POPI_Connector_Updater(
			POPI_CONNECTOR_FILE,
			POPI_CONNECTOR_UPDATE_URL,
			POPI_CONNECTOR_VERSION
		);
	}
}

