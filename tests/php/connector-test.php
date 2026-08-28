<?php

declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');
define('AUTH_KEY', 'connector-auth-test-key');
define('SECURE_AUTH_KEY', 'connector-secure-auth-test-key');
define('LOGGED_IN_KEY', 'connector-logged-in-test-key');
define('NONCE_KEY', 'connector-nonce-test-key');
define('POPI_CONNECTOR_CONTRACT_VERSION', '1.0.0');
define('POPI_CONNECTOR_DIR', __DIR__ . '/../../popi-connector/');
define('POPI_CONNECTOR_URL', 'https://example.test/wp-content/plugins/popi-connector/');

final class WP_Error {
    private $code;
    private $message;
    private $data;
    public function __construct($code = '', $message = '', $data = null) { $this->code = $code; $this->message = $message; $this->data = $data; }
    public function get_error_code() { return $this->code; }
    public function get_error_message() { return $this->message; }
    public function get_error_data() { return $this->data; }
}

function is_wp_error($value) { return $value instanceof WP_Error; }
function wp_json_encode($value, $flags = 0) { return json_encode($value, $flags); }

require_once __DIR__ . '/../../popi-connector/includes/class-crypto.php';
require_once __DIR__ . '/../../popi-connector/includes/class-contracts.php';

function expect_true($condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

function expect_same($expected, $actual, string $message): void {
    if ($expected !== $actual) throw new RuntimeException($message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true));
}

expect_true(POPI_Connector_Crypto::available(), 'Required crypto primitives must be available');

$secret = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFG';
$cipherA = POPI_Connector_Crypto::encrypt_secret($secret);
$cipherB = POPI_Connector_Crypto::encrypt_secret($secret);
expect_true(is_string($cipherA) && is_string($cipherB), 'Encryption must return serialized envelopes');
expect_true($cipherA !== $cipherB, 'AES-GCM encryption must use a fresh IV');
expect_same($secret, POPI_Connector_Crypto::decrypt_secret($cipherA), 'Encrypted secret must round-trip');

$tamperedCipher = json_decode($cipherA, true);
$tamperedCipher['data'][0] = $tamperedCipher['data'][0] === 'A' ? 'B' : 'A';
expect_true(is_wp_error(POPI_Connector_Crypto::decrypt_secret(json_encode($tamperedCipher))), 'Tampered ciphertext must be rejected');

$envelope = array(
    'key_id' => 'key_test',
    'timestamp' => 1787836800,
    'nonce' => 'abcdefghijklmnopqrstuv',
    'request_id' => '018f0000-0000-7000-8000-000000000001',
    'tenant_id' => 'tenant_1',
    'project_id' => 'project_1',
    'module_installation_id' => 'installation_1',
    'connection_id' => 'connection_1',
    'payload_b64' => POPI_Connector_Crypto::base64url_encode('{"page":1}'),
);
$signature = POPI_Connector_Crypto::sign_request($secret, 'POST', '/wp-json/popi-connector/v1/popiweb/entries/search', $envelope);
expect_same('RzKFpOVYHxHp8OU6A1Wn-MgiZDVUIlugJ6XOlNvjlRA', $signature, 'HMAC contract vector changed unexpectedly');
$envelope['signature'] = $signature;
expect_true(POPI_Connector_Crypto::verify_request_signature($secret, 'POST', '/wp-json/popi-connector/v1/popiweb/entries/search', $envelope), 'Valid request signature must pass');

$tamperedEnvelope = $envelope;
$tamperedEnvelope['project_id'] = 'project_2';
expect_true(!POPI_Connector_Crypto::verify_request_signature($secret, 'POST', '/wp-json/popi-connector/v1/popiweb/entries/search', $tamperedEnvelope), 'Binding tampering must invalidate signature');
expect_true(!POPI_Connector_Crypto::verify_request_signature($secret, 'POST', '/wp-json/popi-connector/v1/popiweb/entries/get', $envelope), 'Route tampering must invalidate signature');

$context = array('key_id' => 'key_test', 'request_id' => 'request-response-test', 'master_secret' => $secret);
$signedResponse = POPI_Connector_Crypto::signed_response($context, 200, array('ok' => true));
expect_true(is_array($signedResponse), 'Signed response must be produced');
$verifiedResponse = POPI_Connector_Crypto::verify_response($secret, $signedResponse, 'request-response-test', POPI_Connector_Crypto::RESPONSE_INFO);
expect_same(array('ok' => true), $verifiedResponse, 'Signed response must verify and decode');
$signedResponse['status'] = 500;
expect_true(is_wp_error(POPI_Connector_Crypto::verify_response($secret, $signedResponse, 'request-response-test', POPI_Connector_Crypto::RESPONSE_INFO)), 'Tampered response must fail verification');

$pluginRoot = realpath(__DIR__ . '/../../popi-connector');
$contractHash = hash_file('sha256', $pluginRoot . '/contracts/v1/manifest.json');
expect_same($contractHash, POPI_Connector_Contracts::bundle_sha256(), 'Runtime contract hash must identify the shipped bundle manifest');
expect_same('https://example.test/wp-content/plugins/popi-connector/contracts/v1/openapi.json', POPI_Connector_Contracts::openapi_url(), 'Runtime must expose the shipped OpenAPI document');
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($pluginRoot, FilesystemIterator::SKIP_DOTS));
$phpFiles = array();
foreach ($iterator as $file) {
    if ($file->getExtension() === 'php') $phpFiles[] = $file->getPathname();
}
$allSource = '';
foreach ($phpFiles as $path) {
    $source = file_get_contents($path);
    $allSource .= $source;
    expect_true(strpos($source, 'str_contains(') === false, basename($path) . ' uses PHP 8-only str_contains()');
    expect_true(strpos($source, 'str_starts_with(') === false, basename($path) . ' uses PHP 8-only str_starts_with()');
}

$restSource = file_get_contents($pluginRoot . '/includes/class-rest-api.php');
expect_true(strpos($restSource, "'permission_callback' => '__return_true'") === false, 'Connector REST endpoints must never be public');
expect_true(strpos($restSource, 'DELETE') === false, 'Connector v1 must not expose DELETE operations');
expect_true(strpos($allSource, 'Authorization:') === false, 'Connector must not depend on the Authorization header');

echo "POPI Connector tests passed\n";
