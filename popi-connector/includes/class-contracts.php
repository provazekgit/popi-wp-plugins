<?php

defined( 'ABSPATH' ) || exit;

final class POPI_Connector_Contracts {

	const CONTRACT_VERSION = '1.0.0-rc.1';

	public static function health( $context ) {
		global $wpdb;
		$tables = POPI_Connector_Storage::tables();
		$table_status = array();
		foreach ( $tables as $name => $table ) {
			$table_status[ $name ] = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
		}
		return array(
			'ok'               => ! in_array( false, $table_status, true ),
			'plugin'           => 'popi-connector',
			'plugin_version'   => POPI_CONNECTOR_VERSION,
			'contract_version' => self::CONTRACT_VERSION,
			'wordpress_version'=> get_bloginfo( 'version' ),
			'php_version'      => PHP_VERSION,
			'server_time'      => time(),
			'multisite'        => is_multisite(),
			'tables'           => $table_status,
			'binding_status'   => $context['binding']['status'],
		);
	}

	public static function manifest() {
		return array(
			'protocols' => array( POPI_Connector_Crypto::PROTOCOL ),
			'contracts' => array(
				'core'     => self::CONTRACT_VERSION,
				'popiweb'  => self::CONTRACT_VERSION,
				'popicast' => self::CONTRACT_VERSION,
			),
			'operations' => array(
				'core.health', 'core.manifest', 'core.site', 'core.frontend.get', 'core.frontend.set',
				'core.key.prepare', 'core.key.commit', 'core.revoke',
				'popiweb.schema.read', 'popiweb.entries.search', 'popiweb.entries.get', 'popiweb.entries.patch',
				'popicast.show.get', 'popicast.episodes.search', 'popicast.episodes.get',
			),
			'destructive_operations' => array(),
			'max_request_bytes' => POPI_Connector_Authentication::MAX_BODY_BYTES,
		);
	}

	public static function site() {
		return array(
			'site_instance_id' => get_option( 'popi_connector_site_instance_id' ),
			'canonical_url'     => home_url( '/' ),
			'rest_url'          => rest_url( 'popi-connector/v1' ),
			'name'              => get_bloginfo( 'name' ),
			'description'       => get_bloginfo( 'description' ),
			'language'          => get_bloginfo( 'language' ),
			'timezone'          => wp_timezone_string(),
		);
	}

	public static function schema_read( $context ) {
		$allowed = self::allowed_post_types( $context['binding'] );
		$output  = array();
		foreach ( $allowed as $post_type ) {
			$object = get_post_type_object( $post_type );
			if ( ! $object || empty( $object->show_in_rest ) ) {
				continue;
			}
			$taxonomies = array_values( get_object_taxonomies( $post_type, 'names' ) );
			$meta       = array_keys( get_registered_meta_keys( 'post', $post_type ) );
			$output[]   = array(
				'name'       => $post_type,
				'label'      => isset( $object->labels->singular_name ) ? $object->labels->singular_name : $object->label,
				'rest_base'  => $object->rest_base ? $object->rest_base : $post_type,
				'taxonomies' => $taxonomies,
				'meta_keys'  => array_values( array_intersect( $meta, self::allowed_meta_keys( $context['binding'] ) ) ),
			);
		}
		return array( 'post_types' => $output );
	}

	public static function entries_search( $context, $module = 'popiweb' ) {
		$payload   = $context['payload'];
		$post_type = isset( $payload['post_type'] ) ? sanitize_key( $payload['post_type'] ) : self::default_post_type( $context['binding'], $module );
		if ( ! self::post_type_allowed( $context['binding'], $post_type ) ) {
			return self::error( 'popi_post_type_denied', 'Tento typ obsahu není pro připojení povolený.', 403 );
		}
		$page     = isset( $payload['page'] ) ? max( 1, (int) $payload['page'] ) : 1;
		$per_page = isset( $payload['per_page'] ) ? max( 1, min( 100, (int) $payload['per_page'] ) ) : 50;
		$args = array(
			'post_type'              => $post_type,
			'post_status'            => self::allowed_statuses( $context['binding'] ),
			'paged'                  => $page,
			'posts_per_page'         => $per_page,
			'orderby'                => 'modified',
			'order'                  => 'ASC',
			'no_found_rows'          => false,
			'ignore_sticky_posts'    => true,
			'update_post_term_cache' => false,
		);
		if ( ! empty( $payload['modified_after'] ) && self::valid_gmt_datetime( $payload['modified_after'] ) ) {
			$args['date_query'] = array( array( 'column' => 'post_modified_gmt', 'after' => $payload['modified_after'], 'inclusive' => false ) );
		}
		$query = new WP_Query( $args );
		$items = array();
		foreach ( $query->posts as $post ) {
			$items[] = self::serialize_post( $post, $context['binding'] );
		}
		return array(
			'items'       => $items,
			'page'        => $page,
			'per_page'    => $per_page,
			'total'       => (int) $query->found_posts,
			'total_pages' => (int) $query->max_num_pages,
		);
	}

