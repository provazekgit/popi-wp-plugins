<?php
defined( 'ABSPATH' ) || exit;

class Popi_Landing_Docs {

	public static function init(): void {
		add_action( 'admin_menu', array( self::class, 'add_page' ) );
	}

	public static function add_page(): void {
		add_submenu_page(
			'edit.php?post_type=popi_landing',
			'Nápověda',
			'Nápověda',
			'manage_options',
			'popi-landing-docs',
			array( self::class, 'render_page' )
		);
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap" style="max-width:860px;">
			<h1>Landing Pages — Nápověda</h1>

			<?php self::section( 'Slovníček pojmů', array(
				'LP (landing page)' => 'Vstupní stránka, na kterou vede placená reklama (Sklik, Google Ads) — má jeden cíl: přesvědčit návštěvníka k akci (koupit, přihlásit se...).',
				'Meta title'   => 'Titulek stránky — zobrazuje se v záložce prohlížeče a jako klikatelný nadpis ve výsledcích Google. Liší se od H1 (hlavního nadpisu na stránce).',
				'Meta description' => 'Krátký popis stránky pod nadpisem ve výsledcích Google. Ovlivňuje, jestli na výsledek lidé kliknou.',
				'OG (Open Graph)' => 'Standard, který určuje, jak stránka vypadá při sdílení na Facebooku, LinkedInu apod. — náhledový obrázek, titulek, popis. „OG obrázek" = obrázek pro tento náhled.',
				'CTA (Call To Action)' => 'Výzva k akci — text a odkaz tlačítka, které má návštěvníka přimět k dalšímu kroku.',
				'UTM' => 'Sada parametrů v URL (<code>utm_source</code>, <code>utm_medium</code>, <code>utm_campaign</code>), díky kterým Google Analytics (GA4) pozná, odkud návštěvník přišel — z jaké reklamy nebo kampaně.',
				'Quality Score' => 'Hodnocení kvality reklamy/LP v Google Ads — ovlivňuje, kolik platíš za proklik. Lepší message match (shoda reklamy a LP) Quality Score zlepšuje.',
				'Schema.org / JSON-LD' => 'Strukturovaná data — kód neviditelný pro návštěvníka, který říká Google, co stránka znamená (cena, dostupnost produktu...). Díky němu se může ve vyhledávání zobrazit rozšířený výsledek (rich result) přímo s cenou.',
				'Message match' => 'Shoda mezi textem reklamy a textem na LP (zejména H1) — pokud nesedí, návštěvník stránku hned opustí.',
			) ); ?>

			<?php self::section( 'LP — Základ (popi-landing-page)', array(
				'Hlavní nadpis (H1)'   => 'Největší nadpis stránky — první věc kterou návštěvník přečte. <strong>Musí odpovídat textu v reklamě</strong> (tzv. message match). Pokud reklama říká „Motýlí dům pro děti", H1 musí říkat totéž — jinak návštěvník stránku opustí.',
				'Hlavní popis (perex)' => 'Jeden nebo dva věty pod H1. Upřesní nabídku a motivuje ke čtení nebo akci.',
				'CTA — text tlačítka' => 'Popis výzvy k akci. Příklady: „Koupit vstupenku", „Rezervovat termín", „Zjistit více". Krátké, konkrétní, akční.',
				'CTA — URL'           => '<strong>Základní URL tlačítka bez UTM parametrů.</strong> UTM parametry se přidají automaticky funkcí <code>popi_lp_cta_url()</code> — viz sekce UTM níže. Příklad: <code>https://papilonia.cz/cs/online?papilonia=teplice</code>',
			) ); ?>

			<?php self::section( 'LP — SEO & Sdílení (popi-landing-page)', array(
				'Meta title'                => 'Název záložky v prohlížeči a nadpis ve výsledcích vyhledávání. Doporučeno 50–60 znaků. Může se lišit od H1 — v Google Ads ovlivňuje <strong>Quality Score</strong>.',
				'Meta description'          => 'Popis zobrazovaný pod nadpisem ve vyhledávači. Doporučeno 140–160 znaků. Nemá přímý vliv na ranking, ale ovlivňuje míru prokliků.',
				'OG obrázek (soc. sítě)'    => 'Náhledový obrázek při sdílení landing page na Facebook, LinkedIn apod. Doporučená velikost <strong>1200 × 630 px</strong>. Dedikované pole zaručuje správné načtení — Featured Image sítě někdy ignorují.',
				'Náhledový obrázek — mobil' => 'Alternativní obrázek pro mobilní zařízení místo standardního náhledového obrázku. Slug: <code>popi_lp_nahledovy_obrazek_mobil</code>.<br><strong>Použití v Bricks:</strong> přidej druhý Image element vedle featured image, nastav zdroj na toto ACF pole a skryj ho na desktop/tablet (Bricks → Viditelnost → skrýt na desktop a tablet). Pokud nevyplníš, zobrazí se standardní náhledový obrázek.',
			) ); ?>

			<?php self::section( 'LP — SEA & Kampaň (popi-landing-page)', array(
				'Klíčové slovo kampaně' => 'Interní poznámka — na jaký vyhledávací dotaz tato landing page cílí. Na frontendu se nezobrazí. Slouží pro přehled při správě více LP (např. „kam s dětmi Teplice", „tip na výlet Ústecký kraj").',
				'UTM source'   => 'Zdroj návštěvnosti. Příklady: <code>sklik</code>, <code>google-ads</code>, <code>facebook</code>. Výchozí hodnota se nastavuje v <strong>Landing Pages → Výchozí hodnoty</strong>.',
				'UTM medium'   => 'Typ reklamního kanálu. Příklady: <code>cpc</code>, <code>paid-social</code>, <code>email</code>. Výchozí hodnota z nastavení pluginu.',
				'UTM campaign' => 'Název konkrétní kampaně pro tuto LP. Příklad: <code>deti-teplice-2026</code>.',
			) ); ?>

			<?php self::section( 'CTA tlačítko s UTM — jak to funguje', array(
				'Princip'    => 'Funkce <code>popi_lp_cta_url()</code> přečte pole <strong>CTA — URL</strong> a automaticky za ně přidá UTM parametry vyplněné v sekci SEA — Kampaň. Není potřeba UTM parametry psát ručně do URL.',
				'Workflow'   => '<strong>1.</strong> Vyplň pole <em>CTA — URL</em> (základní URL bez UTM). <strong>2.</strong> Vyplň UTM source, medium, campaign. <strong>3.</strong> V Bricks šabloně použij funkci níže — ta vše složí dohromady.',
				'Příklad výstupu' => 'Pole CTA URL: <code>https://papilonia.cz/cs/online?papilonia=teplice</code><br>UTM source: <code>sklik</code> / medium: <code>cpc</code> / campaign: <code>deti-teplice</code><br>→ Výsledek: <code>https://papilonia.cz/cs/online?papilonia=teplice&amp;utm_source=sklik&amp;utm_medium=cpc&amp;utm_campaign=deti-teplice</code>',
				'Použití v Bricks — doporučeno' => '<strong>Tlačítko → Odkaz → Typ odkazu: Dynamická data → ikona {} → skupina Popi → LP CTA URL s UTM (popi-landing-page).</strong> Tag se jmenuje <code>{popi_lp_cta_url_utm}</code>.',
				'Použití v Bricks — alternativa' => 'Code element nebo PHP v URL poli: <code>&lt;?php echo popi_lp_cta_url( get_the_ID() ); ?&gt;</code>',
				'Bez UTM'    => 'Pokud jsou UTM pole prázdná, funkce vrátí čistou CTA URL bez parametrů.',
				'Více platforem na jedné LP' => 'Pokud chceš stejnou LP pro Sklik i Google Ads, vytvoř kopii pomocí Duplicate Post. Na kopii změníš slug a UTM source — obsah zůstane stejný.',
			) ); ?>

			<?php self::section( 'UTM odkazy pro kampaně (popi-landing-page)', array(
					'K čemu slouží' => 'Box „UTM odkazy pro kampaně" na editaci landing page automaticky vygeneruje odkazy <strong>na samotnou LP</strong> pro 7 kanálů — Google Ads, Sklik, Meta Ads, Instagram (organicky), LinkedIn (organicky), LinkedIn Ads, Newsletter. Tyto odkazy se vkládají jako cílová URL do reklamy / příspěvku, nikoliv do CTA tlačítka.',
					'Rozdíl od CTA URL' => 'CTA URL (sekce Základ) je odkaz <strong>z</strong> LP ven (např. na rezervační systém) a má jednu sadu UTM parametrů z nastavení SEA — Kampaň. UTM odkazy pro kampaně vedou <strong>na</strong> LP a každý kanál má vlastní pevně danou kombinaci utm_source/utm_medium.',
					'UTM campaign' => 'Přebírá se z pole <strong>UTM campaign</strong> (sekce SEA — Kampaň), pokud je vyplněné. Jinak se použije slug landing page.',
					'Podmínka' => 'Landing page musí být alespoň jednou uložena (mít vyplněný slug) — jinak box zobrazí výzvu k uložení.',
					'Použití' => 'Klikni na <strong>Kopírovat</strong> u příslušného kanálu a vlož odkaz do Google Ads / Sklik / Meta Ads / e-mailu apod.',
				) ); ?>

				<?php self::section( 'Ceník (popi-landing-page)', array(
					'Pole'           => '5 pevných kategorií: <strong>Cena — jednotlivec, dítě, rodina, senior, skupina</strong>. Volný text (umožňuje „od 250 Kč"). Nepoužitou kategorii nech prázdnou — do tabulky ani do Schema.org se pak nezahrne.',
					'Výchozí hodnoty' => 'Ceny lze předvyplnit globálně v podmenu <strong>Výchozí hodnoty</strong> (sekce Ceník) — předvyplní se na každé nové LP, na konkrétní položce lze přepsat nebo vymazat.',
					'Zobrazení'      => 'Shortcode <code>[popi_lp_pricing]</code> vypíše jednoduchou HTML tabulku všech vyplněných cen (bez stylu — vzhled dodej v Bricks/CSS přes třídu <code>.popi-pricing-table</code>).',
					'Schema.org'     => 'Stejné ceny se použijí i ve strukturovaných datech (sekce níže) — nemusí se zadávat dvakrát.',
				) ); ?>

				<?php self::section( 'Schema.org — strukturovaná data (Product)', array(
					'Kdy se vypisuje' => 'Jen pokud je na LP vyplněná alespoň jedna cena z ceníku. Bez ceny by Product schema bylo neúplné, takže se v tom případě nevypíše vůbec — lepší nic, než neplatná structured data.',
					'Formát'          => 'Typ <code>Product</code> s polem <code>offers</code> obsahujícím jednu nabídku za každou vyplněnou kategorii ceníku (jednotlivec/dítě/rodina/senior/skupina) — Google rich results umí zobrazit víc cenových variant najednou.',
					'Cena'            => 'Z textu ceny se vytáhne první číslo (např. „250 Kč" → 250). Měna se nastavuje globálně v podmenu Výchozí hodnoty.',
					'Ověření'         => 'Po nasazení zkontroluj výstup v <a href="https://search.google.com/test/rich-results" target="_blank" rel="noopener">Google Rich Results Test</a>.',
				) ); ?>

				<?php self::section( 'Výchozí hodnoty', array(
				'K čemu slouží'    => 'Stránka <strong>Výchozí hodnoty</strong> (v tomto menu) umožňuje předvyplnit všechna pole při vytváření nové landing page. Usnadní práci pokud jsou hodnoty u většiny LP stejné — např. stejné CTA tlačítko nebo UTM source.',
				'Přepsání hodnoty' => 'Výchozí hodnota je jen předvyplnění — na konkrétní LP ji lze libovolně přepsat.',
				'OG obrázek'       => 'Výchozí OG obrázek se použije pokud konkrétní LP nemá vlastní OG obrázek.',
				'Měna'             => 'Použije se ve Schema.org datech ceníku (výchozí CZK).',
			) ); ?>

			<?php self::section( 'Bricks šablona — přehled ACF polí', array(
				'Hlavní nadpis'    => '<code>{popi_lp_hlavni_nadpis}</code> — dynamický tag v Bricks (nadpisový element)',
				'Perex'            => '<code>{popi_lp_hlavni_popis}</code> — dynamický tag v Bricks (textový element)',
				'CTA text'         => '<code>{popi_lp_cta_text}</code> — dynamický tag pro text tlačítka',
				'CTA URL s UTM'    => '<strong>Tlačítko → Odkaz → Dynamická data → skupina Popi → LP CTA URL s UTM (popi-landing-page)</strong>. Tag: <code>{popi_lp_cta_url_utm}</code>. Nikdy nepoužívej přímo <code>{popi_lp_cta_url}</code> — to vrátí URL bez UTM.',
				'Meta title'       => '<code>{popi_lp_meta_title}</code> — pro SEO plugin nebo &lt;head&gt;',
				'Meta description' => '<code>{popi_lp_meta_desc}</code> — pro SEO plugin nebo &lt;head&gt;',
				'OG obrázek'       => '<code>{popi_lp_og_image}</code>',
				'Ceník'            => 'Shortcode <code>[popi_lp_pricing]</code> v Code elementu — vypíše tabulku vyplněných cen.',
				'Povolení CPT'     => 'Aby Bricks LP renderoval, musí být CPT povolen: <strong>Bricks → Settings → Post Types → zaškrtni Landing Pages</strong>.',
				'Šablona'          => 'Vytvoř šablonu v <strong>Bricks → Templates → Add New</strong>. Typ: <code>Content</code>. Podmínka: <code>Post Type = Landing Pages</code>.',
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
				esc_html( $label ),
				wp_kses( $desc, array( 'strong' => array(), 'code' => array(), 'em' => array(), 'br' => array() ) )
			);
		}
		echo '</table></div>';
	}
}
