<?php

defined( 'ABSPATH' ) || exit;

final class POPI_Connector_Rate_Limiter {

	public static function consume( $binding_id, $bucket, $limit, $window_seconds = 60 ) {
		global $wpdb;
		$table        = POPI_Connector_Storage::tables()['rate_limits'];
		$window_start = (int) ( floor( time() / $window_seconds ) * $window_seconds );
		$window_date  = gmdate( 'Y-m-d H:i:s', $window_start );
		$now          = POPI_Connector_Storage::utc_now();

		$query = $wpdb->prepare(
			"INSERT INTO $table (binding_id,bucket,window_start,request_count,updated_at)
			 VALUES (%s,%s,%s,1,%s)
			 ON DUPLICATE KEY UPDATE request_count = request_count + 1, updated_at = VALUES(updated_at)",
			$binding_id,
			$bucket,
			$window_date,
			$now
		);
		$wpdb->query( $query );
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT request_count FROM $table WHERE binding_id = %s AND bucket = %s AND window_start = %s",
				$binding_id,
				$bucket,
				$window_date
			)
		);

		if ( $count > $limit ) {
			return new WP_Error(
				'popi_rate_limited',
				'Limit požadavků byl překročen.',
				array( 'status' => 429, 'retry_after' => max( 1, $window_start + $window_seconds - time() ) )
			);
		}
		return true;
	}
}

