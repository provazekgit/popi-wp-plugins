<?php

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

wp_clear_scheduled_hook( 'popi_connector_cron' );
wp_clear_scheduled_hook( 'popi_connector_dispatch_outbox' );

// Připojení a audit se při běžném odinstalování záměrně zachovávají. Úplné
// smazání je dostupné jen jako výslovné provozní rozhodnutí ve wp-config.php.
if ( ! defined( 'POPI_CONNECTOR_REMOVE_DATA' ) || true !== POPI_CONNECTOR_REMOVE_DATA ) {
	return;
}

global $wpdb;
$prefix = $wpdb->prefix . 'popi_connector_';
foreach ( array( 'nonces', 'rate_limits', 'outbox', 'audit', 'keys', 'bindings' ) as $suffix ) {
	$table = $prefix . $suffix;
	$wpdb->query( "DROP TABLE IF EXISTS `$table`" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- fixed internal prefix, explicit opt-in.
}
foreach ( array( 'popi_connector_db_version', 'popi_connector_site_instance_id', 'popi_connector_api_base', 'popi_connector_frontend', 'popi_connector_frontend_snapshot', 'popi_connector_pending_claim' ) as $option ) {
	delete_option( $option );
}
