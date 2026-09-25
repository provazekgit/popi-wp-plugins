<?php

declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');
define('AUTH_KEY', 'connector-auth-test-key');
define('SECURE_AUTH_KEY', 'connector-secure-auth-test-key');
define('LOGGED_IN_KEY', 'connector-logged-in-test-key');
define('NONCE_KEY', 'connector-nonce-test-key');
define('POPI_CONNECTOR_CONTRACT_VERSION', '1.1.0');
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
function sanitize_title($value) { return preg_replace('/[^a-z0-9-]/', '', strtolower((string) $value)); }
function esc_url_raw($value) { return filter_var((string) $value, FILTER_VALIDATE_URL) ? (string) $value : ''; }
function get_post_meta($postId, $key, $single = false) {
    $values = array(
        '34:popi_gallery' => array(25, 24),
        '34:popi_width_mm' => 120,
        '24:_wp_attachment_image_alt' => 'Dětská souprava',
        '25:_wp_attachment_image_alt' => 'Detail výšivky',
    );
    return $values[$postId . ':' . $key] ?? '';
}
function metadata_exists($type, $postId, $key) {
    return in_array($postId . ':' . $key, array('34:popi_gallery', '34:popi_width_mm', '24:_wp_attachment_image_alt', '25:_wp_attachment_image_alt'), true);
}
final class ACPT_Test_Attachment {
    private $id;
    public function __construct($id) { $this->id = $id; }
    public function getId() { return $this->id; }
}
function get_acpt_fields($args) {
    if (($args['post_id'] ?? 0) !== 35) return array();
    return array('parametry-realizace_popi_gallery' => array(new ACPT_Test_Attachment(25)));
}
function get_post_thumbnail_id($postId) { return in_array($postId, array(34, 35), true) ? 24 : 0; }
function get_permalink($postId) { return 'https://example.test/realizace/' . $postId; }
function wp_attachment_is_image($postId) { return in_array($postId, array(24, 25), true); }
function wp_get_attachment_url($postId) { return 'https://example.test/uploads/' . $postId . '.jpg'; }
function wp_get_attachment_metadata($postId) { return array('width' => $postId === 24 ? 450 : 600, 'height' => 600); }
function get_post_mime_type($postId) { return 'image/jpeg'; }
function get_object_taxonomies($postType, $output = 'names') {
    $taxonomies = array(
        'popi_textile' => (object) array('name' => 'popi_textile', 'public' => true, 'show_in_rest' => true),
        'internal_notes' => (object) array('name' => 'internal_notes', 'public' => false, 'show_in_rest' => true),
    );
    return $output === 'objects' ? $taxonomies : array_keys($taxonomies);
}
function wp_get_object_terms($postId, $taxonomy) {
    if ($postId !== 34 || $taxonomy !== 'popi_textile') return array();
    return array((object) array('term_id' => 3, 'slug' => 'detsky-textil', 'name' => 'Dětský textil'));
}
function get_option($key, $default = false) {
    if ($key === 'popi_connector_legacy_connections') {
        return array('binding_1' => array('declared' => true, 'purpose' => 'content_sync', 'note' => 'Legacy POPIcast'));
    }
    return $default;
}
function get_users($args = array()) { return array(7); }
function wp_is_application_passwords_supported() { return true; }
function get_post_types($args = array(), $output = 'names') {
    $types = array(
        'post' => (object) array('name' => 'post', 'public' => true, 'publicly_queryable' => true, 'show_in_rest' => true),
        'page' => (object) array('name' => 'page', 'public' => true, 'publicly_queryable' => false, 'show_in_rest' => true),
        'internal_rest' => (object) array('name' => 'internal_rest', 'public' => false, 'publicly_queryable' => false, 'show_in_rest' => true),
    );
    return $output === 'objects' ? $types : array_keys($types);
}

final class WP_Application_Passwords {
    public static function get_user_application_passwords($userId) {
        return array(array(
            'name' => 'POPIcast secret name',
            'uuid' => 'credential-uuid',
            'password' => 'must-not-leak',
            'last_ip' => '192.0.2.1',
            'last_used' => 1_788_188_400,
        ));
    }
}

