<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://www.subscribepro.com/
 * @since      1.0.0
 *
 * @package    Spro
 * @subpackage Spro/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Spro
 * @subpackage Spro/includes
 * @author     Brady Christopher <brady.christopher@toptal.com>
 */
class Spro {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Spro_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'SPRO_VERSION' ) ) {
			$this->version = SPRO_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'spro';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();

	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Spro_Loader. Orchestrates the hooks of the plugin.
	 * - Spro_i18n. Defines internationalization functionality.
	 * - Spro_Admin. Defines all hooks for the admin area.
	 * - Spro_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-spro-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-spro-i18n.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-spro-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-spro-public.php';

		/**
		 * Custom Template Loader
		 */
		if( ! class_exists( 'Gamajo_Template_Loader' ) ) {
			require plugin_dir_path( dirname( __FILE__ ) ). 'includes/lib/class-gamajo-template-loader.php';
		}

		require plugin_dir_path( dirname( __FILE__ ) ) . 'includes/lib/class-custom-template-loader.php';

		/**
		* Composer Autoloader
		*/
	   	require_once plugin_dir_path( dirname( __FILE__ ) ) . 'vendor/autoload.php';

		$this->loader = new Spro_Loader();

	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Spro_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new Spro_i18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

		$plugin_admin = new Spro_Admin( $this->get_plugin_name(), $this->get_version() );

		// Add Subscribe Pro Tab to WooCommerce products with a checkbox
		$this->loader->add_filter( 'woocommerce_product_data_tabs', $plugin_admin, 'spro_tab' );
		$this->loader->add_action( 'woocommerce_product_data_panels', $plugin_admin, 'spro_subscription_options_product_tab_content' );
		$this->loader->add_action( 'woocommerce_process_product_meta', $plugin_admin, 'spro_save_subscription_options_field' );

		// Add custom fields to the user edit screen
		$this->loader->add_action( 'show_user_profile', $plugin_admin, 'spro_extra_user_profile_fields' );
		$this->loader->add_action( 'edit_user_profile', $plugin_admin, 'spro_extra_user_profile_fields' );

		// Save the custom fields on the user edit screen
		$this->loader->add_action( 'personal_options_update', $plugin_admin, 'spro_save_extra_user_profile_fields' );
		$this->loader->add_action( 'edit_user_profile_update', $plugin_admin, 'spro_save_extra_user_profile_fields' );

		// Save/Update our plugin options
		$this->loader->add_action( 'admin_init', $plugin_admin, 'options_update' );

		// Add menu item
		$this->loader->add_action( 'admin_menu', $plugin_admin, 'add_plugin_admin_menu' );
	
		// Add Settings link to the plugin
		$plugin_basename = plugin_basename( plugin_dir_path( __DIR__ ) . $this->plugin_name . '.php' );

		// AJAX Handler for Test Connection Button
		$this->loader->add_action( 'wp_ajax_nopriv_test_connection', $plugin_admin, 'spro_test_connection' );
		$this->loader->add_action( 'wp_ajax_test_connection', $plugin_admin, 'spro_test_connection' );
		
		$this->loader->add_action( 'wp_ajax_nopriv_save_connection_credentials', $plugin_admin, 'spro_save_connection_credentials' );
		$this->loader->add_action( 'wp_ajax_save_connection_credentials', $plugin_admin, 'spro_save_connection_credentials' );

		// Clear Product Cache and Create SP Product on Save
		$this->loader->add_action( 'save_post', $plugin_admin, 'spro_update_product_on_save', 10, 3 );
		$this->loader->add_action( 'admin_notices', $plugin_admin, 'spro_admin_notices' );

		// Add Bulk Edit Fields
		$this->loader->add_action( 'woocommerce_product_bulk_edit_start', $plugin_admin, 'spro_bulk_edit_fields' );
		$this->loader->add_action( 'woocommerce_product_bulk_edit_save', $plugin_admin, 'spro_bulk_edit_save' );

		// Product Sync Update
		// $this->loader->add_action( 'init', $plugin_admin, 'update_sp_product' );

	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {

		$plugin_public = new Spro_Public( $this->get_plugin_name(), $this->get_version() );

		// Enqueue Scripts
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );

		// Add the endpoint for the subscriptions page within WooCommerce My Account
		$this->loader->add_action( 'init', $plugin_public, 'spro_add_endpoints' );
		$this->loader->add_filter( 'woocommerce_account_menu_items', $plugin_public, 'spro_subscriptions_tab' );
		$this->loader->add_filter( 'woocommerce_account_menu_items', $plugin_public, 'spro_subscriptions_tab' );
		$this->loader->add_filter( 'woocommerce_account_subscriptions_endpoint', $plugin_public, 'spro_render_subscriptions_tab' );

		// Add custom options to Subscribe Pro products
		$this->loader->add_action( 'woocommerce_before_add_to_cart_button', $plugin_public, 'spro_before_add_to_cart_btn' );
		$this->loader->add_filter( 'woocommerce_add_to_cart_validation', $plugin_public, 'spro_validate_custom_field', 10, 3 );
		$this->loader->add_filter( 'woocommerce_add_cart_item_data', $plugin_public, 'spro_add_custom_field_item_data', 10, 4 );
		$this->loader->add_action( 'woocommerce_checkout_create_order_line_item', $plugin_public, 'spro_add_custom_data_to_order', 10, 4 );
		$this->loader->add_action( 'woocommerce_cart_calculate_fees', $plugin_public, 'spro_apply_discount', 10, 1 );
		$this->loader->add_action( 'template_redirect', $plugin_public, 'spro_checkout_redirect', 10, 1 );
		$this->loader->add_action( 'woocommerce_update_cart_action_cart_updated', $plugin_public, 'spro_cart_updated', 10, 1 );
		
		// Remove unwanted line items from WooCommerce Emails
		$this->loader->add_filter( 'woocommerce_order_item_get_formatted_meta_data', $plugin_public, 'spro_unset_email_data', 10, 2 );

		// WooCommerce Payment Complete
		$this->loader->add_action( 'woocommerce_thankyou', $plugin_public, 'spro_payment_complete' );

		// Create Order Endpoint
		$this->loader->add_action( 'rest_api_init', $plugin_public, 'spro_rest_init' );
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Spro_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

}
