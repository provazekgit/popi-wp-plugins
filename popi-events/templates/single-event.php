<?php
/**
 * Sablona pro jednotlivy termin (CPT event).
 * SEO canonical na rodicovsky kurz resi includes/seo.php — tato sablona
 * jen zobrazuje obsah a odkaz zpet na kurz.
 */
defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	$event_id   = get_the_ID();
	$course_id  = (int) get_field( 'popi_event_course_id', $event_id );
	$date_start = get_field( 'popi_event_date_start', $event_id );
	?>
	<article class="popi-event-single">
		<header class="popi-event-single-header">
			<h1><?php the_title(); ?></h1>
			<?php if ( $date_start ) : ?>
				<p class="popi-event-single-date"><?php echo esc_html( popi_events_format_date( $date_start ) ); ?></p>
			<?php endif; ?>
		</header>

		<dl class="popi-event-single-meta">
			<?php $time_start = get_field( 'popi_event_time_start', $event_id ); ?>
			<?php $time_end   = get_field( 'popi_event_time_end', $event_id ); ?>
			<?php if ( $time_start || $time_end ) : ?>
				<div class="popi-event-single-meta-row">
					<dt>Čas</dt>
					<dd><?php echo esc_html( trim( $time_start . ( $time_end ? ' – ' . $time_end : '' ) ) ); ?></dd>
				</div>
			<?php endif; ?>

			<?php $hours = get_field( 'popi_event_hours_total', $event_id ); ?>
			<?php if ( $hours ) : ?>
				<div class="popi-event-single-meta-row">
					<dt>Rozsah</dt>
					<dd><?php echo esc_html( $hours ); ?> hod.</dd>
				</div>
			<?php endif; ?>

			<?php $location = get_field( 'popi_event_location', $event_id ); ?>
			<?php if ( $location ) : ?>
				<div class="popi-event-single-meta-row">
					<dt>Místo</dt>
					<dd><?php echo esc_html( $location ); ?></dd>
				</div>
			<?php endif; ?>

			<?php $price = get_field( 'popi_event_price', $event_id ); ?>
			<?php if ( $price ) : ?>
				<div class="popi-event-single-meta-row">
					<dt>Cena</dt>
					<dd><?php echo esc_html( $price ); ?></dd>
				</div>
			<?php endif; ?>
		</dl>

		<?php $note = get_field( 'popi_event_note', $event_id ); ?>
		<?php if ( $note ) : ?>
			<p class="popi-event-single-note"><?php echo esc_html( $note ); ?></p>
		<?php endif; ?>

		<?php if ( $course_id ) : ?>
			<p class="popi-event-single-back">
				<a href="<?php echo esc_url( get_permalink( $course_id ) ); ?>">
					&larr; Zpět na kurz: <?php echo esc_html( get_the_title( $course_id ) ); ?>
				</a>
			</p>
		<?php endif; ?>
	</article>
	<?php
endwhile;

get_footer();