	public static function entry_get( $context ) {
		$id   = isset( $context['payload']['id'] ) ? (int) $context['payload']['id'] : 0;
		$post = $id > 0 ? get_post( $id ) : null;
		if ( ! $post || ! self::post_type_allowed( $context['binding'], $post->post_type ) ) {
			return self::error( 'popi_entry_not_found', 'Záznam nebyl nalezen.', 404 );
		}
		return self::serialize_post( $post, $context['binding'] );
	}

	public static function entry_patch( $context ) {
		$payload = $context['payload'];
		$id      = isset( $payload['id'] ) ? (int) $payload['id'] : 0;
		$post    = $id > 0 ? get_post( $id ) : null;
		if ( ! $post || ! self::post_type_allowed( $context['binding'], $post->post_type ) ) {
			return self::error( 'popi_entry_not_found', 'Záznam nebyl nalezen.', 404 );
		}
		if ( empty( $payload['expected_modified_gmt'] ) || ! hash_equals( (string) $post->post_modified_gmt, (string) $payload['expected_modified_gmt'] ) ) {
			return self::error( 'popi_entry_conflict', 'Záznam byl mezitím změněn ve WordPressu.', 409 );
		}
		$changes = isset( $payload['changes'] ) && is_array( $payload['changes'] ) ? $payload['changes'] : array();
		$allowed = self::allowed_write_fields( $context['binding'] );
		$update  = array( 'ID' => $id );
		$map     = array( 'title' => 'post_title', 'content' => 'post_content', 'excerpt' => 'post_excerpt' );
		foreach ( $map as $input => $field ) {
			if ( in_array( $input, $allowed, true ) && isset( $changes[ $input ] ) && is_string( $changes[ $input ] ) ) {
				$update[ $field ] = wp_kses_post( $changes[ $input ] );
			}
		}
		if ( in_array( 'status', $allowed, true ) && isset( $changes['status'] ) ) {
			$status = sanitize_key( $changes['status'] );
			if ( ! in_array( $status, self::allowed_statuses( $context['binding'] ), true ) ) {
				return self::error( 'popi_status_denied', 'Požadovaný stav příspěvku není povolený.', 403 );
			}
			if ( 'publish' === $status && ! in_array( 'popiweb.entries:publish', POPI_Connector_Storage::binding_scopes( $context['binding'] ), true ) ) {
				return self::error( 'popi_publish_denied', 'Připojení nemá právo publikovat.', 403 );
			}
			$update['post_status'] = $status;
		}
		if ( count( $update ) > 1 ) {
			$result = wp_update_post( wp_slash( $update ), true );
			if ( is_wp_error( $result ) ) {
				return self::error( 'popi_entry_update_failed', $result->get_error_message(), 500 );
			}
		}
		if ( in_array( 'meta', $allowed, true ) && isset( $changes['meta'] ) && is_array( $changes['meta'] ) ) {
			$allowed_meta = self::allowed_meta_keys( $context['binding'] );
			foreach ( $changes['meta'] as $key => $value ) {
				$key = sanitize_key( $key );
				if ( ! in_array( $key, $allowed_meta, true ) || is_array( $value ) || is_object( $value ) ) {
					continue;
				}
				update_post_meta( $id, $key, sanitize_textarea_field( (string) $value ) );
			}
		}
		$updated = get_post( $id );
		POPI_Connector_Audit::record(
			'popiweb.entry_patched',
			'success',
			array(
				'binding_id' => $context['binding']['binding_id'],
				'request_id' => $context['request_id'],
				'actor_type' => 'service',
				'actor_id'   => $context['binding']['installation_id'],
				'metadata'   => array( 'post_id' => $id, 'post_type' => $post->post_type, 'fields' => array_keys( $changes ) ),
			)
		);
		return self::serialize_post( $updated, $context['binding'] );
	}

