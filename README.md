# popi-wp-plugins

WordPress pluginy pro weby Popiweb. Distribuovány přes update server na `api.popisite.cz`.

## Pluginy

### popishop-cart-handoff
Bezpečné předání krátkodobě podepsaného košíku z POPIshop storefrontu do
existujícího WooCommerce checkoutu.

### popishop-staging-guard
MU plugin pro chráněnou WooCommerce staging kopii. Vynutí `noindex`, zachytí
e-maily bez odeslání a povolí pouze určené offline platební brány.

### popi-migration-recovery-guard
Dočasný MU plugin pro bezpečné odstavení přesně určených neúplných pluginů po
migraci. Nemění databázi a po opravě souborů se odstraní.

### popi-clanky-blog
CPT Články se SEO poli, CTA tlačítkem, UTM trackingem a automatickým Table of Contents.

### popi-landing-page
CPT Landing Pages pro Sklik/Google Ads kampaně. SEO, SEA, UTM parametry, Bricks Builder podpora.

---

## Vydání nové verze

```bash
# 1. Uprav kód a bump verze v PHP hlavičce pluginu
# 2. Commit změn
git add . && git commit -m "popi-clanky-blog: bump na 1.1.0"

# 3. Tag ve formátu popi-clanky-v* nebo popi-landing-v*
git tag popi-clanky-v1.1.0
git push origin master --tags
```

Pro POPIshop použij tag `popishop-v*`. GitHub Actions automaticky vytvoří ZIP
a přiloží ho k Release.

## POPIshop plugin lokálně

```bash
npm run test:popishop
npm run test:staging-guard
npm run package:popishop
```

## Po releasu — update server

Uprav `REGISTRY` v `popi_site/apps/api/src/routes/wp-plugins.ts`:
- `version` → nová verze
- `download_url` → URL nového GitHub Release ZIPu
- `last_updated` → dnešní datum
- `changelog` → co se změnilo

Push do popi_site → Vercel auto-deploy → WP weby uvidí dostupnou aktualizaci.
