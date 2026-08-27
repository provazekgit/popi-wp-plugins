<?php

defined( 'ABSPATH' ) || exit;

final class POPI_Connector_Installer {

	const DB_OPTION = 'popi_connector_db_version';
	const CRON_HOOK = 'popi_connector_cron';

	public static function activate() {
		if ( is_multisite() ) {
			wp_die( esc_html__( 'POPI Connector 1.0 zatím nepodporuje WordPress Multisite.', 'popi-connector' ) );
		}
		if ( ! POPI_Connector_Crypto::available() ) {
			wp_die( esc_html__( 'POPI Connector vyžaduje OpenSSL AES-256-GCM, HMAC, HKDF a bezpečný generátor náhodných čísel.', 'popi-connector' ) );
		}
		self::install_schema();
		self::install_capabilities();
		self::install_defaults();
		self::schedule_cron();
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	public static function maybe_upgrade() {
		if ( get_option( self::DB_OPTION ) !== POPI_CONNECTOR_DB_VERSION ) {
			self::install_schema();
			self::install_capabilities();
		}
		self::schedule_cron();
	}

	private static function install_defaults() {
		if ( false === get_option( 'popi_connector_site_instance_id', false ) ) {
			add_option( 'popi_connector_site_instance_id', wp_generate_uuid4(), '', false );
		}
		if ( false === get_option( 'popi_connector_api_base', false ) ) {
			add_option( 'popi_connector_api_base', 'https://api.popisite.cz', '', false );
		}
		if ( false === get_option( 'popi_connector_frontend', false ) ) {
			add_option(
				'popi_connector_frontend',
				array(
					'mode'                => 'wordpress',
					'title'               => '',
					'description'         => '',
					'redirect_url'        => '',
					'redirect_status'     => 302,
					'allow_remote_manage' => 0,
				),
				'',
				false
			);
		}
	}

	private static function schedule_cron() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + 300, 'hourly', self::CRON_HOOK );
		}
	}

	private static function install_capabilities() {
		$role = get_role( 'administrator' );
		if ( ! $role ) {
			return;
		}

		foreach ( self::capabilities() as $capability ) {
			$role->add_cap( $capability );
		}
	}

	public static function capabilities() {
		return array(
			'manage_popi_connector',
			'pair_popi_connector',
			'view_popi_connector_status',
			'manage_popi_connector_frontend',
			'rotate_popi_connector_keys',
			'revoke_popi_connector',
			'view_popi_connector_audit',
		);
	}

	private static function install_schema() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$tables  = POPI_Connector_Storage::tables();

		$sql = array();
		$sql[] = "CREATE TABLE {$tables['bindings']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			binding_id varchar(191) NOT NULL,
			connection_id varchar(191) NOT NULL,
			tenant_id varchar(191) NOT NULL,
			project_id varchar(191) NOT NULL,
			installation_id varchar(191) NOT NULL,
			module varchar(32) NOT NULL,
			status varchar(24) NOT NULL DEFAULT 'active',
			scopes longtext NOT NULL,
			config longtext NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			last_seen_at datetime NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY binding_id (binding_id),
			UNIQUE KEY installation_id (installation_id),
			KEY tenant_project (tenant_id,project_id),
			KEY connection_id (connection_id)
		) $charset;";

		$sql[] = "CREATE TABLE {$tables['keys']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			binding_id varchar(191) NOT NULL,
			key_id varchar(191) NOT NULL,
			secret_cipher longtext NOT NULL,
			status varchar(24) NOT NULL DEFAULT 'active',
			not_before datetime NULL,
			expires_at datetime NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY key_id (key_id),
			KEY binding_status (binding_id,status)
		) $charset;";

		$sql[] = "CREATE TABLE {$tables['nonces']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			key_id varchar(191) NOT NULL,
			nonce varchar(96) NOT NULL,
			request_id varchar(96) NOT NULL,
			response_cache longtext NULL,
			created_at datetime NOT NULL,
			expires_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY key_nonce (key_id,nonce),
			KEY expires_at (expires_at),
			KEY request_id (request_id)
		) $charset;";

		$sql[] = "CREATE TABLE {$tables['rate_limits']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			binding_id varchar(191) NOT NULL,
			bucket varchar(64) NOT NULL,
			window_start datetime NOT NULL,
			request_count int(11) unsigned NOT NULL DEFAULT 0,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY binding_bucket_window (binding_id,bucket,window_start),
			KEY updated_at (updated_at)
		) $charset;";

		$sql[] = "CREATE TABLE {$tables['audit']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			binding_id varchar(191) NULL,
			request_id varchar(96) NULL,
			event varchar(96) NOT NULL,
			result varchar(24) NOT NULL,
			actor_type varchar(24) NOT NULL,
			actor_id varchar(191) NULL,
			metadata longtext NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY binding_created (binding_id,created_at),
			KEY event_created (event,created_at)
		) $charset;";

		$sql[] = "CREATE TABLE {$tables['outbox']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_id varchar(96) NOT NULL,
			binding_id varchar(191) NOT NULL,
			event_name varchar(96) NOT NULL,
			payload longtext NOT NULL,
			status varchar(24) NOT NULL DEFAULT 'pending',
			attempts int(11) unsigned NOT NULL DEFAULT 0,
			next_attempt_at datetime NOT NULL,
			last_error varchar(500) NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY event_id (event_id),
			KEY dispatch_queue (status,next_attempt_at),
			KEY binding_id (binding_id)
		) $charset;";

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}

		update_option( self::DB_OPTION, POPI_CONNECTOR_DB_VERSION, false );
	}
}
