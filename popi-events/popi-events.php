<?php
/**
 * Plugin Name: Popi Events
 * Plugin URI:  https://popisite.cz/plugins/popi-events/
 * Description: Lehka sprava kurzu a jejich terminu (CPT, ACF, shortcody, archiv, kalendar) — nahrazuje The Events Calendar pro SEO-cisty use-case kurzu s opakovanymi terminy.
 * Version:     1.3.5
 * Author:      Karel Provázek – Popiweb
 * Author URI:  https://popisite.cz
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * License:     GPL-2.0+
 */

defined( 'ABSPATH' ) || exit;

define( 'POPI_EVENTS_VERSION',    '1.3.5' );
define( 'POPI_EVENTS_DIR',        plugin_dir_path( __FILE__ ) );
define( 'POPI_EVENTS_URL',        plugin_dir_url( __FILE__ ) );
define( 'POPI_EVENTS_UPDATE_URL', 'https://api.popisite.cz/api/v1/public/plugins/popi-events' );

require_once POPI_EVENTS_DIR . 'includes/cpt-course.php';
require_once POPI_EVENTS_DIR . 'includes/cpt-event.php';
require_once POPI_EVENTS_DIR . 'includes/acf-fields.php';
require_once POPI_EVENTS_DIR . 'includes/templates.php';
require_once POPI_EVENTS_DIR . 'includes/seo.php';
require_once POPI_EVENTS_DIR . 'includes/class-settings.php';
require_once POPI_EVENTS_DIR . 'includes/class-docs.php';
require_once POPI_EVENTS_DIR . 'includes/class-updater.php';
require_once POPI_EVENTS_DIR . 'includes/class-utm-generator.php';
require_once POPI_EVENTS_DIR . 'includes/schema.php';
require_once POPI_EVENTS_DIR . 'includes/helpers.php';
require_once POPI_EVENTS_DIR . 'includes/shortcodes.php';

use Popi\Events\Cpt_Course;
use Popi\Events\Cpt_Event;
use Popi\Events\Acf_Fields;
use Popi\Events\Templates;
use Popi\Events\Seo;
use Popi\Events\Settings;
use Popi\Events\Docs;
use Popi\Events\Shortcodes;
use Popi\Events\Updater;
use Popi\Events\Utm_Generator;
use Popi\Events\Schema;

// CPT
add_action( 'init', array( Cpt_Course::class, 'register' ) );
add_action( 'init', array( Cpt_Event::class, 'register' ) );
Cpt_Event::init();

// ACF pole
Acf_Fields::init();

// Shortcody
Shortcodes::init();

// Sablony (single-event, archive-event, vyber stranky Kalendar)
Templates::init();

// SEO (canonical na kurz, noindex)
Seo::init();

// Schema.org JSON-LD (Event, na strance kurzu)
Schema::init();

// UTM generator (odkazy na kurz pro jednotlive kanaly)
Utm_Generator::init();

// Nastaveni (vychozi hodnoty) + Napoveda
Settings::init();
Docs::init();

// Auto-aktualizace
add_action( 'plugins_loaded', function () {
	new Updater( __FILE__, POPI_EVENTS_UPDATE_URL, POPI_EVENTS_VERSION );
} );

// Upozorneni v adminu, pokud chybi ACF — bez nej plugin nema odkud cist
// data kurzu/terminu (vsechna vlastni pole jsou ACF pole).
add_action( 'admin_notices', function () {
	if ( function_exists( 'get_field' ) || ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	echo '<div class="notice notice-error"><p><strong>Popi Events:</strong> plugin <strong>Advanced Custom Fields</strong> (ACF) neni aktivni. Bez nej nepujde vyplnit ani zobrazit zadne pole kurzu/terminu (datum, misto, cena...). Nainstaluj a aktivuj ACF (postaci bezplatna verze).</p></div>';
} );

// Flush rewrite rules pri aktivaci/deaktivaci
register_activation_hook( __FILE__, function () {
	Cpt_Course::register();
	Cpt_Event::register();
	flush_rewrite_rules();
} );

register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