final class POPI_Connector_Storage {
    public static function binding_config($binding) { return $binding['config'] ?? array(); }
}

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
expect_same('1.1.0', $fixture['contract'], 'Connector contract version changed unexpectedly');
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
$remoteSource = file_get_contents($pluginRoot . '/includes/class-remote.php');
$adminSource = file_get_contents($pluginRoot . '/includes/class-admin.php');
$outboxSource = file_get_contents($pluginRoot . '/includes/class-outbox.php');
$legacySource = file_get_contents($pluginRoot . '/includes/class-legacy-connections.php');
expect_true(strpos($restSource, "'permission_callback' => '__return_true'") === false, 'Connector REST endpoints must never be public');
expect_true(strpos($restSource, 'DELETE') === false, 'Connector v1 must not expose DELETE operations');
expect_true(strpos($allSource, 'Authorization:') === false, 'Connector must not depend on the Authorization header');
expect_true(strpos($authSource, 'verify_request_signature') < strpos($authSource, 'consume_nonce'), 'Replay nonce must be consumed only after signature verification');
expect_true(strpos($authSource, 'binding_mismatch') !== false && strpos($authSource, 'scope_denied') !== false, 'Tenant tuple and capability scopes must fail closed');
expect_true(strpos($pairingSource, 'CLAIM_PATH') !== false && strpos($pairingSource, 'claim_token') !== false, 'Pairing must use a one-time claim token');
expect_true(strpos($pairingSource, 'rotations/prepare') !== false && strpos($pairingSource, 'rotations/commit') !== false, 'Rotation must use prepare and commit phases');
expect_true(strpos($storageSource, "status = 'retiring'") !== false && strpos($storageSource, "status = 'revoked'") !== false, 'Rotation grace and revocation states must be persisted');
expect_same('1.1.0', POPI_CONNECTOR_CONTRACT_VERSION, 'Plugin must expose the compatible minor contract version');
expect_true(strpos($remoteSource, "'/api/v1/connectors/wordpress/health'") !== false, 'Signed outbound health must target the typed POPIsite endpoint');
expect_true(strpos($remoteSource, "'core.health:read'") !== false, 'Outbound health must fail closed without the existing health scope');
expect_true(strpos($adminSource, 'POPI_Connector_Remote::report_health') !== false, 'Diagnostics must verify HMAC health instead of only public HTTPS');
expect_true(strpos($outboxSource, 'POPI_Connector_Remote::report_health') !== false, 'Scheduled maintenance must report signed health outbound');
expect_true(strpos($legacySource, "'password'") === false && strpos($legacySource, "'uuid'") === false && strpos($legacySource, "'last_ip'") === false, 'Legacy inventory must not serialize Application Password secrets or identifiers');
expect_true(strpos($legacySource, 'MAX_USERS_SCANNED') !== false, 'Legacy inventory must keep its user scan bounded');
expect_true(strpos($adminSource, 'popi_connector_legacy_save_') !== false, 'Legacy declarations must use a binding-specific CSRF nonce');
expect_true(strpos($adminSource, 'popi_connector_module_config_save_') !== false, 'Content type changes must use a binding-specific CSRF nonce');
expect_true(strpos($adminSource, "get_post_types( array( 'show_in_rest' => true ), 'objects' )") !== false, 'Content type selection must require an active REST API');
expect_true(strpos($adminSource, '$post_type->public') !== false && strpos($adminSource, '$post_type->publicly_queryable') !== false, 'Content type selection must include public REST types such as the built-in page type');
expect_true(strpos($adminSource, 'self::selectable_post_types( \'objects\' )') !== false && strpos($adminSource, 'self::selectable_post_types( \'names\' )') !== false, 'Rendered and submitted content type choices must use the same allowlist');
expect_true(strpos($adminSource, 'binding.config_updated') !== false, 'Content type changes must be audited');
expect_true(strpos($storageSource, 'update_binding_config') !== false, 'Binding config must support a non-destructive update without re-pairing');
expect_true(strpos($contractsSource, "health['legacy_connection']") !== false, 'Legacy health extension must stay optional on clean WordPress installations');

$serializePost = new ReflectionMethod('POPI_Connector_Contracts', 'serialize_post');
$serializePost->setAccessible(true);
$serializedPost = $serializePost->invoke(null, (object) array(
    'ID' => 34,
    'post_type' => 'popi_realization',
    'post_name' => 'detska-souprava',
    'post_status' => 'publish',
    'post_title' => 'Dětská souprava',
    'post_excerpt' => 'Ukázka realizace',
    'post_content' => 'Obsah realizace',
    'post_modified_gmt' => '2026-09-11 08:00:00',
), array('config' => array('allowed_meta_keys' => array('popi_gallery', 'popi_width_mm'))));
expect_same(24, $serializedPost['featured_media_id'], 'Legacy featured media ID must stay available');
expect_same('https://example.test/uploads/24.jpg', $serializedPost['featured_media_url'], 'Featured media URL must be serialized');
expect_same(25, $serializedPost['gallery'][0]['id'], 'Gallery must contain allowed image attachments without duplicating the featured image');
expect_same(array(25, 24), $serializedPost['meta']['popi_gallery'], 'Scalar meta arrays must remain available for tolerant consumers');
expect_same('detsky-textil', $serializedPost['taxonomies']['popi_textile'][0]['slug'], 'Public REST taxonomy terms must be serialized');
expect_true(!isset($serializedPost['taxonomies']['internal_notes']), 'Private taxonomies must not be serialized');

