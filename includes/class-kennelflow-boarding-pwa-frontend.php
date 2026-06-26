<?php
/**
 * Public /kennelflow-mobile endpoint (no theme, staff-only).
 *
 * @package KennelFlow_Boarding
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KennelFlow_Boarding_PWA_Frontend
 */
class KennelFlow_Boarding_PWA_Frontend {

	const QUERY_VAR = 'kf_pwa';

	/**
	 * Bump when the public rewrite rule changes. Triggers a one-time flush so
	 * /kennelflow-mobile works without visiting Settings → Permalinks.
	 */
	const REWRITE_RULES_VERSION = 1;

	/**
	 * Option key: stores REWRITE_RULES_VERSION after the last successful flush.
	 */
	const OPTION_REWRITE_RULES_VERSION = 'kennelflow_boarding_pwa_rewrite_rules_version';

	/**
	 * @var string
	 */
	const OPTION_REWRITE_RULES_VERSION_LEGACY = 'kennelpress_pwa_rewrite_rules_version';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_rewrite_rules' ), 0 );
		add_action( 'init', array( __CLASS__, 'maybe_flush_rewrite_rules' ), 20 );
		add_filter( 'query_vars', array( __CLASS__, 'register_query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_serve_pwa' ), 1 );
	}

	/**
	 * Ensure pretty URL rules are stored (fixes /kennelflow-mobile when only ?kf_pwa=1 worked).
	 *
	 * @return void
	 */
	public static function maybe_flush_rewrite_rules() {
		$stored = (int) get_option( self::OPTION_REWRITE_RULES_VERSION, get_option( self::OPTION_REWRITE_RULES_VERSION_LEGACY, 0 ) );
		if ( $stored >= self::REWRITE_RULES_VERSION ) {
			return;
		}
		self::register_rewrite_rules();
		flush_rewrite_rules();
		update_option( self::OPTION_REWRITE_RULES_VERSION, self::REWRITE_RULES_VERSION );
	}

	/**
	 * Pretty URL: /kennelflow-mobile → ?kf_pwa=1
	 *
	 * @return void
	 */
	public static function register_rewrite_rules() {
		add_rewrite_rule( '^kennelflow-mobile/?$', 'index.php?kf_pwa=1', 'top' );
	}

	/**
	 * Register public query var.
	 *
	 * @param string[] $vars Vars.
	 * @return string[]
	 */
	public static function register_query_vars( $vars ) {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/**
	 * Whether this request should load the PWA shell.
	 *
	 * @return bool
	 */
	protected static function is_pwa_request() {
		if ( 1 === (int) get_query_var( self::QUERY_VAR ) ) {
			return true;
		}
		if ( isset( $_GET['kf_pwa'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['kf_pwa'] ) ) ) {
			return true;
		}
		// When rewrites are missing or not flushed, /kennelflow-mobile may 404 before kf_pwa is set.
		if ( self::is_pwa_request_path() ) {
			return true;
		}
		return false;
	}

	/**
	 * True when the request path is /kennelflow-mobile (supports subdirectory installs).
	 *
	 * @return bool
	 */
	protected static function is_pwa_request_path() {
		if ( empty( $_SERVER['REQUEST_URI'] ) ) {
			return false;
		}
		$raw  = wp_unslash( $_SERVER['REQUEST_URI'] );
		$path = wp_parse_url( $raw, PHP_URL_PATH );
		if ( ! is_string( $path ) || '' === $path ) {
			return false;
		}
		$path      = '/' . trim( $path, '/' );
		$home_path = wp_parse_url( home_url(), PHP_URL_PATH );
		if ( is_string( $home_path ) && '' !== trim( $home_path, '/' ) ) {
			$home_path = '/' . trim( $home_path, '/' );
			if ( strpos( $path, $home_path . '/' ) === 0 ) {
				$path = substr( $path, strlen( $home_path ) );
			} elseif ( $path === $home_path ) {
				$path = '/';
			}
		}
		$path = trim( $path, '/' );
		return 'kennelflow-mobile' === $path;
	}

	/**
	 * Canonical URL for redirect_to after login.
	 *
	 * @return string
	 */
	protected static function get_pwa_url() {
		if ( get_option( 'permalink_structure' ) ) {
			return trailingslashit( home_url( '/kennelflow-mobile' ) );
		}
		return add_query_arg( self::QUERY_VAR, '1', trailingslashit( home_url( '/' ) ) );
	}

	/**
	 * template_redirect: serve minimal HTML or redirect to login.
	 *
	 * @return void
	 */
	public static function maybe_serve_pwa() {
		if ( ! self::is_pwa_request() ) {
			return;
		}

		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( self::get_pwa_url() ) );
			exit;
		}

		if ( ! KennelFlow_Boarding_PWA_Report_Admin::user_can_access() ) {
			wp_die(
				esc_html__( 'You do not have permission to access this app.', 'kennelflow-boarding' ),
				esc_html__( 'Forbidden', 'kennelflow-boarding' ),
				array( 'response' => 403 )
			);
		}

		$js_path = KENNELFLOW_BOARDING_PLUGIN_DIR . 'build/pwa-report-card.js';
		if ( ! is_readable( $js_path ) ) {
			wp_die(
				esc_html__( 'The Mobile Report Card bundle is missing. Run npm run build:pwa on the server.', 'kennelflow-boarding' ),
				esc_html__( 'Service unavailable', 'kennelflow-boarding' ),
				array( 'response' => 503 )
			);
		}

		nocache_headers();
		status_header( 200 );
		header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );

		self::output_minimal_html();
		exit;
	}

	/**
	 * Minimal HTML5 document: viewport, shell CSS, localized boot, bundle, root mount.
	 *
	 * @return void
	 */
	protected static function output_minimal_html() {
		$js_url  = KENNELFLOW_BOARDING_PLUGIN_URL . 'build/pwa-report-card.js';
		$css_url = KENNELFLOW_BOARDING_PLUGIN_URL . 'assets/css/kf-pwa-shell.css';
		$ver     = KENNELFLOW_BOARDING_VERSION;

		$boot = KennelFlow_Boarding_PWA_Report_Admin::get_pwa_boot_data();
		$json = wp_json_encode( $boot, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );
		if ( false === $json ) {
			$json = '{}';
		}

		$title = sprintf(
			/* translators: %s: site name */
			__( 'KennelFlow Mobile — %s', 'kennelflow-boarding' ),
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES )
		);

		echo '<!DOCTYPE html><html class="kf-pwa-html" ';
		language_attributes();
		echo '><head>';
		echo '<meta charset="' . esc_attr( get_bloginfo( 'charset' ) ) . '">';
		echo '<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0">';
		echo '<title>' . esc_html( $title ) . '</title>';
		// phpcs:disable WordPress.WP.EnqueuedResources -- Stand-alone staff PWA shell outside normal theme/wp_enqueue_* lifecycle.
		echo '<link rel="stylesheet" href="' . esc_url( $css_url ) . '?ver=' . esc_attr( $ver ) . '">';
		echo '</head>';
		echo '<body class="kf-pwa-body">';
		$boot_b64 = base64_encode( $json );
		echo '<div id="kf-pwa-root" data-boot="' . esc_attr( $boot_b64 ) . '"></div>';
		$boot_src = KENNELFLOW_BOARDING_PLUGIN_URL . 'assets/js/kf-pwa-shell-boot.js';
		echo '<script src="' . esc_url( $boot_src ) . '?ver=' . esc_attr( $ver ) . '"></script>';
		echo '<script src="' . esc_url( $js_url ) . '?ver=' . esc_attr( $ver ) . '" defer></script>';
		// phpcs:enable WordPress.WP.EnqueuedResources
		echo '</body></html>';
	}
}
