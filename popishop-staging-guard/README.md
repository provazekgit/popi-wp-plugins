# POPIshop Staging Guard

MU plugin pro neveřejnou kopii WooCommerce. Aktivuje se pouze tehdy, když
`wp_get_environment_type()` vrací `staging`.

## Instalace

Soubor `popishop-staging-guard.php` nahrajte přímo do
`wp-content/mu-plugins/`. WordPress jej načte automaticky; v administraci jej
nelze omylem vypnout.

Do `wp-config.php` před řádek `That's all, stop editing` vložte:

```php
define('WP_ENVIRONMENT_TYPE', 'staging');
define('DISABLE_WP_CRON', true);
define('DISALLOW_FILE_EDIT', true);
define('FORCE_SSL_ADMIN', true);
define('POPISHOP_STAGING_ALLOWED_GATEWAYS', 'cod,bacs');
```

Plugin vynutí `noindex`, vypne WordPress sitemapu a tracking WooCommerce,
skryje jiné platební brány a zachytí posledních 50 e-mailů bez odeslání.
Adresáti se ukládají pouze jako zkrácený SHA-256 otisk. Obsah je dostupný jen
uživateli s oprávněním `manage_woocommerce` v **WooCommerce → Staging e-maily**.

Před produkčním přepnutím tento soubor z `mu-plugins` odstraňte až po kontrole
produkčních plateb, SMTP a indexace. Samotná změna `WP_ENVIRONMENT_TYPE` na
`production` všechny jeho zásahy také vypne.
