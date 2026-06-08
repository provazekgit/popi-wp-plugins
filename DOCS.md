# Dokumentace — popi-wp-plugins

WordPress pluginy pro weby Popiweb. Distribuovány přes update server `api.popisite.cz`.

---

## Obsah

- [Pro administrátora](#pro-administrátora)
  - [Instalace a aktivace](#instalace-a-aktivace)
  - [popi-clanky-blog — správa článků](#popi-clanky-blog--správa-článků)
  - [popi-landing-page — správa landing pages](#popi-landing-page--správa-landing-pages)
  - [Aktualizace pluginů](#aktualizace-pluginů)
- [Pro vývojáře](#pro-vývojáře)
  - [Struktura pluginů](#struktura-pluginů)
  - [Přidání nového ACF pole](#přidání-nového-acf-pole)
  - [Přidání nové sekce nastavení](#přidání-nové-sekce-nastavení)
  - [Vydání nové verze](#vydání-nové-verze)
  - [Přidání nového pluginu do distribuce](#přidání-nového-pluginu-do-distribuce)
  - [Update server](#update-server)

---

## Pro administrátora

### Instalace a aktivace

**Požadavky:**
- WordPress 6.2+
- PHP 7.4+
- ACF (Advanced Custom Fields) — free verze stačí

**Postup:**
1. Nahraj složku pluginu do `/wp-content/plugins/` přes FTP
2. WP Admin → Pluginy → aktivuj plugin
3. Nastavení → Trvalé odkazy → Uložit (flush permalinks)

> Po aktivaci se automaticky vytvoří výchozí termy taxonomie a nastaví rewrite pravidla.

---

### popi-clanky-blog — správa článků

**Menu v administraci:**
```
Články blog
├── Všechny články
├── Přidat nový
├── Cílové skupiny     — kategorizace článků (rodiny, turisté...)
├── Výchozí hodnoty    — přednastavení polí pro nové články
└── Nápověda
```

**Pole v editoru článku (sidebar):**

| Sekce | Pole | Popis |
|---|---|---|
| SEO & Sdílení | Meta title | Název pro vyhledávač, 50–60 znaků |
| SEO & Sdílení | Meta description | Popis pro vyhledávač, 140–160 znaků |
| SEO & Sdílení | OG obrázek | Náhled pro sdílení na soc. sítě, 1200×630 px |
| CTA & Tracking | CTA text | Text tlačítka s výzvou k akci |
| CTA & Tracking | CTA URL | Cílová adresa tlačítka |
| CTA & Tracking | UTM source | Zdroj pro Analytics (vyletustecko, newsletter...) |
| CTA & Tracking | UTM medium | Typ kanálu (clanek, email, social...) |
| CTA & Tracking | UTM campaign | Název kampaně nebo tématu článku |

**Table of Contents (obsah článku):**
Navigace na sekce se generuje automaticky z H2 a H3 nadpisů. Není potřeba nic vyplňovat — stačí psát článek s nadpisy. Zobrazení závisí na Bricks šabloně (viz vývojářská sekce).

**Výchozí hodnoty:**
Stránka *Výchozí hodnoty* předvyplní pole při vytvoření nového článku. Hodnoty lze na konkrétním článku přepsat.

---

### popi-landing-page — správa landing pages

**Menu v administraci:**
```
Landing Pages
├── Všechny landing pages
├── Přidat novou
├── Cílové skupiny     — Rodiny s dětmi, Turisté, Ústecký kraj, Deštivé dny
├── Výchozí hodnoty    — přednastavení polí pro nové LP
└── Nápověda
```

**Pole v editoru landing page:**

| Sekce | Pole | Popis |
|---|---|---|
| Základ | Hlavní nadpis (H1) | Musí odpovídat textu reklamy (message match) |
| Základ | Hlavní popis (perex) | 1–2 věty pod H1 |
| Základ | CTA text | Text tlačítka, např. „Koupit vstupenku" |
| Základ | CTA URL | Adresa cílové stránky (eshop, rezervace...) |
| SEO & Sdílení | Meta title | 50–60 znaků, ovlivňuje Quality Score v Google Ads |
| SEO & Sdílení | Meta description | 140–160 znaků |
| SEO & Sdílení | OG obrázek | Náhled pro soc. sítě, 1200×630 px |
| SEA — Kampaň | Klíčové slovo kampaně | Interní poznámka — na co LP cílí |
| SEA — Kampaň | UTM source | sklik, google-ads, facebook... |
| SEA — Kampaň | UTM medium | cpc, paid-social... |
| SEA — Kampaň | UTM campaign | Název kampaně, např. deti-teplice-2026 |

**URL formát:** `/lp/nazev-stranky/`

**Duplikace LP pro více platforem:**
Použij Duplicate Post plugin — zkopíruje post včetně všech polí. Na kopii změníš slug a UTM source. Obsah (nadpis, popis, CTA) zůstane stejný.

**Bricks šablona — první nastavení:**
1. WP Admin → Bricks → Settings → Post Types → zaškrtni `Landing Pages` → Uložit
2. Bricks → Templates → Add New → typ `Content`, podmínka `Post Type = Landing Pages`
3. CTA tlačítko — URL mapuj přes Code element: `<?php echo popi_cta_url( get_the_ID() ); ?>`
4. Hero obrázek — mapuj na `{featured_image}` (ne na ACF pole)

---

### Aktualizace pluginů

Pluginy se aktualizují standardně přes WP Admin → Pluginy — stejně jako pluginy z WordPress.org.

Když vývojář vydá novou verzi, zobrazí se v administraci oznámení „Dostupná aktualizace". Kliknutím na Aktualizovat se stáhne nový ZIP z GitHub Releases a nainstaluje.

---

## Pro vývojáře

### Struktura pluginů

Oba pluginy mají identickou strukturu:

```
plugin-name/
├── plugin-name.php          ← hlavní soubor: konstanty, require_once, hooky
└── includes/
    ├── class-cpt.php        ← registrace CPT a taxonomie
    ├── class-settings.php   ← stránka Výchozí hodnoty v WP admin
    ├── class-acf-fields.php ← ACF local field groups (pole viditelná v editoru)
    ├── class-updater.php    ← auto-update z api.popisite.cz
    ├── class-docs.php       ← stránka Nápověda v WP admin
    └── functions.php        ← helper funkce (popi_cta_url, popi_clanky_toc...)
```

**Konstanty definované v hlavním souboru:**

```php
POPI_LANDING_VERSION     // aktuální verze, musí odpovídat tagu v git
POPI_LANDING_DIR         // absolutní cesta ke složce pluginu
POPI_LANDING_UPDATE_URL  // URL update serveru
```

**Výchozí parametry polí (`class-acf-fields.php`):**

Každý typ pole má svůj default profil (`d_text()`, `d_textarea()`, `d_url()`, `d_image()`). Konkrétní pole přepíše jen co se liší:

```php
array_merge( self::d_text(), array(
    'key'   => 'field_popi_nova_vec',
    'label' => 'Nová věc',
    'name'  => 'popi_nova_vec',
    // required, placeholder, instructions → z d_text()
) ),
```

Změna výchozího parametru (např. `rows` u všech textarea) = jedna změna v `d_textarea()`.

---

### Přidání nového ACF pole

1. Otevři `includes/class-acf-fields.php`
2. Najdi příslušnou skupinu (`group_zaklad`, `group_seo`, `group_sea`...)
3. Přidej pole pomocí `array_merge` se správným default profilem
4. Pokud má mít pole výchozí hodnotu z nastavení, přidej:
   - pole do `class-settings.php` (sekce + `add_field`)
   - `sanitize_callback` v metodě `sanitize()`
   - `'default_value' => PodlePlugin_Settings::get('klic_pole')` do ACF definice

---

### Přidání nové sekce nastavení

V `includes/class-settings.php`:

```php
// 1. Přidej sekci
add_settings_section( 'popi_section_nova', 'Název sekce', null, 'popi-landing-settings' );

// 2. Přidej pole
self::add_field( 'klic_pole', 'Popis pole', 'text', 'popi_section_nova', 'placeholder' );

// 3. Přidej sanitizaci
'klic_pole' => sanitize_text_field( $input['klic_pole'] ?? '' ),
```

Typy polí: `text`, `textarea`, `url`, `image` (image zobrazí media uploader).

---

### Vydání nové verze

```bash
# 1. Uprav kód v lokálním popi-wp-plugins repo
# 2. Bump version v PHP hlavičce (Version: X.Y.Z)
# 3. Commit
git add . && git commit -m "popi-clanky-blog: popis změn"

# 4. Tag — formát je důležitý pro GitHub Actions
git tag popi-clanky-v1.2.0      # pro popi-clanky-blog
git tag popi-landing-v1.1.0     # pro popi-landing-page

# 5. Push
git push origin master --tags
```

GitHub Actions automaticky:
- Vytvoří ZIP ze složky pluginu
- Vytvoří GitHub Release a přiloží ZIP

Download URL bude:
```
https://github.com/provazekgit/popi-wp-plugins/releases/download/popi-clanky-v1.2.0/popi-clanky-blog.zip
```

---

### Přidání nového pluginu do distribuce

1. Vytvoř složku pluginu v tomto repo (stejná struktura jako existující pluginy)
2. Přidej tag prefix do `.github/workflows/release.yml`:

```yaml
on:
  push:
    tags:
      - 'popi-clanky-v*'
      - 'popi-landing-v*'
      - 'popi-novy-v*'        # ← přidej
```

3. Rozšiř detekci pluginu ve workflow:

```bash
if [[ "$TAG" == popi-clanky-* ]]; then
  echo "dir=popi-clanky-blog" >> $GITHUB_OUTPUT
  echo "name=popi-clanky-blog" >> $GITHUB_OUTPUT
elif [[ "$TAG" == popi-landing-* ]]; then
  echo "dir=popi-landing-page" >> $GITHUB_OUTPUT
  echo "name=popi-landing-page" >> $GITHUB_OUTPUT
else
  echo "dir=popi-novy-plugin" >> $GITHUB_OUTPUT      # ← přidej
  echo "name=popi-novy-plugin" >> $GITHUB_OUTPUT
fi
```

4. Přidej záznam do REGISTRY v update serveru (viz níže)

---

### Update server

**Soubor:** `popi_site/apps/api/src/routes/wp-plugins.ts`
**Endpoint:** `GET https://api.popisite.cz/api/v1/public/plugins/:slug`

Po vydání nové verze aktualizuj `REGISTRY`:

```typescript
"popi-clanky-blog": {
  name: "Popi CPT Články",
  version: "1.2.0",                          // ← nová verze
  download_url: "https://github.com/provazekgit/popi-wp-plugins/releases/download/popi-clanky-v1.2.0/popi-clanky-blog.zip",  // ← nová URL
  requires: "6.2",
  requires_php: "7.4",
  tested: "6.7",
  last_updated: "2026-06-08",                // ← dnešní datum
  sections: {
    description: "...",
    changelog: "= 1.2.0 =\n* Co se změnilo\n\n= 1.1.0 =\n* ...",  // ← přidej nahoře
  },
},
```

Po push do popi_site → Vercel auto-deploy (cca 1 minuta) → WP weby uvidí dostupnou aktualizaci.
