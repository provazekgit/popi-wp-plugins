<?php

namespace Popi\Events;

defined( 'ABSPATH' ) || exit;

class Shortcodes {

	public static function init(): void {
		add_shortcode( 'popi_course_events', array( self::class, 'course_events' ) );
		add_shortcode( 'popi_upcoming_events', array( self::class, 'upcoming_events' ) );
		add_shortcode( 'popi_events_calendar', array( self::class, 'events_calendar' ) );
		add_shortcode( 'popi_courses', array( self::class, 'courses' ) );
	}

	/** [popi_course_events id="123"] */
	public static function course_events( $atts ): string {
		$atts = shortcode_atts( array( 'id' => 0 ), $atts );
		$course_id = (int) $atts['id'];
		if ( ! $course_id ) {
			return '';
		}

		$events = \popi_events_get_course_events( $course_id );
		if ( ! $events ) {
			return '<p class="popi-events-empty">Pro tento kurz momentálně nejsou vypsané žádné termíny.</p>';
		}

		ob_start();
		?>
		<ul class="popi-events-list">
			<?php foreach ( $events as $event ) : ?>
				<li class="popi-event-item">
					<a class="popi-event-link" href="<?php echo esc_url( get_permalink( $event ) ); ?>">
						<span class="popi-event-date"><?php echo esc_html( \popi_events_format_date( get_field( 'popi_event_date_start', $event->ID ) ) ); ?></span>
						<?php $location = get_field( 'popi_event_location', $event->ID ); ?>
						<?php if ( $location ) : ?>
							<span class="popi-event-location"><?php echo esc_html( $location ); ?></span>
						<?php endif; ?>
						<?php $price = get_field( 'popi_event_price', $event->ID ); ?>
						<?php if ( $price ) : ?>
							<span class="popi-event-price"><?php echo esc_html( $price ); ?></span>
						<?php endif; ?>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
		return (string) ob_get_clean();
	}

	/** [popi_upcoming_events days="30"] */
	public static function upcoming_events( $atts ): string {
		$atts = shortcode_atts( array( 'days' => 30 ), $atts );
		$events = \popi_events_get_upcoming_events( (int) $atts['days'] );

		if ( ! $events ) {
			return '<p class="popi-events-empty">V nejbližších dnech nejsou vypsané žádné termíny.</p>';
		}

		ob_start();
		?>
		<ul class="popi-events-list popi-events-list--upcoming">
			<?php foreach ( $events as $event ) : ?>
				<?php $course_id = (int) get_field( 'popi_event_course_id', $event->ID ); ?>
				<li class="popi-event-item">
					<a class="popi-event-link" href="<?php echo esc_url( get_permalink( $event ) ); ?>">
						<span class="popi-event-date"><?php echo esc_html( \popi_events_format_date( get_field( 'popi_event_date_start', $event->ID ) ) ); ?></span>
						<span class="popi-event-course"><?php echo esc_html( $course_id ? get_the_title( $course_id ) : get_the_title( $event ) ); ?></span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
		return (string) ob_get_clean();
	}

	/** [popi_events_calendar month="current"] nebo month="2026-06" */
	public static function events_calendar( $atts ): string {
		$atts = shortcode_atts( array( 'month' => 'current' ), $atts );

		$year_month = isset( $_GET['popi_cal'] ) ? sanitize_text_field( wp_unslash( $_GET['popi_cal'] ) ) : $atts['month'];
		if ( 'current' === $year_month || ! preg_match( '/^\d{4}-\d{2}$/', $year_month ) ) {
			$year_month = current_time( 'Y-m' );
		}

		$events_by_day = array();
		foreach ( \popi_events_get_month_events( $year_month ) as $event ) {
			$date = get_field( 'popi_event_date_start', $event->ID );
			$events_by_day[ $date ][] = $event;
		}

		$first_day_ts = strtotime( $year_month . '-01' );
		$days_in_month = (int) date( 't', $first_day_ts );
		$start_weekday = (int) date( 'N', $first_day_ts ); // 1 (po) - 7 (ne)

		$prev_month = date( 'Y-m', strtotime( '-1 month', $first_day_ts ) );
		$next_month = date( 'Y-m', strtotime( '+1 month', $first_day_ts ) );

		ob_start();
		?>
		<div class="popi-calendar">
			<div class="popi-calendar-nav">
				<a href="<?php echo esc_url( add_query_arg( 'popi_cal', $prev_month ) ); ?>" class="popi-calendar-prev">&laquo; předchozí</a>
				<span class="popi-calendar-title"><?php echo esc_html( date_i18n( 'F Y', $first_day_ts ) ); ?></span>
				<a href="<?php echo esc_url( add_query_arg( 'popi_cal', $next_month ) ); ?>" class="popi-calendar-next">následující &raquo;</a>
			</div>
			<table class="popi-calendar-grid">
				<thead>
					<tr>
						<th>Po</th><th>Út</th><th>St</th><th>Čt</th><th>Pá</th><th>So</th><th>Ne</th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<?php for ( $i = 1; $i < $start_weekday; $i++ ) : ?>
							<td class="popi-calendar-empty"></td>
						<?php endfor; ?>
						<?php
						$weekday = $start_weekday;
						for ( $day = 1; $day <= $days_in_month; $day++ ) :
							$date_str  = sprintf( '%s-%02d', $year_month, $day );
							$day_events = $events_by_day[ $date_str ] ?? array();
							?>
							<td class="popi-calendar-day<?php echo $day_events ? ' has-events' : ''; ?>">
								<span class="popi-calendar-daynum"><?php echo (int) $day; ?></span>
								<?php if ( $day_events ) : ?>
									<a class="popi-calendar-badge" href="<?php echo esc_url( get_post_type_archive_link( 'event' ) . '?date=' . $date_str ); ?>">
										<?php echo count( $day_events ); ?>×
									</a>
								<?php endif; ?>
							</td>
							<?php
							if ( 7 === $weekday && $day < $days_in_month ) {
								echo '</tr><tr>';
								$weekday = 1;
							} else {
								$weekday++;
							}
						endfor;
						for ( $i = $weekday; $i <= 7; $i++ ) :
							?>
							<td class="popi-calendar-empty"></td>
						<?php endfor; ?>
					</tr>
				</tbody>
			</table>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/** [popi_courses category="it"] */
	public static function courses( $atts ): string {
		$atts = shortcode_atts( array( 'category' => '' ), $atts );
		$courses = \popi_events_get_courses( $atts['category'] );

		if ( ! $courses ) {
			return '<p class="popi-courses-empty">Žádné kurzy k zobrazení.</p>';
		}

		ob_start();
		?>
		<ul class="popi-courses-list">
			<?php foreach ( $courses as $course ) : ?>
				<li class="popi-course-item">
					<a class="popi-course-link" href="<?php echo esc_url( get_permalink( $course ) ); ?>">
						<?php echo esc_html( get_the_title( $course ) ); ?>
					</a>
					<?php if ( has_excerpt( $course ) ) : ?>
						<p class="popi-course-excerpt"><?php echo esc_html( get_the_excerpt( $course ) ); ?></p>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
		return (string) ob_get_clean();
	}
}
