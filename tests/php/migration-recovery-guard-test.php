<?php

define('ABSPATH', __DIR__);
define(
    'POPI_MIGRATION_DISABLED_PLUGINS',
    'toret-vyfakturuj/toret-vyfakturuj.php, wp-rocket\\wp-rocket.php'
);

function add_filter() {}
function add_action() {}

require dirname(__DIR__, 2) . '/popi-migration-recovery-guard/popi-migration-recovery-guard.php';

$failures = 0;

function recovery_check($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function recovery_test($name, $operation) {
    global $failures;
    try {
        $operation();
        echo "PASS {$name}\n";
    } catch (Throwable $error) {
        $failures++;
        fwrite(STDERR, "FAIL {$name}: {$error->getMessage()}\n");
    }
}

recovery_test('configuration is normalized', function () {
    recovery_check(
        POPI_Migration_Recovery_Guard::disabled_plugins() === array(
            'toret-vyfakturuj/toret-vyfakturuj.php',
            'wp-rocket/wp-rocket.php',
        ),
        'disabled plugin list was not normalized'
    );
});

recovery_test('selected plugins are removed without changing input', function () {
    $active = array(
        'woocommerce/woocommerce.php',
        'toret-vyfakturuj/toret-vyfakturuj.php',
        'wp-rocket/wp-rocket.php',
    );
    $filtered = POPI_Migration_Recovery_Guard::filter_active_plugins($active);
    recovery_check($filtered === array('woocommerce/woocommerce.php'), 'selected plugins remain active');
    recovery_check(count($active) === 3, 'input plugin list was modified');
});

recovery_test('network plugins are removed by key', function () {
    $active = array(
        'toret-vyfakturuj/toret-vyfakturuj.php' => 123,
        'woocommerce/woocommerce.php' => 456,
    );
    $filtered = POPI_Migration_Recovery_Guard::filter_sitewide_plugins($active);
    recovery_check(
        $filtered === array('woocommerce/woocommerce.php' => 456),
        'network plugin was not filtered'
    );
});

if ($failures > 0) {
    exit(1);
}
echo "POPI migration recovery guard tests passed\n";
