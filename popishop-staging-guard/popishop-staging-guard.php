<?php
/**
 * Plugin Name: POPIshop Staging Guard
 * Description: Bezpečnostní pojistky pro neveřejnou WooCommerce staging kopii.
 * Version: 0.1.0
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Author: POPI
 */

defined('ABSPATH') || exit;

final class POPIshop_Staging_Guard {
    const VERSION = '0.2.0';
    const MAIL_LOG_OPTION = 'popishop_staging_mail_log';
    const MAX_MAIL_LOG = 50;

    public static function boot() {
        if (!self::is_staging()) {
            return;
        }
        add_filter('wp_robots', array(__CLASS__, 'robots'));
        add_filter('wp_sitemaps_enabled', '__return_false');
        add_filter('pre_option_blog_public', '__return_zero');
        add_filter('pre_wp_mail', array(__CLASS__, 'capture_mail'), 10, 2);
        add_filter('woocommerce_available_payment_gateways', array(__CLASS__, 'limit_payment_gateways'));
        add_filter('woocommerce_allow_tracking', '__return_false');
        add_filter('wp_headers', array(__CLASS__, 'protect_response_headers'));
        add_action('send_headers', array(__CLASS__, 'send_noindex_header'));
        add_action('admin_menu', array(__CLASS__, 'admin_menu'));
        add_action('admin_notices', array(__CLASS__, 'admin_notice'));
        add_action('admin_post_popishop_staging_clear_mail_log', array(__CLASS__, 'clear_mail_log'));
    }

    public static function is_staging() {
        if (function_exists('wp_get_environment_type')) {
            return wp_get_environment_type() === 'staging';
        }
        return defined('WP_ENVIRONMENT_TYPE') && WP_ENVIRONMENT_TYPE === 'staging';
    }

    public static function robots($robots) {
        $robots['noindex'] = true;
        $robots['nofollow'] = true;
        $robots['noarchive'] = true;
        return $robots;
    }

    public static function send_noindex_header() {
        if (!headers_sent()) {
            header('X-Robots-Tag: noindex, nofollow, noarchive', true);
            header('X-POPIshop-Staging-Guard: active', true);
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0', true);
        }
    }

    public static function protect_response_headers($headers) {
        $headers = is_array($headers) ? $headers : array();
        $headers['X-Robots-Tag'] = 'noindex, nofollow, noarchive';
        $headers['X-POPIshop-Staging-Guard'] = 'active';
        $headers['Cache-Control'] = 'no-store, no-cache, must-revalidate, max-age=0';
        return $headers;
    }

    public static function capture_mail($return, $attributes) {
        $attributes = is_array($attributes) ? $attributes : array();
        $recipients = isset($attributes['to']) ? (array) $attributes['to'] : array();
        $entry = array(
            'captured_at' => gmdate('c'),
            'recipients' => array_map(array(__CLASS__, 'redact_recipient'), $recipients),
            'subject' => isset($attributes['subject']) ? (string) $attributes['subject'] : '',
            'message' => isset($attributes['message']) ? (string) $attributes['message'] : '',
        );
        $log = get_option(self::MAIL_LOG_OPTION, array());
        $log = is_array($log) ? $log : array();
        array_unshift($log, $entry);
        update_option(self::MAIL_LOG_OPTION, array_slice($log, 0, self::MAX_MAIL_LOG), false);
        return true;
    }

    public static function limit_payment_gateways($gateways) {
        $allowed = defined('POPISHOP_STAGING_ALLOWED_GATEWAYS')
            ? array_filter(array_map('trim', explode(',', POPISHOP_STAGING_ALLOWED_GATEWAYS)))
            : array('cod', 'bacs');
        foreach (array_keys($gateways) as $gateway_id) {
            if (!in_array($gateway_id, $allowed, true)) {
                unset($gateways[$gateway_id]);
            }
        }
        return $gateways;
    }

    public static function admin_menu() {
        add_submenu_page(
            'woocommerce',
            'Staging e-maily',
            'Staging e-maily',
            'manage_woocommerce',
            'popishop-staging-mail',
            array(__CLASS__, 'mail_log_page')
        );
    }

    public static function admin_notice() {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }
        echo '<div class="notice notice-warning"><p><strong>POPIshop staging:</strong> indexace, ostré e-maily a nepovolené platební brány jsou zablokované.</p></div>';
    }

    public static function mail_log_page() {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }
        $log = get_option(self::MAIL_LOG_OPTION, array());
        $log = is_array($log) ? $log : array();
        $clear_url = wp_nonce_url(
            admin_url('admin-post.php?action=popishop_staging_clear_mail_log'),
            'popishop_staging_clear_mail_log'
        );
        echo '<div class="wrap"><h1>Zachycené staging e-maily</h1>';
        echo '<p>Tyto zprávy nebyly odeslány. Adresáti jsou uloženi pouze jako nevratné otisky.</p>';
        if ($log) {
            echo '<p><a class="button" href="' . esc_url($clear_url) . '">Vymazat záznamy</a></p>';
        }
        foreach ($log as $entry) {
            echo '<div class="card" style="max-width:900px"><h2>' . esc_html($entry['subject']) . '</h2>';
            echo '<p><code>' . esc_html($entry['captured_at']) . '</code> · ' . esc_html(implode(', ', $entry['recipients'])) . '</p>';
            echo '<pre style="white-space:pre-wrap">' . esc_html($entry['message']) . '</pre></div>';
        }
        if (!$log) {
            echo '<p>Zatím nebyl zachycen žádný e-mail.</p>';
        }
        echo '</div>';
    }

    public static function clear_mail_log() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die('Nemáte oprávnění tuto akci provést.');
        }
        check_admin_referer('popishop_staging_clear_mail_log');
        delete_option(self::MAIL_LOG_OPTION);
        wp_safe_redirect(admin_url('admin.php?page=popishop-staging-mail'));
        exit;
    }

    private static function redact_recipient($recipient) {
        $normalized = strtolower(trim((string) $recipient));
        return 'sha256:' . substr(hash('sha256', $normalized), 0, 12);
    }
}

POPIshop_Staging_Guard::boot();
