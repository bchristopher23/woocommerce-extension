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

		$widget_url = get_option( 'spro_settings_subscriptions_widget_url' );
		$widget_config = str_replace( '\\', '', get_option( 'spro_settings_subscriptions_widget_config' ) );
		
		?>
	
		<h2>My Subscriptions</h2>
		<a href="https://kerstinflorian.com/auto-replenish-faq/" style="display: block; text-decoration: underline; font-size: 18px;" target="_blank">Auto Replenish FAQ</a>

		<div class="content">
			<!-- My Subscriptions Widget div goes in main body of page -->
			<div id="sp-my-subscriptions"></div>
		</div>
	
		<!-- Load the Subscribe Pro widget script -->
		<script
			type="text/javascript"
			src="<?php echo $widget_url; ?>"
		></script>
	
		<?php
	
		$user_id = get_current_user_id();
		$spro_customer_id = get_the_author_meta( 'spro_id', $user_id );
		$username = SPRO_CLIENT_ID;
		$password = SPRO_CLIENT_SECRET;
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
				apiBaseUrl: '<?php echo SPRO_BASE_URL; ?>',
				apiAccessToken: '<?php echo $return->access_token; ?>',
				environmentKey: '<?php echo $return->spreedly_environment_key; ?>',
				customerId: '<?php echo $spro_customer_id; ?>',
				<?php echo substr($widget_config, 1, -1); ?>
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

		$is_subscription_product = ( get_post_meta( $product_id, '_spro_product', true ) == 'yes' ) ? true : false;

		if ( $is_subscription_product ) {

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
		
		if( ! empty( $_POST['delivery_discount'] ) ) {

			// Add the item data
			$cart_item_data['delivery_discount'] = $_POST['delivery_discount'];

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
				$type = isset( $cart_item['delivery_type'] ) ? $cart_item['delivery_type'] : '';


				if ( $discount != '' && $type == 'regular' ) {

					$price = get_post_meta( $cart_item['product_id'] , '_price', true );
					$quantity = $cart_item['quantity'];

					$discount_fee = ( $discount / 100 ) * $price;

					$fee += ( $discount_fee * $quantity );

					// Add tax back in that was removed from the discount
					// $new_price_after_discount = $price - $discount_fee;
					// $tax_before_fee = $price * .08;

					// $tax_after_fee = $new_price_after_discount * .08;

					// $tax_difference = ($tax_before_fee - $tax_after_fee) * $quantity;

				}
				
			}
		}

		if ( $fee > 0 ) {

			// Add the tax back in if from CA
			// $user_state = WC()->customer->get_shipping_state();

			// if ( $user_state === 'CA' ) {
			// 	$cart->add_fee( __( "Tax Fee", "woocommerce" ), $tax_difference, false );
			// }

			// Add the discount
			$cart->add_fee( __( "Discount for subscription", "woocommerce" ), - $fee, false );

		}

	}

	/**
	 * Add custom field to order object
	 * 
	 * @since 1.0.0
	 */
	public function spro_add_custom_data_to_order( $item, $cart_item_key, $values, $order ) {

		foreach( $item as $cart_item_key=>$values ) {

			$delivery_type = '';

			if( isset( $values['delivery_type'] ) ) {

				$delivery_type = $values['delivery_type'];
				$item->add_meta_data( __( 'Delivery Type', 'spro' ), $delivery_type, true );
			
			}

			if ( $delivery_type == 'regular' ) {
				if( isset( $values['delivery_frequency'] ) ) {
					$item->add_meta_data( __( 'Delivery Frequency', 'spro' ), $values['delivery_frequency'], true );
				}
				
				if( isset( $values['delivery_discount'] ) ) {
	
					$item->add_meta_data( __( 'Delivery Discount', 'spro' ), $values['delivery_discount'], true );
				}
			}

		}

	}

	/**
	 * Redirect on checkout if not logged in
	 */
	function spro_checkout_redirect() {
		
		if ( ! is_user_logged_in() && is_checkout() ) {

			global $woocommerce;
			$items = $woocommerce->cart->get_cart();
			$redirect = false;
		
			foreach( $items as $item => $values ) {

				if ( $values['delivery_type'] == 'regular' ) {
					$redirect = true;
				}

			} 

			// Force customer to create an account is checking out with a subscription product
			if ( $redirect ) {
				wc_add_notice( 'Please log in or register to complete your purchase.', 'notice' );
				wp_redirect( get_permalink( get_option('woocommerce_myaccount_page_id') ) . '?redirect_to_checkout' );
				exit;	
			}

		}
	
	}
	
	/**
	 * Update cart products
	 */
	function spro_cart_updated() {

		$contents = WC()->cart->cart_contents;

		// loop over the cart
		foreach( $contents as $key => $values ) {

			if ( isset( $_POST['cart'][$key]['delivery_type'] ) ) {
				$contents[$key]['delivery_type'] = $_POST['cart'][$key]['delivery_type'];
			}

			if ( isset( $_POST['cart'][$key]['delivery_frequency'] ) ) {
				$contents[$key]['delivery_frequency'] = $_POST['cart'][$key]['delivery_frequency'];
			}

			if ( isset( $_POST['cart'][$key]['delivery_discount'] ) ) {
				$contents[$key]['delivery_discount'] = $_POST['cart'][$key]['delivery_discount'];
			}

		}

		WC()->cart->set_cart_contents( $contents );
		
		return true;

	}

	/**
	 * Retrieves Access Token
	 * 
	 * @since 1.0.0
	 */
	public function spro_get_access_token() {

		delete_transient( 'spro_access_token' );

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

		$product_id = wc_get_product_id_by_sku( $sku );

		delete_transient( $product_id . '_spro_product' );

		if ( false === ( get_transient( $product_id . '_spro_product' ) ) ) {

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

			set_transient( $product_id . '_spro_product', $response_body, 24 * HOUR_IN_SECONDS );

		}

		return get_transient( $product_id . '_spro_product' );

	}

	/**
	 * WooCommerce Payment Complete Hook
	 * 
	 * @since 1.0.0
	 * @param Integer $order_id Order ID.
	 */
	public function spro_payment_complete( $order_id ) {

		if ( !is_user_logged_in() ) {
			return;
		}

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
		$cc_expiry = get_post_meta( $order_id, '_card_expiry', true );
		$cc_number = get_post_meta( $order_id, '_card_number', true );
		$cc_type = get_post_meta( $order_id, '_card_type', true );
		$cc_month = substr( $cc_expiry, 0, 2 );
		$cc_year = '20' . substr( $cc_expiry, -2 );
		$cc_last4  = substr( $cc_number, -4);
		$customer_profile_id = get_user_meta( $customer_id, 'CustNum', true );
		$shipping_method = @array_shift( $order->get_shipping_methods() );;
		$shipping_method_id = $shipping_method['method_id'];
		$is_subscription_order = false;
		
		// Ebiz Data
		$ebiz_data = get_post_meta( $order_id, '[EBIZCHARGE]|methodid|refnum|authcode|avsresultcode|cvv2resultcode|woocommerceorderid', true );
		$ebiz_data_array = explode('|', $ebiz_data);
		$ebiz_payment_method = $ebiz_data_array[1];
		$ebiz_ref_num = $ebiz_data_array[2];

		// Check if this is a subscription order
		foreach( $order->get_items() as $item_id => $line_item ) {
			$type = wc_get_order_item_meta( $item_id, 'Delivery Type', true );
			
			if ( $type == 'regular' ) {
				$is_subscription_order = true;
			}
		}

		// Don't run if ebiz data is not present or if it's not a subscription order
		if ( $ebiz_data == '' || $is_subscription_order == false ) {
			return;
		}

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

		}

		$data = array(
			'payment_token' => $ebiz_payment_method,
		);

		// Check if payment profile exists if cc data is empty
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

		if ( empty( $payment_profile_array ) ) {

			if ( $cc_number == '' ) {
				
				// Get CC Data from eBizCharge
				$cc_data = $this->spro_get_ebiz_cc_data( $customer_profile_id, $ebiz_payment_method );

				$cc_last4 = $cc_data['cc_last4'];
				$cc_month = $cc_data['cc_month'];
				$cc_year = $cc_data['cc_year'];
				$cc_type = $cc_data['cc_type'];
			}

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

			update_post_meta( $order_id, 'spro_subscription_id', $sp_payment_profile_id );


		} else {


			// Payment profile found, update existing profile instead of creating new one.
			$sp_payment_profile_id = $payment_profile_array[0]->id;

			// Get CC Data from eBizCharge
			$cc_data = $this->spro_get_ebiz_cc_data( $customer_profile_id, $ebiz_payment_method );

			$cc_last4 = $cc_data['cc_last4'];
			$cc_month = $cc_data['cc_month'];
			$cc_year = $cc_data['cc_year'];
			$cc_type = $cc_data['cc_type'];
			
			// Update existing payment profile
			$response = $client->post( SPRO_BASE_URL . '/services/v2/vault/paymentprofiles/' . $sp_payment_profile_id . '.json', [
				'verify' => false,
				'auth' => [SPRO_CLIENT_ID, SPRO_CLIENT_SECRET],
				'json' => ['payment_profile' =>
					array(
						'creditcard_last_digits' => $cc_last4,
						'creditcard_month' => $cc_month,
						'creditcard_year' => $cc_year,
						'creditcard_type' => $cc_type,
						'billing_address' => $billing_address
					)
				]
			]);

		}

		// Create the subscriptions for each subscription product
		foreach( $order->get_items() as $item_id => $line_item ) {

			$item_data = $line_item->get_data();
			$product = $line_item->get_product();
			$sku = $product->get_sku();
			$product_name = $product->get_name();
			$item_quantity = $line_item->get_quantity();
			$item_total = $product->get_price();

			$is_subscription_product = get_post_meta( $product->get_id(), '_spro_product', true );

			if ( $is_subscription_product == 'yes' ) {

				$type = wc_get_order_item_meta( $item_id, 'Delivery Type', true );
				$frequency = wc_get_order_item_meta( $item_id, 'Delivery Frequency', true );

				if ( $type == 'regular' ) {

					if ( $shipping_address['first_name'] != '' ) {
						$subscription_address = $shipping_address;
					} else {
						$subscription_address = $billing_address;
					}

					$response = $client->post( SPRO_BASE_URL . '/services/v2/subscription.json', [
						'verify' => false,
						'auth' => [SPRO_CLIENT_ID, SPRO_CLIENT_SECRET],
						'json' => ['subscription' => 
							array(
								'customer_id' => $spro_customer_id,
								'payment_profile_id' => $sp_payment_profile_id,
								'product_sku' => $sku,
								'requires_shipping' => true,
								'shipping_method_code' => $shipping_method_id,
								'shipping_address' => $subscription_address,
								'qty' => $item_quantity,
								'next_order_date' => date("F j, Y"),
								'first_order_already_created' => true,
								'interval' => $frequency
							)
						]
					]);
					
					$response_body = json_decode( $response->getBody() );

					// Save the subscription id to the order for use in the order callback function
					wc_update_order_item_meta( $item_id, 'spro_subscription_id', $response_body->subscription->id );

				}

			}

		}

	}

	/**
	 * Get Credit Card Data From Ebizcharge
	 */
	public function spro_get_ebiz_cc_data( $customer_profile_id, $ebiz_payment_method ) {

		$client_a = new SoapClient('https://soap.ebizcharge.net/eBizService.svc?singleWsdl');

		$ebiz_settings = get_option( 'woocommerce_ebizcharge_settings' );
	
		$ebiz_security_id = $ebiz_settings['securityid'];
		$ebiz_user_id = $ebiz_settings['username'];
		$ebiz_password = $ebiz_settings['password'];

		$securityToken = array(
			'SecurityId' => $ebiz_security_id,
			'UserId' => $ebiz_user_id,
			'Password' => $ebiz_password
		);

		try {
			
			$res = $client_a->GetCustomerPaymentMethodProfile(
				array(
					'securityToken' => $securityToken,
					'customerToken' => $customer_profile_id,
					'paymentMethodId' => $ebiz_payment_method
				)
			);

			$payment_data = $res->GetCustomerPaymentMethodProfileResult;
			$expiration = $payment_data->CardExpiration;

			$cc_last4 = substr( $payment_data->CardNumber, -4 );
			$cc_month = substr( $expiration, -2 );
			$cc_year = substr( $expiration, 0, 4 );
			$cc_type = $payment_data->CardType;

			return array(
				'cc_last4' => $cc_last4,
				'cc_month' => $cc_month,
				'cc_year' => $cc_year,
				'cc_type' => $cc_type
			);

		
		} catch ( SoapFault $e ) {
			
			die( "Payment profile not found: " . $e->getMessage() );

		}
	}

    /**
     * @param $request
     * @param string $sharedSecret
     *
     * @return bool
     */
    public function validate_request_hmac( $request, $sharedSecret ) {

        // Get signature from request header
        $hmacSignature = $request->get_header( SP_HMAC_HEADER );

		// Get request body (JSON string)
		$body = $request->get_body();

        // Calculate the hash of body using shared secret and SHA-256 algorithm
        $calculatedHash = hash_hmac( 'sha256', $body, $sharedSecret, false );

        // Compare signature using secure compare method
        return hash_equals( $calculatedHash, $hmacSignature );

    }

	/**
	 * spro_rest_testing_endpoint
	 * @return WP_REST_Response
	 */
	function spro_order_callback_ebiz( $data ) {

		// Shared Secret Validation
		$secret = get_option( 'spro_settings_callback_secret' );

		if ( !$this->validate_request_hmac( $data, $secret ) ) {
			return new WP_REST_Response( array( 'error' => 'Invalid Shared Secret', 'secret' => $secret ), 401 );
		}
		
		// Get Order Data From Subscribe Pro
		$order_data = $data->get_json_params();

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

		// Return error if order creation failed
		if ( is_wp_error( $order ) ) {

			$error_string = $order->get_error_message();

			$response = new WP_REST_Response( array( 
				'itemErrors' => array(
					array(
						'subscriptionId' => strval( $order_data['items'][0]['subscription']['id'] ),
						'errorMessage' => $error_string
					)
				)
			), 409 );

			return $response;
			
		}

		// Set Addresses
		$order->set_address( $billing_address, 'billing' );
		$order->set_address( $shipping_address, 'shipping' );

		// Add products to the order
		$products_array = array();
		$item_error_array = array();
		$subscription_ids = array();

		foreach ( $order_data['items'] as $item ) {
			
			// Item Data
			$sku = $item['productSku'];
			$qty = $item['qty'];
			$product_id = wc_get_product_id_by_sku( $sku );
			$product = wc_get_product( $product_id );

			if ( $product == null || $product == false ) {

				$error_array = array(
					'subscriptionId' => strval( $item['subscription']['id'] ),
					'errorMessage' => 'Invalid SKU, product not found'
				);

				array_push( $item_error_array, $error_array );

			} else {

				$order->add_product( $product, $qty );
				array_push( $subscription_ids, strval( $item['subscription']['id'] ) );	
			
			}

		}

		// Create Product Array For Response
		$i = 0;

		foreach ( $order->get_items() as $item_key => $item ) {

			// Item ID is directly accessible from the $item_key in the foreach loop or
			$item_id = $item->get_id();

			## Using WC_Order_Item_Product methods ##
			$product = $item->get_product(); // Get the WC_Product object
			
			// Add delivery type
			$item->add_meta_data( __( 'Delivery Type', 'spro' ), 'regular', true );

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
				'subscriptionId' => $subscription_ids[$i]
			);

			array_push( $products_array, $product );

			$i++;

		}

		// Add shipping to order
		$shipping_item = new WC_Order_Item_Shipping();
		$shipping_item->set_method_id( $order_data['shippingMethodCode'] );
		$order->add_item( $shipping_item );

		// Calculate totals
		$order->calculate_totals();

		$customer_profile_id = get_user_meta( $order_data["platformCustomerId"], 'CustNum', true );

		// Charge payment profile
		$charge = $this->ebizChargeCustomerProfile( $customer_profile_id, $order_data['payment']['paymentToken'], $order->get_total(), $billing_address['postcode'] );

		// Prepare return data
		$return_data = array(
			"orderNumber" => strval( $order->get_id() ),
			"orderDetails" => array(
				"customerId" => strval( $order_data["customerId"] ),
				"customerEmail" => $order_data["customerEmail"],
				"platformCustomerId" => strval( $order_data["platformCustomerId"] ),
				"platformOrderId" => strval( $order->get_id() ),
				"orderNumber" => strval( $order->get_id() ),
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
			),
		);

		if ( !empty( $products_array ) ) {
			$return_data['orderDetails']['items'] = $products_array;
		} else {

			// Remove the order
			wp_delete_post( $order_id, true );

			// Return 409 with product errors if no products were added
			$response = new WP_REST_Response( array( 
				'itemErrors' => $item_error_array
			), 409 );

			return $response;
		}

		// Prepare return data and update order with ebiz data if payment was successful
		if ( $charge['status'] ) {
	
			// Update order status
			$trans_id = $charge['trans_id'];
			
			$return_data['orderDetails']['salesOrderToken'] = strval( $trans_id );
			
			update_post_meta(  $order->get_id(), '_transaction_id', $trans_id );
			update_post_meta(  $order->get_id(), '_payment_method_title', 'Credit Card' );
			$order->update_status( 'processing', 'Ebiz charge completed successfully, transaction ID: ' . $trans_id );

			// Return response
			if ( !empty( $item_error_array) ) {

				// Add any errors generated while adding products to the order
				$return_data['itemErrors'] = array();

				foreach( $item_error_array as $error ) {
					array_push( $return_data['itemErrors'], $error );
				}

				// Partial Success
				$response = new WP_REST_Response( $return_data, 202 );

			} else {

				// Success
				$response = new WP_REST_Response( $return_data, 201 );
			
			}

		} else {

			// Update order status
			$order->update_status( 'failed', $charge['error'] );

			$return_data['itemErrors'] = array();

			// Add charge error to return data
			$charge_error = array(
				'subscriptionId' => strval( $order_data['items'][0]['subscription']['id'] ),
				'errorMessage' => $charge['error']
			);

			array_push( $return_data['itemErrors'], $charge_error );

			// Add any errors generated while adding products to the order
			if ( !empty( $item_error_array ) ) {

				foreach( $item_error_array as $error ) {

					$return_data['itemErrors'] = array();
					array_push( $return_data['itemErrors'], $error );

				}

			}

			// Partial Success
			$response = new WP_REST_Response( $return_data, 202 );

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

		$ebiz_settings = get_option( 'woocommerce_ebizcharge_settings' );
	
		$ebiz_security_id = $ebiz_settings['securityid'];
		$ebiz_user_id = $ebiz_settings['username'];
		$ebiz_password = $ebiz_settings['password'];

		$securityToken = array(
			'SecurityId' => $ebiz_security_id,
			'UserId' => $ebiz_user_id,
			'Password' => $ebiz_password
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

	function spro_unset_email_data($formatted_meta, $item){
		// Only on emails notifications
		if( is_admin() || is_wc_endpoint_url() )
			return $formatted_meta;
	
		foreach( $formatted_meta as $key => $meta ) {
			if( in_array( $meta->key, array('Delivery Type', 'spro_subscription_id') ) )
				unset($formatted_meta[$key]);
		}
		return $formatted_meta;
	}

}