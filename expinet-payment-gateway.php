<?php 
/*
 * Plugin Name: Expinet Payment Gateway
 * Description: Credit card payments gateway to accept the payment on your woocommerce store.
 * Author: Crest Infosystems Pvt. Ltd.
 * Author URI: crestinfosystems.com
 * Version: 1.0
 */

if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) return;

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'expinet_settings_page');
function expinet_settings_page( $links ) {
	// Build and escape the URL.
    $url = add_query_arg( array(
        'page' => 'wc-settings',
        'tab' => 'checkout',
        'section' => 'expinet',
    ), get_admin_url() . 'admin.php' );
	// Create the link.
	$settings_link = "<a href='$url'>" . __( 'Settings' ) . '</a>';
	// Adds the link to the end of the array.
	array_unshift(
		$links,
		$settings_link
	);
	return $links;
}

add_filter( 'woocommerce_payment_gateways', 'add_custom_gateway_class' );
function add_custom_gateway_class( $gateways ) {
    $gateways[] = 'WC_Expinet_Gateway'; // payment gateway class name
    return $gateways;
}

add_action( 'plugins_loaded', 'initialize_expinet_class' );
function initialize_expinet_class() {
    class WC_Expinet_Gateway extends WC_Payment_Gateway {

        public function __construct() {

            $this->id = 'expinet'; // payment gateway ID
            $this->icon = ''; // payment gateway icon
            $this->has_fields = true; // for custom credit card form
            $this->title = __( 'Expinet Payment Gateway', 'epg' ); // vertical tab title
            $this->method_title = __( 'Expinet Payment Gateway', 'epg' ); // payment method name
            $this->method_description = __( 'Expinet Payment Gateway', 'epg' ); // payment method description
        
            $this->supports = array( 'default_credit_card_form' );
        
            // load backend options fields
            $this->init_form_fields();
        
            // load the settings.
            $this->init_settings();
            $this->title = $this->get_option( 'title' );
            $this->description = $this->get_option( 'description' );
            $this->enabled = $this->get_option( 'enabled' );
            $this->expinet_domain = $this->get_option( 'expinet_domain' );
            $this->expinet_location_id = $this->get_option( 'expinet_location_id' );
            $this->expinet_developer_id = $this->get_option( 'expinet_developer_id' );
            $this->expinet_service_id = $this->get_option( 'expinet_service_id' );
            $this->expinet_user_api_key = $this->get_option( 'expinet_user_api_key' );
            $this->expinet_user_id = $this->get_option( 'expinet_user_id' );
            $this->expinet_user_hash_key = $this->get_option( 'expinet_user_hash_key' );
            $this->apiVersion = 'v2';
            
            // Action hook to saves the settings
            if(is_admin()) {
                  add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            }
            
            // Action hook to load custom JavaScript
            add_action( 'wp_enqueue_scripts', array( $this, 'payment_gateway_scripts' ) );

            //Remove expinet payment gateway for the recuring payment
            add_filter( 'woocommerce_available_payment_gateways', array( $this, 'woocommerce_available_payment_gateways'), 10, 1 );
            
        }

        public function woocommerce_available_payment_gateways( $available_gateways ){
            if ( is_admin() ) return $available_gateways;
            if ( ! is_checkout() ) return $available_gateways;

            $restrict_type = array("subscription", "variable-subscription");
            $unset = false;
            foreach ( WC()->cart->get_cart_contents() as $key => $values ) {
                $product = wc_get_product($values['product_id']);
                if( in_array($product->get_type(), $restrict_type))
                {
                    $unset = true;
                    break;
                }
            }
            if ( $unset == true ) unset( $available_gateways['expinet'] );

            return $available_gateways;
        }

        public function init_form_fields(){

            $this->form_fields = array(
                'enabled' => array(
                    'title'       => __( 'Enable/Disable', 'epg' ),
                    'label'       => __( 'Enable Expinet Gateway', 'epg' ),
                    'type'        => 'checkbox',
                    'description' => __( 'This enable the Expinet gateway which allow to accept payment through creadit card.', 'epg' ),
                    'default'     => 'no',
                    'desc_tip'    => true
                ),
                'title' => array(
                    'title'       => __( 'Title', 'epg'),
                    'type'        => 'text',
                    'description' => __( 'This controls the title which the user sees during checkout.', 'epg' ),
                    'default'     => __( 'Credit Card', 'epg' ),
                    'desc_tip'    => true,
                ),
                'description' => array(
                    'title'       => __( 'Description', 'epg' ),
                    'type'        => 'textarea',
                    'description' => __( 'This controls the description which the user sees during checkout.', 'epg' ),
                    'default'     => __( 'Pay with your credit card via expinet payment gateway.', 'epg' ),
                    'desc_tip'    => true,
                ),
                'expinet_domain' => array(
                    'title'       => __( 'Expinet Domain', 'epg' ),
                    'type'        => 'text',
                    'description' => __( 'Expinet payment domain url', 'epg' ),
                    'default'     => 'https://api.sandbox.expinet.net',
                    'desc_tip'    => true,
                ),
                'expinet_location_id' => array(
                    'title'       => __( 'Location/Merchant Id', 'epg' ),
                    'type'        => 'text',
                    'description' => __( 'Expinet payment location id', 'epg' ),
                    'default'     => '',
                    'desc_tip'    => true,
                ),
                'expinet_developer_id' => array(
                    'title'       => __( 'Developer ID', 'epg' ),
                    'type'        => 'text',
                    'description' => __( 'Expinet payment developer id', 'epg' ),
                    'default'     => '',
                    'desc_tip'    => true,
                ),
                'expinet_service_id' => array(
                    'title'       => __( 'Service ID', 'epg' ),
                    'type'        => 'text',
                    'description' => __( 'Expinet payment service id', 'epg' ),
                    'default'     => '',
                    'desc_tip'    => true,
                ),
                'expinet_user_id' => array(
                    'title'       => __( 'User ID', 'epg' ),
                    'type'        => 'text',
                    'description' => __( 'Expinet payment user id', 'epg' ),
                    'default'     => '',
                    'desc_tip'    => true,
                ),
                'expinet_user_api_key' => array(
                    'title'       => __( 'API Key', 'epg' ),
                    'type'        => 'text',
                    'description' => __( 'Expinet payment user api key', 'epg' ),
                    'default'     => '',
                    'desc_tip'    => true,
                ),
                'expinet_user_hash_key' => array(
                    'title'       => __( 'User Hash Key', 'epg' ),
                    'type'        => 'text',
                    'description' => __( 'Expinet payment user hash key', 'epg' ),
                    'default'     => '',
                    'desc_tip'    => true,
                )
            );
        }

        public function payment_fields() {

            if ( $this->description ) {

                echo wpautop( wp_kses_post( $this->description ) );
            }
            
            ?>
        
            <fieldset id="wc-<?php echo esc_attr( $this->id ); ?>-cc-form" class="wc-credit-card-form wc-payment-form" style="background:transparent;">
        
            <div class="row">
                <div class="col-sm-6">
                    <?php do_action( 'woocommerce_credit_card_form_start', $this->id ); ?>

                    <div class="form-row form-row-wide validate-required">
                        <label>Card Number <span class="required">*</span></label>
                        <input id="card_number" type="text" name="card_number" placeholder="<?php _e('Enter credit card number', 'epg'); ?>" autocomplete="off">
                        <div class="form-control1 card_thumbs pl-3">
                            <img alt="Visa" data-type="visa" src="<?php echo plugins_url( 'images/visa.png', __FILE__ ); ?>">
                            <img alt="Master Card" data-type="mastercard" src="<?php echo plugins_url( 'images/master_card.png', __FILE__ ); ?>">
                            <img alt="Discover" data-type="discover" src="<?php echo plugins_url( 'images/discover.png', __FILE__ ); ?>">
                            <img alt="American Express" data-type="amex" src="<?php echo plugins_url( 'images/american_express.png', __FILE__ ); ?>">
                        </div>
                    </div>
                    <div class="form-row form-row-wide validate-required pd-0">
                        <div class="col-sm-6">
                            <label>Expiry Date <span class="required">*</span></label>
                            <input id="expiry_date" type="text" name="expiry_date" placeholder="<?php _e('MM/YY', 'epg'); ?>" autocomplete="off" maxlength="5">
                        </div>
                        <div class="col-sm-6">
                            <label>Secure Code (cvv) <span class="required">*</span><span class="help_info"><i class="la la-question-circle "></i> <p> The CVV Number ("Card Verification Value") on your credit card or debit card is a 3 digit number on VISA®, MasterCard® and Discover® branded credit and debit cards. On your American Express® branded credit or debit card it is a 4 digit numeric code. <br>
                                    <br> Providing your CVV number to an online merchant proves that you actually have the physical credit or debit card - and helps to keep you safe while reducing fraud.
                                </p></span></label>
                            <input class="form-control" name="ccv" placeholder="000/0000" type="text" maxlength="4">
                        </div>
                    </div>   
                    <div class="form-row form-row-wide validate-required">
                        <label for="cardName"><?php _e('Name on card', 'woocommerce'); ?>  <span class="required">*</span></label>
                        <input name="cardName" placeholder="<?php _e('Name on card', 'woocommerce'); ?>" type="text" require>
                    </div>     
                    <?php do_action( 'woocommerce_credit_card_form_end', $this->id ); ?>
                </div>                            
            </div>
        
            </fieldset>
        
            <?php
         
        }

        public function payment_gateway_scripts() {

            if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) ) {
                return;
            }
        
            if ( 'no' === $this->enabled ) {
                return;
            }

            wp_enqueue_style( 'epg_custom', plugins_url( 'css/custom.css', __FILE__ ) );
            wp_enqueue_script( 'epg_custom_js', plugins_url( 'js/custom.js', __FILE__ ), array( 'jquery') );
        }

        public function validate_fields(){
           
            if( empty( $_POST[ 'card_number' ]) ) {
                wc_add_notice(  'Card Number is required!', 'error' );
                $error = true;
            }

            if( empty( $_POST[ 'expiry_date' ]) ) {
                wc_add_notice(  'Expiry Month is required!', 'error' );
                $error = true;
            }
            else
            {
                $currentMonth = (int) date('m');
                $currentYear = (int) date('y');
                $expiry_dateAr = explode("/", $_POST[ 'expiry_date' ]);
                $passYear = (int) $expiry_dateAr[1];
                $passMonth = (int) $expiry_dateAr[0];
                if( $passYear < $currentYear){
                       
                        wc_add_notice(  'You card is expired!', 'error' );
                        $error = true;
                }
                else if( $passYear == $currentYear)
                {
                    if( $passMonth <= $currentMonth)
                    {
                      
                        wc_add_notice(  'You card is expired!', 'error' );
                        $error = true;
                    }
                }
            }

            if( empty( $_POST[ 'ccv' ]) ) {
                wc_add_notice(  'CCV is required!', 'error' );
                $error = true;
            }
            if( empty( $_POST[ 'cardName' ]) ) {
                wc_add_notice(  'Name on card is required!', 'error' );
                $error = true;
            }
            else
            {
                if ( !preg_match('/^[A-Za-z]+$/', str_replace(" ","",$_POST['cardName']) ) )
                {
                    wc_add_notice(  'Name on card must be alphabetic values.', 'error' );
                    $error = true;
                }
            }

            if( $error )
            {
                return false;
            }
            else
            {
                return true;
            }
         
        }

        public function process_payment( $order_id ) {

            global $woocommerce;
         
            // get order detailes
            $order = wc_get_order( $order_id );
         
            $Trident_POS_API = new Trident_POS_API();

            $card_number = str_replace(" ", "",$_REQUEST['card_number']);
            $expiry_date = str_replace("/", "", $_REQUEST['expiry_date']);
            $shipping = $order->get_shipping_total();
            $amount = $order->get_total();
			$name = $_REQUEST['cardName'];
            
            $api_request = array();
            $api_request["action"] = 'sale';  
            $api_request["payment_method"] = 'cc'; 
			$api_request["account_holder_name"] = $name;
            $api_request["account_number"] = $card_number;  
            $api_request["exp_date"] = $expiry_date;  
            $api_request["transaction_amount"] = $amount;  
            $api_request["location_id"] = $this->expinet_location_id;  
    
            $req['transaction'] = $api_request;
            $request = json_encode($req);
    
            $endPoint = 'transactions';
            $response = $this->apiCall($endPoint,$request);
            if( $response['status'] == "success" && !isset($response['data']['errors']))
            {
                    // we received the payment
                    $order->payment_complete();
                    $order->reduce_order_stock();
                    
                    // notes to customer
                    $order->add_order_note( 'Transaction id is '.$response['data']['transaction']['id'] );   
                    $order->update_meta_data( '_transaction_id', $response['data']['transaction']['id'] );
                    update_post_meta( $order_id, '_transaction_id', $response['data']['transaction']['id'] );
                    $order->update_meta_data( '_payment_response', $response );

                    // empty cart
                    $woocommerce->cart->empty_cart();
         
                    // redirect to the thank you page
                    return array(
                        'result' => 'success',
                        'redirect' => $this->get_return_url( $order )
                    );
         
            } else {
                $msg = array();
                foreach($response["data"]['errors'] as $key => $error)
                {
                    array_push($msg, $error[0]);
                }
                wc_add_notice( implode("\n",$msg), 'error' );
                return;
            }
         
        }

        public function apiCall( $endPoint = '',$request = array(), $method = 'POST' ){

            $domain = $this->expinet_domain .'/'. $this->apiVersion .'/'.$endPoint;
            $requestLog['url'] = $domain;
            $requestLog['request'] = json_decode($request,true);

            $curl = curl_init();
            curl_setopt_array($curl, array(
              CURLOPT_URL => $domain,
              CURLOPT_RETURNTRANSFER => true,
              CURLOPT_ENCODING => "",
              CURLOPT_MAXREDIRS => 10,
              CURLOPT_TIMEOUT => 150,
              CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
              CURLOPT_CUSTOMREQUEST => $method,
              CURLOPT_POSTFIELDS => $request,
              CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json",
                "cache-control: no-cache",
                "developer-id: ".$this->expinet_developer_id,
                "user-api-key: ".$this->expinet_user_api_key,
                "user-id: ".$this->expinet_user_id
              )
            ));
            
            $response = curl_exec($curl);
            $errormsg = curl_error($curl);
            $errorCode = curl_errno($curl);
            $results = array();
            if ($errormsg) {
                $results['status'] = 'error';
                $results['data'] = $errormsg;
                $results['errorcodetxt'] = curl_error_codes($errorCode);
                $results =  $this->handleResponse(json_encode($results));
                if($results['valid'] == "fail")
                {
                    $results['status'] = 'success';
                    $epoint = ($endPoint == 'transactions') ? 'transaction' : 'routertransaction';
                    $results['data'][$epoint]['configuration_id'] = $results['errorcode'];
                }
            } else {

                $handleSucessRes = $this->handleResponse($response);
                if($handleSucessRes['valid'] == "success")
                {
                    $results['status'] = 'success';
                    $results['data'] = json_decode($response,true);
                }
                else
                {
                    $results['status'] = 'success';
    
                    if($endPoint == 'transactions')
                    {
                        $results['data']['transaction']['configuration_id'] = $handleSucessRes['errorcode'];
                    }
                    else
                    {
                        $results['data']['routertransaction']['configuration_id'] = $handleSucessRes['errorcode'];
                    }
                }
            }     
            curl_close($curl);    
            $results['request'] = $requestLog;

            $log = new WC_Logger();
            $log_entry = print_r( $requestLog, true );
            $log_entry .= 'Response : ' . print_r( $results, true );
            $log->log( 'expinet-orders', $log_entry );

            return $results;
        }

        public function handleResponse($response)
        {
            $response = json_decode($response,true);

            $return = [];
            
            if(isset($response['status']) && $response['status'] == 406)
            {
                $code =  5001;
            }
            else if(isset($response['status']) && $response['status'] == 401)
            {
                $code = 5002;
            }
            else if(isset($response['errors']) && isset($response['errors']['location_id'][0]) &&  $response['errors']['location_id'][0]== 'location_id provided is not valid')
            {
                $code =  5003;
            }
            else if(isset($response['errors']) && isset($response['errors']['terminal_id'][0]) &&  $response['errors']['terminal_id'][0]== 'Terminal not found')
            {
                $code =  5004;
            }
            else if(isset( $response['errors']['product_transaction_id'][0]) && $response['errors']['product_transaction_id'][0] == 'Product transaction not found' )
            {
                $code =  5005;
            }
            else if(isset( $response['errors']['transaction_api_id'][0]) && $response['errors']['transaction_api_id'][0] == 'transaction_api_id already exists in this location' )
            {
                $code =  5006;
            }
            else if(isset($response['status']) && $response['status'] == 'error')
            {
                if (isset($response['errorcodetxt'])) {
                    switch ($response['errorcodetxt']) {
                        case 'CURLE_OPERATION_TIMEDOUT':
                            $code =  5008; break;
                        case 'CURLE_COULDNT_CONNECT':
                            $code =  5009; break;
                        default:
                            $code =  5007; break;
                    }
                }
            }
            else
            {
                $return['valid'] = "success";
                return $return;
            }
            $return['valid'] = "fail";
            $return['errorcode'] = $code;
            return $return;   
        }
    }
}
?>