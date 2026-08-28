# POPI Connector — rollout a pilot emanual.cz

## Bezpečné pořadí

1. Nasadit aditivní POPIsite migraci a API/UI bez vytvoření produkčního pairingu.
2. Vydat podepsaný release `popi-connector-v1.0.0`, zapsat jeho SHA-256 do
   updater registry a ověřit instalaci ZIPu na staging WordPressu.
3. Na `emanual.cz` zaznamenat výchozí `blog_public`, permalink strukturu,
   aktivní Application Password integrace a stav frontendu. Udělat databázovou
   a souborovou zálohu.
4. V POPIsite vybrat správný workspace, projekt a konkrétní POPIweb nebo
   POPIcast `ModuleInstallation`, vytvořit jednorázový kód a ve WordPressu ho
   uplatnit.
5. Před potvrzením porovnat hlášené canonical/admin/callback URL. Potvrdit jen
   očekávaný HTTPS web. Spustit health, manifest a read-only reconciliation.
6. Po 24 hodinách bez chyb povolit pouze potřebné write scope. Frontend měnit
   samostatným krokem; pro pilot nejprve `noindex`, pak informační stránku nebo
   redirect na ověřenou POPIweb doménu.
7. Legacy `WP_APPLICATION_PASSWORD` ponechat jako explicitní rollback cestu.
   Žádný automatický fallback zápisů. Po dobu minimálně 30 dní měřit použití,
   replay/rate-limit chyby, dead outbox a rozdíly reconciliation.

## Stop podmínky

- Hlášená doména neodpovídá očekávanému webu.
- Clock skew přes pět minut, opakované signature/replay chyby nebo chybějící
  auditní záznam.
- Reconciliation vrací odlišné počty/ID obsahu.
- Frontend změna zasáhne administraci, REST, cron, login nebo `/.well-known/`.
- Rotace klíče nedokončí prepare i commit na obou stranách.

## Rollback

1. V POPIsite odvolat connector binding a vypnout jeho serverový feature flag.
2. V pluginu použít rollback frontendu; tím se obnoví původní `blog_public` i
   uložené nastavení.
3. Přepnout produkt na existující Application Password cestu pouze explicitně.
4. Plugin deaktivovat až po potvrzení, že outbox neobsahuje nedoručené události.
   Deaktivace nemaže tabulky ani klíče; kompromitovaný binding je nutné nejprve
   odvolat. Databázovou migraci POPIsite během pilotu nevracet destruktivně.

Produkční migrace, pairing `emanual.cz`, změna frontendu a odstranění legacy
credentials vždy vyžadují samostatné lidské schválení.
