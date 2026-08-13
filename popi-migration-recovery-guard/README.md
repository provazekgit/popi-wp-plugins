# POPI Migration Recovery Guard

Dočasný MU plugin pro obnovu WordPressu po neúplném přenosu souborů. Umožní
odstavit přesně určené pluginy ještě před jejich načtením, aniž by změnil
serializovanou hodnotu `active_plugins` v databázi.

## Kdy jej použít

- databáze označuje plugin jako aktivní,
- hlavní soubor pluginu existuje, ale chybí některá jeho závislost,
- WordPress kvůli tomu končí fatální PHP chybou,
- není bezpečné upravovat serializované hodnoty ručně v phpMyAdminu.

Guard není náhradou opravy pluginu. Slouží pouze k bezpečnému spuštění webu a
administrace během migrace.

## Instalace

1. Pokud neexistuje, vytvořte `wp-content/mu-plugins`.
2. Nahrajte do ní přímo soubor `popi-migration-recovery-guard.php`.
3. Do `wp-config.php` před řádek `That's all, stop editing` vložte například:

```php
define(
    'POPI_MIGRATION_DISABLED_PLUGINS',
    'toret-vyfakturuj/toret-vyfakturuj.php'
);
```

Více pluginů se odděluje čárkou:

```php
define(
    'POPI_MIGRATION_DISABLED_PLUGINS',
    'toret-vyfakturuj/toret-vyfakturuj.php,wp-rocket/wp-rocket.php'
);
```

Hodnotou je cesta hlavního souboru pluginu relativně vůči `wp-content/plugins`.
Guard podporuje běžný WordPress i síťově aktivované pluginy v multisite.

## Bezpečné ukončení opravy

1. Nahrajte úplný a důvěryhodný balíček odstaveného pluginu.
2. Ověřte verzi pluginu, licenci a jeho konfiguraci na stagingu.
3. Odstraňte plugin z konstanty `POPI_MIGRATION_DISABLED_PLUGINS`.
4. Ověřte web, administraci a nový `debug.log`.
5. Až bude seznam prázdný, odstraňte konstantu i MU plugin.

Dokud je guard aktivní, WordPress zobrazí administrátorovi výrazné upozornění.
Žádný plugin nemaže a hodnotu `active_plugins` v databázi neupravuje.
