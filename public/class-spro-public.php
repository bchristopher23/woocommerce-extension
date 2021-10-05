<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://www.subscribepro.com/
 * @since      1.0.0
 *
 * @package    Spro
 * @subpackage Spro/public
 */

use GuzzleHttp\Client;
use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\RequestException;

require SPRO_PLUGIN_DIR . 'vendor/auth-sdk-php-2.0.2/autoload.php';

use net\authorize\api\contract\v1 as AnetAPI;
use net\authorize\api\controller as AnetController;

class Spro_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since 1.0.0
	 * @access private
	 * @var String $plugin_name The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string $version The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since 1.0.0
	 * @param string $plugin_name The name of the plugin.
	 * @param string $version The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Plugin_Name_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Plugin_Name_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/public.js', array( 'jquery' ), $this->version, false );

	}

	/**
	 * Add the endpoint for the subscriptions tab in WooCommerce My Account
	 *
	 * @since 1.0.0
	 */
	public function spro_add_endpoints() {

		add_rewrite_endpoint('subscriptions', EP_ROOT | EP_PAGES);
	
	}

	/**
	 * Add the subscriptions tab in WooCommerce My Account
	 *
	 * @since  1.0.0
	 * @param Array $items tab items.
	 */
	public function spro_subscriptions_tab( $items ) {

		$logout = $items['customer-logout'];
		unset($items['customer-logout']);
		$items['subscriptions'] = __( 'Subscriptions', 'spro' );
		$items['customer-logout'] = $logout;
		return $items;
	
	}

	/**
	 * Render the subscriptions tab content
	 *
	 * @since 1.0.0
	 */
	public function spro_render_subscriptions_tab() {
		?>
	
		<h2>Your Subscriptions</h2>
	
		<div class="content">
			<!-- My Subscriptions Widget div goes in main body of page -->
			<div id="sp-my-subscriptions"></div>
		</div>
	
		<!-- Load the Subscribe Pro widget script -->
		<script
			type="text/javascript"
			src="https://hosted.subscribepro.com/my-subscriptions/widget-my-subscriptions-1.2.5.js"
		></script>
	
		<?php
	
		$user_id = get_current_user_id();
		$spro_customer_id = get_the_author_meta( 'spro_id', $user_id );
		$username = "1609_5gbssq82jj0gkg448s4gsg08swgogscswsgg48oks4c4wc8oc8";
		$password = "4o86nv7vj4w0o0o000ws8o8cckgsk8gcksw4oos488gsg00kko";
		$host = SPRO_BASE_URL . '/oauth/v2/token';
	
		$data = array(
			'grant_type' => 'client_credentials',
			'scope' => 'widget',
			'customer_id' => $spro_customer_id
		);
	
		$ch = curl_init($host);    
		curl_setopt($ch, CURLOPT_USERPWD, $username . ":" . $password);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data) );
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		$return = json_decode( curl_exec($ch) );
		
		?>
	
		<!-- Pass configuration and init the Subscribe Pro widget -->
		<script type="text/javascript">
			// Setup config for Subscribe Pro
			var widgetConfig = {
				apiBaseUrl: 'SPRO_BASE_URL',
				apiAccessToken: '<?php echo $return->access_token; ?>',
				environmentKey: '<?php echo $return->environment_key; ?>',
				customerId: '<?php echo $spro_customer_id; ?>',
				themeName: 'base',
			};
			// Call widget init()
			window.MySubscriptions.init(widgetConfig);
		</script>
	
		<?php
	}

	/**
	 * Add the Subscribe Pro options to the single product page
	 *
	 * @since 1.0.0
	 */
	public function spro_before_add_to_cart_btn() {

		global $product;

		$is_spro = get_post_meta( $product->get_id(), '_spro_product', true );

		if ( $is_spro == 'yes' ) {

			$response_body = $this->spro_get_product( $product->get_sku() );

			$intervals = $response_body[0]->intervals;
			$price = $response_body[0]->price;
			$isDiscountPercentage = $response_body[0]->isDiscountPercentage;
			$discount = $response_body[0]->discount;

			if ( $isDiscountPercentage == true ) {
				$discount = round((float)$discount * 100 ) . '%';
			} else {
				$discount = '$' . number_format( $discount, 2, '.', ',' );
			}
	
			$product_data = array(
				'intervals' => $intervals,
				'price' => number_format( $price, 2, '.', ',' ),
				'isDiscountPercentage' => $isDiscountPercentage,
				'discount' => $discount
			);
	
			$templates = new Spro_Template_Loader;
	
			ob_start();
	
			$templates->set_template_data( $product_data, 'product_data' );
			$templates->get_template_part( 'woocommerce/content', 'options' );
	
			echo ob_get_clean();

		}

		$templates = new Spro_Template_Loader;

	}

	/**
	 * Validate the Subscribe Pro options on the single product page
	 * 
	 * @since 1.0.0
	 * @param Array $passed Validation status.
	 * @param Integer $product_id Product ID.
	 * @param Boolean $quantity Quantity
	 */
	public function spro_validate_custom_field( $passed, $product_id, $quantity ) {

		if( empty( $_POST['delivery_type'] ) ) {
			// Fails validation
			$passed = false;
			wc_add_notice( __( 'Please select a delivery type', 'spro' ), 'error' );
		}

		if( empty( $_POST['delivery_frequency'] ) ) {
			// Fails validation
			$passed = false;
			wc_add_notice( __( 'Please select a delivery frequency', 'spro' ), 'error' );
		}

		return $passed;
	}

	/**
	 * Add the custom field data to the cart
	 * @since 1.0.0
	 * @param Array $cart_item_data Cart item meta data.
	 * @param Integer $product_id Product ID.
	 * @param Integer $variation_id Variation ID.
	 * @param Boolean $quantity Quantity
	 */
	public function spro_add_custom_field_item_data( $cart_item_data, $product_id, $variation_id, $quantity ) {
		
		if( ! empty( $_POST['delivery_type'] ) ) {
			// Add the item data
			$cart_item_data['delivery_type'] = $_POST['delivery_type'];
		}
		
		if( ! empty( $_POST['delivery_frequency'] ) ) {
			// Add the item data
			$cart_item_data['delivery_frequency'] = $_POST['delivery_frequency'];
		}
		
		if( ! empty( $_POST['delivery_discount'] ) && ! empty( $_POST['delivery_type'] ) ) {

			// Add the item data
			if ( $_POST['delivery_type'] == 'Regular' ) {
				$cart_item_data['delivery_discount'] = $_POST['delivery_discount'];
			}

		}

		return $cart_item_data;

	}

	/**
	 * Apply discount to product
	 */
	public function spro_apply_discount( $cart ) {

		if ( is_admin() && ! defined('DOING_AJAX' ) ) {
			return;
		}

		if ( did_action('woocommerce_cart_calculate_fees') >= 2 ) {
			return;
		}

		$fee = 0;

		// Loop through cart items
		foreach ( $cart->get_cart() as $cart_item ) {

			if( isset( $cart_item['delivery_discount'] ) ) {
				
				$discount = intval( $cart_item['delivery_discount'] );

				if ( $discount != '' ) {

					$price = get_post_meta( $cart_item['product_id'] , '_price', true );
					$quantity = $cart_item['quantity'];

					$discount_fee = ($discount / 100) * $price;

					$fee += ($discount_fee * $quantity);

				}
				
			}
		}

		if ( $fee > 0 ) {
			$cart->add_fee( __( "Discount for subscription", "woocommerce" ), - $fee );
		}

	}

	/**
	 * Display the custom field value in the cart
	 * 
	 * @since 1.0.0
	 */
	public function spro_cart_item_name( $name, $cart_item, $cart_item_key ) {
		
		if( isset( $cart_item['delivery_type'] ) ) {
			$name .= sprintf(
			'<br><strong>Delivery Type</strong>: %s<br>',
			esc_html( $cart_item['delivery_type'] )
			);
		}

		if( isset( $cart_item['delivery_frequency'] ) ) {
			$name .= sprintf(
			'<strong>Delivery Frequency</strong>: %s<br>',
			esc_html( $cart_item['delivery_frequency'] )
			);
		}
		
		return $name;

	}

	/**
	 * Add custom field to order object
	 * 
	 * @since 1.0.0
	 */
	public function spro_add_custom_data_to_order( $item, $cart_item_key, $values, $order ) {

		foreach( $item as $cart_item_key=>$values ) {
			if( isset( $values['delivery_type'] ) ) {
				$item->add_meta_data( __( 'Delivery Type', 'spro' ), $values['delivery_type'], true );
			}

			if( isset( $values['delivery_frequency'] ) ) {
				$item->add_meta_data( __( 'Delivery Frequency', 'spro' ), $values['delivery_frequency'], true );
			}
			
			if( isset( $values['delivery_discount'] ) ) {
				$item->add_meta_data( __( 'Delivery Discount', 'spro' ), $values['delivery_discount'], true );
			}
		}

	}

	/**
	 * Redirect on checkout if not logged in
	 */
	function spro_checkout_redirect() {
		
		if ( ! is_user_logged_in() && is_checkout() ) {

			wc_add_notice( 'Please log in or register to complete your purchase.', 'notice' );

			wp_redirect( home_url( '/my-account?redirect_to_checkout' ) );
			exit;
		
		}
	
	}

	/**
	 * Retrieves Access Token
	 * 
	 * @since 1.0.0
	 */
	public function spro_get_access_token() {

		// delete_transient( 'spro_access_token' );

		if ( false === ( $value = get_transient( 'spro_access_token' ) ) ) {
			
			$client = new Client();
			$user_id = get_current_user_id();

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
	 * Retrieves Product Data
	 * 
	 * @since 1.0.0
	 */
	public function spro_get_product( $sku ) {

		// delete_transient( $sku . '_spro_product' );
		
		if ( false === ( $value = get_transient( $sku . '_spro_product' ) ) ) {
			
			$client = new Client();
			$access_token = $this->spro_get_access_token();

			$data = array(
				'access_token' => $access_token,
				'sku' => $sku,
			);
	
			$response = $client->request(
				'GET',
				SPRO_BASE_URL . '/products',
				[
				'auth' => [SPRO_CLIENT_ID, SPRO_CLIENT_SECRET],
				'verify' => false,
				'query' => http_build_query( $data )
				]
			);
	
			$response_body = json_decode( $response->getBody() );

			set_transient( $sku . '_spro_product', $response_body, 24 * HOUR_IN_SECONDS );

		}

		return get_transient( $sku . '_spro_product' );

	}

	/**
	 * WooCommerce Payment Complete Hook
	 * 
	 * @since 1.0.0
	 * @param Integer $order_id Order ID.
	 */
	public function spro_payment_complete( $order_id ) {

		// Get Order Info
		$order = wc_get_order( $order_id );
		$customer_id = get_current_user_id();
		$spro_customer_id = get_user_meta( $customer_id, 'spro_id', true );
		$is_spro_customer = $spro_customer_id != '' ? true : false;
		$user_info = get_userdata( $customer_id );
		$user_first_name = $user_info->first_name;
		$user_last_name = $user_info->last_name;
		$user_email = $user_info->user_email;
		$client = new Client();
		$access_token = $this->spro_get_access_token();
		$ebiz_data = get_post_meta( $order_id, '[EBIZCHARGE]|methodid|refnum|authcode|avsresultcode|cvv2resultcode|woocommerceorderid', true );
		$ebiz_data_array = explode('|', $ebiz_data);
		$ebiz_payment_method = $ebiz_data_array[1];
		$ebiz_ref_num = $ebiz_data_array[2];
		$cc_year = get_post_meta( $order_id, 'card_exp_year', true );
		$cc_month = get_post_meta( $order_id, 'card_exp_month', true );
		$cc_last4 = get_post_meta( $order_id, 'card_last4', true );
		$cc_type = get_post_meta( $order_id, 'card_type', true );

		// echo 'ebiz data is ' . $ebiz_data;
		
		// update_post_meta( $order_id, 'ebiz_payment_method_id', $ebiz_payment_method );

		$shipping_method = $order->get_shipping_method();

		// Customer Billing Address
		$billing_address = array(
			'first_name' => $order->get_billing_first_name(),
			'last_name' => $order->get_billing_last_name(),
			'street1' => $order->get_billing_address_1(),
			'street2' => $order->get_billing_address_2(),
			'city' => $order->get_billing_city(),
			'region' => $order->get_billing_state(),
			'zip' => $order->get_billing_postcode(),
			'country' => $order->get_billing_country()
		);

		// Customer Shipping Address
		$shipping_address = array(
			'first_name' => $order->get_shipping_first_name(),
			'last_name' => $order->get_shipping_last_name(),
			'street1' => $order->get_shipping_address_1(),
			'street2' => $order->get_shipping_address_2(),
			'city' => $order->get_shipping_city(),
			'region' => $order->get_shipping_state(),
			'zip' => $order->get_shipping_postcode(),
			'country' => $order->get_shipping_country()
		);

		// Create the customer in Subscribe Pro if needed
		if ( !$is_spro_customer ) {

			$response = $client->post( SPRO_BASE_URL . '/services/v2/customer.json', [
				'verify' => false,
				'auth' => [SPRO_CLIENT_ID, SPRO_CLIENT_SECRET],
				'json' => ['customer' => 
					array(
						'platform_specific_customer_id' => $customer_id,
						'first_name' => $user_first_name,
						'last_name' => $user_last_name,
						'email' => $user_email
					)
				]
			]);
			
			$response_body = json_decode( $response->getBody() );

			// Update WooCommerce customer with subscribe pro id
			$spro_customer_id = $response_body->customer->id;
			update_user_meta( $customer_id, 'spro_id', $spro_customer_id );

			// echo '<pre>';
			// print_r( $response_body );
			// echo '</pre>';

		}

		// echo '<pre>';
		// print_r( $order );
		// echo '</pre>';

		// echo 'ebiz payment method is ' . $ebiz_payment_method;

		$data = array(
			'payment_token' => $ebiz_payment_method,
		);

		// Check if payment profile exists
		$response = $client->request(
			'GET',
			SPRO_BASE_URL . '/services/v2/vault/paymentprofiles.json',
			[
			'auth' => [SPRO_CLIENT_ID, SPRO_CLIENT_SECRET],
			'verify' => false,
			'query' => http_build_query( $data )
			]
		);

		$payment_profile_response =  json_decode( $response->getBody() );
		$payment_profile_array = $payment_profile_response->payment_profiles;

		// echo 'payment response';
		// echo '<pre>';
		// print_r( $payment_profile_response );
		// echo '</pre>';

		if ( 1 == 2) { //empty( $payment_profile_array ) ) {

			// echo 'creating new payment profile.';

			// echo 'month is ' . $cc_month;
			
			// Create new payment profile if needed		
			$response = $client->post( SPRO_BASE_URL . '/services/v2/vault/paymentprofile/external-vault.json', [
				'verify' => false,
				'auth' => [SPRO_CLIENT_ID, SPRO_CLIENT_SECRET],
				'json' => ['payment_profile' =>
					array(
						'customer_id' => $spro_customer_id,
						'payment_token' => $ebiz_payment_method,
						'creditcard_last_digits' => $cc_last4,
						'creditcard_month' => $cc_month,
						'creditcard_year' => $cc_year,
						'creditcard_type' => $cc_type,
						'billing_address' => $billing_address
					)
				]
			]);

			$response_body = json_decode( $response->getBody() );

			$sp_payment_profile_id = $response_body->payment_profile->id;

		} else {

			$sp_payment_profile_id = $payment_profile_array[0]->id;

			// Get CC Data from eBizCharge
			$client_e = new SoapClient('https://soap.ebizcharge.net/eBizService.svc?singleWsdl');

			$securityToken = array(
				'SecurityId' => 'ec3b1d57-962b-4cca-9004-d15abb525dfb',
				'UserId' => 'SubscribeSB',
				'Password' => 'ZtQFpin4'
			);

			$customer_profile_id = get_user_meta( $customer_id, 'CustNum', true );

			try {
				
				$res = $client_e->GetCustomerPaymentMethodProfile(
					array(
						'securityToken' => $securityToken,
						'customerToken' => $customer_profile_id,
						'paymentMethodId' => $ebiz_payment_method
					)
				);

				// echo '<pre>';
				// print_r($res->GetCustomerPaymentMethodProfileResult);
				// echo '</pre>';

				$payment_data = $res->GetCustomerPaymentMethodProfileResult;
				$expiration = $payment_data->CardExpiration;

				// Update existing payment profile
				$response = $client->post( SPRO_BASE_URL . '/services/v2/vault/paymentprofiles/' . $sp_payment_profile_id . '.json', [
					'verify' => false,
					'auth' => [SPRO_CLIENT_ID, SPRO_CLIENT_SECRET],
					'json' => ['payment_profile' =>
						array(
							'creditcard_last_digits' => substr( $payment_data->CardNumber, -4 ),
							'creditcard_month' => substr( $expiration, -2 ),
							'creditcard_year' => substr( $expiration, 0, 4 ),
							'creditcard_type' => $payment_data->CardType,
							'billing_address' => $billing_address
						)
					]
				]);
			
			} catch (SoapFault $e) {
				
				die("getTransaction failed: " . $e->getMessage());

			}

		}

		// echo '<pre>';
		// print_r( $payment_profile_response );
		// echo '</pre>';

		foreach( $order->get_items() as $item_id => $line_item ) {

			$item_data = $line_item->get_data();
			$product = $line_item->get_product();
			$sku = $product->get_sku();
			$product_name = $product->get_name();
			$item_quantity = $line_item->get_quantity();
			$item_total = $product->get_price();

			$is_subscription_product = get_post_meta( $product->get_id(), '_spro_product', true );

			if ( $is_subscription_product == 'yes' ) {

				$frequency = wc_get_order_item_meta( $item_id, 'Delivery Frequency', true );
				
				$response = $client->post( SPRO_BASE_URL . '/services/v2/subscription.json', [
					'verify' => false,
					'auth' => [SPRO_CLIENT_ID, SPRO_CLIENT_SECRET],
					'json' => ['subscription' => 
						array(
							'customer_id' => $spro_customer_id,
							'payment_profile_id' => $sp_payment_profile_id,
							'product_sku' => $sku,
							'requires_shipping' => true,
							'shipping_method_code' => $shipping_method,
							'shipping_address' => $shipping_address,
							'qty' => $item_quantity,
							'next_order_date' => date("F j, Y"),
							'first_order_already_created' => true,
							'interval' => $frequency
						)
					]
				]);
				
				$response_body = json_decode( $response->getBody() );
	
				// echo '<pre>';
				// print_r( $response_body );
				// echo '</pre>';

			}

		}

	}

	/**
	 * Save Ebiz Data to Order
	 */
	public function spro_payment_post( $order_id ) { 
	
		update_post_meta( $order_id, 'ebiz_payment_method_save', $_POST['ebizcharge-use-stored-payment-info'] );
		update_post_meta( $order_id, 'card_type', $_POST['cardtype'] );
		update_post_meta( $order_id, 'card_exp_year', $_POST['expyear'] );
		update_post_meta( $order_id, 'card_exp_month', $_POST['expmonth'] );
		update_post_meta( $order_id, 'card_last4', substr( $_POST['ccnum'], -4 ) );

		error_log( 'hit' );

		error_log( print_r( $_POST, true ) );

	}

    /**
     * @param \GuzzleHttp\Psr7\Request $request
     * @param string $sharedSecret
     *
     * @return bool
     */
    public function validate_request_hmac(\GuzzleHttp\Psr7\Request $request, $sharedSecret) {

        // Get signature from request header
        $hmacSignature = $request->getHeader(SP_HMAC_HEADER);
        
        // Get request body (JSON string)
        $body = $request->getBody();

        // Calculate the hash of body using shared secret and SHA-256 algorithm
        $calculatedHash = hash_hmac('sha256', $body, $sharedSecret, false);
        
        // Compare signature using secure compare method
        return hash_equals($calculatedHash, $hmacSignature);

    }

	/**
	 * spro_rest_testing_endpoint
	 * @return WP_REST_Response
	 */
	function spro_order_callback_ebiz( $data ) {

		// Get Order Data From Subscribe Pro
		$order_data = $data->get_json_params();

		// $order_data = get_transient( 'order_data' );

		// set_transient( 'order_data', $order_data );

		error_log( 'hit' );

		error_log( print_r( $order_data, true ) );

		// Create WooCommerce Order
		global $woocommerce;

		$billing_address = array(
			'first_name' => $order_data['billingAddress']['firstName'],
			'last_name'  => $order_data['billingAddress']['lastName'],
			'email'      => $order_data['customerEmail'],
			'phone'      => $order_data['billingAddress']['phone'],
			'address_1'  => $order_data['billingAddress']['street1'],
			'address_2'  => $order_data['billingAddress']['street2'],
			'city'       => $order_data['billingAddress']['city'],
			'state'      => $order_data['billingAddress']['region'],
			'postcode'   => $order_data['billingAddress']['postcode'],
			'country'    => $order_data['billingAddress']['country']
		);
	  
		$shipping_address = array(
			'first_name' => $order_data['shippingAddress']['firstName'],
			'last_name'  => $order_data['shippingAddress']['lastName'],
			'email'      => $order_data['customerEmail'],
			'phone'      => $order_data['shippingAddress']['phone'],
			'address_1'  => $order_data['shippingAddress']['street1'],
			'address_2'  => $order_data['shippingAddress']['street2'],
			'city'       => $order_data['shippingAddress']['city'],
			'state'      => $order_data['shippingAddress']['region'],
			'postcode'   => $order_data['shippingAddress']['postcode'],
			'country'    => $order_data['shippingAddress']['country']
		);

		// Create the order
		$order = wc_create_order( array( 'customer_id' => $order_data['platformCustomerId'] ) );

		// Add products to the order
		$products_array = array();

		foreach ( $order_data['items'] as $item ) {
			
			// Item Data
			$sku = $item['productSku'];
			$product_id = wc_get_product_id_by_sku( $sku );

			// Add product to order
			$order->add_product( wc_get_product( $product_id ) );

		}

		// Create Product Array For Response
		foreach ( $order->get_items() as $item_key => $item ) {

			// Item ID is directly accessible from the $item_key in the foreach loop or
			$item_id = $item->get_id();

			## Using WC_Order_Item_Product methods ##
			$product = $item->get_product(); // Get the WC_Product object
			
			// Item Data
			$sku = $product->get_sku();
			$product_id = wc_get_product_id_by_sku( $sku );
			$item_name = $item->get_name();
			$quantity = $item->get_quantity();  
			$line_subtotal = $item->get_subtotal();
			$line_total = $item->get_total();
			$line_total_tax = $item->get_total_tax();

			// Get an instance of Product WP_Post object
			$post_obj = get_post( $product_id );
		
			// The product short description
			$product_short_desciption = $post_obj->post_excerpt;

			$product = array(
				"platformOrderItemId" => strval( $order->get_id() ),
				"productSku" => $sku,
				"productName" => $item_name,
				"shortDescription" => $product_short_desciption,
				"qty" => strval( $quantity ),
				"requiresShipping" => true,
				"unitPrice" => strval( $line_subtotal ),
				"shippingTotal" => "0",
				"taxTotal" => strval( $line_total_tax ),
				"lineTotal" => strval( $line_total ),
				"subscriptionId" => "243867"
			);

			array_push( $products_array, $product );

		}

		// Add Shipping Method
		// $item = new WC_Order_Item_Shipping();

		// $item->set_method_title( $order_data['shippingMethodCodes'][0]['method_code'] );
		// $order->add_item( $item );

		// Set Addresses
		$order->set_address( $billing_address, 'billing' );
		$order->set_address( $shipping_address, 'shipping' );

		// Calculate totals
		$order->calculate_totals();

		$customer_profile_id = get_user_meta( $order_data["platformCustomerId"], 'CustNum', true );

		// Charge payment profile 
		$charge = $this->ebizChargeCustomerProfile( $customer_profile_id, $order_data['payment']['paymentToken'], $order->get_total(), $billing_address['postcode'] );

		// Prepare return data and update order with ebiz data if payment was successful
		if ( $charge['status'] ) {

			$return_data = array(
				"orderNumber" => strval( $order->get_id() ),
				"orderDetails" => array(
					"customerId" => strval( $order_data["customerId"] ),
					"customerEmail" => $order_data["customerEmail"],
					"platformCustomerId" => strval( $order_data["platformCustomerId"] ),
					"platformOrderId" => strval( $order->get_id() ),
					"orderNumber" => strval( $order->get_id() ),
					"salesOrderToken" => strval( $charge['trans_id'] ),
					"orderStatus" => "placed",
					"orderState" => "open",
					"orderDateTime" => strval( $order->get_date_created() ),
					"currency" => "USD",
					"shippingTotal" => strval( $order->get_shipping_total() ),
					"taxTotal" => strval( $order->get_total_tax() ),
					"total" => strval( $order->get_total() ),
					"shippingAddress" => array(
						"firstName" => $order_data["shippingAddress"]["firstName"],
						"lastName" => $order_data["shippingAddress"]["lastName"],
						"street1" => $order_data["shippingAddress"]["street1"],
						"street2" => $order_data["shippingAddress"]["street2"],
						"city" => $order_data["shippingAddress"]["city"],
						"region" => $order_data["shippingAddress"]["region"],
						"postcode" => $order_data["shippingAddress"]["postcode"],
						"country" => $order_data["shippingAddress"]["country"],
						"phone" => $order_data["shippingAddress"]["phone"]
					),
					"billingAddress" => array(
						"firstName" => $order_data["billingAddress"]["firstName"],
						"lastName" => $order_data["billingAddress"]["lastName"],
						"street1" => $order_data["billingAddress"]["street1"],
						"street2" => $order_data["billingAddress"]["street2"],
						"city" => $order_data["billingAddress"]["city"],
						"region" => $order_data["billingAddress"]["region"],
						"postcode" => $order_data["billingAddress"]["postcode"],
						"country" => $order_data["billingAddress"]["country"],
						"phone" => $order_data["billingAddress"]["phone"]
					),
					"items" => $products_array
				),
			);
	
			// Update order status
			$trans_id = $charge['trans_id'];
			update_post_meta(  $order->get_id(), '_transaction_id', $trans_id );
			update_post_meta(  $order->get_id(), '_payment_method_title', 'Credit Card' );
			$order->update_status( 'processing', 'Ebiz charge completed successfully, transaction ID: ' . $trans_id );

			// Return response
			$response = new WP_REST_Response( $return_data, 201 );

		} else {

			// Update order status
			$order->update_status( 'failed', $charge['error'] );
			$response = new WP_REST_Response( array( 'error' => $charge['error'] ), 400 );

		}

		return $response;

	}

	/**
	 * spro_rest_testing_endpoint
	 * @return WP_REST_Response
	 */
	function spro_order_callback_anet( $data ) {

		// Get Order Data From Subscribe Pro
		$order_data = $data->get_json_params();

		// $order_data = get_transient( 'order_data' );

		// set_transient( 'order_data', $order_data );

		// error_log( 'hit' );

		// error_log( print_r( $order_data, true ) );

		// Create WooCommerce Order
		global $woocommerce;

		$billing_address = array(
			'first_name' => $order_data['billingAddress']['firstName'],
			'last_name'  => $order_data['billingAddress']['lastName'],
			'email'      => $order_data['customerEmail'],
			'phone'      => $order_data['billingAddress']['phone'],
			'address_1'  => $order_data['billingAddress']['street1'],
			'address_2'  => $order_data['billingAddress']['street2'],
			'city'       => $order_data['billingAddress']['city'],
			'state'      => $order_data['billingAddress']['region'],
			'postcode'   => $order_data['billingAddress']['postcode'],
			'country'    => $order_data['billingAddress']['country']
		);
	  
		$shipping_address = array(
			'first_name' => $order_data['shippingAddress']['firstName'],
			'last_name'  => $order_data['shippingAddress']['lastName'],
			'email'      => $order_data['customerEmail'],
			'phone'      => $order_data['shippingAddress']['phone'],
			'address_1'  => $order_data['shippingAddress']['street1'],
			'address_2'  => $order_data['shippingAddress']['street2'],
			'city'       => $order_data['shippingAddress']['city'],
			'state'      => $order_data['shippingAddress']['region'],
			'postcode'   => $order_data['shippingAddress']['postcode'],
			'country'    => $order_data['shippingAddress']['country']
		);

		// Create the order
		$order = wc_create_order( array( 'customer_id' => $order_data['platformCustomerId'] ) );

		// Add products to the order
		$products_array = array();

		foreach ( $order_data['items'] as $item ) {
			
			// Item Data
			$sku = $item['productSku'];
			$product_id = wc_get_product_id_by_sku( $sku );

			// Add product to order
			$order->add_product( wc_get_product( $product_id ) );

		}

		// Create Product Array For Response
		foreach ( $order->get_items() as $item_key => $item ) {

			// Item ID is directly accessible from the $item_key in the foreach loop or
			$item_id = $item->get_id();

			## Using WC_Order_Item_Product methods ##
			$product = $item->get_product(); // Get the WC_Product object
			
			// Item Data
			$sku = $product->get_sku();
			$product_id = wc_get_product_id_by_sku( $sku );
			$item_name = $item->get_name();
			$quantity = $item->get_quantity();  
			$line_subtotal = $item->get_subtotal();
			$line_total = $item->get_total();
			$line_total_tax = $item->get_total_tax();

			// Get an instance of Product WP_Post object
			$post_obj = get_post( $product_id );
		
			// The product short description
			$product_short_desciption = $post_obj->post_excerpt;

			$product = array(
				"platformOrderItemId" => strval( $order->get_id() ),
				"productSku" => $sku,
				"productName" => $item_name,
				"shortDescription" => $product_short_desciption,
				"qty" => strval( $quantity ),
				"requiresShipping" => true,
				"unitPrice" => strval( $line_subtotal ),
				"shippingTotal" => "0",
				"taxTotal" => strval( $line_total_tax ),
				"lineTotal" => strval( $line_total ),
				"subscriptionId" => "243867"
			);

			array_push( $products_array, $product );

		}

		// Add Shipping Method
		// $item = new WC_Order_Item_Shipping();

		// $item->set_method_title( $order_data['shippingMethodCodes'][0]['method_code'] );
		// $order->add_item( $item );

		// Set Addresses
		$order->set_address( $billing_address, 'billing' );
		$order->set_address( $shipping_address, 'shipping' );

		// Calculate totals
		$order->calculate_totals();

		// Get payment profile id from token
		global $wpdb;

		$sql = $wpdb->prepare ( "SELECT * FROM wp_woocommerce_payment_tokens WHERE token LIKE %s", $order_data['payment']['paymentToken'] );
		$results = $wpdb->get_results( $sql , ARRAY_A );
		$payment_token_id = $results[0]['token_id'];

		$sql = $wpdb->prepare( "SELECT * FROM wp_woocommerce_payment_tokenmeta WHERE payment_token_id LIKE %s AND meta_key LIKE \"customer_profile_id\"", $payment_token_id );
		$results = $wpdb->get_results( $sql , ARRAY_A );
		$customer_profile_id = $results[0]['meta_value'];

		// Charge payment profile 900074265, 900093396
		$charge = $this->anetChargeCustomerProfile( $customer_profile_id, $order_data['payment']['paymentToken'], $order->get_total() );

		// Prepare return data and update order with ebiz data if payment was successful
		if ( $charge['status'] ) {

			$return_data = array(
				"orderNumber" => strval( $order->get_id() ),
				"orderDetails" => array(
					"customerId" => strval( $order_data["customerId"] ),
					"customerEmail" => $order_data["customerEmail"],
					"platformCustomerId" => strval( $order_data["platformCustomerId"] ),
					"platformOrderId" => strval( $order->get_id() ),
					"orderNumber" => strval( $order->get_id() ),
					"salesOrderToken" => strval( $charge['trans_id'] ),
					"orderStatus" => "placed",
					"orderState" => "open",
					"orderDateTime" => strval( $order->get_date_created() ),
					"currency" => "USD",
					"shippingTotal" => strval( $order->get_shipping_total() ),
					"taxTotal" => strval( $order->get_total_tax() ),
					"total" => strval( $order->get_total() ),
					"shippingAddress" => array(
						"firstName" => $order_data["shippingAddress"]["firstName"],
						"lastName" => $order_data["shippingAddress"]["lastName"],
						"street1" => $order_data["shippingAddress"]["street1"],
						"street2" => $order_data["shippingAddress"]["street2"],
						"city" => $order_data["shippingAddress"]["city"],
						"region" => $order_data["shippingAddress"]["region"],
						"postcode" => $order_data["shippingAddress"]["postcode"],
						"country" => $order_data["shippingAddress"]["country"],
						"phone" => $order_data["shippingAddress"]["phone"]
					),
					"billingAddress" => array(
						"firstName" => $order_data["billingAddress"]["firstName"],
						"lastName" => $order_data["billingAddress"]["lastName"],
						"street1" => $order_data["billingAddress"]["street1"],
						"street2" => $order_data["billingAddress"]["street2"],
						"city" => $order_data["billingAddress"]["city"],
						"region" => $order_data["billingAddress"]["region"],
						"postcode" => $order_data["billingAddress"]["postcode"],
						"country" => $order_data["billingAddress"]["country"],
						"phone" => $order_data["billingAddress"]["phone"]
					),
					"items" => $products_array
				),
			);
	
			// Update order status
			$trans_id = $charge['trans_id'];
			update_post_meta(  $order->get_id(), '_wc_authorize_net_cim_credit_card_trans_id', $trans_id );
			update_post_meta(  $order->get_id(), '_transaction_id', $trans_id );
			update_post_meta(  $order->get_id(), '_payment_method', 'authorize_net_cim_credit_card' );
			update_post_meta(  $order->get_id(), '_payment_method_title', 'Credit Card' );
			$order->update_status( 'processing', 'Authorize.net charge completed successfully, transaction ID: ' . $trans_id );

			// Return response
			$response = new WP_REST_Response( $return_data, 201 );

		} else {

			// Update order status
			$order->update_status( 'failed', $charge['error'] );
			$response = new WP_REST_Response( array( 'error' => $charge['error'] ), 400 );

		}

		return $response;

	}

	/**
	 * spro_rest_init
	 */
	function spro_rest_init() {

		// route url: domain.com/wp-json/$namespace/$route
		$namespace = 'api/v1';
		$route     = 'order';

		$spro_settings_payment_method = get_option( 'spro_settings_payment_method' );

		if ( $spro_settings_payment_method == 'anet' ) {
			
			// authorize.net
			register_rest_route($namespace, $route, array(
				'methods'   => 'POST',
				'callback'  => array( $this, 'spro_order_callback_anet' ),
				'args' => array(),
				'permission_callback' => '__return_true'
			));

		}		
		
		if ( $spro_settings_payment_method == 'ebiz' ) {

			// eBiz
			register_rest_route($namespace, $route, array(
				'methods'   => 'POST',
				'callback'  => array( $this, 'spro_order_callback_ebiz' ),
				'args' => array(),
				'permission_callback' => '__return_true'
			));

		}

	}

	/**
	 * Charge Payment Profile
	 */
	function ebizChargeCustomerProfile( $customer_profile_id, $payment_profile_id, $amount, $zip ) {
		
		$client = new SoapClient('https://soap.ebizcharge.net/eBizService.svc?singleWsdl');

		$securityToken = array(
			'SecurityId' => 'ec3b1d57-962b-4cca-9004-d15abb525dfb',
			'UserId' => 'SubscribeSB',
			'Password' => 'ZtQFpin4'
		);

		$customerTransactionRequest = array(
			'isRecurring' => false,
			'IgnoreDuplicate' => true,
			'Details' => array(
				'Description' => 'WooCommerce Order from Subscribe Pro Subscription',
				'Amount' => $amount,
				'Tax' => 0,
				'Currency' => '',
				'Shipping' => '',
				'ShipFromZip' => $zip,
				'Discount' => 0,
				'Subtotal' => $amount,
				'AllowPartialAuth' => false,
				'Tip' => 0,
				'NonTax' => false,
				'Duty' => 0,
			),
			'Software' => 'woocommerce',
			'MerchReceipt' => false,
			'CustReceiptName' => '',
			'CustReceiptEmail' => '',
			'CustReceipt' => '2',
			'Command' => 'sale',
		);

		try {

			$transactionResult = $client->runCustomerTransaction(
				array(
					'securityToken' => $securityToken,
					'custNum' => $customer_profile_id,
					'paymentMethodID' => $payment_profile_id,
					'tran' => $customerTransactionRequest
				)
			);
	
			$transaction = $transactionResult->runCustomerTransactionResult;
	
			error_log( print_r( $transaction, true ) );

			if ( $transaction->Result != 'Approved' ) {
				
				$return_data = array(
					'status' => false,
					'error' => $transaction->Error
				);

			} else {

				$return_data = array(
					'status' => true,
					'trans_id' => $transaction->RefNum
				);

			}


		} catch (SoapFault $e) {

			$return_data = array(
				'status' => false,
				'error' => 'Payment failed, no response.'
			);

		}

		return $return_data;

	}

	/**
	 * Authorize.net Charge Payment Profl
	 */
	function anetChargeCustomerProfile( $profileid, $paymentprofileid, $amount ) {

		$merchantAuthentication = new AnetAPI\MerchantAuthenticationType();
		$merchantAuthentication->setName("6jH6f6Wr");
		$merchantAuthentication->setTransactionKey("9dxs95ND682YA8rL");
		
		// Set the transaction's refId
		$refId = 'ref' . time();

		$profileToCharge = new AnetAPI\CustomerProfilePaymentType();
		$profileToCharge->setCustomerProfileId($profileid);
		$paymentProfile = new AnetAPI\PaymentProfileType();
		$paymentProfile->setPaymentProfileId($paymentprofileid);
		$profileToCharge->setPaymentProfile($paymentProfile);

		$transactionRequestType = new AnetAPI\TransactionRequestType();
		$transactionRequestType->setTransactionType( "authCaptureTransaction"); 
		$transactionRequestType->setAmount($amount);
		$transactionRequestType->setProfile($profileToCharge);

		$request = new AnetAPI\CreateTransactionRequest();
		$request->setMerchantAuthentication($merchantAuthentication);
		$request->setRefId( $refId);
		$request->setTransactionRequest( $transactionRequestType);
		$controller = new AnetController\CreateTransactionController($request);
		$response = $controller->executeWithApiResponse( \net\authorize\api\constants\ANetEnvironment::SANDBOX);

		if ($response != null) {
			if($response->getMessages()->getResultCode() == "Ok") {
				$tresponse = $response->getTransactionResponse();
				
				if ($tresponse != null && $tresponse->getMessages() != null) {

					// echo " Transaction Response code : " . $tresponse->getResponseCode() . "\n";
					// echo  "Charge Customer Profile APPROVED  :" . "\n";
					// echo " Charge Customer Profile AUTH CODE : " . $tresponse->getAuthCode() . "\n";
					// echo " Charge Customer Profile TRANS ID  : " . $tresponse->getTransId() . "\n";
					// echo " Code : " . $tresponse->getMessages()[0]->getCode() . "\n"; 
					// echo " Description : " . $tresponse->getMessages()[0]->getDescription() . "\n";

					// return $tresponse->getTransId();

					$return_data = array(
						'status' => true,
						'trans_id' => $tresponse->getTransId()
					);

				} else {

					$return_data = array(
						'status' => false
					);

					if($tresponse->getErrors() != null) {
						$tresponse->getErrors()[0]->getErrorCode();
						$tresponse->getErrors()[0]->getErrorText();
						$return_data['error'] = $tresponse->getErrors()[0]->getErrorText();
					}

				}
			} else {

				$return_data = array(
					'status' => false
				);

				$tresponse = $response->getTransactionResponse();

				if( $tresponse != null && $tresponse->getErrors() != null ) {
					$tresponse->getErrors()[0]->getErrorCode();
					$tresponse->getErrors()[0]->getErrorText();
					$return_data['error'] = $tresponse->getErrors()[0]->getErrorText();
				} else {
					$response->getMessages()->getMessage()[0]->getCode();
					$response->getMessages()->getMessage()[0]->getText();
					$return_data['error'] = $response->getMessages()->getMessage()[0]->getText();
				}

			}
		} else {

			$return_data = array(
				'status' => false,
				'error' => 'Payment failed, no response.'
			);
		
		}

		return $return_data;

	}

}