<?php
defined( 'ABSPATH' ) || exit;

class Popi_Clanky_Docs {

	public static function init(): void {
		add_action( 'admin_menu', array( self::class, 'add_page' ) );
	}

	public static function add_page(): void {
		add_submenu_page(
			'edit.php?post_type=popi_clanky',
			'Nápověda',
			'Nápověda',
			'manage_options',
			'popi-clanky-docs',
			array( self::class, 'render_page' )
		);
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap" style="max-width:860px;">
			<h1>Články blog — Nápověda</h1>

			<?php self::section( 'Článek — SEO & Sdílení (popi-clanky-blog)', array(
				'Meta title'             => 'Název záložky v prohlížeči a výsledku ve vyhledávači. Doporučeno 50–60 znaků. Pokud nevyplníš, použije se název článku.',
				'Meta description'       => 'Popis zobrazovaný pod nadpisem ve vyhledávači. Doporučeno 140–160 znaků.',
				'OG obrázek (soc. sítě)' => 'Náhledový obrázek při sdílení na Facebook, LinkedIn apod. Doporučená velikost 1200 × 630 px. Pokud nevyplníš, sítě použijí libovolný obrázek z článku.',
			) ); ?>

			<?php self::section( 'Článek — CTA & Tracking (popi-clanky-blog)', array(
				'CTA — text tlačítka' => 'Popis tlačítka pro akci čtenáře (např. „Koupit vstupenky online"). Zobrazí se v šabloně článku.',
				'CTA — URL'           => '<strong>Základní URL tlačítka bez UTM parametrů.</strong> UTM parametry se přidají automaticky funkcí <code>popi_clanky_cta_url()</code> — viz sekce níže. Příklad: <code>https://papilonia.cz/cs/online</code>',
				'UTM source'          => 'Zdroj návštěvnosti. Příklady: <code>vyletustecko</code>, <code>newsletter</code>, <code>facebook</code>. Výchozí hodnota se nastavuje v <strong>Články blog → Výchozí hodnoty</strong>.',
				'UTM medium'          => 'Typ kanálu. Příklady: <code>clanek</code>, <code>email</code>, <code>social</code>. Výchozí hodnota z nastavení.',
				'UTM campaign'        => 'Název konkrétní kampaně nebo tématu článku. Příklad: <code>letni-vylet-2026</code>.',
			) ); ?>

			<?php self::section( 'CTA tlačítko s UTM — jak to funguje', array(
				'Princip'    => 'Funkce <code>popi_clanky_cta_url()</code> přečte pole <strong>CTA — URL</strong> a automaticky za ně přidá UTM parametry. Není potřeba UTM parametry psát ručně do URL.',
				'Workflow'   => '<strong>1.</strong> Vyplň pole <em>CTA — URL</em> (základní URL bez UTM). <strong>2.</strong> Vyplň UTM source, medium, campaign. <strong>3.</strong> V Bricks šabloně použij funkci níže — ta vše složí dohromady.',
				'Příklad výstupu' => 'Pole CTA URL: <code>https://papilonia.cz/cs/online</code><br>UTM source: <code>vyletustecko</code> / medium: <code>clanek</code> / campaign: <code>letni-vylet</code><br>→ Výsledek: <code>https://papilonia.cz/cs/online?utm_source=vyletustecko&amp;utm_medium=clanek&amp;utm_campaign=letni-vylet</code>',
				'Použití v Bricks — doporučeno' => '<strong>Tlačítko → Odkaz → Typ odkazu: Dynamická data → ikona {} → skupina Popi → Článek CTA URL s UTM (popi-clanky-blog).</strong> Tag se jmenuje <code>{popi_clanek_cta_url_utm}</code>.',
				'Použití v Bricks — alternativa' => 'Code element nebo PHP v URL poli: <code>&lt;?php echo popi_clanky_cta_url( get_the_ID() ); ?&gt;</code>',
				'Bez UTM'    => 'Pokud jsou UTM pole prázdná, funkce vrátí čistou CTA URL bez parametrů.',
			) ); ?>

			<?php self::section( 'Obsah článku — Table of Contents', array(
				'Jak to funguje'   => 'Plugin automaticky najde všechny nadpisy H2 a H3 v textu článku a přidá jim kotvy (id="..."). Není potřeba nic nastavovat — stačí psát článek s nadpisy.',
				'Použití v Bricks' => 'Do šablony článku přidej element <strong>Code</strong> a vlož: <code>&lt;?php echo popi_clanky_toc( get_the_ID() ); ?&gt;</code>. Vrátí HTML navigaci <code>&lt;nav class="popi-toc"&gt;</code> se seznamem odkazů na jednotlivé sekce.',
				'Prázdný výstup'   => 'Pokud článek neobsahuje žádný H2 ani H3 nadpis, funkce vrátí prázdný řetězec — navigace se nezobrazí vůbec.',
				'Styl navigace'    => 'Třída <code>.popi-toc</code> a <code>.toc-h2</code> / <code>.toc-h3</code> — nastyl v Bricks nebo vlastním CSS.',
			) ); ?>

			<?php self::section( 'Výchozí hodnoty', array(
				'K čemu slouží'    => 'Stránka <strong>Výchozí hodnoty</strong> (v tomto menu) umožňuje předvyplnit pole při vytváření nového článku. Usnadňuje práci pokud jsou hodnoty u většiny článků stejné (např. stejné CTA tlačítko).',
				'Přepsání hodnoty' => 'Výchozí hodnota je jen předvyplnění — na konkrétním článku ji lze libovolně přepsat.',
				'OG obrázek'       => 'Výchozí OG obrázek se použije pokud konkrétní článek nemá vlastní OG obrázek vyplněný.',
			) ); ?>

			<?php self::section( 'Bricks šablona — přehled ACF polí', array(
				'Meta title'       => '<code>{popi_clanek_meta_title}</code> — pro SEO plugin nebo &lt;head&gt;',
				'Meta description' => '<code>{popi_clanek_meta_desc}</code> — pro SEO plugin nebo &lt;head&gt;',
				'OG obrázek'       => '<code>{popi_clanek_og_image}</code>',
				'CTA text'         => '<code>{popi_clanek_cta_text}</code> — dynamický tag pro text tlačítka',
				'CTA URL s UTM'    => '<strong>Tlačítko → Odkaz → Dynamická data → skupina Popi → Článek CTA URL s UTM (popi-clanky-blog)</strong>. Tag: <code>{popi_clanek_cta_url_utm}</code>. Nikdy nepoužívej přímo <code>{popi_clanek_cta_url}</code> — to vrátí URL bez UTM.',
				'Table of Contents' => '<code>&lt;?php echo popi_clanky_toc( get_the_ID() ); ?&gt;</code> — automatická navigace z nadpisů H2/H3',
				'Šablona'          => 'Vytvoř šablonu v <strong>Bricks → Templates → Add New</strong>. Typ: <code>Content</code>. Podmínka: <code>Post Type = Články blog</code>.',
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
