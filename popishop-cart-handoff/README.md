# POPIshop Cart Handoff

Minimal WooCommerce plugin for the POPIshop native cart. It accepts only a
short-lived HMAC-signed cart payload posted by the browser, validates every
product and variation again in WooCommerce, adds the lines to the WooCommerce
session, and redirects to the existing checkout.

## Installation

1. Run `npm run package:popishop` from the repository root. Install the
   generated `dist/popishop-cart-handoff.zip`; its `.sha256` file can be used
   to verify that the archive was not changed after CI produced it.
2. Install and activate it in WordPress under Plugins.
3. In POPIshop administration generate a cart handoff key for the connected
   shop.
4. Open WooCommerce > POPIshop, paste the key, and save.

Rotating the key in POPIshop immediately invalidates the previous key. Update
the plugin setting in the same maintenance window. The 12-character key
fingerprint shown on both administration screens must match.

## Updates

WordPress checks the public POPIsite plugin registry and offers new versions in
the standard Plugins screen. Release ZIP files are published from tags named
`popishop-v*`. Before installation, the plugin verifies the SHA-256 checksum
provided by the registry.

## Security properties

- Tokens expire after five minutes.
- Tokens are submitted with `POST`, not included in URLs.
- Product IDs, variations, quantities, stock, and purchasability are checked
  again against WooCommerce.
- A replayed token does not add the same cart lines twice in one WooCommerce
  session.
- The plugin stores no POPIshop account, customer, order, or payment data.
- `GET /wp-json/popishop/v1/cart-handoff` exposes only plugin readiness,
  WooCommerce availability, and the non-secret key fingerprint for staging
  verification. It is explicitly non-cacheable.

The package command intentionally reads the plugin from the current Git commit
and refuses uncommitted plugin changes. This keeps the installed archive tied
to a reviewable source revision.

Run `npm run test:popishop` from the repository root to exercise token
validation, expiry, stock limits, variation ownership, and the readiness
fingerprint without requiring a live WordPress installation.
