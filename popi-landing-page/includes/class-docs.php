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

			<?php self::section( 'Landing Page — Základ', array(
				'Hlavní nadpis (H1)'   => 'Největší nadpis stránky — první věc kterou návštěvník přečte. <strong>Musí odpovídat textu v reklamě</strong> (tzv. message match). Pokud reklama říká „Motýlí dům pro děti", H1 musí říkat totéž — jinak návštěvník stránku opustí.',
				'Hlavní popis (perex)' => 'Jeden nebo dva věty pod H1. Upřesní nabídku a motivuje ke čtení nebo akci.',
				'CTA — text tlačítka' => 'Popis výzvy k akci. Příklady: „Koupit vstupenku", „Rezervovat termín", „Zjistit více". Krátké, konkrétní, akční.',
				'CTA — URL'           => 'Cílová adresa tlačítka. Funkce <code>popi_cta_url()</code> automaticky přidá UTM parametry — viz sekce UTM níže.',
			) ); ?>

			<?php self::section( 'SEO & Sdílení', array(
				'Meta title'             => 'Název záložky v prohlížeči a nadpis ve výsledcích vyhledávání. Doporučeno 50–60 znaků. Může se lišit od H1 — v Google Ads ovlivňuje <strong>Quality Score</strong>.',
				'Meta description'       => 'Popis zobrazovaný pod nadpisem ve vyhledávači. Doporučeno 140–160 znaků. Nemá přímý vliv na ranking, ale ovlivňuje míru prokliků.',
				'OG obrázek (soc. sítě)' => 'Náhledový obrázek při sdílení landing page na Facebook, LinkedIn apod. Doporučená velikost <strong>1200 × 630 px</strong>. Dedikované pole zaručuje správné načtení — Featured Image sítě někdy ignorují.',
			) ); ?>

			<?php self::section( 'SEA — Kampaň', array(
				'Klíčové slovo kampaně' => 'Interní poznámka — na jaký vyhledávací dotaz tato landing page cílí. Na frontendu se nezobrazí. Slouží pro přehled při správě více LP (např. „kam s dětmi Teplice", „tip na výlet Ústecký kraj").',
			) ); ?>

			<?php self::section( 'UTM parametry', array(
				'UTM source'   => 'Zdroj návštěvnosti pro Google Analytics. Nastaví se per landing page. Příklady: <code>sklik</code>, <code>google-ads</code>, <code>facebook</code>. Výchozí hodnota z nastavení pluginu.',
				'UTM medium'   => 'Typ reklamního kanálu. Příklady: <code>cpc</code>, <code>paid-social</code>, <code>email</code>. Výchozí hodnota z nastavení pluginu.',
				'UTM campaign' => 'Název konkrétní kampaně pro tuto LP. Příklad: <code>deti-teplice-2026</code>.',
				'Jak UTM fungují' => 'Funkce <code>popi_cta_url()</code> přečte CTA URL a UTM pole z aktuální LP a složí finální URL automaticky. Příklad výstupu: <code>https://papilonia.cz/vstupenky?utm_source=sklik&utm_medium=cpc&utm_campaign=deti-teplice</code>. Použití v Bricks Code elementu: <code>&lt;?php echo popi_cta_url( get_the_ID() ); ?&gt;</code>',
				'Více platforem na jedné LP' => 'Pokud chceš stejnou LP pro Sklik i Google Ads, vytvoř kopii pomocí Duplicate Post. Na kopii změníš slug a UTM source — obsah zůstane stejný.',
			) ); ?>

			<?php self::section( 'Výchozí hodnoty', array(
				'K čemu slouží'    => 'Stránka <strong>Výchozí hodnoty</strong> (v tomto menu) umožňuje předvyplnit všechna pole při vytváření nové landing page. Usnadní práci pokud jsou hodnoty u většiny LP stejné — např. stejné CTA tlačítko nebo UTM source.',
				'Přepsání hodnoty' => 'Výchozí hodnota je jen předvyplnění — na konkrétní LP ji lze libovolně přepsat.',
				'OG obrázek'       => 'Výchozí OG obrázek se použije pokud konkrétní LP nemá vlastní OG obrázek.',
			) ); ?>

			<?php self::section( 'Bricks šablona', array(
				'Povolení CPT'      => 'Aby Bricks LP renderoval, musí být CPT povolen: <strong>Bricks → Settings → Post Types → zaškrtni Landing Pages</strong>.',
				'Šablona'           => 'Vytvoř šablonu v <strong>Bricks → Templates → Add New</strong>. Typ: <code>Content</code>. Podmínka: <code>Post Type = Landing Pages</code>.',
				'Dynamická data'    => 'V Bricks editoru vyber textový element → ikona <code>{}</code> → ACF → vyber pole (např. <code>popi_hlavni_nadpis</code>).',
				'CTA URL s UTM'     => 'Na tlačítku CTA místo přímého ACF pole použij Code element: <code>&lt;?php echo popi_cta_url( get_the_ID() ); ?&gt;</code>',
				'Featured Image'    => 'Hero sekce — mapuj na <code>{featured_image}</code>, ne na ACF pole. OG obrázek je dedikované ACF pole výhradně pro soc. sítě.',
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
				wp_kses( $desc, array( 'strong' => array(), 'code' => array(), 'em' => array() ) )
			);
		}
		echo '</table></div>';
	}
}
