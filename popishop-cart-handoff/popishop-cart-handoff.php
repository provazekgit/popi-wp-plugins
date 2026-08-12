<?php
/**
 * Plugin Name: POPIshop Cart Handoff
 * Description: Přijme krátkodobě podepsaný košík z POPIshopu a bezpečně jej předá do WooCommerce checkoutu.
 * Version: 0.1.1
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 8.0
 * Author: POPI
 * Text Domain: popishop-cart-handoff
 */

defined('ABSPATH') || exit;

define('POPISHOP_CART_HANDOFF_FILE', __FILE__);
define('POPISHOP_CART_HANDOFF_DIR', plugin_dir_path(__FILE__));
define('POPISHOP_CART_HANDOFF_UPDATE_URL', 'https://api.popisite.cz/api/v1/public/plugins/popishop-cart-handoff');

require_once POPISHOP_CART_HANDOFF_DIR . 'includes/class-updater.php';

final class POPIshop_Cart_Handoff {
    const VERSION = '0.1.1';
    const OPTION_SECRET = 'popishop_cart_handoff_secret';
    const SESSION_USED_TOKENS = 'popishop_cart_handoff_used_tokens';

    public static function boot() {
        add_action('admin_menu', array(__CLASS__, 'admin_menu'));
        add_action('admin_init', array(__CLASS__, 'register_settings'));
        add_action('rest_api_init', array(__CLASS__, 'register_rest_routes'));
        add_action('template_redirect', array(__CLASS__, 'handle_handoff'), 1);
    }

    public static function admin_menu() {
        add_submenu_page(
            'woocommerce',
            'POPIshop',
            'POPIshop',
            'manage_woocommerce',
            'popishop-cart-handoff',
            array(__CLASS__, 'settings_page')
        );
    }

    public static function register_settings() {
        register_setting('popishop_cart_handoff', self::OPTION_SECRET, array(
            'type' => 'string',
            'sanitize_callback' => array(__CLASS__, 'sanitize_secret'),
            'default' => '',
        ));
    }

