<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://www.subscribepro.com/
 * @since      1.0.0
 *
 * @package    Spro
 * @subpackage Spro/admin
 */

use GuzzleHttp\Client;
use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\RequestException;

class Spro_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Add Subscribe Pro Tab to WooCommerce Products
	 * 
	 * @since    1.0.0
	 * @param    array	$tabs	array of tab data
	 */
	public function spro_tab( $tabs ) {

		// Key should be exactly the same as in the class product_type
		$tabs['subscription'] = array(
			'label'	 => __( 'Subscribe Pro', 'spro' ),
			'target' => 'subscription_options',
			'class'  => ('show_if_spro_subscription'),
		);

		return $tabs;
	}


	/**
	 * Add Custom Fields to the Subscribe Pro Tab on WooCommerce Products
	 * 
	 * @since    1.0.0
	 */
	public function spro_subscription_options_product_tab_content() {
	
		?>
		<div id='subscription_options' class='panel woocommerce_options_panel'>
			<div class='options_group'>
				<?php
				woocommerce_wp_checkbox( array(
					'id'          => '_spro_product',
					'label'       => __( 'Subscription Product?', 'spro' ),
					'desc_tip'    => 'true',
					'description' => __( 'Check this box if the product should be connected to Subscribe Pro.', 'spro' ),
				));
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Save the custom field data from the Subscribe Pro tab
	 * 
	 * @since    1.0.0
	 * @param    integer $post_id	The product ID being saved
	 */
	public function spro_save_subscription_options_field( $post_id ) {

		$spro_checkbox = isset( $_POST['_spro_product'] ) ? 'yes' : 'no';

		update_post_meta( $post_id, '_spro_product', $spro_checkbox );
	
	}

	/**
	 * Add the Subscribe Pro Customer Fields to the user edit screen
	 * 
	 * @since    1.0.0
	 * @param    object	$user	The user object that is being edited
	 */
	public function spro_extra_user_profile_fields( $user ) {
		?>
		
		<h3><?php _e("Subscribe Pro Information", "spro"); ?></h3>
	
		<table class="form-table">
			<tr>
				<th><label for="spro_id"><?php _e("Subscribe Pro Customer ID", 'spro'); ?></label></th>
				<td>
					<input type="text" name="spro_id" id="spro_id" value="<?php echo esc_attr( get_the_author_meta( 'spro_id', $user->ID ) ); ?>" class="regular-text" /><br />
				</td>
			</tr>
		</table>

		<?php

	}

	/**
	 * Save the custom field data from the Subscribe Pro tab
	 * 
	 * @since    1.0.0
	 * @param    integer $user_id	The user ID being saved
	 */
	public function spro_save_extra_user_profile_fields( $user_id ) {

		if ( empty( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'update-user_' . $user_id ) ) {
			return;
		}
		
		if ( !current_user_can( 'edit_user', $user_id ) ) { 
			return false; 
		}

		update_user_meta( $user_id, 'spro_id', $_POST['spro_id'] );
	
	}

	/**
	 * Register the administration menu for this plugin into the WordPress Dashboard menu.
	 *
	 * @since    1.0.0
	 */
	public function add_plugin_admin_menu() {

		/**
		 * Add a settings page for this plugin to the Settings menu.
		 *
		 * @link https://codex.wordpress.org/Function_Reference/add_options_page
		 *
		 */
		add_submenu_page( 'woocommerce', 'Subscribe Pro Settings', 'Subscribe Pro', 'manage_options', 'spro_settings', array( $this, 'display_plugin_setup_page' ) );

	}

	/**
	 * Add settings action link to the plugins page.
	 *
	 * @since    1.0.0
	 */
	public function add_action_links( $links ) {

		/**
		 * Documentation : https://codex.wordpress.org/Plugin_API/Filter_Reference/plugin_action_links_(plugin_file_name)
		 * The "plugins.php" must match with the previously added add_submenu_page first option.
		 */
		$settings_link = array( '<a href="' . admin_url( 'woocommerce?page=spro_settings' ) . '">' . __( 'Settings', 'spro_settings' ) . '</a>', );

		return array_merge(  $settings_link, $links );

	}

	/**
	 * Render the settings page for this plugin.
	 *
	 * @since    1.0.0
	 */
	public function display_plugin_setup_page() {

		$templates = new Spro_Template_Loader;
	
		ob_start();

		$templates->get_template_part( 'admin/content', 'settings' );

		echo ob_get_clean();

	}

	public function options_update() {

		register_setting( 'spro_settings', 'spro_settings', array(
			'sanitize_callback' => array( $this, 'validate' ),
		) );

	}

	/**
	 * Save Connection Credentials
	 */
	public function spro_save_connection_credentials() {

		$name = isset( $_POST['name'] ) ? $_POST['name'] : '';
		$val = isset( $_POST['val'] ) ? $_POST['val'] : '';

		update_option( 'spro_settings_' . $name, $val );

		wp_send_json_success( json_encode( array( 'name' => $name, 'val' => get_option( 'spro_settings_' . $name ) ) ) );

	}
	
	/**
	 * Test API Connection
	 */
	public function spro_test_connection() {

		$client = new Client();	
		
		$data = array(
			'grant_type' => 'client_credentials',
			'client_id' => SPRO_CLIENT_ID,
			'client_secret' => SPRO_CLIENT_SECRET
		);

		try {

			$response = $client->request(
				'GET',
				SPRO_BASE_URL . '/oauth/v2/token',
				[
				'verify' => false,
				'query' => http_build_query($data)
				]
			);
			
		} catch (\Throwable $t) {
			wp_send_json_success($t);
		}

		wp_send_json_success( 'success' );

	}

	/**
	 * Clears Product Data Cache on Product Save and Creates Subscribe Pro Product if it doesn't exist
	 * 
	 * @since 1.0.0
	 */
	public function spro_update_product_on_save( $post_id, $post, $update ) {
		
		if ( $post->post_status == 'auto-draft' || $post->post_status == 'draft' || $post->post_type != 'product' ) {
			return;
		}

		$spro_product = get_post_meta( $post_id, '_spro_product', true);

		if ( $spro_product == 'yes' ) {

			// Clear product cache
			delete_transient( $post_id . '_spro_product' );

			// Check if product exists within Subscribe Pro
			$product = wc_get_product( $post_id );

			if ( isset( $_POST['_sku'] ) ) {
				$sku = $_POST['_sku'];
			}

			// Check if product exists in Subscribe Pro and create if it doesn't
			$exists = $this->spro_product_exists( $sku );

			// Create new product or update product
			if ( $exists != false ) {

				$name = get_the_title( $post_id );
				$price = $product->get_price();

				$this->spro_update_product( $name, $price, $exists );
			
			} else {
				$this->spro_create_product( $post_id );
			}

		}

	}

	// public function update_sp_product() {

	// 	$current_user = wp_get_current_user();
	// 	$login = $current_user->user_login;

	// 	if ( $login === 'grayson' ) {

	// 		$args = array(
	// 			'posts_per_page' => 10,
	// 			'paged' => 16,
	// 			'post_type' => 'product',
	// 			'meta_query' => array(
	// 				array (
	// 					'key'     => '_spro_product',
	// 					'value'   => true,
	// 					'compare' => '='
	// 				)
	// 			),
	// 		);
			
	// 		$products = wc_get_products( $args );
	
	// 		foreach( $products as $product ) {
	
	// 			// Clear product cache
	// 			delete_transient( $product->get_id() . '_spro_product' );
	
	// 			// Check if product exists within Subscribe Pro
	// 			$product = wc_get_product( $product->get_id() );

	// 			$sku = $product->get_sku();

	// 			// Check if product exists in Subscribe Pro and create if it doesn't
	// 			$exists = $this->spro_product_exists( $sku );
	
	// 			// Create new product or update product
	// 			if ( $exists != false ) {
	
	// 				$name = get_the_title( $product->get_id() );
	// 				$price = $product->get_price();
	
	// 				$this->spro_update_product( $name, $price, $exists );
	
	// 				echo 'updated product ' . $product->get_id() . '<br>';
				
	// 			} else {
	// 				$this->spro_create_product( $product->get_id() );
	// 				echo 'created product ' . $product->get_id() . '<br>';
	
	// 			}
	// 		}
	// 	}

	// }


	/**
	 * Retrieves Access Token
	 * 
	 * @since 1.0.0
	 */
	public function spro_get_access_token() {

		// delete_transient( 'spro_access_token' );

		if ( false === ( $value = get_transient( 'spro_access_token' ) ) ) {
			
			$client = new Client();

			$data = array(
				'grant_type' => 'client_credentials',
				'scope' => 'client',
			);

			try {
				
				$response = $client->request(
					'GET',
					SPRO_BASE_URL . '/oauth/v2/token',
					[
					'auth' => [SPRO_CLIENT_ID, SPRO_CLIENT_SECRET],
					'verify' => false,
					'query' => http_build_query($data)
					]
				);

			} catch (RequestException $e) {
				echo Psr7\Message::toString($e->getRequest());
				if ($e->hasResponse()) {
					echo Psr7\Message::toString($e->getResponse());
				}
			}

			$access_token = json_decode( $response->getBody() )->access_token;
	
			set_transient( 'spro_access_token', $access_token, HOUR_IN_SECONDS );

		}

		return get_transient( 'spro_access_token' );

	}

	/**
	 * Add query var for admin notices
	 * 
	 * @since 1.0.0
	 */
	public function add_notice_query_var( $location ) {
		remove_filter( 'redirect_post_location', array( $this, 'add_notice_query_var' ), 99 );
		session_start();
		return add_query_arg( array( 'admin_message' => $_SESSION['admin_message'] ), $location );
	}

	/**
	 * Display admin notices
	 * 
	 * @since 1.0.0
	 */
	public function spro_admin_notices() {

		session_start();

		if ( ! isset( $_SESSION['admin_message'] ) ) {
			return;
		}

		?>
		  	<div class="<?php echo esc_html_e( $_SESSION['admin_message']['type'], 'spro' ); ?>">
			<p><?php esc_html_e( $_SESSION['admin_message']['message'], 'spro' ); ?></p>
		  </div>

		<?php

		unset( $_SESSION['admin_message'] );
	}

	/**
	 * Bulk Edit Fields
	 * 
	 * @since 1.0.0
	 */
	public function spro_bulk_edit_fields() {

		$is_spro_product = get_post_meta( get_the_ID(), '_spro_product', true );

		?>
		<div class="inline-edit-group">
			<label class="alignleft">
				<span class="title" style="width: 11em;">Subscription Product?</span>
				<input type="checkbox" class="checkbox" name="_spro_product" id="_spro_product" <?php echo $is_spro_product == 'yes' ? 'checked' : ''; ?>>
			</label>
		</div>
		<?php
	}

	/**
	 * Bulk Edit Save
	 * 
	 * @since 1.0.0
	 */
	public function spro_bulk_edit_save( $product ) {
		
		$post_id = $product->get_id();
		$sku = $product->get_sku();
		$name = get_the_title( $post_id );
		$price = $product->get_price();

	   if ( isset( $_REQUEST['_spro_product'] ) && $_REQUEST['_spro_product'] == 'on' ) {

			$field = $_REQUEST['_spro_product'];
			update_post_meta( $post_id, '_spro_product', 'yes' );

			// Check if product exists in Subscribe Pro and create if it doesn't
			$exists = $this->spro_product_exists( $sku );

			// Create new product or update product
			if ( $exists != false ) {

				$name = get_the_title( $post_id );
				$price = $product->get_price();

				$this->spro_update_product( $name, $price, $exists );
			} else {
				$this->spro_create_product( $post_id );
			}

		} else {
			
			update_post_meta( $post_id, '_spro_product', 'no' );

		}

	}

	/**
	 * Check if a product exists in Subscribe Pro
	 * 
	 * @since 1.0.0
	 */
	public function spro_product_exists( $sku ) {

		$client = new Client();
		$access_token = $this->spro_get_access_token();

		$response = $client->get( SPRO_BASE_URL . '/services/v2/products.json?sku=' . $sku, [
			'verify' => false,
			'auth' => [SPRO_CLIENT_ID, SPRO_CLIENT_SECRET],
		]);
		
		$response_body = json_decode( $response->getBody() );

		if ( empty( $response_body->products ) ) {

			// Product Doesn't Exist
			return false;
		
		} else {
			
			// Product Exists
			return $response_body->products[0]->id;
		}

	}

	/**
	 * Create a Subscribe Pro Product
	 * 
	 * @since 1.0.0
	 */
	public function spro_create_product( $post_id ) {

		// If product does not exist, create product
		$product = wc_get_product( $post_id );
		$name = get_the_title( $post_id );
		$price = $product->get_price();
		$sku = $product->get_sku();

		$client = new Client();
		$access_token = $this->spro_get_access_token();

		try {

			$response = $client->request(
				'POST',
				SPRO_BASE_URL . '/services/v2/product.json',
				[
					'auth' => [SPRO_CLIENT_ID, SPRO_CLIENT_SECRET],
					'verify' => false,
					'json' => ['product' =>
						array(
							'sku' => $sku,
							'name' => $name,
							'price' => $price
						)
					]
				]
			);
	
			$response_body = json_decode( $response->getBody() );

			session_start();

			$_SESSION['admin_message'] = array(
				'type' => 'updated',
				'message' => 'Successfully created product #' . $response_body->product->id,
			);

		} catch (RequestException $e) {

			session_start();

			$_SESSION['admin_message'] = array(
				'type' => 'error',
				'message' => $e->getMessage()
			);

			add_filter( 'redirect_post_location', array( $this, 'add_notice_query_var' ), 99 );

		}

	}

	/**
	 * Update a Subscribe Pro Product
	 * 
	 * @since 1.0.0
	 */
	public function spro_update_product( $name, $price, $id ) {

		$client = new Client();
		$access_token = $this->spro_get_access_token();

		try {

			$response = $client->request(
				'POST',
				SPRO_BASE_URL . '/services/v2/products/' . $id,
				[
					'auth' => [SPRO_CLIENT_ID, SPRO_CLIENT_SECRET],
					'verify' => false,
					'json' => ['product' =>
						array(
							'name' => $name,
							'price' => $price
						)
					]
				]
			);
	
			$response_body = json_decode( $response->getBody() );

			session_start();

			$_SESSION['admin_message'] = array(
				'type' => 'updated',
				'message' => 'Successfully updated product #' . $response_body->product->id,
			);

		} catch (RequestException $e) {

			session_start();

			$_SESSION['admin_message'] = array(
				'type' => 'error',
				'message' => $e->getMessage()
			);

			add_filter( 'redirect_post_location', array( $this, 'add_notice_query_var' ), 99 );

		}

	}

}