# POPI Connector

Univerzální WordPress konektor pro POPIsite, POPIweb a POPIcast. Je navržený
pro sdílené PHP hostingy, které nemusí předávat HTTP `Authorization` hlavičku.
Plugin nepoužívá hlavní WordPress heslo a ve výchozím režimu nepotřebuje ani
WordPress Application Password.

## Požadavky

- WordPress 6.2+
- PHP 7.4+
- HTTPS
- OpenSSL s AES-256-GCM, `hash_hmac`, `hash_hkdf` a `random_bytes`
- single-site WordPress; Multisite není ve verzi 1.0 podporovaný

## Instalace a párování

1. Nainstalujte release ZIP a aktivujte plugin.
2. V POPIsite vyberte workspace, projekt a jednu `ModuleInstallation`.
3. Vygenerujte desetiminutový jednorázový kód.
4. Ve WordPressu otevřete **POPI Connector → Připojení** a kód vložte.
5. V POPIsite potvrďte skutečně nahlášenou doménu a požadované scopes.
6. Ve WordPressu obnovte stav párování. Plugin vygeneruje lokální 256bit secret,
   odešle jej do POPIsite přes TLS a uloží jej šifrovaně.
7. Zkontrolujte záložku Diagnostika. Frontend přepněte až samostatně.

Jeden párovací kód vytváří jeden binding na konkrétní tuple
`workspace + project + ModuleInstallation + Connection`. Stejný WordPress může
mít samostatný POPIweb i POPIcast binding, každý s vlastním klíčem a scopes.

## Bezpečnostní model

- HMAC-SHA-256 request i response envelopes bez závislosti na hlavičkách.
- Oddělené HKDF klíče pro směry POPIsite → WordPress a WordPress → POPIsite.
- Timestamp ±5 minut, jednorázový nonce a databázová replay ochrana.
- Per-binding rate limiting a audit bez secretů nebo obsahových payloadů.
- Žádná obecná proxy, libovolná URL, libovolná REST cesta ani `DELETE`.
- POPIweb zapisuje pouze povolená pole/meta keys a používá optimistic locking.
- Key rotation má pending fázi a pětiminutové překryvné okno.
- Uninstall data automaticky nemaže. Úplné smazání vyžaduje explicitní
  `define( 'POPI_CONNECTOR_REMOVE_DATA', true );`.

Kompromitace WordPressu včetně `wp-config.php` znamená kompromitaci lokálního
bindingu. V takovém případě binding okamžitě odvolejte v POPIsite a po opravě
web znovu spárujte.

## REST contracts v1

Všechny routes používají `POST` a podepsanou JSON obálku pod namespace
`/wp-json/popi-connector/v1`.

- Core: health, manifest, site, frontend get/set, rotation, revoke.
- POPIweb: schema read, entries search/get/patch.
- POPIcast: show get, episodes search/get.

Povolené post types, meta keys, statusy, write fields a ověřené frontend hosty
přicházejí v binding configu z POPIsite. Neznámý scope nebo security status je
vždy odmítnut.

## Frontend

Aktivace nic nemění. Administrátor může zvolit WordPress frontend, pouze
`noindex`, informační stránku s WordPress editorem, HTTPS redirect nebo 404
headless režim. Před první změnou se uloží rollback snapshot včetně původního
`blog_public`. Admin, REST, cron, login a `/.well-known/` zůstávají dostupné.

`noindex` je doporučení pro vyhledávače, nikoli access control.

## Události

Změny povolených post types se zapisují do durable outboxu. Doručení používá
idempotentní `event_id`, podepsaný request, exponenciální retry a dead stav po
deseti neúspěšných pokusech. WP-Cron je retry mechanismus; POPIsite musí nadále
umět reconciliation.

## Vývoj, test a release

```bash
npm run test:connector
git add popi-connector tests/php/connector-test.php
git commit -m "Add POPI Connector"
npm run package:connector
```

Release tag `popi-connector-v1.0.0` spustí GitHub Action, která zopakuje test,
vytvoří `popi-connector.zip` a jeho SHA-256. Updater přijímá jen balíčky z
odpovídajícího GitHub Releases prefixu a checksum ověřuje před instalací.

## Rollback a kompatibilita

Connector je nová HMAC cesta. Existující `WP_APPLICATION_PASSWORD` cesta v
POPIsite/POPIwebu zůstává během rollout okna zachovaná. U zápisů nesmí být
tichý fallback; návrat na legacy cestu je explicitní serverový feature flag.

Plugin data model je aditivní. Downgrade pluginu tabulky nemaže. Před odstraněním
legacy credentials je nutné změřit nulu jejich použití a dodržet alespoň
30denní soak period.
