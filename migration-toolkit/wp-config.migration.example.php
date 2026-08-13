<?php
// Copy only the required definitions before: /* That's all, stop editing! */

define('WP_ENVIRONMENT_TYPE', 'staging');
define('DISABLE_WP_CRON', true);
define('DISALLOW_FILE_EDIT', true);
define('FORCE_SSL_ADMIN', true);

// Keep only offline test methods visible during staging checkout tests.
define('POPISHOP_STAGING_ALLOWED_GATEWAYS', 'cod,bacs');

// List only plugin entry files confirmed by the PHP fatal error log.
// Remove each item immediately after installing and verifying a complete copy.
define(
    'POPI_MIGRATION_DISABLED_PLUGINS',
    'toret-vyfakturuj/toret-vyfakturuj.php'
);

// Never place passwords, API keys, salts, or database credentials here.