$serializedAcptPost = $serializePost->invoke(null, (object) array(
    'ID' => 35,
    'post_type' => 'popi_realization',
    'post_name' => 'acpt-gallery',
    'post_status' => 'draft',
    'post_title' => 'ACPT gallery',
    'post_excerpt' => '',
    'post_content' => '',
    'post_modified_gmt' => '2026-09-25 10:00:00',
), array('config' => array('allowed_meta_keys' => array('popi_gallery'))));
expect_same(array(25), $serializedAcptPost['meta']['popi_gallery'], 'Allowed ACPT fields must be exposed without duplicating them into post meta');
expect_same(25, $serializedAcptPost['gallery'][0]['id'], 'ACPT gallery attachment objects must serialize as connector media');

require_once $pluginRoot . '/includes/class-admin.php';
$selectablePostTypes = new ReflectionMethod('POPI_Connector_Admin', 'selectable_post_types');
$selectablePostTypes->setAccessible(true);
expect_same(array('post', 'page'), $selectablePostTypes->invoke(null, 'names'), 'Public REST selection must include core pages and exclude internal REST types');

require_once $pluginRoot . '/includes/class-authentication.php';
$payloadValidator = new ReflectionMethod('POPI_Connector_Authentication', 'valid_payload_b64');
$payloadValidator->setAccessible(true);
expect_same(true, $payloadValidator->invoke(null, 'e30'), 'A valid base64url payload must pass validation');
expect_same(false, $payloadValidator->invoke(null, 'A='), 'Base64 padding must be rejected');
expect_same(false, $payloadValidator->invoke(null, 'A'), 'A one-character payload must be rejected');
$maximumPayload = str_repeat('A', POPI_Connector_Authentication::MAX_PAYLOAD_B64_BYTES);
expect_same(true, $payloadValidator->invoke(null, $maximumPayload), 'The documented maximum payload length must pass without a PCRE compilation error');
expect_same(false, $payloadValidator->invoke(null, $maximumPayload . 'A'), 'An oversized payload must be rejected before regex validation');
unset($maximumPayload);

require_once $pluginRoot . '/includes/class-audit.php';
$auditMethod = new ReflectionMethod('POPI_Connector_Audit', 'sanitize_metadata');
$auditMethod->setAccessible(true);
$redacted = $auditMethod->invoke(null, array('token' => 'secret', 'nested' => array('authorization' => 'Bearer x'), 'safe' => 'ok'));
expect_same('[redacted]', $redacted['token'], 'Audit must redact token fields');
expect_same('[redacted]', $redacted['nested']['authorization'], 'Audit must redact nested authorization fields');
expect_same('ok', $redacted['safe'], 'Audit must retain non-sensitive diagnostics');

require_once $pluginRoot . '/includes/class-legacy-connections.php';
$legacyPayload = POPI_Connector_Legacy_Connections::health_payload(array('binding_id' => 'binding_1', 'module' => 'popicast'));
expect_same(true, $legacyPayload['detection']['configured'], 'Application Password inventory must detect configured credentials');
expect_same(1, $legacyPayload['detection']['credential_count'], 'Application Password inventory must report only an aggregate count');
expect_true((bool) preg_match('/Z$/', $legacyPayload['detection']['last_used_at']), 'Application Password last-used time must use contract-compatible UTC Z notation');
expect_same(true, $legacyPayload['declared'], 'A binding-specific operator declaration must be included');
$legacyJson = json_encode($legacyPayload);
expect_true(strpos($legacyJson, 'must-not-leak') === false, 'Application Password value must never enter health payload');
expect_true(strpos($legacyJson, 'credential-uuid') === false, 'Application Password UUID must never enter health payload');
expect_true(strpos($legacyJson, '192.0.2.1') === false, 'Application Password last IP must never enter health payload');

echo "POPI Connector tests passed\n";
