# popi-wp-plugins

WordPress pluginy pro weby Popiweb. Distribuovány přes update server na `api.popisite.cz`.

## Pluginy

### popi-connector
Univerzální bezpečné propojení WordPressu s POPIsite, POPIwebem a POPIcastem.
Používá jednorázové párovací kódy, HMAC podpisy, per-installation scopes,
ochranu proti replay útokům, audit, rate limiting a vratné řízení headless
frontendu. Nevyžaduje hlavní WordPress heslo ani předávání `Authorization`
hlavičky.

### popishop-cart-handoff
Bezpečné předání krátkodobě podepsaného košíku z POPIshop storefrontu do
existujícího WooCommerce checkoutu.

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
git push origin main --tags
```

Pro POPIshop použij tag `popishop-v*`. GitHub Actions automaticky vytvoří ZIP
a přiloží ho k Release.

## POPIshop plugin lokálně

```bash
npm run test:popishop
npm run package:popishop
```

## POPI Connector lokálně

```bash
npm run test:connector
npm run package:connector
```

Release tag má tvar `popi-connector-v*`. Registry updateru používá endpoint
`https://api.popisite.cz/api/v1/public/plugins/popi-connector` a před instalací
ověřuje SHA-256 release ZIPu.

Od verze 1.2.0 má Connector záložku **Aplikační hesla**. Zobrazuje pouze
souhrnnou, omezenou kontrolu existence WordPress Application Passwords a ke
konkrétnímu tenant/project/installation bindingu dovolí uložit provozní
poznámku. Hesla, UUID, názvy credentials, uživatelé ani IP adresy se neodesílají;
POPIsite dostane deklaraci pouze v podepsaném health reportu.

Od verze 1.2.1 lze v záložce **Moduly** bez nového párování povolit další
REST typy obsahu, například CPT vytvořený přes ACPT nebo ACF. Volba je lokální
pro konkrétní binding, omezená na veřejně dotazovatelné REST typy a auditovaná.

Verze 1.2.2 zahrnuje také vestavěný typ `page`, který je veřejný a dostupný
přes REST API, i když jej WordPress neoznačuje příznakem `publicly_queryable`.

Verze 1.2.3 normalizuje čas posledního použití Application Password do UTC
tvaru končícího `Z`, který přijímá sdílený Connector kontrakt.

## Po releasu — update server

Uprav `REGISTRY` v `popi_site/apps/api/src/routes/wp-plugins.ts`:
- `version` → nová verze
- `download_url` → URL nového GitHub Release ZIPu
- `last_updated` → dnešní datum
- `changelog` → co se změnilo

Push do popi_site → Vercel auto-deploy → WP weby uvidí dostupnou aktualizaci.
