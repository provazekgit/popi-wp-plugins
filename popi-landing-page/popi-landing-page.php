<?php
/**
 * Plugin Name: Popi Landing Page
 * Plugin URI:  https://popisite.cz/plugins/popi-landing-page/
 * Description: CPT a ACF pole pro Sklik/Google Ads landing pages Papilonia Teplice.
 * Version:     1.0.0
 * Author:      Karel Provázek – Popiweb
 * Author URI:  https://popisite.cz
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * License:     GPL-2.0+
 */

defined( 'ABSPATH' ) || exit;

define( 'POPI_LANDING_VERSION',    '1.0.0' );
define( 'POPI_LANDING_DIR',        plugin_dir_path( __FILE__ ) );
define( 'POPI_LANDING_UPDATE_URL', 'https://api.popisite.cz/api/v1/public/plugins/popi-landing-page' );

require_once POPI_LANDING_DIR . 'includes/class-cpt.php';
require_once POPI_LANDING_DIR . 'includes/class-settings.php';
require_once POPI_LANDING_DIR . 'includes/class-acf-fields.php';
require_once POPI_LANDING_DIR . 'includes/class-updater.php';
require_once POPI_LANDING_DIR . 'includes/class-docs.php';
require_once POPI_LANDING_DIR . 'includes/functions.php';

// CPT + taxonomie + seed termů
add_action( 'init', array( 'Popi_Landing_CPT', 'register' ) );
add_action( 'init', array( 'Popi_Landing_CPT', 'register_taxonomy' ), 5 );
add_action( 'init', array( 'Popi_Landing_CPT', 'seed_terms' ), 20 );

// Nastavení (výchozí hodnoty) + Nápověda
Popi_Landing_Settings::init();
Popi_Landing_Docs::init();

// ACF pole
Popi_Landing_ACF::init();

// Auto-aktualizace
add_action( 'plugins_loaded', function () {
	new Popi_Landing_Updater( __FILE__, POPI_LANDING_UPDATE_URL, POPI_LANDING_VERSION );
} );

// Flush rewrite rules při aktivaci/deaktivaci
register_activation_hook( __FILE__, function () {
	Popi_Landing_CPT::register();
	flush_rewrite_rules();
} );

register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
