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
			<h1>Kurzy / Akce — Nápověda</h1>

			<?php self::section( 'Požadavky', array(
				'WordPress'  => 'Minimálně verze <strong>6.2</strong>.',
				'PHP'        => 'Minimálně verze <strong>7.4</strong>. Aktuální verze pluginu je odzkoušená i na PHP 8.x.',
				'Advanced Custom Fields (ACF)' => '<strong>Povinné.</strong> Postačí bezplatná verze. Všechna vlastní pole kurzu i termínu (datum, místo, cena, SEO, CTA...) jsou ACF pole — bez aktivního ACF se nedají vyplnit ani zobrazit. Pokud ACF chybí, plugin to v adminu nahlásí (červené upozornění nahoře).',
				'Kompatibilita s SEO pluginy'  => 'Funguje s <strong>Yoast SEO</strong> a <strong>Rank Math</strong> — canonical (termín → kurz) a noindex se napojí přímo na jejich filtry. S jiným SEO pluginem (All in One SEO, SEOPress) se použije vlastní záložní <code>&lt;meta&gt;</code> tag, aby web vždy fungoval.',
				'Proč sluggy popi_course/popi_event' => 'Typ obsahu kurzu/termínu má interní název (slug) <code>popi_course</code>/<code>popi_event</code>, ne jen obecné „course"/„event" — to by se mohlo srazit s jiným pluginem se stejnou funkcí (LMS systémy, The Events Calendar, WooCommerce). Veřejné URL adresy (<code>/kurzy/</code>, <code>/terminy/</code>) tím nejsou ovlivněny.',
			) ); ?>

			<?php self::section( 'Slovníček pojmů', array(
				'Meta title'   => 'Titulek stránky — zobrazuje se v záložce prohlížeče a jako klikatelný nadpis ve výsledcích Google. Liší se od H1 (nadpisu na stránce) — meta title je pro vyhledávač, H1 pro návštěvníka.',
				'Meta description' => 'Krátký popis stránky pod nadpisem ve výsledcích Google. Nemá přímý vliv na pořadí ve vyhledávání, ale ovlivňuje, jestli na výsledek lidé kliknou.',
				'OG (Open Graph)' => 'Standard, který určuje, jak stránka vypadá při sdílení na Facebooku, LinkedInu apod. — náhledový obrázek, titulek, popis. „OG obrázek" = obrázek pro tento náhled.',
				'CTA (Call To Action)' => 'Výzva k akci — text a odkaz tlačítka, které má návštěvníka přimět k dalšímu kroku (přihlásit se, zaplatit...).',
				'UTM' => 'Sada parametrů v URL (<code>utm_source</code>, <code>utm_medium</code>, <code>utm_campaign</code>), díky kterým Google Analytics (GA4) pozná, odkud návštěvník přišel — z jaké reklamy, kanálu nebo kampaně.',
				'GA4 (Google Analytics 4)' => 'Aktuální verze Google Analytics — nástroj pro sledování návštěvnosti a chování uživatelů na webu.',
				'Schema.org / JSON-LD' => 'Strukturovaná data — kód neviditelný pro návštěvníka, který říká Google, co stránka znamená (datum akce, místo, cena...). Díky němu se ve vyhledávání může zobrazit rozšířený výsledek (rich result) s těmito údaji přímo.',
				'Canonical' => 'Značka, která Google řekne, která URL je ta „hlavní" verze obsahu — používá se, když existuje víc podobných/souvisejících stránek (zde: termín → kurz), aby si nekonkurovaly ve vyhledávání.',
				'Noindex' => 'Instrukce vyhledávačům, aby danou stránku nezahrnuly do výsledků vyhledávání.',
				'Rubrika (kategorie)' => 'Standardní WordPress způsob, jak obsah zařadit do tématu/typu — stejná rubrika se může používat napříč kurzy, články i dalším obsahem webu.',
			) ); ?>

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
				'Forma konání'          => 'Prezenčně / Online / Hybridně — používá se jen pro Schema.org (Google rich results), na frontendu se nikde nezobrazuje automaticky.',
			) ); ?>

			<?php self::section( 'Kurz / Akce — SEO & Sdílení (ACF)', array(
				'Meta title'                => 'Název pro vyhledávač, 50–60 znaků.',
				'Meta description'          => 'Popis pro vyhledávač, 140–160 znaků.',
				'OG obrázek'                => 'Náhled při sdílení na Facebook/LinkedIn, doporučeno 1200×630 px. Stejný obrázek se použije i ve Schema.org datech (sekce níže).',
				'Náhledový obrázek — mobil' => 'Volitelná alternativa pro mobilní zařízení. Bez vyplnění se na mobilu zobrazí stejný obrázek jako na PC/tabletu — standardní WP Featured Image (Náhledový obrázek v postranním panelu editoru).',
			) ); ?>

			<?php self::section( 'Kurz / Akce — CTA & UTM (ACF)', array(
				'CTA — text tlačítka' => 'Text tlačítka vedoucího z kurzu ven (např. „Přihlásit se na kurz").',
				'CTA — URL'           => 'Základní URL přihlašovacího/rezervačního formuláře <strong>bez</strong> UTM parametrů — ty se přidají automaticky.',
				'UTM source / medium' => 'Pro GA4 — odkud návštěvník přišel (google, sklik, facebook...) a jaký typ kanálu (cpc, social...).',
				'UTM campaign'        => 'Název konkrétní kampaně pro tento kurz. Stejná hodnota se použije i v generátoru UTM odkazů (viz sekce níže).',
			) ); ?>

			<?php self::section( 'CTA tlačítko s UTM — jak to funguje', array(
				'Princip'  => 'Funkce <code>popi_events_course_cta_url()</code> přečte pole <strong>CTA — URL</strong> kurzu a přidá UTM parametry. Stejný princip jako u <code>popi-landing-page</code> a <code>popi-clanky-blog</code>.',
				'Použití v Bricks' => '<strong>Tlačítko → Odkaz → Dynamická data → skupina Popi → Kurz CTA URL s UTM (popi-events)</strong>. Tag: <code>{popi_course_cta_url_utm}</code>.',
				'Alternativa'      => 'Code element nebo PHP: <code>&lt;?php echo popi_events_course_cta_url( get_the_ID() ); ?&gt;</code>',
			) ); ?>

			<?php self::section( 'UTM odkazy pro kampaně (generátor)', array(
				'K čemu slouží' => 'Meta box na editaci kurzu/akce vygeneruje 7 hotových odkazů <strong>na stránku kurzu</strong> (cílová URL pro reklamu) — Sklik, Google Ads, Meta Ads, Instagram, LinkedIn organicky/ads, Newsletter.',
				'Rozdíl od CTA URL' => 'CTA URL vede z kurzu ven (na přihlášku). UTM odkazy pro kampaně vedou na kurz — vkládají se jako cílová URL reklamy v Google Ads/Sklik/Meta Ads.',
				'UTM campaign' => 'Přebírá se z pole <strong>UTM campaign</strong>, pokud je vyplněné, jinak ze slugu kurzu. Pole se dá upravit přímo v generátoru — uloží se zpátky do ACF pole.',
			) ); ?>

			<?php self::section( 'Schema.org — strukturovaná data (Event)', array(
				'Kde se vypisuje'  => 'Na stránce <strong>kurzu</strong> (ne na termínu) — termíny jsou typicky noindex, takže by na nich Google rich results nezobrazil. Kurz vypíše všechny své nadcházející termíny jako pole Event objektů (<code>@graph</code>).',
				'Použitá pole'     => 'Název a obrázek z kurzu; datum, čas, místo, cena a forma konání (<strong>Forma konání</strong> — pole na termínu) z jednotlivého termínu.',
				'Cena'             => 'Z pole Cena se vytáhne první číslo (např. „2 990 Kč" → 2990). Pokud cena neobsahuje číslo (např. „na dotaz"), Schema cenu vůbec nevypíše — neplatná structured data se nezobrazí raději vůbec, než špatně.',
				'Pořadatel a měna' => 'Nastavují se globálně v podmenu Výchozí hodnoty (sekce Schema.org) — platí pro všechny kurzy, není potřeba vyplňovat u každého zvlášť.',
				'Ověření'          => 'Po nasazení zkontroluj výstup v <a href="https://search.google.com/test/rich-results" target="_blank" rel="noopener">Google Rich Results Test</a>.',
			) ); ?>

			<?php self::section( 'Shortcody', array(
				'[popi_course_events id="123"]'    => 'Výpis všech termínů konkrétního kurzu (podle ID kurzu), seřazené od nejbližšího data.',
				'[popi_upcoming_events days="30"]'  => 'Výpis termínů libovolného kurzu v následujících X dnech (výchozí 30).',
				'[popi_events_calendar month="current"]' => 'Měsíční kalendářní grid. <code>month</code> může být „current" nebo konkrétní <code>RRRR-MM</code>. Kliknutí na den s termínem vede do archivu filtrovaného na ten den.',
				'[popi_courses category="it"]'      => 'Výpis kurzů/akcí podle slugu standardní WP rubriky (category). Bez parametru zobrazí všechny. Typ akce (webinář/kurz/workshop) i téma se zadává stejnou rubrikou jako u ostatních popi pluginů.',
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
				'Odolnost vůči aktualizacím SEO pluginu' => 'Yoast SEO i Rank Math občas změní formát dat předávaných do svých filtrů (stalo se to např. u Yoastu mezi verzemi). Plugin to zvládá obě varianty — a pokud by v budoucnu přišel úplně jiný formát, raději tu jednu funkci (noindex) nevykoná, než aby spadl celý web.',
			) ); ?>

			<?php self::section( 'Výchozí hodnoty (popi-events-settings)', array(
				'K čemu slouží'    => 'Stránka <strong>Výchozí hodnoty</strong> (submenu pod Kurzy / Akce) předvyplní pole nového termínu i kurzu — nemusí se opisovat ručně, pokud má většina stejné parametry.',
				'Termín'           => 'Místo, Cena, Čas od, Čas do, Celkový počet hodin, přepínač „Skrýt z vyhledávačů" (výchozí noindex).',
				'Kurz — SEO & Sdílení' => 'Meta title, Meta description, OG obrázek.',
				'Kurz — CTA & UTM' => 'CTA text, CTA URL, UTM source, UTM medium.',
				'Schema.org'       => 'Pořadatel (název, URL) a měna — platí globálně pro výpočet ceny ve strukturovaných datech.',
				'Přepsání hodnoty' => 'Výchozí hodnota je jen předvyplnění — na konkrétní položce ji lze libovolně přepsat.',
			) ); ?>

			<?php self::section( 'Šablony', array(
				'single-event.php'  => 'Zobrazení jednotlivého termínu. Theme může přepsat vlastním souborem <code>single-event.php</code> ve své složce.',
				'archive-event.php' => 'Archiv na <code>/terminy/</code> s filtrem. Theme může přepsat vlastním <code>archive-event.php</code>.',
				'calendar.php'      => 'Šablona stránky „Kalendář termínů (Popi Events)" — volitelná, vybírá se v Atributy stránky.',
			) ); ?>

			<?php self::section( 'Bricks šablona — přehled ACF polí (kurz)', array(
				'Meta title'       => '<code>{popi_course_meta_title}</code> — pro SEO plugin nebo &lt;head&gt;',
				'Meta description' => '<code>{popi_course_meta_desc}</code>',
				'OG obrázek'       => '<code>{popi_course_og_image}</code>',
				'CTA text'         => '<code>{popi_course_cta_text}</code>',
				'CTA URL s UTM'    => '<strong>Tlačítko → Odkaz → Dynamická data → skupina Popi → Kurz CTA URL s UTM (popi-events)</strong>. Tag: <code>{popi_course_cta_url_utm}</code>. Nikdy nepoužívej přímo <code>{popi_course_cta_url}</code> — to vrátí URL bez UTM.',
				'Povolení CPT'     => 'Aby Bricks Kurzy/Akce a Termíny renderoval, musí být povoleny: <strong>Bricks → Settings → Post Types</strong>.',
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
