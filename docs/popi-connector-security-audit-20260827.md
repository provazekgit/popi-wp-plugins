# POPI Connector security audit — 2026-08-27

## Scope and immutable inputs

- Plugin input: `7ca59930e33d7be266b83ad2e8824bbbe3b1d686` (`popi-wp-plugins` PR #5).
- POPIsite input: `54b5846395fbdc078d8b8f92c275b507c5e370c1` (`popisite` PR #46).
- Contract maturity: preview `1.0.0-rc.1`; all product adapters remain disabled by default.
- No production environment, database, DNS, WordPress, credential, role or ownership operation was performed.

## Controls reviewed and tested

- Pairing tokens are single-use, short-lived and never persisted in plaintext.
- Requests use HMAC-SHA256 over an exact canonical tuple and compare signatures in constant time.
- A nonce is consumed only after signature verification; the database uniqueness invariant prevents replay.
- Tenant, project, installation and connection bindings are checked as one tuple.
- Capability scopes are deny-by-default and product-specific.
- Key lifecycle transitions are one-way: prepare, commit, retire and revoke cannot reactivate an old key.
- Audit payloads redact secret, signature, token, authorization and credential fields.
- The plugin does not accept public callbacks and does not expose credential-bearing REST operations.
- POPIsite outbound routing rejects private, link-local, documentation, multicast and transition-address ranges, including IPv4-mapped hexadecimal IPv6, NAT64, 6to4 and Teredo forms.
- POPIweb and POPIcast adapters accept typed operations only. They cannot accept a URL, HTTP method, path or credential and do not implement automatic legacy fallback.
- Cross-language compatibility is anchored by one shared HMAC test vector.

## Findings addressed in this draft integration

1. Central executable schemas and compatibility metadata replace duplicated product-local contract definitions.
2. Exact opt-in feature guards make the server routes, dashboard UI and product adapters unreachable unless explicitly enabled.
3. SSRF checks now cover encoded IPv4-in-IPv6 and IPv6 transition prefixes that the immutable input did not cover completely.
4. Pairing and key status changes now use explicit allowed-transition helpers and reject rollback/reactivation.
5. Automated tests cover tenant isolation, pairing, HMAC tampering, replay ordering, capability denial, rotation, revocation and compatibility.

## Compatibility

- WordPress: 6.2 or newer.
- PHP: 7.4 or newer; source and syntax checks avoid PHP 8-only constructs.
- Initial topology: WordPress single-site.
- Supported product adapters in the preview contract: `popiweb` and `popicast`; POPIsite owns the typed gateway and shared `core` operations.
- Preview product adapters are intentionally not connected to existing sync, import, publish or feed paths.

## Residual risks and release gates

- No migration rehearsal or production migration was run; the immutable POPIsite input still requires its separately approved database gate.
- No live WordPress request, pairing ceremony or credential import was performed.
- Preview deployments must keep all connector feature flags absent or false.
- The plugin still needs a non-production WordPress matrix run across representative shared hosting before release.
- POPIsite dependency audit reports inherited issues in the current input baseline (esbuild/vite/vitest and Next.js transitive postcss/sharp). npm proposes breaking major upgrades, so they were not changed in this scoped integration and require a separate dependency-security PR.
- Merging contracts and enabling any adapter require separate human approval after stacked draft review and preview verification.

## Verification evidence

- Plugin PHP tests and PHP syntax checks: passed.
- Executable contracts type-check, tests and build: passed.
- POPIsite API: 40 files / 350 tests passed; type-check and production build passed.
- POPIweb API: 10 files / 50 tests passed; type-check and production builds passed.
- POPIcast: 3 files / 11 tests passed; build passed; lint has two pre-existing warnings and no errors.
- POPIsite and POPIweb repository lint commands remain non-automatable because their existing Next.js apps have no committed ESLint configuration and open an interactive setup prompt. Their production builds completed built-in lint/type validation successfully.
