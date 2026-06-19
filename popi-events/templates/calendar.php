<?php
/**
 * Page Template: Kalendář termínů (Popi Events)
 *
 * Vyber tuto sablonu na strance v Atributy stranky -> Sablona, aby se na
 * dane strance zobrazil mesicni kalendarni grid terminu. Vnitrne pouziva
 * stejny shortcode jako [popi_events_calendar], aby nedoslo k duplicite kodu.
 */
defined( 'ABSPATH' ) || exit;

get_header();
?>

<div class="popi-events-calendar-page">
	<?php
	while ( have_posts() ) :
		the_post();
		the_title( '<h1>', '</h1>' );
		the_content();
	endwhile;

	echo do_shortcode( '[popi_events_calendar]' );
	?>
</div>

<?php
get_footer();
