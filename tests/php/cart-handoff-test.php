<?php

define('ABSPATH', __DIR__);
define('HOUR_IN_SECONDS', 3600);

$GLOBALS['popishop_test_secret'] = '';
$GLOBALS['popishop_test_products'] = array();
$GLOBALS['popishop_test_transients'] = array();
$GLOBALS['popishop_test_remote'] = null;
$GLOBALS['popishop_test_download'] = null;

class WP_Error {
    private $code;
    private $message;

    public function __construct($code, $message) {
        $this->code = $code;
        $this->message = $message;
    }

    public function get_error_code() { return $this->code; }
    public function get_error_message() { return $this->message; }
}

class WP_REST_Response {
    public $data;
    public $headers = array();

    public function __construct($data) { $this->data = $data; }
    public function header($name, $value) { $this->headers[$name] = $value; }
}

class WooCommerce {}

class Fake_Product {
    private $type;
    private $stock;

    public function __construct($type = 'simple', $stock = 10) {
        $this->type = $type;
        $this->stock = $stock;
    }

    public function is_purchasable() { return true; }
    public function is_in_stock() { return $this->stock > 0; }
    public function has_enough_stock($quantity) { return $quantity <= $this->stock; }
    public function is_type($type) { return $this->type === $type; }
}

class WC_Product_Variation extends Fake_Product {
    private $parent_id;

    public function __construct($parent_id, $stock = 10) {
        parent::__construct('variation', $stock);
        $this->parent_id = $parent_id;
    }

    public function get_parent_id() { return $this->parent_id; }
    public function get_variation_attributes() { return array('attribute_size' => 'large'); }
}

function add_action() {}
function add_filter() {}
function add_submenu_page() {}
function register_setting() {}
function register_rest_route() {}
function plugin_dir_path($file) { return dirname($file) . '/'; }
function plugin_basename($file) { return 'popishop-cart-handoff/' . basename($file); }
function sanitize_text_field($value) { return trim((string) $value); }
function add_settings_error() {}
function get_option($name, $default = '') { return $name === 'popishop_cart_handoff_secret' ? $GLOBALS['popishop_test_secret'] : $default; }
function is_wp_error($value) { return $value instanceof WP_Error; }
function wc_get_product($id) { return isset($GLOBALS['popishop_test_products'][$id]) ? $GLOBALS['popishop_test_products'][$id] : false; }
function get_transient($key) { return array_key_exists($key, $GLOBALS['popishop_test_transients']) ? $GLOBALS['popishop_test_transients'][$key] : false; }
function set_transient($key, $value) { $GLOBALS['popishop_test_transients'][$key] = $value; }
function wp_remote_get() { return $GLOBALS['popishop_test_remote']; }
function wp_remote_retrieve_response_code($response) { return isset($response['code']) ? $response['code'] : 0; }
function wp_remote_retrieve_body($response) { return isset($response['body']) ? $response['body'] : ''; }
function get_bloginfo($key) { return $key === 'version' ? '6.7' : 'https://shop.test'; }
function download_url() { return $GLOBALS['popishop_test_download']; }
function wp_delete_file($file) { if (is_file($file)) unlink($file); }
function trailingslashit($value) { return rtrim($value, '/\\') . '/'; }

require dirname(__DIR__, 2) . '/popishop-cart-handoff/popishop-cart-handoff.php';

$failures = 0;

function check($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
}

function run_test($name, $operation) {
    global $failures;
    try {
        $operation();
        echo "PASS {$name}\n";
    } catch (Throwable $error) {
        $failures++;
        fwrite(STDERR, "FAIL {$name}: {$error->getMessage()}\n");
    }
}

function private_call($method, array $arguments) {
    $reflection = new ReflectionMethod('POPIshop_Cart_Handoff', $method);
    $reflection->setAccessible(true);
    return $reflection->invokeArgs(null, $arguments);
}