    public static function register_rest_routes() {
        register_rest_route('popishop/v1', '/cart-handoff', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'health'),
            'permission_callback' => '__return_true',
        ));
    }

    public static function health() {
        $secret = (string) get_option(self::OPTION_SECRET, '');
        $response = new WP_REST_Response(array(
            'ok' => true,
            'plugin' => 'popishop-cart-handoff',
            'version' => self::VERSION,
            'configured' => $secret !== '',
            'woocommerceActive' => class_exists('WooCommerce'),
            'fingerprint' => $secret === '' ? null : substr(hash('sha256', $secret), 0, 12),
        ));
        $response->header('Cache-Control', 'no-store');
        return $response;
    }

    public static function sanitize_secret($value) {
        $secret = trim(sanitize_text_field($value));
        if ($secret === '') {
            return (string) get_option(self::OPTION_SECRET, '');
        }
        if (!preg_match('/^[A-Za-z0-9_-]{40,100}$/', $secret)) {
            add_settings_error(self::OPTION_SECRET, 'invalid_secret', 'Klíč POPIshopu nemá očekávaný formát.');
            return (string) get_option(self::OPTION_SECRET, '');
        }
        return $secret;
    }

    public static function settings_page() {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }
        $stored_secret = (string) get_option(self::OPTION_SECRET, '');
        $configured = $stored_secret !== '';
        ?>
        <div class="wrap">
            <h1>POPIshop</h1>
            <p>Klíč propojí nativní košík POPIshopu s checkoutem tohoto WooCommerce obchodu.</p>
            <?php settings_errors(self::OPTION_SECRET); ?>
            <form method="post" action="options.php">
                <?php settings_fields('popishop_cart_handoff'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="popishop-cart-secret">Klíč předání košíku</label></th>
                        <td>
                            <input id="popishop-cart-secret" name="<?php echo esc_attr(self::OPTION_SECRET); ?>" type="password" class="regular-text" autocomplete="new-password" value="" placeholder="<?php echo $configured ? esc_attr('Klíč je uložený') : ''; ?>" />
                            <p class="description">V POPIshop administraci vygenerujte nový klíč a vložte jej sem. Nový klíč okamžitě zneplatní předchozí.</p>
                            <?php if ($configured) : ?><p><strong>Otisk klíče:</strong> <code><?php echo esc_html(substr(hash('sha256', $stored_secret), 0, 12)); ?></code></p><?php endif; ?>
                        </td>
                    </tr>
                </table>
                <?php submit_button($configured ? 'Uložit nový klíč' : 'Uložit klíč'); ?>
            </form>
        </div>
        <?php
    }

    public static function handle_handoff() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['popishop_cart_token'])) {
            return;
        }
        nocache_headers();
        $token = trim(sanitize_text_field(wp_unslash($_POST['popishop_cart_token'])));
        $secret = (string) get_option(self::OPTION_SECRET, '');
        $payload = self::verify_token($token, $secret);
        if (is_wp_error($payload)) {
            self::fail($payload->get_error_message());
        }
        if (!function_exists('WC')) {
            self::fail('WooCommerce není aktivní.');
        }
        if (function_exists('wc_load_cart') && (!WC()->session || !WC()->cart)) {
            wc_load_cart();
        }
        if (!WC()->session || !WC()->cart) {
            self::fail('Košík WooCommerce se nepodařilo inicializovat.');
        }

        $token_hash = hash('sha256', $token);
        $used_tokens = WC()->session->get(self::SESSION_USED_TOKENS, array());
        $used_tokens = is_array($used_tokens) ? array_filter($used_tokens, function($expires) {
            return (int) $expires >= time();
        }) : array();
        if (isset($used_tokens[$token_hash])) {
            wp_safe_redirect(wc_get_checkout_url());
            exit;
        }

        $validated = self::validate_items($payload['items']);
        if (is_wp_error($validated)) {
            self::fail($validated->get_error_message());
        }

        $added_keys = array();
        foreach ($validated as $item) {
            try {
                $cart_key = WC()->cart->add_to_cart(
                    $item['product_id'],
                    $item['quantity'],
                    $item['variation_id'],
                    $item['variation_attributes']
                );
            } catch (Throwable $error) {
                $cart_key = false;
            }
            if (!$cart_key) {
                foreach ($added_keys as $added_key) {
                    WC()->cart->remove_cart_item($added_key);
                }
                self::fail('Jednu z položek se nepodařilo přidat do košíku.');
            }
            $added_keys[] = $cart_key;
        }

        $used_tokens[$token_hash] = (int) $payload['exp'];
        if (count($used_tokens) > 20) {
            $used_tokens = array_slice($used_tokens, -20, null, true);
        }
        WC()->session->set(self::SESSION_USED_TOKENS, $used_tokens);
        WC()->cart->calculate_totals();
        wp_safe_redirect(wc_get_checkout_url());
        exit;
    }

    private static function verify_token($token, $secret) {
        if ($secret === '' || strlen($secret) < 40) {
            return new WP_Error('not_configured', 'Propojení POPIshopu není v tomto obchodě aktivní.');
        }
        if (strlen($token) > 4096) {
            return new WP_Error('token_too_long', 'Předání košíku je neplatné.');
        }
        $parts = explode('.', $token);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            return new WP_Error('token_invalid', 'Předání košíku je neplatné.');
        }
        $expected = self::base64url_encode(hash_hmac('sha256', $parts[0], $secret, true));
        if (!hash_equals($expected, $parts[1])) {
            return new WP_Error('signature_invalid', 'Podpis košíku se nepodařilo ověřit.');
        }
        $decoded = self::base64url_decode($parts[0]);
        $payload = $decoded === false ? null : json_decode($decoded, true);
        if (!is_array($payload) || !isset($payload['v'], $payload['exp'], $payload['nonce'], $payload['items']) || (int) $payload['v'] !== 1) {
            return new WP_Error('payload_invalid', 'Obsah košíku je neplatný.');
        }
        $now = time();
        $expires = (int) $payload['exp'];
        if ($expires < $now || $expires > $now + 600) {
            return new WP_Error('token_expired', 'Platnost předání košíku vypršela. Vraťte se prosím do obchodu.');
        }
        if (!is_string($payload['nonce']) || !preg_match('/^[A-Za-z0-9_-]{16}$/', $payload['nonce'])) {
            return new WP_Error('nonce_invalid', 'Předání košíku je neplatné.');
        }
        if (!is_array($payload['items']) || count($payload['items']) < 1 || count($payload['items']) > 20) {
            return new WP_Error('items_invalid', 'Košík neobsahuje platné položky.');
        }
        return $payload;
    }

    private static function validate_items($items) {
        $validated = array();
        foreach ($items as $line) {
            if (!is_array($line) || count($line) !== 3) {
                return new WP_Error('line_invalid', 'Položka košíku je neplatná.');
            }
            $product_id = filter_var($line[0], FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)));
            $variation_id = filter_var($line[1], FILTER_VALIDATE_INT, array('options' => array('min_range' => 0)));
            $quantity = filter_var($line[2], FILTER_VALIDATE_INT, array('options' => array('min_range' => 1, 'max_range' => 99)));
            if ($product_id === false || $variation_id === false || $quantity === false) {
                return new WP_Error('line_values_invalid', 'Položka košíku má neplatné hodnoty.');
            }
            $product = wc_get_product($product_id);
            if (!$product || !$product->is_purchasable() || !$product->is_in_stock() || !$product->has_enough_stock($quantity)) {
                return new WP_Error('product_unavailable', 'Některý produkt již není možné objednat.');
            }
            $attributes = array();
            if ($variation_id > 0) {
                $variation = wc_get_product($variation_id);
                if (!$variation || !is_a($variation, 'WC_Product_Variation') || (int) $variation->get_parent_id() !== (int) $product_id || !$variation->is_purchasable() || !$variation->is_in_stock() || !$variation->has_enough_stock($quantity)) {
                    return new WP_Error('variation_unavailable', 'Vybraná varianta již není dostupná.');
                }
                $attributes = $variation->get_variation_attributes();
            } elseif ($product->is_type('variable')) {
                return new WP_Error('variation_required', 'U produktu je nutné znovu vybrat variantu.');
            }
            $validated[] = array(
                'product_id' => (int) $product_id,
                'variation_id' => (int) $variation_id,
                'quantity' => (int) $quantity,
                'variation_attributes' => $attributes,
            );
        }
        return $validated;
    }

    private static function fail($message) {
        if (function_exists('wc_add_notice')) {
            wc_add_notice($message, 'error');
        }
        $target = function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/');
        wp_safe_redirect($target);
        exit;
    }

    private static function base64url_encode($value) {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64url_decode($value) {
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $value)) {
            return false;
        }
        $padding = strlen($value) % 4;
        if ($padding > 0) {
            $value .= str_repeat('=', 4 - $padding);
        }
        return base64_decode(strtr($value, '-_', '+/'), true);
    }
}

POPIshop_Cart_Handoff::boot();
new POPIshop_Cart_Handoff_Updater(
    POPISHOP_CART_HANDOFF_FILE,
    POPISHOP_CART_HANDOFF_UPDATE_URL,
    POPIshop_Cart_Handoff::VERSION
);
