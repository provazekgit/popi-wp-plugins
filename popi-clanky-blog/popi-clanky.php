<?php
/**
 * Plugin Name: Popi CPT Články
 * Plugin URI:  https://popisite.cz/plugins/popi-clanky-blog/
 * Description: CPT Články se SEO poli pro weby Popiweb.
 * Version:     1.2.0
 * Author:      Karel Provázek – Popiweb
 * Author URI:  https://popisite.cz
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * License:     GPL-2.0+
 */

defined( 'ABSPATH' ) || exit;

define( 'POPI_CLANKY_VERSION',    '1.2.0' );
define( 'POPI_CLANKY_DIR',        plugin_dir_path( __FILE__ ) );
define( 'POPI_CLANKY_UPDATE_URL', 'https://api.popisite.cz/api/v1/public/plugins/popi-clanky-blog' );

require_once POPI_CLANKY_DIR . 'includes/class-cpt.php';
require_once POPI_CLANKY_DIR . 'includes/class-settings.php';
require_once POPI_CLANKY_DIR . 'includes/class-acf-fields.php';
require_once POPI_CLANKY_DIR . 'includes/class-updater.php';
require_once POPI_CLANKY_DIR . 'includes/class-docs.php';
require_once POPI_CLANKY_DIR . 'includes/functions.php';

// CPT
add_action( 'init', array( 'Popi_Clanky_CPT', 'register' ) );

// Nastavení (výchozí hodnoty) + Nápověda
Popi_Clanky_Settings::init();
Popi_Clanky_Docs::init();

// ACF pole
Popi_Clanky_ACF::init();

// Auto-aktualizace
add_action( 'plugins_loaded', function () {
	new Popi_Clanky_Updater( __FILE__, POPI_CLANKY_UPDATE_URL, POPI_CLANKY_VERSION );
} );

// Flush rewrite rules při aktivaci/deaktivaci
register_activation_hook( __FILE__, function () {
	Popi_Clanky_CPT::register();
	flush_rewrite_rules();
} );

register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
