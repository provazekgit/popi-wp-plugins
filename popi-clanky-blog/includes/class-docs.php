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

			<?php self::section( 'SEO & Sdílení', array(
				'Meta title'             => 'Název záložky v prohlížeči a výsledku ve vyhledávači. Doporučeno 50–60 znaků. Pokud nevyplníš, použije se název článku.',
				'Meta description'       => 'Popis zobrazovaný pod nadpisem ve vyhledávači. Doporučeno 140–160 znaků.',
				'OG obrázek (soc. sítě)' => 'Náhledový obrázek při sdílení na Facebook, LinkedIn apod. Doporučená velikost 1200 × 630 px. Pokud nevyplníš, sítě použijí libovolný obrázek z článku.',
			) ); ?>

			<?php self::section( 'CTA & Tracking', array(
				'CTA — text tlačítka' => 'Popis tlačítka pro akci čtenáře (např. „Koupit vstupenky online"). Zobrazí se v šabloně článku.',
				'CTA — URL'           => 'Cílová URL tlačítka — kam se čtenář dostane po kliknutí.',
				'UTM source'          => 'Zdroj návštěvnosti pro Google Analytics. Příklady: vyletustecko, newsletter, facebook. Výchozí hodnota z nastavení.',
				'UTM medium'          => 'Typ kanálu. Příklady: clanek, email, social. Výchozí hodnota z nastavení.',
				'UTM campaign'        => 'Název konkrétní kampaně nebo tématu článku. Příklad: letni-vylet-2026.',
			) ); ?>

			<?php self::section( 'Obsah článku (Table of Contents)', array(
				'Jak to funguje'   => 'Plugin automaticky najde všechny nadpisy H2 a H3 v textu článku a přidá jim kotvy (id="..."). Není potřeba nic nastavovat — stačí psát článek s nadpisy.',
				'Použití v Bricks' => 'Do šablony článku přidej element <strong>Code</strong> a vlož: <code>&lt;?php echo popi_clanky_toc( get_the_ID() ); ?&gt;</code>. Vrátí HTML navigaci &lt;nav class="popi-toc"&gt; se seznamem odkazů na jednotlivé sekce.',
				'Prázdný výstup'   => 'Pokud článek neobsahuje žádný H2 ani H3 nadpis, funkce vrátí prázdný řetězec — navigace se nezobrazí vůbec.',
				'Styl navigace'    => 'Třída <code>.popi-toc</code> a <code>.toc-h2</code> / <code>.toc-h3</code> — nastyl v Bricks nebo vlastním CSS.',
			) ); ?>

			<?php self::section( 'Výchozí hodnoty', array(
				'K čemu slouží'    => 'Stránka <strong>Výchozí hodnoty</strong> (v tomto menu) umožňuje předvyplnit pole při vytváření nového článku. Usnadňuje práci pokud jsou hodnoty u většiny článků stejné (např. stejné CTA tlačítko).',
				'Přepsání hodnoty' => 'Výchozí hodnota je jen předvyplnění — na konkrétním článku ji lze libovolně přepsat.',
				'OG obrázek'       => 'Výchozí OG obrázek se použije pokud konkrétní článek nemá vlastní OG obrázek vyplněný.',
			) ); ?>

		</div>
		<style>
			.popi-docs-section { margin: 0 0 32px; }
			.popi-docs-section h2 { border-bottom: 1px solid #ddd; padding-bottom: 8px; }
			.popi-docs-table { width: 100%; border-collapse: collapse; }
			.popi-docs-table th { text-align: left; background: #f9f9f9; padding: 8px 12px; width: 220px; border: 1px solid #e5e5e5; font-weight: 600; }
			.popi-docs-table td { padding: 8px 12px; border: 1px solid #e5e5e5; vertical-align: top; }
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
				wp_kses( $desc, array( 'strong' => array(), 'code' => array(), 'em' => array() ) )
			);
		}
		echo '</table></div>';
	}
}
