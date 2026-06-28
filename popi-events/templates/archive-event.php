<?php
/**
 * Archiv vsech terminu (/terminy/) s jednoduchym filtrem podle mesice,
 * roku, mista a kurzu. Cte parametry z $_GET — zadne AJAX, zadny JS framework.
 */
defined( 'ABSPATH' ) || exit;

get_header();

$filter_course   = isset( $_GET['course'] ) ? absint( $_GET['course'] ) : 0;
$filter_month    = isset( $_GET['month'] ) ? absint( $_GET['month'] ) : 0;
$filter_year     = isset( $_GET['year'] ) ? absint( $_GET['year'] ) : 0;
$filter_location = isset( $_GET['location'] ) ? sanitize_text_field( wp_unslash( $_GET['location'] ) ) : '';
$filter_date     = isset( $_GET['date'] ) ? sanitize_text_field( wp_unslash( $_GET['date'] ) ) : '';

$meta_query = array();

if ( $filter_date && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $filter_date ) ) {
	$meta_query[] = array( 'key' => 'popi_event_date_start', 'value' => $filter_date, 'compare' => '=' );
} elseif ( $filter_month || $filter_year ) {
	$year  = $filter_year ?: (int) current_time( 'Y' );
	if ( $filter_month ) {
		$start = sprintf( '%04d-%02d-01', $year, $filter_month );
		$end   = date( 'Y-m-t', strtotime( $start ) );
	} else {
		$start = sprintf( '%04d-01-01', $year );
		$end   = sprintf( '%04d-12-31', $year );
	}
	$meta_query[] = array( 'key' => 'popi_event_date_start', 'value' => array( $start, $end ), 'compare' => 'BETWEEN', 'type' => 'DATE' );
}

if ( $filter_course ) {
	$meta_query[] = array( 'key' => 'popi_event_course_id', 'value' => $filter_course );
}

if ( $filter_location ) {
	$meta_query[] = array( 'key' => 'popi_event_location', 'value' => $filter_location, 'compare' => 'LIKE' );
}

$query_args = array(
	'post_type'      => \Popi\Events\Cpt_Event::POST_TYPE,
	'post_status'    => 'publish',
	'posts_per_page' => -1,
	'meta_key'       => 'popi_event_date_start',
	'orderby'        => 'meta_value',
	'order'          => 'ASC',
);
if ( $meta_query ) {
	$query_args['meta_query'] = $meta_query;
}

$events = get_posts( $query_args );
$courses = popi_events_get_courses();
?>

<div class="popi-events-archive">
	<h1>Termíny kurzů</h1>

	<form class="popi-events-filter" method="get">
		<select name="course">
			<option value="">Všechny kurzy</option>
			<?php foreach ( $courses as $course ) : ?>
				<option value="<?php echo (int) $course->ID; ?>" <?php selected( $filter_course, $course->ID ); ?>>
					<?php echo esc_html( get_the_title( $course ) ); ?>
				</option>
			<?php endforeach; ?>
		</select>

		<select name="month">
			<option value="">Měsíc</option>
			<?php for ( $m = 1; $m <= 12; $m++ ) : ?>
				<option value="<?php echo $m; ?>" <?php selected( $filter_month, $m ); ?>>
					<?php echo esc_html( date_i18n( 'F', strtotime( "2026-{$m}-01" ) ) ); ?>
				</option>
			<?php endfor; ?>
		</select>

		<input type="number" name="year" placeholder="Rok" value="<?php echo $filter_year ? (int) $filter_year : ''; ?>" min="2020" max="2100">

		<input type="text" name="location" placeholder="Místo" value="<?php echo esc_attr( $filter_location ); ?>">

		<button type="submit">Filtrovat</button>
		<a href="<?php echo esc_url( get_post_type_archive_link( \Popi\Events\Cpt_Event::POST_TYPE ) ); ?>">Zrušit filtr</a>
	</form>

	<?php if ( ! $events ) : ?>
		<p class="popi-events-empty">Žádné termíny neodpovídají filtru.</p>
	<?php else : ?>
		<ul class="popi-events-list">
			<?php foreach ( $events as $event ) : ?>
				<?php $course_id = (int) popi_events_get_field( 'popi_event_course_id', $event->ID ); ?>
				<li class="popi-event-item">
					<a class="popi-event-link" href="<?php echo esc_url( get_permalink( $event ) ); ?>">
						<span class="popi-event-date"><?php echo esc_html( popi_events_format_date( popi_events_get_field( 'popi_event_date_start', $event->ID ) ) ); ?></span>
						<span class="popi-event-course"><?php echo esc_html( $course_id ? get_the_title( $course_id ) : get_the_title( $event ) ); ?></span>
						<?php $location = popi_events_get_field( 'popi_event_location', $event->ID ); ?>
						<?php if ( $location ) : ?>
							<span class="popi-event-location"><?php echo esc_html( $location ); ?></span>
						<?php endif; ?>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</div>

<?php
get_footer();
