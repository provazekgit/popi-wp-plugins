<?php

defined( 'ABSPATH' ) || exit;

final class POPI_Connector_Admin {

	const PAGE_SLUG = 'popi-connector';
	const NOTICE_PREFIX = 'popi_connector_notice_';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_popi_connector_pair', array( __CLASS__, 'handle_pair' ) );
		add_action( 'admin_post_popi_connector_pair_refresh', array( __CLASS__, 'handle_pair_refresh' ) );
		add_action( 'admin_post_popi_connector_revoke', array( __CLASS__, 'handle_revoke' ) );
		add_action( 'admin_post_popi_connector_rotate', array( __CLASS__, 'handle_rotate' ) );
		add_action( 'admin_post_popi_connector_frontend_save', array( __CLASS__, 'handle_frontend_save' ) );
		add_action( 'admin_post_popi_connector_frontend_rollback', array( __CLASS__, 'handle_frontend_rollback' ) );
		add_action( 'admin_post_popi_connector_advanced_save', array( __CLASS__, 'handle_advanced_save' ) );
		add_action( 'admin_post_popi_connector_diagnostics', array( __CLASS__, 'handle_diagnostics' ) );
	}

	public static function menu() {
		add_menu_page(
			'POPI Connector',
			'POPI Connector',
			'view_popi_connector_status',
			self::PAGE_SLUG,
			array( __CLASS__, 'render' ),
			'dashicons-randomize',
			58
		);
	}

	public static function render() {
		if ( ! current_user_can( 'view_popi_connector_status' ) ) {
			wp_die( esc_html__( 'Nemáte oprávnění zobrazit POPI Connector.', 'popi-connector' ) );
		}
		$tabs = array(
			'overview'    => 'Přehled',
			'connection'  => 'Připojení',
			'modules'     => 'Moduly',
			'frontend'    => 'Frontend',
			'security'    => 'Zabezpečení',
			'audit'       => 'Audit',
			'diagnostics' => 'Diagnostika',
			'help'        => 'Nápověda',
			'advanced'    => 'Pokročilé',
		);
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview';
		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = 'overview';
		}
		?>
		<div class="wrap popi-connector-admin">
			<h1>POPI Connector</h1>
			<p>Bezpečné propojení tohoto WordPressu s POPIsite, POPIwebem a POPIcastem.</p>
			<?php self::render_notice(); ?>
			<nav class="nav-tab-wrapper" aria-label="Nastavení POPI Connectoru">
				<?php foreach ( $tabs as $slug => $label ) : ?>
					<a class="nav-tab <?php echo $tab === $slug ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( self::page_url( $slug ) ); ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>
			<div style="max-width:1100px;padding-top:22px">
				<?php
				$method = 'render_' . $tab;
				if ( is_callable( array( __CLASS__, $method ) ) ) {
					call_user_func( array( __CLASS__, $method ) );
				}
				?>
			</div>
		</div>
		<?php
	}

	private static function render_overview() {
		$bindings = POPI_Connector_Storage::list_bindings();
		$active   = array_filter( $bindings, function ( $binding ) { return 'active' === $binding['status']; } );
		$frontend = POPI_Connector_Frontend::settings();
		?>
		<div class="card" style="max-width:none">
			<h2>Stav</h2>
			<table class="widefat striped"><tbody>
			<tr><th>Plugin</th><td>POPI Connector <?php echo esc_html( POPI_CONNECTOR_VERSION ); ?></td></tr>
			<tr><th>Připojení</th><td><?php echo $active ? '<strong style="color:#16803a">Aktivní (' . esc_html( count( $active ) ) . ')</strong>' : '<strong style="color:#9a6700">Nepřipojeno</strong>'; ?></td></tr>
			<tr><th>Frontend</th><td><code><?php echo esc_html( $frontend['mode'] ); ?></code></td></tr>
			<tr><th>Indexace</th><td><?php echo (int) get_option( 'blog_public', 1 ) ? 'Povolená' : 'Vyhledávače jsou požádány o neindexování'; ?></td></tr>
			<tr><th>Instance webu</th><td><code><?php echo esc_html( get_option( 'popi_connector_site_instance_id' ) ); ?></code></td></tr>
			</tbody></table>
			<?php if ( ! $active ) : ?><p><a class="button button-primary" href="<?php echo esc_url( self::page_url( 'connection' ) ); ?>">Spárovat s POPIsite</a></p><?php endif; ?>
		</div>
		<?php
		self::bindings_table( $bindings, false );
	}

	private static function render_connection() {
		$pending = get_option( POPI_Connector_Pairing::CLAIM_OPTION, array() );
		?>
		<div class="card" style="max-width:none">
			<h2>Spárovat další modul</h2>
			<p>V POPIsite vyberte workspace, projekt a konkrétní instalaci modulu. Vygenerovaný jednorázový kód vložte sem. Jeden kód páruje právě jednu ModuleInstallation.</p>
			<?php if ( is_multisite() ) : ?>
				<div class="notice notice-error inline"><p>Verze 1.0 nepodporuje WordPress Multisite. Párování je z bezpečnostních důvodů zablokované.</p></div>
			<?php elseif ( ! POPI_Connector_Crypto::available() ) : ?>
				<div class="notice notice-error inline"><p>Hosting nemá potřebné OpenSSL/hash funkce. Párování nelze bezpečně provést.</p></div>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="popi_connector_pair">
					<?php wp_nonce_field( 'popi_connector_pair' ); ?>
					<label for="popi-pairing-code"><strong>Jednorázový kód</strong></label><br>
					<input id="popi-pairing-code" name="pairing_code" class="regular-text code" maxlength="24" autocomplete="one-time-code" placeholder="POPI-XXXX-XXXX-XXXX" required>
					<?php submit_button( 'Uplatnit kód', 'primary', 'submit', false ); ?>
				</form>
			<?php endif; ?>
		</div>
		<?php if ( is_array( $pending ) && ! empty( $pending['claim_id'] ) ) : ?>
		<div class="card" style="max-width:none">
			<h2>Čeká na potvrzení v POPIsite</h2>
			<p>Claim: <code><?php echo esc_html( $pending['claim_id'] ); ?></code>. Zkontrolujte v POPIsite doménu, projekt a scopes, připojení potvrďte a potom obnovte stav.</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="popi_connector_pair_refresh">
				<?php wp_nonce_field( 'popi_connector_pair_refresh' ); ?>
				<?php submit_button( 'Obnovit stav párování', 'secondary', 'submit', false ); ?>
			</form>
		</div>
		<?php endif; ?>
		<?php self::bindings_table( POPI_Connector_Storage::list_bindings(), true ); ?>
		<?php
	}

	private static function render_modules() {
		$bindings = POPI_Connector_Storage::list_bindings();
		if ( ! $bindings ) {
			echo '<div class="notice notice-info inline"><p>Nejdříve spárujte alespoň jeden modul.</p></div>';
			return;
		}
		foreach ( $bindings as $binding ) {
			$config = POPI_Connector_Storage::binding_config( $binding );
			echo '<div class="card" style="max-width:none"><h2>' . esc_html( strtoupper( $binding['module'] ) ) . '</h2>';
			echo '<p><strong>Installation:</strong> <code>' . esc_html( $binding['installation_id'] ) . '</code></p>';
			echo '<p><strong>Scopes:</strong> ';
			foreach ( POPI_Connector_Storage::binding_scopes( $binding ) as $scope ) {
				echo '<code style="margin-right:6px">' . esc_html( $scope ) . '</code>';
			}
			echo '</p><p><strong>Povolené typy obsahu:</strong> ' . esc_html( implode( ', ', isset( $config['allowed_post_types'] ) ? (array) $config['allowed_post_types'] : array() ) ) . '</p>';
			echo '</div>';
		}
	}

	private static function render_frontend() {
		if ( ! current_user_can( 'manage_popi_connector_frontend' ) ) {
			echo '<div class="notice notice-error inline"><p>Nemáte oprávnění měnit frontend.</p></div>';
			return;
		}
		$s = POPI_Connector_Frontend::settings();
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="popi_connector_frontend_save">
			<?php wp_nonce_field( 'popi_connector_frontend_save' ); ?>
			<table class="form-table" role="presentation">
			<tr><th><label for="popi-mode">Režim</label></th><td><select id="popi-mode" name="mode">
			<?php foreach ( array( 'wordpress' => 'WordPress frontend aktivní', 'noindex' => 'Pouze noindex', 'information' => 'Informační stránka', 'redirect' => 'Přesměrování', 'disabled' => 'Frontend vypnutý (404)' ) as $value => $label ) : ?>
			<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $s['mode'], $value ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?></select><p class="description">Aktivace pluginu frontend sama nemění. Každá změna zde vytvoří vratný snapshot.</p></td></tr>
			<tr><th><label for="popi-title">Titulek</label></th><td><input id="popi-title" name="title" class="regular-text" value="<?php echo esc_attr( $s['title'] ); ?>"></td></tr>
			<tr><th>Popis informační stránky</th><td><?php wp_editor( $s['description'], 'popi_connector_description', array( 'textarea_name' => 'description', 'textarea_rows' => 10, 'media_buttons' => false ) ); ?></td></tr>
			<tr><th><label for="popi-redirect">Cílová HTTPS URL</label></th><td><input id="popi-redirect" type="url" name="redirect_url" class="regular-text" value="<?php echo esc_attr( $s['redirect_url'] ); ?>" placeholder="https://www.example.cz/"></td></tr>
			<tr><th><label for="popi-status">HTTP status</label></th><td><select id="popi-status" name="redirect_status"><?php foreach ( array( 302, 307, 301, 308 ) as $status ) : ?><option value="<?php echo esc_attr( $status ); ?>" <?php selected( (int) $s['redirect_status'], $status ); ?>><?php echo esc_html( $status ); ?></option><?php endforeach; ?></select> <label><input type="checkbox" name="confirm_permanent" value="1"> Potvrzuji permanentní redirect 301/308</label></td></tr>
			<tr><th>Vzdálená správa</th><td><label><input type="checkbox" name="allow_remote_manage" value="1" <?php checked( ! empty( $s['allow_remote_manage'] ) ); ?>> POPIsite smí měnit frontend v rámci ověřeného projektu</label></td></tr>
			</table>
			<?php submit_button( 'Uložit frontend' ); ?>
		</form>
		<?php if ( false !== get_option( POPI_Connector_Frontend::SNAPSHOT_OPTION, false ) ) : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Obnovit původní stav před správou POPI Connectorem?');">
			<input type="hidden" name="action" value="popi_connector_frontend_rollback"><?php wp_nonce_field( 'popi_connector_frontend_rollback' ); ?><?php submit_button( 'Obnovit původní frontend', 'secondary', 'submit', false ); ?>
		</form>
		<?php endif; ?>
		<?php
	}

	private static function render_security() {
		global $wpdb;
		$tables = POPI_Connector_Storage::tables();
		$keys = $wpdb->get_results( "SELECT binding_id,key_id,status,not_before,expires_at,created_at FROM {$tables['keys']} ORDER BY id DESC", ARRAY_A );
		?>
		<h2>Klíče</h2><table class="widefat striped"><thead><tr><th>Binding</th><th>Key ID / otisk</th><th>Stav</th><th>Platnost</th></tr></thead><tbody>
		<?php foreach ( $keys as $key ) : ?><tr><td><code><?php echo esc_html( $key['binding_id'] ); ?></code></td><td><code><?php echo esc_html( $key['key_id'] ); ?></code><br><small><?php echo esc_html( substr( hash( 'sha256', $key['key_id'] ), 0, 12 ) ); ?></small></td><td><?php echo esc_html( $key['status'] ); ?></td><td><?php echo esc_html( $key['expires_at'] ? $key['expires_at'] : 'bez expirace' ); ?></td></tr><?php endforeach; ?>
		<?php if ( ! $keys ) : ?><tr><td colspan="4">Žádné klíče.</td></tr><?php endif; ?></tbody></table>
		<p>Secrets se v administraci ani API nikdy nezobrazují. Rotace používá nový key ID a pětiminutové překryvné okno.</p>
		<?php
	}

	private static function render_audit() {
		if ( ! current_user_can( 'view_popi_connector_audit' ) ) {
			echo '<div class="notice notice-error inline"><p>Nemáte oprávnění zobrazit audit.</p></div>'; return;
		}
		$rows = POPI_Connector_Audit::recent( 200 );
		?><table class="widefat striped"><thead><tr><th>Čas UTC</th><th>Událost</th><th>Výsledek</th><th>Binding / request</th><th>Metadata</th></tr></thead><tbody>
		<?php foreach ( $rows as $row ) : ?><tr><td><?php echo esc_html( $row['created_at'] ); ?></td><td><code><?php echo esc_html( $row['event'] ); ?></code></td><td><?php echo esc_html( $row['result'] ); ?></td><td><small><?php echo esc_html( $row['binding_id'] ); ?><br><?php echo esc_html( $row['request_id'] ); ?></small></td><td><code><?php echo esc_html( $row['metadata'] ); ?></code></td></tr><?php endforeach; ?>
		<?php if ( ! $rows ) : ?><tr><td colspan="5">Audit je zatím prázdný.</td></tr><?php endif; ?></tbody></table><?php
	}

	private static function render_diagnostics() {
		$tables = POPI_Connector_Storage::tables(); global $wpdb;
		$checks = array(
			'HTTPS webu' => 0 === strpos( home_url( '/' ), 'https://' ),
			'HTTPS REST API' => 0 === strpos( rest_url(), 'https://' ),
			'Kryptografie' => POPI_Connector_Crypto::available(),
			'WordPress Multisite vypnutý' => ! is_multisite(),
			'Cron naplánovaný' => (bool) wp_next_scheduled( POPI_Connector_Installer::CRON_HOOK ),
		);
		foreach ( $tables as $name => $table ) { $checks[ 'Tabulka ' . $name ] = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table; }
		?><table class="widefat striped"><tbody><?php foreach ( $checks as $label => $ok ) : ?><tr><th><?php echo esc_html( $label ); ?></th><td><?php echo $ok ? '<span style="color:#16803a">✓ OK</span>' : '<span style="color:#b42318">✕ Chyba</span>'; ?></td></tr><?php endforeach; ?><tr><th>Serverový čas UTC</th><td><code><?php echo esc_html( gmdate( DATE_ATOM ) ); ?></code></td></tr><tr><th>API POPIsite</th><td><code><?php echo esc_html( POPI_Connector_Remote::api_base() ); ?></code></td></tr></tbody></table>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="popi_connector_diagnostics"><?php wp_nonce_field( 'popi_connector_diagnostics' ); ?><?php submit_button( 'Otestovat odchozí spojení', 'secondary', 'submit', false ); ?></form><?php
	}

	private static function render_help() {
		?>
		<h2>Požadavky</h2><ul><li>WordPress 6.2 nebo novější, PHP 7.4 nebo novější.</li><li>HTTPS pro WordPress i POPIsite.</li><li>OpenSSL, HMAC, HKDF a bezpečný generátor náhodných hodnot.</li><li>Žádný další plugin není povinný. ACF je volitelný; jeho meta klíče musí být výslovně povolené bindingem.</li><li>WordPress Multisite není ve verzi 1.0 podporovaný.</li></ul>
		<h2>Rychlý start</h2><ol><li>V POPIsite vyberte workspace, projekt a instalaci POPIwebu nebo POPIcastu.</li><li>Vygenerujte jednorázový kód a vložte jej do záložky Připojení.</li><li>V POPIsite zkontrolujte nahlášenou doménu a scopes a claim potvrďte.</li><li>Ve WordPressu klikněte na Obnovit stav párování.</li><li>V Diagnostice ověřte zelené kontroly.</li><li>Frontend přepněte až jako samostatný, vratný krok.</li></ol>
		<h2>Pro vývoj a design</h2><p>Plugin nevytváří CPT ani šablony produktu. POPIweb a POPIcast čtou pouze post types a meta keys povolené v binding contractu. Informační stránka je nezávislá na tématu a nepouští shortcodes. Bricks, Beaver Builder ani ACF nejsou pro konektor povinné.</p>
		<h2>Řešení problémů</h2><p>Při rozdílu času nad pět minut se podepsané requesty odmítnou. Po změně WordPress salts nebo obnovení databáze na jiné doméně proveďte nové párování a starý binding v POPIsite odvolejte.</p>
		<?php
	}

	private static function render_advanced() {
		if ( ! current_user_can( 'manage_popi_connector' ) ) { echo '<p>Nemáte oprávnění.</p>'; return; }
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="popi_connector_advanced_save"><?php wp_nonce_field( 'popi_connector_advanced_save' ); ?><table class="form-table"><tr><th><label for="popi-api-base">POPIsite API</label></th><td><input id="popi-api-base" name="api_base" type="url" class="regular-text" value="<?php echo esc_attr( POPI_Connector_Remote::api_base() ); ?>"><p class="description">Produkce přijímá pouze https://api.popisite.cz. Vlastní HTTPS endpoint lze povolit jen explicitní konstantou POPI_CONNECTOR_ALLOW_CUSTOM_API_BASE ve wp-config.php.</p></td></tr></table><?php submit_button(); ?></form>
		<?php
	}

	private static function bindings_table( $bindings, $actions ) {
		?><h2>Připojené instalace</h2><table class="widefat striped"><thead><tr><th>Modul</th><th>Workspace / projekt</th><th>Installation / connection</th><th>Stav</th><th>Poslední spojení</th><?php if ( $actions ) : ?><th>Akce</th><?php endif; ?></tr></thead><tbody>
		<?php foreach ( $bindings as $binding ) : ?><tr><td><strong><?php echo esc_html( strtoupper( $binding['module'] ) ); ?></strong></td><td><code><?php echo esc_html( $binding['tenant_id'] ); ?></code><br><code><?php echo esc_html( $binding['project_id'] ); ?></code></td><td><code><?php echo esc_html( $binding['installation_id'] ); ?></code><br><code><?php echo esc_html( $binding['connection_id'] ); ?></code></td><td><?php echo esc_html( $binding['status'] ); ?></td><td><?php echo esc_html( $binding['last_seen_at'] ? $binding['last_seen_at'] . ' UTC' : 'zatím ne' ); ?></td><?php if ( $actions ) : ?><td>
		<?php if ( 'active' === $binding['status'] && current_user_can( 'rotate_popi_connector_keys' ) ) : ?><form style="display:inline" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="popi_connector_rotate"><input type="hidden" name="binding_id" value="<?php echo esc_attr( $binding['binding_id'] ); ?>"><?php wp_nonce_field( 'popi_connector_rotate_' . $binding['binding_id'] ); ?><button class="button">Rotovat klíč</button></form><?php endif; ?>
		<?php if ( 'active' === $binding['status'] && current_user_can( 'revoke_popi_connector' ) ) : ?><form style="display:inline" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Opravdu odvolat toto připojení?');"><input type="hidden" name="action" value="popi_connector_revoke"><input type="hidden" name="binding_id" value="<?php echo esc_attr( $binding['binding_id'] ); ?>"><?php wp_nonce_field( 'popi_connector_revoke_' . $binding['binding_id'] ); ?><button class="button button-link-delete">Odvolat</button></form><?php endif; ?>
		</td><?php endif; ?></tr><?php endforeach; ?><?php if ( ! $bindings ) : ?><tr><td colspan="6">Žádné připojení.</td></tr><?php endif; ?></tbody></table><?php
	}

	public static function handle_pair() {
		self::guard( 'pair_popi_connector', 'popi_connector_pair' );
		if ( is_multisite() ) { self::redirect_result( 'connection', new WP_Error( 'multisite_unsupported', 'WordPress Multisite není podporovaný.' ) ); }
		$result = POPI_Connector_Pairing::claim( isset( $_POST['pairing_code'] ) ? wp_unslash( $_POST['pairing_code'] ) : '' );
		self::redirect_result( 'connection', $result, 'Kód byl uplatněn. Připojení nyní potvrďte v POPIsite.' );
	}

	public static function handle_pair_refresh() { self::guard( 'pair_popi_connector', 'popi_connector_pair_refresh' ); self::redirect_result( 'connection', POPI_Connector_Pairing::refresh(), 'Připojení je aktivní.' ); }
	public static function handle_revoke() { $id = isset( $_POST['binding_id'] ) ? sanitize_text_field( wp_unslash( $_POST['binding_id'] ) ) : ''; self::guard( 'revoke_popi_connector', 'popi_connector_revoke_' . $id ); self::redirect_result( 'connection', POPI_Connector_Pairing::revoke( $id ), 'Připojení bylo odvoláno.' ); }
	public static function handle_rotate() { $id = isset( $_POST['binding_id'] ) ? sanitize_text_field( wp_unslash( $_POST['binding_id'] ) ) : ''; self::guard( 'rotate_popi_connector_keys', 'popi_connector_rotate_' . $id ); self::redirect_result( 'security', POPI_Connector_Pairing::rotate( $id ), 'Klíč byl bezpečně rotován.' ); }

	public static function handle_frontend_save() {
		self::guard( 'manage_popi_connector_frontend', 'popi_connector_frontend_save' );
		$input = array(
			'mode' => isset( $_POST['mode'] ) ? wp_unslash( $_POST['mode'] ) : '', 'title' => isset( $_POST['title'] ) ? wp_unslash( $_POST['title'] ) : '', 'description' => isset( $_POST['description'] ) ? wp_unslash( $_POST['description'] ) : '', 'redirect_url' => isset( $_POST['redirect_url'] ) ? wp_unslash( $_POST['redirect_url'] ) : '', 'redirect_status' => isset( $_POST['redirect_status'] ) ? wp_unslash( $_POST['redirect_status'] ) : 302, 'confirm_permanent' => ! empty( $_POST['confirm_permanent'] ), 'allow_remote_manage' => ! empty( $_POST['allow_remote_manage'] ),
		);
		self::redirect_result( 'frontend', POPI_Connector_Frontend::apply( $input, 'user', get_current_user_id() ), 'Frontend byl uložen.' );
	}

	public static function handle_frontend_rollback() { self::guard( 'manage_popi_connector_frontend', 'popi_connector_frontend_rollback' ); self::redirect_result( 'frontend', POPI_Connector_Frontend::rollback( get_current_user_id() ), 'Původní frontend byl obnoven.' ); }

	public static function handle_advanced_save() {
		self::guard( 'manage_popi_connector', 'popi_connector_advanced_save' );
		$url = isset( $_POST['api_base'] ) ? untrailingslashit( esc_url_raw( wp_unslash( $_POST['api_base'] ), array( 'https' ) ) ) : '';
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		if ( ! $url || 0 !== strpos( $url, 'https://' ) || ( 'api.popisite.cz' !== $host && ( ! defined( 'POPI_CONNECTOR_ALLOW_CUSTOM_API_BASE' ) || ! POPI_CONNECTOR_ALLOW_CUSTOM_API_BASE ) ) ) { self::redirect_result( 'advanced', new WP_Error( 'api_url_invalid', 'API musí být https://api.popisite.cz; vlastní staging endpoint vyžaduje explicitní konstantu ve wp-config.php.' ) ); }
		update_option( 'popi_connector_api_base', $url, false ); POPI_Connector_Audit::record( 'settings.api_base_changed', 'success', array( 'actor_type' => 'user', 'actor_id' => get_current_user_id(), 'metadata' => array( 'host' => wp_parse_url( $url, PHP_URL_HOST ) ) ) ); self::redirect_result( 'advanced', true, 'API adresa byla uložena.' );
	}

	public static function handle_diagnostics() {
		self::guard( 'view_popi_connector_status', 'popi_connector_diagnostics' );
		$response = wp_safe_remote_get( POPI_Connector_Remote::api_base() . '/health', array( 'timeout' => 10, 'redirection' => 0, 'sslverify' => true ) );
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) { self::redirect_result( 'diagnostics', new WP_Error( 'diagnostic_failed', is_wp_error( $response ) ? $response->get_error_message() : 'POPIsite health endpoint nevrátil HTTP 200.' ) ); }
		self::redirect_result( 'diagnostics', true, 'Odchozí HTTPS spojení s POPIsite funguje.' );
	}

	private static function guard( $capability, $nonce_action ) { if ( ! current_user_can( $capability ) ) { wp_die( 'Nemáte oprávnění.' ); } check_admin_referer( $nonce_action ); }
	private static function redirect_result( $tab, $result, $success = 'Hotovo.' ) { $error = is_wp_error( $result ); self::set_notice( $error ? $result->get_error_message() : $success, $error ? 'error' : 'success' ); wp_safe_redirect( self::page_url( $tab ) ); exit; }
	private static function set_notice( $message, $type ) { set_transient( self::NOTICE_PREFIX . get_current_user_id(), array( 'message' => sanitize_text_field( $message ), 'type' => sanitize_key( $type ) ), 60 ); }
	private static function render_notice() { $key = self::NOTICE_PREFIX . get_current_user_id(); $notice = get_transient( $key ); delete_transient( $key ); if ( is_array( $notice ) && ! empty( $notice['message'] ) ) { echo '<div class="notice notice-' . esc_attr( 'error' === $notice['type'] ? 'error' : 'success' ) . ' is-dismissible"><p>' . esc_html( $notice['message'] ) . '</p></div>'; } }
	private static function page_url( $tab ) { return add_query_arg( array( 'page' => self::PAGE_SLUG, 'tab' => $tab ), admin_url( 'admin.php' ) ); }
}
