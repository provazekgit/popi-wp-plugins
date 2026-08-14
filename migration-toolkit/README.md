# POPI WordPress Migration Toolkit

Balíček obsahuje dočasné bezpečnostní nástroje pro obnovu WordPress/WooCommerce
kopie. Není to běžný WordPress plugin instalovaný přes administraci.

## Obsah ZIPu

- `mu-plugins/popi-migration-recovery-guard.php` dočasně odstaví přesně zadané
  neúplné pluginy bez změny databáze.
- `mu-plugins/popishop-staging-guard.php` zablokuje indexaci, ostré e-maily a
  jiné než výslovně povolené offline platební brány.
- `wp-config.migration.example.php` obsahuje pouze bezpečné příklady konstant.
- `.htaccess.staging.example` vynutí HTTP heslo, serverový `noindex` a zákaz
  cachování ještě před spuštěním WordPressu.
- `manifest.json` uvádí zdrojový commit a SHA-256 obou PHP souborů.

## Nasazení na staging

1. Ověřte SHA-256 celého ZIPu podle sousedního `.sha256` souboru.
2. Zkopírujte oba PHP soubory přímo do `wp-content/mu-plugins/`.
3. Do `wp-config.php` přeneste potřebné řádky z příkladu před závěrečný komentář.
4. Recovery seznam ponechte prázdný, dokud log neurčí konkrétní rozbitý plugin.
5. Ověřte upozornění v administraci, `noindex`, zachycení e-mailu a dostupné
   platební metody.

## WEDOS ochrana před prvním přihlášením

1. Existující `.htaccess` nejprve zazálohujte.
2. Z příkladu přeneste ochranný blok nad standardní WordPress rewrite pravidla.
3. `XXXXXX` nahraďte číselným ID hostingu a `SUBDOMAIN` názvem složky ve
   `www/subdom`.
4. Vedle `.htaccess` vytvořte `.htpasswd` s jedním silným, náhodně generovaným
   heslem. Soubor ani heslo nepatří do repozitáře.
5. Bez hesla musí HTML vracet `401`. Woo REST API smí obejít HTTP heslo, ale bez
   vlastních WooCommerce klíčů musí odmítnout přístup.
6. Po přihlášení musí odpověď obsahovat `X-Robots-Tag` a
   `X-POPIshop-Staging-Guard`. Pokud chybí, staging se nepoužívá.

## Odstranění

Recovery guard se odstraní ihned po nahrání a ověření čistých pluginů. Staging
guard se odstraní až při samostatně schváleném produkčním přechodu, po ověření
SMTP, plateb, indexace a cronů. Změna `WP_ENVIRONMENT_TYPE` na `production`
zásahy staging guardu vypne, ale nepovažuje se sama o sobě za dokončený audit.

Do ZIPu ani repozitáře nepatří hesla, API klíče, WordPress salts, databázové
údaje ani zákaznická data.
