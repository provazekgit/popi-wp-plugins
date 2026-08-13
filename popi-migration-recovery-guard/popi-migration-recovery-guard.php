<?php
/**
 * Plugin Name: POPI Migration Recovery Guard
 * Description: Docasne odstavi vybrane pluginy pri oprave neuplne WordPress migrace, bez zmeny databaze.
 * Version: 0.1.0
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Author: POPI
 */

defined('ABSPATH') || exit;

final class POPI_Migration_Recovery_Guard {
    const CONFIG_KEY = 'POPI_MIGRATION_DISABLED_PLUGINS';

    public static function boot() {
        add_filter('option_active_plugins', array(__CLASS__, 'filter_active_plugins'), 1);
        add_filter('site_option_active_sitewide_plugins', array(__CLASS__, 'filter_sitewide_plugins'), 1);
        add_action('admin_notices', array(__CLASS__, 'admin_notice'));
        add_action('network_admin_notices', array(__CLASS__, 'admin_notice'));
    }

    public static function disabled_plugins() {
        if (!defined(self::CONFIG_KEY)) {
            return array();
        }

        $configured = constant(self::CONFIG_KEY);
        $plugins = is_array($configured) ? $configured : explode(',', (string) $configured);
        $plugins = array_map(array(__CLASS__, 'normalize_plugin'), $plugins);
        return array_values(array_unique(array_filter($plugins)));
    }

    public static function filter_active_plugins($plugins) {
        if (!is_array($plugins)) {
            return $plugins;
        }

        $disabled = self::disabled_plugins();
        if (!$disabled) {
            return $plugins;
        }

        return array_values(array_filter($plugins, function ($plugin) use ($disabled) {
            return !in_array(self::normalize_plugin($plugin), $disabled, true);
        }));
    }

    public static function filter_sitewide_plugins($plugins) {
        if (!is_array($plugins)) {
            return $plugins;
        }

        foreach (self::disabled_plugins() as $plugin) {
            unset($plugins[$plugin]);
        }
        return $plugins;
    }

    public static function admin_notice() {
        if (!current_user_can('activate_plugins')) {
            return;
        }

        $disabled = self::disabled_plugins();
        if (!$disabled) {
            return;
        }

        echo '<div class="notice notice-error"><p><strong>POPI migration recovery:</strong> ';
        echo esc_html('Dočasně odstavené pluginy: ' . implode(', ', $disabled) . '. Databáze nebyla změněna.');
        echo '</p></div>';
    }

    private static function normalize_plugin($plugin) {
        $plugin = trim(str_replace('\\', '/', (string) $plugin));
        return ltrim($plugin, '/');
    }
}

POPI_Migration_Recovery_Guard::boot();