function base64url($value) {
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function token_for($secret, $expires) {
    $payload = base64url(json_encode(array(
        'v' => 1,
        'exp' => $expires,
        'nonce' => 'abcdefghijklmnop',
        'items' => array(array(101, 0, 2)),
    )));
    return $payload . '.' . base64url(hash_hmac('sha256', $payload, $secret, true));
}

function contract_token() {
    $secret = 'test-secret-with-at-least-forty-characters-123';
    $payload = base64url(json_encode(array(
        'v' => 1,
        'exp' => 1786536300,
        'nonce' => 'abcdefghijklmnop',
        'items' => array(array(42, 0, 2), array(80, 81, 1)),
    )));
    return $payload . '.' . base64url(hash_hmac('sha256', $payload, $secret, true));
}

function update_manifest($version, $sha256) {
    return array(
        'code' => 200,
        'body' => json_encode(array(
            'version' => $version,
            'download_url' => 'https://github.com/provazekgit/popi-wp-plugins/releases/download/popishop-v' . $version . '/popishop-cart-handoff.zip',
            'sha256' => $sha256,
            'requires' => '6.4',
            'requires_php' => '7.4',
            'tested' => '6.7',
            'last_updated' => '2026-08-12',
            'sections' => array('description' => 'Test', 'changelog' => 'Test'),
        )),
    );
}

run_test('valid signed token', function() {
    $payload = private_call('verify_token', array(token_for('test-secret-with-at-least-forty-characters-123', time() + 300), 'test-secret-with-at-least-forty-characters-123'));
    check(is_array($payload) && $payload['items'][0][0] === 101, 'valid token was rejected');
});

run_test('POPIshop API v1 contract vector', function() {
    $expected = 'eyJ2IjoxLCJleHAiOjE3ODY1MzYzMDAsIm5vbmNlIjoiYWJjZGVmZ2hpamtsbW5vcCIsIml0ZW1zIjpbWzQyLDAsMl0sWzgwLDgxLDFdXX0.oFF4DeRvOaqFS7SQ7_lqP1tg33XQxMCHhqn6INCaCiE';
    check(contract_token() === $expected, 'plugin signature format differs from the POPIshop API');
});

run_test('invalid signature', function() {
    $result = private_call('verify_token', array(token_for('test-secret-with-at-least-forty-characters-123', time() + 300) . 'x', 'test-secret-with-at-least-forty-characters-123'));
    check(is_wp_error($result) && $result->get_error_code() === 'signature_invalid', 'invalid signature was accepted');
});

run_test('expired token', function() {
    $result = private_call('verify_token', array(token_for('test-secret-with-at-least-forty-characters-123', time() - 1), 'test-secret-with-at-least-forty-characters-123'));
    check(is_wp_error($result) && $result->get_error_code() === 'token_expired', 'expired token was accepted');
});

run_test('simple product stock', function() {
    $GLOBALS['popishop_test_products'] = array(101 => new Fake_Product('simple', 2));
    $valid = private_call('validate_items', array(array(array(101, 0, 2))));
    $invalid = private_call('validate_items', array(array(array(101, 0, 3))));
    check(is_array($valid) && count($valid) === 1, 'available simple product was rejected');
    check(is_wp_error($invalid) && $invalid->get_error_code() === 'product_unavailable', 'insufficient stock was accepted');
});

run_test('variation parent validation', function() {
    $GLOBALS['popishop_test_products'] = array(
        200 => new Fake_Product('variable', 10),
        201 => new WC_Product_Variation(200, 4),
        202 => new WC_Product_Variation(999, 4),
    );
    $valid = private_call('validate_items', array(array(array(200, 201, 2))));
    $invalid = private_call('validate_items', array(array(array(200, 202, 2))));
    check(is_array($valid) && $valid[0]['variation_attributes']['attribute_size'] === 'large', 'valid variation was rejected');
    check(is_wp_error($invalid) && $invalid->get_error_code() === 'variation_unavailable', 'foreign variation was accepted');
});

run_test('health fingerprint', function() {
    $GLOBALS['popishop_test_secret'] = 'test-secret-with-at-least-forty-characters-123';
    $response = POPIshop_Cart_Handoff::health();
    check($response->data['configured'] === true, 'configured state is false');
    check($response->data['woocommerceActive'] === true, 'WooCommerce state is false');
    check($response->data['fingerprint'] === substr(hash('sha256', $GLOBALS['popishop_test_secret']), 0, 12), 'fingerprint does not match');
    check($response->headers['Cache-Control'] === 'no-store', 'health response is cacheable');
});

run_test('update is offered for a newer version', function() {
    $GLOBALS['popishop_test_transients'] = array();
    $GLOBALS['popishop_test_remote'] = update_manifest('0.1.2', str_repeat('a', 64));
    $updater = new POPIshop_Cart_Handoff_Updater(__DIR__ . '/popishop-cart-handoff.php', POPISHOP_CART_HANDOFF_UPDATE_URL, '0.1.1');
    $transient = (object) array('checked' => array('popishop-cart-handoff/popishop-cart-handoff.php' => '0.1.1'));
    $result = $updater->check_update($transient);
    check(isset($result->response['popishop-cart-handoff/popishop-cart-handoff.php']), 'new version was not offered');
    check($result->response['popishop-cart-handoff/popishop-cart-handoff.php']->new_version === '0.1.2', 'wrong update version');
});

run_test('valid update checksum is accepted', function() {
    $contents = 'signed-plugin-archive';
    $temporary = tempnam(sys_get_temp_dir(), 'popishop-update-');
    file_put_contents($temporary, $contents);
    $GLOBALS['popishop_test_download'] = $temporary;
    $GLOBALS['popishop_test_transients'] = array();
    $GLOBALS['popishop_test_remote'] = update_manifest('0.1.2', hash('sha256', $contents));
    $updater = new POPIshop_Cart_Handoff_Updater(__DIR__ . '/popishop-cart-handoff.php', POPISHOP_CART_HANDOFF_UPDATE_URL, '0.1.1');
    $package = json_decode($GLOBALS['popishop_test_remote']['body'])->download_url;
    $result = $updater->verify_download(false, $package, null, array('plugin' => 'popishop-cart-handoff/popishop-cart-handoff.php'));
    check($result === $temporary, 'valid package checksum was rejected');
    unlink($temporary);
});

run_test('invalid update checksum is rejected and deleted', function() {
    $temporary = tempnam(sys_get_temp_dir(), 'popishop-update-');
    file_put_contents($temporary, 'tampered-plugin-archive');
    $GLOBALS['popishop_test_download'] = $temporary;
    $GLOBALS['popishop_test_transients'] = array();
    $GLOBALS['popishop_test_remote'] = update_manifest('0.1.2', str_repeat('b', 64));
    $updater = new POPIshop_Cart_Handoff_Updater(__DIR__ . '/popishop-cart-handoff.php', POPISHOP_CART_HANDOFF_UPDATE_URL, '0.1.1');
    $package = json_decode($GLOBALS['popishop_test_remote']['body'])->download_url;
    $result = $updater->verify_download(false, $package, null, array('plugin' => 'popishop-cart-handoff/popishop-cart-handoff.php'));
    check(is_wp_error($result) && $result->get_error_code() === 'popishop_update_checksum_mismatch', 'tampered package was accepted');
    check(!file_exists($temporary), 'tampered package was not deleted');
});

if ($failures > 0) exit(1);
echo "WordPress cart handoff tests passed\n";
