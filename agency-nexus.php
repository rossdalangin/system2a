<?php
/**
 * Plugin Name: Agency Nexus
 * Description: A comprehensive WordPress plugin for freelancers and agency owners.
 * Version: 1.0.0
 * Author: Jules
 * Text Domain: agency-nexus
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define constants
define( 'AGENCY_NEXUS_VERSION', '1.0.0' );
define( 'AGENCY_NEXUS_PATH', plugin_dir_path( __FILE__ ) );
define( 'AGENCY_NEXUS_URL', plugin_dir_url( __FILE__ ) );

/**
 * Main Agency Nexus Class
 */
class Agency_Nexus {

	/**
	 * Simple symmetric encryption helper.
	 */
	public static function encrypt( $value ) {
		if ( ! get_option( 'an_encrypt_notes' ) ) return $value;
		$key = defined( 'AUTH_SALT' ) ? AUTH_SALT : 'agency-nexus-default-key';
		$method = 'aes-256-cbc';
		$ivlen = openssl_cipher_iv_length($method);
		$iv = openssl_random_pseudo_bytes($ivlen);
		$ciphertext_raw = openssl_encrypt($value, $method, $key, $options=OPENSSL_RAW_DATA, $iv);
		$hmac = hash_hmac('sha256', $ciphertext_raw, $key, $as_binary=true);
		return base64_encode( $iv.$hmac.$ciphertext_raw );
	}

	/**
	 * Simple symmetric decryption helper.
	 */
	public static function decrypt( $ciphertext ) {
		if ( empty($ciphertext) ) return $ciphertext;
		$c = base64_decode($ciphertext, true);
		if ( false === $c ) return $ciphertext; // Not base64

		$key = defined( 'AUTH_SALT' ) ? AUTH_SALT : 'agency-nexus-default-key';
		$c = base64_decode($ciphertext);
		$method = 'aes-256-cbc';
		$ivlen = openssl_cipher_iv_length($method);
		$iv = substr($c, 0, $ivlen);
		$hmac = substr($c, $ivlen, $sha2len=32);
		$ciphertext_raw = substr($c, $ivlen+$sha2len);
		$original_plaintext = openssl_decrypt($ciphertext_raw, $method, $key, $options=OPENSSL_RAW_DATA, $iv);
		$calcmac = hash_hmac('sha256', $ciphertext_raw, $key, $as_binary=true);
		if (hash_equals($hmac, $calcmac)) return $original_plaintext;
		return $ciphertext; // Fallback
	}

	/**
	 * Instance of this class.
	 * @var Agency_Nexus
	 */
	private static $instance = null;

	/**
	 * Loaded modules.
	 * @var array
	 */
	public $modules = [];

	/**
	 * Return an instance of this class.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->includes();
		$this->init_hooks();
	}

	/**
	 * Include required files.
	 */
	private function includes() {
		require_once AGENCY_NEXUS_PATH . 'includes/class-db-manager.php';
		require_once AGENCY_NEXUS_PATH . 'includes/class-permissions.php';
		require_once AGENCY_NEXUS_PATH . 'includes/class-seeder.php';
		require_once AGENCY_NEXUS_PATH . 'includes/class-license-manager.php';
		require_once AGENCY_NEXUS_PATH . 'includes/class-base-module.php';
		require_once AGENCY_NEXUS_PATH . 'includes/class-ai-copilot.php';
		require_once AGENCY_NEXUS_PATH . 'includes/class-api-handler.php';
		require_once AGENCY_NEXUS_PATH . 'includes/class-email-handler.php';
		require_once AGENCY_NEXUS_PATH . 'includes/class-shortcodes.php';

		if ( is_admin() ) {
			require_once AGENCY_NEXUS_PATH . 'admin/class-admin-dashboard.php';
		}
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		register_activation_hook( __FILE__, [ 'Agency_Nexus_DB_Manager', 'create_tables' ] );
		add_action( 'plugins_loaded', [ $this, 'init_plugin' ] );
	}

	/**
	 * Initialize plugin components.
	 */
	public function init_plugin() {
		// Initialize DB Manager
		Agency_Nexus_DB_Manager::get_instance();

		// Initialize Permissions
		Agency_Nexus_Permissions::init();

		// Initialize Admin Dashboard
		if ( is_admin() ) {
			Agency_Nexus_Admin_Dashboard::get_instance();
		}

		// Initialize API Handler
		Agency_Nexus_API_Handler::get_instance();

		// Initialize Email Handler
		Agency_Nexus_Email_Handler::get_instance();

		// Initialize Shortcodes
		Agency_Nexus_Shortcodes::get_instance();

		// Load modules after core components are ready
		$this->load_modules();
	}

	/**
	 * Load modules from the modules directory.
	 */
	private function load_modules() {
		$modules_path = AGENCY_NEXUS_PATH . 'modules/';
		$module_dirs = array_filter( glob( $modules_path . '*' ), 'is_dir' );

		foreach ( $module_dirs as $dir ) {
			$module_name = basename( $dir );
			$module_file = $dir . '/' . $module_name . '.php';

			if ( file_exists( $module_file ) ) {
				require_once $module_file;

				// Assuming class name is Agency_Nexus_Module_{Name}
				$class_name = 'Agency_Nexus_Module_' . str_replace( ' ', '_', ucwords( str_replace( '-', ' ', $module_name ) ) );
				if ( class_exists( $class_name ) ) {
					$module = new $class_name();
					$module->init();
					$this->modules[ $module_name ] = $module;
				}
			}
		}
	}
}

/**
 * Initialize the plugin.
 */
function Agency_Nexus() {
	return Agency_Nexus::get_instance();
}

Agency_Nexus();