	public static function popicast_show() {
		return array(
			'name'        => get_bloginfo( 'name' ),
			'description' => get_bloginfo( 'description' ),
			'url'         => home_url( '/' ),
			'language'    => get_bloginfo( 'language' ),
			'site_icon'   => get_site_icon_url( 512 ),
			'updated_gmt' => get_lastpostmodified( 'GMT' ),
		);
	}

	private static function serialize_post( $post, $binding ) {
		$meta = array();
		foreach ( self::allowed_meta_keys( $binding ) as $key ) {
			$value = get_post_meta( $post->ID, $key, true );
			if ( is_scalar( $value ) || null === $value ) {
				$meta[ $key ] = $value;
			}
		}
		return array(
			'id'                => (int) $post->ID,
			'post_type'         => $post->post_type,
			'slug'              => $post->post_name,
			'status'            => $post->post_status,
			'title'             => $post->post_title,
			'excerpt'           => $post->post_excerpt,
			'content'           => $post->post_content,
			'modified_gmt'      => $post->post_modified_gmt,
			'featured_media_id' => (int) get_post_thumbnail_id( $post->ID ),
			'link'              => get_permalink( $post->ID ),
			'meta'              => $meta,
		);
	}

	private static function allowed_post_types( $binding ) {
		$config = POPI_Connector_Storage::binding_config( $binding );
		$types  = isset( $config['allowed_post_types'] ) && is_array( $config['allowed_post_types'] ) ? $config['allowed_post_types'] : array();
		if ( ! $types ) {
			$types = 'popicast' === $binding['module'] ? array( 'podcast', 'episode' ) : array( 'post', 'page' );
		}
		return array_values( array_unique( array_filter( array_map( 'sanitize_key', $types ) ) ) );
	}

	private static function default_post_type( $binding, $module ) {
		$types = self::allowed_post_types( $binding );
		return isset( $types[0] ) ? $types[0] : ( 'popicast' === $module ? 'episode' : 'post' );
	}

	private static function allowed_meta_keys( $binding ) {
		$config = POPI_Connector_Storage::binding_config( $binding );
		$keys   = isset( $config['allowed_meta_keys'] ) && is_array( $config['allowed_meta_keys'] ) ? $config['allowed_meta_keys'] : array();
		return array_values( array_unique( array_filter( array_map( 'sanitize_key', $keys ) ) ) );
	}

	private static function allowed_write_fields( $binding ) {
		$config = POPI_Connector_Storage::binding_config( $binding );
		$fields = isset( $config['allowed_write_fields'] ) && is_array( $config['allowed_write_fields'] ) ? $config['allowed_write_fields'] : array( 'title', 'content', 'excerpt', 'status', 'meta' );
		return array_values( array_intersect( array( 'title', 'content', 'excerpt', 'status', 'meta' ), array_map( 'sanitize_key', $fields ) ) );
	}

	private static function allowed_statuses( $binding ) {
		$config   = POPI_Connector_Storage::binding_config( $binding );
		$statuses = isset( $config['allowed_statuses'] ) && is_array( $config['allowed_statuses'] ) ? $config['allowed_statuses'] : array( 'publish', 'draft', 'pending', 'private', 'future' );
		return array_values( array_intersect( array( 'publish', 'draft', 'pending', 'private', 'future' ), array_map( 'sanitize_key', $statuses ) ) );
	}

	private static function post_type_allowed( $binding, $post_type ) {
		return in_array( sanitize_key( $post_type ), self::allowed_post_types( $binding ), true );
	}

	private static function valid_gmt_datetime( $value ) {
		return is_string( $value ) && (bool) preg_match( '/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}$/', $value );
	}

	private static function error( $code, $message, $status ) {
		return new WP_Error( $code, $message, array( 'status' => $status ) );
	}
}
