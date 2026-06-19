<?php

namespace Popi\Events;

defined( 'ABSPATH' ) || exit;

/**
 * Nacitani vlastnich sablon pluginu. Pokud aktivni tema definuje vlastni
 * single-event.php / archive-event.php, ma prioritu (standardni WP chovani).
 */
class Templates {

	const PAGE_TEMPLATE = 'popi-events-calendar.php';

	public static function init(): void {
		add_filter( 'template_include', array( self::class, 'template_include' ) );
		add_filter( 'theme_page_templates', array( self::class, 'register_page_template' ) );
	}

	public static function template_include( string $template ): string {
		if ( is_singular( Cpt_Event::POST_TYPE ) && ! locate_template( 'single-event.php' ) ) {
			return POPI_EVENTS_DIR . 'templates/single-event.php';
		}

		if ( is_post_type_archive( Cpt_Event::POST_TYPE ) && ! locate_template( 'archive-event.php' ) ) {
			return POPI_EVENTS_DIR . 'templates/archive-event.php';
		}

		if ( is_page() ) {
			$slug = get_page_template_slug( get_queried_object_id() );
			if ( self::PAGE_TEMPLATE === $slug ) {
				return POPI_EVENTS_DIR . 'templates/calendar.php';
			}
		}

		return $template;
	}

	public static function register_page_template( array $templates ): array {
		$templates[ self::PAGE_TEMPLATE ] = 'Kalendář termínů (Popi Events)';
		return $templates;
	}
}
