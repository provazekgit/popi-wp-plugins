<?php

define('ABSPATH', __DIR__);
define('WP_ENVIRONMENT_TYPE', 'staging');

$GLOBALS['popishop_guard_options'] = array();

function wp_get_environment_type() { return WP_ENVIRONMENT_TYPE; }
function add_filter() {}
function add_action() {}
function get_option($name, $default = false) { return array_key_exists($name, $GLOBALS['popishop_guard_options']) ? $GLOBALS['popishop_guard_options'][$name] : $default; }
function update_option($name, $value) { $GLOBALS['popishop_guard_options'][$name] = $value; return true; }

require dirname(__DIR__, 2) . '/popishop-staging-guard/popishop-staging-guard.php';

$failures = 0;

function guard_check($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
}

function guard_test($name, $operation) {
    global $failures;
    try {
        $operation();
        echo "PASS {$name}\n";
    } catch (Throwable $error) {
        $failures++;
        fwrite(STDERR, "FAIL {$name}: {$error->getMessage()}\n");
    }
}

guard_test('staging environment is required', function() {
    guard_check(POPIshop_Staging_Guard::is_staging() === true, 'guard did not recognize staging');
});

guard_test('robots are disabled', function() {
    $robots = POPIshop_Staging_Guard::robots(array('index' => true, 'follow' => true));
    guard_check($robots['noindex'] === true && $robots['nofollow'] === true && $robots['noarchive'] === true, 'robots remain enabled');
});

guard_test('responses are marked as protected staging content', function() {
    $headers = POPIshop_Staging_Guard::protect_response_headers(array('Content-Type' => 'text/html'));
    guard_check($headers['X-Robots-Tag'] === 'noindex, nofollow, noarchive', 'X-Robots-Tag is missing');
    guard_check($headers['X-POPIshop-Staging-Guard'] === 'active', 'diagnostic guard header is missing');
    guard_check(strpos($headers['Cache-Control'], 'no-store') !== false, 'staging response remains cacheable');
});

guard_test('Apache staging template keeps server and WordPress diagnostics distinct', function() {
    $template = file_get_contents(dirname(__DIR__, 2) . '/migration-toolkit/.htaccess.staging.example');
    guard_check($template !== false, 'staging .htaccess template is missing');
    guard_check(strpos($template, 'X-POPIshop-Staging-Protection "active"') !== false, 'server protection header is missing');
    guard_check(strpos($template, 'X-POPIshop-Staging-Guard "server"') === false, 'server overwrites the WordPress guard header');
    guard_check(strpos($template, '^/wp-json/wc/v3/') !== false, 'Woo REST exception is missing');
    guard_check(strpos($template, '^/wp-json/popishop/v1/cart-handoff/?$') !== false, 'readiness exception is missing');
    guard_check(strpos($template, 'Require valid-user') !== false, 'Basic Auth fallback is missing');
    guard_check(strpos($template, 'wp-admin') === false, 'WordPress administration must not bypass Basic Auth');
});

guard_test('mail is captured without storing the recipient', function() {
    $GLOBALS['popishop_guard_options'] = array();
    $result = POPIshop_Staging_Guard::capture_mail(null, array(
        'to' => array('customer@example.cz'),
        'subject' => 'Objednávka #123',
        'message' => 'Testovací potvrzení objednávky',
    ));
    $log = get_option(POPIshop_Staging_Guard::MAIL_LOG_OPTION, array());
    guard_check($result === true, 'mail was not short-circuited');
    guard_check(count($log) === 1 && $log[0]['subject'] === 'Objednávka #123', 'mail was not captured');
    guard_check(strpos(json_encode($log), 'customer@example.cz') === false, 'recipient address was stored');
    guard_check(strpos($log[0]['recipients'][0], 'sha256:') === 0, 'recipient fingerprint is missing');
});

guard_test('mail log is bounded', function() {
    $GLOBALS['popishop_guard_options'] = array();
    for ($index = 0; $index < 55; $index++) {
        POPIshop_Staging_Guard::capture_mail(null, array('to' => 'test@example.cz', 'subject' => "Mail {$index}", 'message' => 'Test'));
    }
    guard_check(count(get_option(POPIshop_Staging_Guard::MAIL_LOG_OPTION, array())) === 50, 'mail log exceeded its limit');
});

guard_test('only offline gateways remain available', function() {
    $gateways = POPIshop_Staging_Guard::limit_payment_gateways(array('stripe' => new stdClass(), 'cod' => new stdClass(), 'bacs' => new stdClass()));
    guard_check(array_keys($gateways) === array('cod', 'bacs'), 'online gateway remains enabled');
});

if ($failures > 0) exit(1);
echo "POPIshop staging guard tests passed\n";
