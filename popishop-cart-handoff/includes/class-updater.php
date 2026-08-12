<?php

defined('ABSPATH') || exit;

final class POPIshop_Cart_Handoff_Updater {
    const SLUG = 'popishop-cart-handoff';
    const CACHE_KEY = 'popishop_cart_handoff_update_data';

    private $plugin_basename;
    private $update_url;
    private $current_version;
    private $remote_data;

    public function __construct($plugin_file, $update_url, $current_version) {
        $this->plugin_basename = plugin_basename($plugin_file);
        $this->update_url = $update_url;
        $this->current_version = $current_version;

        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_update'));
        add_filter('plugins_api', array($this, 'plugin_info'), 10, 3);
        add_filter('upgrader_pre_download', array($this, 'verify_download'), 10, 4);
        add_filter('upgrader_source_selection', array($this, 'fix_source_dir'), 10, 4);
    }

    public function check_update($transient) {
        if (!is_object($transient) || empty($transient->checked)) {
            return $transient;
        }

        $remote = $this->fetch_remote_data();
        if (!$remote || version_compare($this->current_version, $remote->version, '>=')) {
            return $transient;
        }

        $transient->response[$this->plugin_basename] = (object) array(
            'slug' => self::SLUG,
            'plugin' => $this->plugin_basename,
            'new_version' => $remote->version,
            'url' => 'https://popishop.eu',
            'package' => $remote->download_url,
            'requires' => $remote->requires,
            'requires_php' => $remote->requires_php,
            'tested' => $remote->tested,
        );

        return $transient;
    }

    public function plugin_info($result, $action, $args) {
        if ('plugin_information' !== $action || !is_object($args) || !isset($args->slug) || self::SLUG !== $args->slug) {
            return $result;
        }

        $remote = $this->fetch_remote_data();
        if (!$remote) {
            return $result;
        }

        return (object) array(
            'name' => 'POPIshop Cart Handoff',
            'slug' => self::SLUG,
            'version' => $remote->version,
            'author' => '<a href="https://popishop.eu">POPI</a>',
            'homepage' => 'https://popishop.eu',
            'requires' => $remote->requires,
            'requires_php' => $remote->requires_php,
            'tested' => $remote->tested,
            'last_updated' => $remote->last_updated,
            'download_link' => $remote->download_url,
            'sections' => (array) $remote->sections,
        );
    }

    public function verify_download($reply, $package, $_upgrader, $hook_extra) {
        if (!isset($hook_extra['plugin']) || $this->plugin_basename !== $hook_extra['plugin']) {
            return $reply;
        }
        if (is_wp_error($reply)) {
            return $reply;
        }

        $remote = $this->fetch_remote_data();
        if (!$remote || $package !== $remote->download_url) {
            return new WP_Error('popishop_update_package_invalid', 'Aktualizační balíček POPIshopu nelze ověřit.');
        }

        $temporary_file = download_url($package, 30);
        if (is_wp_error($temporary_file)) {
            return $temporary_file;
        }

        $checksum = hash_file('sha256', $temporary_file);
        if (!is_string($checksum) || !hash_equals($remote->sha256, strtolower($checksum))) {
            wp_delete_file($temporary_file);
            return new WP_Error('popishop_update_checksum_mismatch', 'Kontrolní součet aktualizace POPIshopu nesouhlasí.');
        }

        return $temporary_file;
    }

    public function fix_source_dir($source, $_remote_source, $_upgrader, $hook_extra) {
        if (!isset($hook_extra['plugin']) || $this->plugin_basename !== $hook_extra['plugin']) {
            return $source;
        }

        $correct = trailingslashit(dirname($source)) . self::SLUG . '/';
        if ($source !== $correct) {
            if (!rename($source, $correct)) {
                return new WP_Error('popishop_update_directory', 'Adresář aktualizace POPIshopu nelze připravit.');
            }
            return $correct;
        }

        return $source;
    }

    private function fetch_remote_data() {
        if (null !== $this->remote_data) {
            return $this->remote_data ?: null;
        }

        $cached = get_transient(self::CACHE_KEY);
        if (false !== $cached) {
            $this->remote_data = $cached;
            return $cached ?: null;
        }

        $response = wp_remote_get($this->update_url, array(
            'timeout' => 10,
            'user-agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url'),
        ));
        if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
            set_transient(self::CACHE_KEY, false, HOUR_IN_SECONDS);
            $this->remote_data = false;
            return null;
        }

        $remote = json_decode(wp_remote_retrieve_body($response));
        if (!$this->is_valid_remote_data($remote)) {
            set_transient(self::CACHE_KEY, false, HOUR_IN_SECONDS);
            $this->remote_data = false;
            return null;
        }

        set_transient(self::CACHE_KEY, $remote, 12 * HOUR_IN_SECONDS);
        $this->remote_data = $remote;
        return $remote;
    }

    private function is_valid_remote_data($remote) {
        return is_object($remote)
            && isset($remote->version, $remote->download_url, $remote->sha256)
            && is_string($remote->version)
            && preg_match('/^\d+\.\d+\.\d+$/', $remote->version)
            && is_string($remote->download_url)
            && 0 === strpos($remote->download_url, 'https://github.com/provazekgit/popi-wp-plugins/releases/download/popishop-v')
            && is_string($remote->sha256)
            && preg_match('/^[a-f0-9]{64}$/', $remote->sha256)
            && isset($remote->requires, $remote->requires_php, $remote->tested, $remote->last_updated, $remote->sections);
    }
}
