<?php

namespace Popi\Events;

defined( 'ABSPATH' ) || exit;

/**
 * Automaticka aktualizace pluginu z update serveru na popisite.cz.
 * Stejny protokol jako sourozenecke pluginy (popi-landing-page, popi-clanky-blog).
 */
class Updater {

	private $plugin_slug;
	private $plugin_basename;
	private $update_url;
	private $current_version;

	public function __construct( $plugin_file, $update_url, $current_version ) {
		$this->plugin_slug     = 'popi-events';
		$this->plugin_basename = plugin_basename( $plugin_file );
		$this->update_url      = $update_url;
		$this->current_version = $current_version;

		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_info' ), 10, 3 );
		add_filter( 'upgrader_source_selection', array( $this, 'fix_source_dir' ), 10, 4 );
	}

	public function check_update( $transient ) {
		if ( ! is_object( $transient ) || empty( $transient->checked ) ) {
			return $transient;
		}

		$remote = $this->fetch_remote_data();
		if ( ! $remote || ! isset( $remote->version ) ) {
			return $transient;
		}

		if ( version_compare( $this->current_version, $remote->version, '<' ) ) {
			$transient->response[ $this->plugin_basename ] = (object) array(
				'slug'         => $this->plugin_slug,
				'plugin'       => $this->plugin_basename,
				'new_version'  => $remote->version,
				'url'          => 'https://popisite.cz/plugins/popi-events/',
				'package'      => isset( $remote->download_url ) ? $remote->download_url : '',
				'requires'     => isset( $remote->requires ) ? $remote->requires : '6.2',
				'requires_php' => isset( $remote->requires_php ) ? $remote->requires_php : '7.4',
				'tested'       => isset( $remote->tested ) ? $remote->tested : '',
			);
		}

		return $transient;
	}

	public function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}
		if ( ! is_object( $args ) || ! isset( $args->slug ) || $args->slug !== $this->plugin_slug ) {
			return $result;
		}

		$remote = $this->fetch_remote_data();
		if ( ! $remote ) {
			return $result;
		}

		return (object) array(
			'name'          => 'Popi Events',
			'slug'          => $this->plugin_slug,
			'version'       => isset( $remote->version ) ? $remote->version : $this->current_version,
			'author'        => '<a href="https://popisite.cz">Karel Provázek – Popiweb</a>',
			'homepage'      => 'https://popisite.cz/plugins/popi-events/',
			'requires'      => isset( $remote->requires ) ? $remote->requires : '6.2',
			'requires_php'  => isset( $remote->requires_php ) ? $remote->requires_php : '7.4',
			'tested'        => isset( $remote->tested ) ? $remote->tested : '',
			'last_updated'  => isset( $remote->last_updated ) ? $remote->last_updated : '',
			'download_link' => isset( $remote->download_url ) ? $remote->download_url : '',
			'sections'      => isset( $remote->sections ) ? (array) $remote->sections : array(),
		);
	}

	public function fix_source_dir( $source, $_remote_source, $_upgrader, $hook_extra ) {
		if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->plugin_basename ) {
			return $source;
		}

		$correct = trailingslashit( dirname( $source ) ) . $this->plugin_slug . '/';

		if ( $source !== $correct ) {
			rename( $source, $correct );
			return $correct;
		}

		return $source;
	}

	private function fetch_remote_data() {
		$transient_key = 'popi_events_update_data';
		$cached        = get_transient( $transient_key );

		if ( false !== $cached ) {
			return $cached ? $cached : null;
		}

		$response = wp_remote_get( $this->update_url, array(
			'timeout'    => 10,
			'user-agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ),
		) );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			set_transient( $transient_key, false, HOUR_IN_SECONDS );
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ) );

		if ( ! $data ) {
			set_transient( $transient_key, false, HOUR_IN_SECONDS );
			return null;
		}

		set_transient( $transient_key, $data, 12 * HOUR_IN_SECONDS );
		return $data;
	}
}
