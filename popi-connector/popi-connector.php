<?php
/**
 * Plugin Name: POPI Connector
 * Plugin URI:  https://popisite.cz/plugins/popi-connector/
 * Description: Bezpečné propojení WordPressu s POPIsite, POPIwebem a POPIcastem pomocí párovacího kódu a HMAC podpisů.
 * Version:     1.0.1
 * Author:      POPI
 * Author URI:  https://popisite.cz
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * License:     GPL-2.0+
 * Text Domain: popi-connector
 */

defined( 'ABSPATH' ) || exit;

define( 'POPI_CONNECTOR_VERSION', '1.0.1' );
define( 'POPI_CONNECTOR_DB_VERSION', '1.0.0' );
define( 'POPI_CONNECTOR_CONTRACT_VERSION', '1.0.0' );
define( 'POPI_CONNECTOR_FILE', __FILE__ );
define( 'POPI_CONNECTOR_DIR', plugin_dir_path( __FILE__ ) );
define( 'POPI_CONNECTOR_URL', plugin_dir_url( __FILE__ ) );
define( 'POPI_CONNECTOR_UPDATE_URL', 'https://api.popisite.cz/api/v1/public/plugins/popi-connector' );

require_once POPI_CONNECTOR_DIR . 'includes/class-installer.php';
require_once POPI_CONNECTOR_DIR . 'includes/class-storage.php';
require_once POPI_CONNECTOR_DIR . 'includes/class-audit.php';
require_once POPI_CONNECTOR_DIR . 'includes/class-crypto.php';
require_once POPI_CONNECTOR_DIR . 'includes/class-rate-limiter.php';
require_once POPI_CONNECTOR_DIR . 'includes/class-authentication.php';
require_once POPI_CONNECTOR_DIR . 'includes/class-remote.php';
require_once POPI_CONNECTOR_DIR . 'includes/class-pairing.php';
require_once POPI_CONNECTOR_DIR . 'includes/class-contracts.php';
require_once POPI_CONNECTOR_DIR . 'includes/class-rest-api.php';
require_once POPI_CONNECTOR_DIR . 'includes/class-outbox.php';
require_once POPI_CONNECTOR_DIR . 'includes/class-frontend.php';
require_once POPI_CONNECTOR_DIR . 'includes/class-admin.php';
require_once POPI_CONNECTOR_DIR . 'includes/class-updater.php';
require_once POPI_CONNECTOR_DIR . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'POPI_Connector_Installer', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'POPI_Connector_Installer', 'deactivate' ) );

POPI_Connector_Plugin::boot();
