<?php

namespace Popi\Events;

defined( 'ABSPATH' ) || exit;

class Docs {

	public static function init(): void {
		add_action( 'admin_menu', array( self::class, 'add_page' ) );
	}

	public static function add_page(): void {
		add_submenu_page(
			'edit.php?post_type=' . Cpt_Course::POST_TYPE,
			'Nápověda',
			'Nápověda',
			'manage_options',
			'popi-events-docs',
			array( self::class, 'render_page' )
		);
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap" style="max-width:860px;">
			<h1>Termíny kurzů — Nápověda</h1>

			<?php self::section( 'Datový model', array(
				'Kurz (course)'  => 'Hlavní stránka kurzu. Pevná URL <code>/kurzy/nazev-kurzu/</code> — sbírá SEO autoritu. Dlouhý popis, sylabus, reference patří sem (do editoru kurzu), ne na termín.',
				'Termín (event)' => 'Samostatná položka pro konkrétní datum. Vlastní URL <code>/terminy/nazev/</code>, ale SEO canonical vždy směruje zpět na kurz — termín tedy nesoupeří s kurzem o SEO hodnotu.',
				'Vazba'          => 'Termín odkazuje na kurz přes pole <strong>Kurz</strong> (<code>popi_event_course_id</code>) — ACF Post Object pole.',
			) ); ?>

			<?php self::section( 'Pole termínu (ACF)', array(
				'Kurz'                  => 'Post Object pole — ke kterému kurzu termín patří. Povinné.',
				'Datum termínu'         => 'Date Picker, uložen jako <code>Y-m-d</code>. Řazení termínů (archiv, shortcody) je podle tohoto pole.',
				'Čas od / Čas do'       => 'Volný text (např. „09:00"), aby šlo zapsat i nepravidelné formáty.',
				'Celkový počet hodin'   => 'Číslo — rozsah kurzu v hodinách.',
				'Místo'                 => 'Volný text. Výchozí hodnota se nastavuje v podmenu Výchozí hodnoty.',
				'Cena'                  => 'Volný text (umožňuje i „od 2 990 Kč" nebo „na dotaz").',
				'Krátká poznámka'       => 'Krátký text pouze pro termín (např. „poslední volná místa"). Dlouhý popis nepatří sem — způsobil by duplicitní obsah s kurzem.',
				'Skrýt z vyhledávačů'   => 'Checkbox noindex. Doporučeno zapnuto u většiny termínů — viz sekce SEO níže.',
			) ); ?>

			<?php self::section( 'Shortcody', array(
				'[popi_course_events id="123"]'    => 'Výpis všech termínů konkrétního kurzu (podle ID kurzu), seřazené od nejbližšího data.',
				'[popi_upcoming_events days="30"]'  => 'Výpis termínů libovolného kurzu v následujících X dnech (výchozí 30).',
				'[popi_events_calendar month="current"]' => 'Měsíční kalendářní grid. <code>month</code> může být „current" nebo konkrétní <code>RRRR-MM</code>. Kliknutí na den s termínem vede do archivu filtrovaného na ten den.',
				'[popi_courses category="it"]'      => 'Výpis kurzů podle slugu kategorie (taxonomie Kategorie kurzu). Bez parametru zobrazí všechny kurzy.',
			) ); ?>

			<?php self::section( 'Archiv a kalendář', array(
				'Archiv termínů' => 'Dostupný na <code>/terminy/</code> automaticky (archiv CPT event). Obsahuje filtr podle kurzu, měsíce, roku a místa (GET parametry, žádný JS).',
				'Kalendář jako samostatná stránka' => 'Vytvoř novou stránku → v Atributy stránky → Šablona zvol <strong>Kalendář termínů (Popi Events)</strong>. Stránka zobrazí měsíční grid (stejná logika jako shortcode).',
				'Kalendář kdekoliv jinde' => 'Vlož shortcode <code>[popi_events_calendar]</code> do libovolné stránky nebo Bricks šablony.',
			) ); ?>

			<?php self::section( 'SEO logika', array(
				'Canonical' => 'Termín vždy nastaví <code>rel="canonical"</code> na URL svého kurzu — automaticky, není potřeba nic vyplňovat. Funguje samostatně, nebo se napojí na Yoast SEO / Rank Math, pokud je aktivní.',
				'Noindex'   => 'Pokud je u termínu zapnuté „Skrýt z vyhledávačů", plugin nastaví <code>noindex</code> (vlastní meta tag, nebo přes Yoast/Rank Math filtr).',
				'Proč'      => 'The Events Calendar a podobné pluginy generují pro každý termín plnohodnotnou indexovatelnou stránku → duplicitní obsah a tříštění SEO hodnoty mezi termíny. Tady všechna SEO hodnota zůstává na kurzu.',
			) ); ?>

			<?php self::section( 'Šablony', array(
				'single-event.php'  => 'Zobrazení jednotlivého termínu. Theme může přepsat vlastním souborem <code>single-event.php</code> ve své složce.',
				'archive-event.php' => 'Archiv na <code>/terminy/</code> s filtrem. Theme může přepsat vlastním <code>archive-event.php</code>.',
				'calendar.php'      => 'Šablona stránky „Kalendář termínů (Popi Events)" — volitelná, vybírá se v Atributy stránky.',
			) ); ?>

			<?php self::section( 'Rozšíření do budoucna', array(
				'API endpoints' => 'Datový model (helpers.php) je oddělený od výstupu — lze snadno přidat REST endpoint nad stejnými funkcemi.',
				'ICS export'    => 'Termín má vše potřebné (datum, čas, místo) pro generování .ics — připraveno jako budoucí doplněk, není v MVP.',
				'AJAX filtr'    => 'Archiv teď filtruje přes GET (funguje bez JS). AJAX verze může nahradit formulář bez změny datové vrstvy.',
			) ); ?>

		</div>
		<style>
			.popi-docs-section { margin: 0 0 32px; }
			.popi-docs-section h2 { border-bottom: 1px solid #ddd; padding-bottom: 8px; }
			.popi-docs-table { width: 100%; border-collapse: collapse; }
			.popi-docs-table th { text-align: left; background: #f9f9f9; padding: 8px 12px; width: 220px; border: 1px solid #e5e5e5; font-weight: 600; vertical-align: top; }
			.popi-docs-table td { padding: 8px 12px; border: 1px solid #e5e5e5; vertical-align: top; line-height: 1.6; }
			.popi-docs-table code { background: #f0f0f0; padding: 1px 5px; border-radius: 3px; font-size: 12px; }
		</style>
		<?php
	}

	private static function section( string $title, array $rows ): void {
		echo '<div class="popi-docs-section">';
		echo '<h2>' . esc_html( $title ) . '</h2>';
		echo '<table class="popi-docs-table">';
		foreach ( $rows as $label => $desc ) {
			printf(
				'<tr><th>%s</th><td>%s</td></tr>',
				wp_kses( $label, array( 'code' => array() ) ),
				wp_kses( $desc, array( 'strong' => array(), 'code' => array(), 'em' => array(), 'br' => array() ) )
			);
		}
		echo '</table></div>';
	}
}
