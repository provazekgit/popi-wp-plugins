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
function sanitize_key($value) { return preg_replace('/[^a-z0-9_.-]/', '', strtolower((string) $value)); }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }

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

$fixture = json_decode(file_get_contents(__DIR__ . '/../fixtures/wordpress-connector-v1.json'), true);
expect_true(is_array($fixture), 'Executable connector compatibility fixture must be valid JSON');
expect_same('1.0.0', $fixture['contract'], 'Connector contract version changed unexpectedly');
expect_same(false, $fixture['defaultEnabled'], 'Connector adapters must stay disabled by default');
$envelope = $fixture['hmacVector']['envelope'];
unset($envelope['protocol']);
$signature = POPI_Connector_Crypto::sign_request($fixture['hmacVector']['secret'], $fixture['hmacVector']['method'], $fixture['hmacVector']['path'], $envelope);
expect_same($fixture['hmacVector']['signature'], $signature, 'HMAC contract vector changed unexpectedly');
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
$authSource = file_get_contents($pluginRoot . '/includes/class-authentication.php');
$pairingSource = file_get_contents($pluginRoot . '/includes/class-pairing.php');
$storageSource = file_get_contents($pluginRoot . '/includes/class-storage.php');
$contractsSource = file_get_contents($pluginRoot . '/includes/class-contracts.php');
expect_true(strpos($restSource, "'permission_callback' => '__return_true'") === false, 'Connector REST endpoints must never be public');
expect_true(strpos($restSource, 'DELETE') === false, 'Connector v1 must not expose DELETE operations');
expect_true(strpos($allSource, 'Authorization:') === false, 'Connector must not depend on the Authorization header');
expect_true(strpos($authSource, 'verify_request_signature') < strpos($authSource, 'consume_nonce'), 'Replay nonce must be consumed only after signature verification');
expect_true(strpos($authSource, 'binding_mismatch') !== false && strpos($authSource, 'scope_denied') !== false, 'Tenant tuple and capability scopes must fail closed');
expect_true(strpos($pairingSource, 'CLAIM_PATH') !== false && strpos($pairingSource, 'claim_token') !== false, 'Pairing must use a one-time claim token');
expect_true(strpos($pairingSource, 'rotations/prepare') !== false && strpos($pairingSource, 'rotations/commit') !== false, 'Rotation must use prepare and commit phases');
expect_true(strpos($storageSource, "status = 'retiring'") !== false && strpos($storageSource, "status = 'revoked'") !== false, 'Rotation grace and revocation states must be persisted');
expect_same('1.0.0', POPI_CONNECTOR_CONTRACT_VERSION, 'Plugin must expose the stable contract version');

require_once $pluginRoot . '/includes/class-audit.php';
$auditMethod = new ReflectionMethod('POPI_Connector_Audit', 'sanitize_metadata');
$auditMethod->setAccessible(true);
$redacted = $auditMethod->invoke(null, array('token' => 'secret', 'nested' => array('authorization' => 'Bearer x'), 'safe' => 'ok'));
expect_same('[redacted]', $redacted['token'], 'Audit must redact token fields');
expect_same('[redacted]', $redacted['nested']['authorization'], 'Audit must redact nested authorization fields');
expect_same('ok', $redacted['safe'], 'Audit must retain non-sensitive diagnostics');

echo "POPI Connector tests passed\n";
