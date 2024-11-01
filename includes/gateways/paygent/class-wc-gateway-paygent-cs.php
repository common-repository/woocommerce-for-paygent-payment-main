<?php
/**
 * Paygent Payment Gateway
 *
 * Provides a Paygent Convenience Store Payment Gateway.
 *
 * @class 		WC_Paygent_Gateway
 * @extends		WC_Gateway_Paygent_CS
 * @version		2.0.7
 * @package		WooCommerce/Classes/Payment
 * @author		Artisan Workshop
 */
use ArtisanWorkshop\WooCommerce\PluginFramework\v2_0_13 as Framework;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class WC_Gateway_Paygent_CS extends WC_Payment_Gateway {

    /**
     * Convenience store name array
     *
     * @var array
     */
    public $cs_stores;

    /**
     * Convenience store payment slip number label
     *
     * @var array
     */
    public $cs_slip_label;

    /**
     * Convenience store payment description to customer
     *
     * @var array
     */
    public $cs_description_to_customer;

    /**
     * Framework.
     *
     * @var object
     */
    public $jp4wc_framework;

    /**
     * Debug mode
     *
     * @var string
     */
    public $debug;

    /**
     * Test mode
     *
     * @var string
     */
    public $test_mode;

    /**
     * Set gmopg request class
     *
     * @var stdClass
     */
    public $paygent_request;

    /**
	 * Constructor for the gateway.
	 *
	 * @access public
	 * @return void
	 */
	public function __construct() {

		$this->id                = 'paygent_cs';
		$this->has_fields        = false;
		$this->order_button_text = sprintf(__( 'Proceed to %s', 'woocommerce-for-paygent-payment-main' ), __('Convenience Store', 'woocommerce-for-paygent-payment-main' ));

        // Create plugin fields and settings
		$this->init_form_fields();
		$this->init_settings();
		$this->method_title       = __( 'Paygent Convenience Store Payment Gateway', 'woocommerce-for-paygent-payment-main' );
		$this->method_description = __( 'Allows payments by Paygent Convenience Store in Japan.', 'woocommerce-for-paygent-payment-main' );

        // When no save setting error at chackout page
		if(is_null($this->title)){
			$this->title = __( 'Please set this payment at Control Panel! ', 'woocommerce-for-paygent-payment-main' ).$this->method_title;
		}

		// Get setting values
		foreach ( $this->settings as $key => $val ) $this->$key = $val;

        // Set Convenience Store
		$this->cs_stores = array();
		if(isset($this->setting_cs_se)){
			if($this->setting_cs_se =='yes') $this->cs_stores = array_merge($this->cs_stores, array('00C001' => __( 'Seven Eleven', 'woocommerce-for-paygent-payment-main' )));
			if($this->setting_cs_lm =='yes') $this->cs_stores = array_merge($this->cs_stores, array('00C002' => __( 'Lawson', 'woocommerce-for-paygent-payment-main' ), '00C004' => __( 'Mini Stop', 'woocommerce-for-paygent-payment-main' )));
			if($this->setting_cs_f =='yes') $this->cs_stores = array_merge($this->cs_stores, array('00C005' => __( 'Family Mart', 'woocommerce-for-paygent-payment-main' )));
			if($this->setting_cs_sm =='yes') $this->cs_stores = array_merge($this->cs_stores, array('00C016' => __( 'Seicomart', 'woocommerce-for-paygent-payment-main' )));
			if($this->setting_cs_ctd =='yes') $this->cs_stores = array_merge($this->cs_stores, array('00C014' => __( 'Daily Yamazaki', 'woocommerce-for-paygent-payment-main' )));
		}

        //Set Convenience store payment slip number label
        $this->cs_slip_label = array(
            '00C001' => __( 'Payment slip number', 'woocommerce-for-paygent-payment-main' ),//Seven Eleven
            '00C002' => __( 'Customer number', 'woocommerce-for-paygent-payment-main' ),//Lawson
            '00C004' => __( 'Customer number', 'woocommerce-for-paygent-payment-main' ),//Mini Stop
            '00C005' => __( 'Receiving number', 'woocommerce-for-paygent-payment-main' ),//Family Mart
            '00C016' => __( 'Payment receipt number', 'woocommerce-for-paygent-payment-main' ),//Seicomart
            '00C014' => __( 'Online payment number', 'woocommerce-for-paygent-payment-main' ),//Daily Yamazaki
        );

        $this->cs_description_to_customer = array(
            '00C001' => __( 'Please print out the payment slip (or write down the "Payment slip number (13 digits)") and go to the 7-Eleven store. Please say "Pay for the Internet payment" at the cash register and pay with the payment slip, or give the "Payment slip number" and pay.', 'woocommerce-for-paygent-payment-main' ),
            '00C002' => sprintf(__( 'Make a note of the reported %s and go to Lawson or Ministop store. Please enter the number into the multimedia terminal Loppi or MINISTOPLoppi installed in the store and pay at the cash register with the application ticket issued.', 'woocommerce-for-paygent-payment-main' ), __( '"Customer number" and "confirmation number(400008)"', 'woocommerce-for-paygent-payment-main' )),
            '00C004' => sprintf(__( 'Make a note of the reported %s and go to Lawson or Ministop store. Please enter the number into the multimedia terminal Loppi or MINISTOPLoppi installed in the store and pay at the cash register with the application ticket issued.', 'woocommerce-for-paygent-payment-main' ), __( '"Customer number" and "confirmation number(400008)"', 'woocommerce-for-paygent-payment-main' )),
            '00C005' => __( 'Make a note of the notified "Receiving number" and go to the FamilyMart store. Please enter the number into the Fami port, a multimedia terminal installed in the store, and pay at the cash register with the application ticket issued.', 'woocommerce-for-paygent-payment-main' ),
            '00C016' => __( 'Make a note of the "Payment receipt number" notified and go to the Seicomart store. Please enter the number in the multimedia terminal club station installed in the store and pay at the cash register with the application ticket issued.', 'woocommerce-for-paygent-payment-main' ),
            '00C014' => __( 'Make a note of the notified "Online payment number" and go to the Daily Yamazaki store. Please say "Online payment" at the cash register, enter the "Online payment number" on the cashier\'s customer screen, and pay.', 'woocommerce-for-paygent-payment-main' )
        );

		//Set JP4WC framework
        $this->jp4wc_framework = new Framework\JP4WC_Plugin();

        include_once( 'includes/class-wc-gateway-paygent-request.php' );
        $this->paygent_request = new WC_Gateway_Paygent_Request();

        //Set Test mode.
        $this->test_mode = get_option('wc-paygent-testmode');

        // Actions
		add_action( 'woocommerce_receipt_paygent_cv',                              array( $this, 'receipt_page' ) );
		add_action( 'woocommerce_update_options_payment_gateways',              array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		// Customer Emails
        if ( 'yes' === $this->enabled ) {
            add_action('woocommerce_thankyou_' . $this->id, array($this, 'paygent_cs_thankyou'), 10, 1);
            add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 3);
            add_filter('paygent_cs_slip_label', array($this, 'change_slip_label'));
            add_filter('paygent_cs_description_to_customer', array($this, 'change_description_to_customer'));
        }
        // Custom Thank you page
        if ( 'yes' === $this->enabled ) {
            add_filter( 'woocommerce_thankyou_order_received_text', array( $this, 'paygent_cs_order_received_text' ), 10, 2 );
        }
	}
	/**
	 * Initialize Gateway Settings Form Fields.
	*/
	function init_form_fields() {

		$this->form_fields = array(
			'enabled'     => array(
				'title'       => __( 'Enable/Disable', 'woocommerce-for-paygent-payment-main' ),
				'label'       => __( 'Enable paygent Convenience Store Payment', 'woocommerce-for-paygent-payment-main' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no'
			),
			'title'       => array(
				'title'       => __( 'Title', 'woocommerce-for-paygent-payment-main' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-for-paygent-payment-main' ),
				'default'     => __( 'Convenience Store', 'woocommerce-for-paygent-payment-main' )
			),
			'description' => array(
				'title'       => __( 'Description', 'woocommerce-for-paygent-payment-main' ),
				'type'        => 'textarea',
				'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-for-paygent-payment-main' ),
				'default'     => __( 'Pay at Convenience Store via Paygent.', 'woocommerce-for-paygent-payment-main' )
			),
			'order_button_text' => array(
				'title'       => __( 'Order Button', 'woocommerce-for-paygent-payment-main' ),
				'type'        => 'text',
				'description' => __( 'This controls the order button which the user sees during checkout.', 'woocommerce-for-paygent-payment-main' ),
				'default'     => sprintf(__( 'Proceed to %s', 'woocommerce-for-paygent-payment-main' ), __('Convenience Store', 'woocommerce-for-paygent-payment-main' )),
			),
            'order_received_text' => array(
                'title'       => __( 'Thank you page description', 'woocommerce-for-paygent-payment-main' ),
                'type'        => 'textarea',
                'description' => __( 'This controls the description displayed on the thank you page.', 'woocommerce-for-paygent-payment-main' ),
                'default'     => __( 'Thank you. Your order has been received. Please pay at the convenience store by the specified method.', 'woocommerce-for-paygent-payment-main' ),
            ),
			'setting_cs_se' => array(
				'title'       => __( 'Set Convenience Store', 'woocommerce-for-paygent-payment-main' ),
				'id'          => 'wc-paygent-cs-se',
				'type'        => 'checkbox',
				'label'       => __( 'Seven Eleven', 'woocommerce-for-paygent-payment-main' ),
				'default'     => 'yes',
			),
			'setting_cs_lm' => array(
				'id'          => 'wc-paygent-cs-lm',
				'type'        => 'checkbox',
				'label'       => __( 'Lowson & Mini Stop', 'woocommerce-for-paygent-payment-main' ),
				'default'     => 'yes',
			),
			'setting_cs_f' => array(
				'id'          => 'wc-paygent-cs-f',
				'type'        => 'checkbox',
				'label'       => __( 'Family Mart', 'woocommerce-for-paygent-payment-main' ),
				'default'     => 'yes',
			),
			'setting_cs_sm' => array(
				'id'          => 'wc-paygent-cs-sm',
				'type'        => 'checkbox',
				'label'       => __( 'Seicomart', 'woocommerce-for-paygent-payment-main' ),
				'default'     => 'yes',
			),
			'setting_cs_ctd' => array(
				'id'          => 'wc-paygent-cs-ctd',
				'type'        => 'checkbox',
				'label'       => __( 'Daily Yamazaki', 'woocommerce-for-paygent-payment-main' ),
				'default'     => 'yes',
				'description' => sprintf( __( 'Please check them you are able to use Convenience Store', 'woocommerce-for-paygent-payment-main' )),
			),
            'payment_limit_date' => array(
                'title'       => __( 'Payment limit date', 'woocommerce-for-paygent-payment-main' ),
                'id'          => 'wc-paygent-payment-limit-date',
                'type'        => 'number',
                'default'     => 30,
                'description' => sprintf( __( 'Please input the number of day for payment limit date. Default is 30 days.', 'woocommerce-for-paygent-payment-main' )),
            ),
            'cs_connection_type' => array(
                'title'       => __( 'Convenience store connection type', 'woocommerce-for-paygent-payment-main' ),
                'id'          => 'wc-paygent-connection-type',
                'type'        => 'select',
                'default'     => 'a',
                'description' => sprintf( __( 'Please select the convenience store connection type specified by Paygent. Most contracts are type A.', 'woocommerce-for-paygent-payment-main' )),
                'options'     => array(
                    'a'       => __( 'Type A', 'woocommerce-for-paygent-payment-main' ),
                    'd'       => __( 'Type D', 'woocommerce-for-paygent-payment-main' )
                )
            ),
            'debug' => array(
                'title'       => __( 'Debug Mode', 'woocommerce-for-paygent-payment-main' ),
                'type'        => 'checkbox',
                'label'       => __( 'Enable Debug Mode', 'woocommerce-for-paygent-payment-main' ),
                'default'     => 'no',
                'description' => __( 'Save debug data using WooCommerce logging.', 'woocommerce-for-paygent-payment-main' ),
            ),
		);
	}

	function cs_select() {
		?><select name="cvs_company_id">
		<?php foreach($this->cs_stores as $num => $value){?>
		<option value="<?php echo $num; ?>"><?php echo $value;?></option>
	<?php }?>
		</select><?php 
	}
	/**
	 * UI - Payment page fields for paygent Payment.
	*/
	function payment_fields() {
	// Description of payment method from settings
		if ( $this->description ) { ?>
			<p><?php echo $this->description; ?></p>
		<?php } ?>
		<fieldset  style="padding-left: 40px;">
		<p><?php _e( 'Please select Convenience Store where you want to pay', 'woocommerce-for-paygent-payment-main' );?></p>
		<?php $this->cs_select(); ?>
		</fieldset>
	<?php }

	/**
	 * Process the payment and return the result.
	 */
	function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		$send_data = array();

		// Send request and get response from server
		// Common header
		$telegram_kind = '030';//Conviniense Store Payment
        $prefix_order = get_option( 'wc-paygent-prefix_order' );
        if($prefix_order){
            $send_data['trading_id'] = $prefix_order.$order_id;
        }else{
            $send_data['trading_id'] = 'wc_'.$order_id;
        }
		$send_data['payment_id'] = '';// Same as telegram kind

		$send_data['payment_amount'] = $order->get_total();
		// Customer Name
        $send_data['customer_family_name'] = $order->get_billing_last_name();
        $send_data['customer_name'] = $order->get_billing_first_name();
        $send_data['customer_tel'] = str_replace("-","",$order->get_billing_phone());

        $send_data['payment_limit_date'] = $this->payment_limit_date;// Payment limit date

		$send_data['cvs_company_id'] = $this->get_post( 'cvs_company_id' );// Convenience Store Company ID
		$send_data['sales_type'] = 1;// Payment before shipping

		$response = $this->paygent_request->send_paygent_request($this->test_mode, $order, $telegram_kind, $send_data, $this->debug);
		$result_array = $response['result_array'];

		// Check response
		if ( $response['result'] == 0 ) {
		    // Success
            $cvs_id = wc_clean( $this->get_post( 'cvs_company_id' ) );
            $order->add_meta_data('_paygent_cvs_id', $cvs_id, true);
            $order->add_meta_data('_paygent_receipt_number', $response['result_array'][0]['receipt_number'], true);
            $order->add_meta_data('_paygent_payment_limit_date', $response['result_array'][0]['payment_limit_date'], true);
            if($cvs_id == '00C001'){
                $order->add_meta_data('_paygent_receipt_print_url', $response['result_array'][0]['receipt_print_url'], true);
            }

            //set transaction id for Paygent Order Number
            $order->set_transaction_id(wc_clean( $response['result_array'][0]['payment_id'] ));
			// Mark as on-hold (we're awaiting the payment)
            $cs_stores = $this->cs_stores;
            $message = __( 'Awaiting Convenience store payment', 'woocommerce-for-paygent-payment-main' ).PHP_EOL;
            $message .= __( 'CVS Payment', 'woocommerce-for-paygent-payment-main').':'.$cs_stores[$cvs_id].PHP_EOL;
            $message .= __( 'Receipt number', 'woocommerce-for-paygent-payment-main' ).':'.$response['result_array'][0]['receipt_number'].PHP_EOL;
            $message .= __( 'Payment limit date', 'woocommerce-for-paygent-payment-main' ).':'.$response['result_array'][0]['payment_limit_date'].PHP_EOL;
            if($cvs_id == '00C001'){
                $message .= __( 'Receipt Print Url', 'woocommerce-for-paygent-payment-main' ).':'.$response['result_array'][0]['receipt_print_url'].PHP_EOL;
            }

			$order->update_status( 'on-hold', $message );

			// Reduce stock levels
            wc_reduce_stock_levels( $order_id );

			// Remove cart
			WC()->cart->empty_cart();

			// Return thank you redirect
			return array (
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order ),
			);
		} else if ( $response['result'] == 1 ) {//System Error
			// Other transaction error
			$order->add_order_note( __( 'Paygent Payment failed. Sysmte Error: ', 'woocommerce-for-paygent-payment-main' ) . $response['responseCode'] .':'. mb_convert_encoding($response['responseDetail'],"UTF-8","SJIS" ).':'.'wc_'.$order_id );
			wc_add_notice( __( 'Sorry, there was an error.', 'woocommerce-for-paygent-payment-main' ) .mb_convert_encoding($response['responseDetail'],"UTF-8","SJIS" ). ' Error Code:' . $response['responseCode'] , $notice_type = 'error');
		} else {
			// No response or unexpected response
			$order->add_order_note( __( "Paygent Payment failed. Some trouble happened.", 'woocommerce-for-paygent-payment-main' ). $response['result'] .':'.$response['responseCode'] .':'. mb_convert_encoding($response['responseDetail'],"UTF-8","SJIS").':'.'wc_'.$order_id );
			wc_add_notice(__( 'No response from payment gateway server. Try again later or contact the site administrator.', 'woocommerce-for-paygent-payment-main' ). $response['result'] , $notice_type = 'error');
		}
	}

	function receipt_page( $order ) {
		echo '<p>' . __( 'Thank you for your order.', 'woocommerce-for-paygent-payment-main' ) . '</p>';
	}

	/**
	 * Get post data if set
     *
     * @param string $name
     * @return mixed
     *
	 */
	private function get_post( $name ) {
		if ( isset( $_POST[ $name ] ) ) {
			return wc_clean($_POST[ $name ]);
		}
		return null;
	}

    /**
     * Add content to the WC emails For Convenient Information.
     *
     * @access public
     * @param WC_Order $order
     * @param bool $sent_to_admin
     * @return void
     */
    public function email_instructions( $order, $sent_to_admin ) {
	    $payment_method = $order->get_payment_method();
    	if ( ! $sent_to_admin && 'paygent_cs' === $payment_method && 'on-hold' === $order->status ) {
			if ( $this->instructions ) {
				echo wpautop( wptexturize( $this->instructions ) ) . PHP_EOL;
			}
			$this->paygent_cs_details($order);
		}
    }

    /**
     * Get Convinience Store Payment details and place into a list format
     *
     * @param object $order WC_Order
     */
    private function paygent_cs_details($order) {
		$cvs_array = $this->cs_stores;
        $res['usable_cvs_company_id'] = $order->get_meta('_paygent_cvs_id', true);
        $res['receipt_number'] = $order->get_meta('_paygent_receipt_number', true);
        $res['payment_limit_date'] = $order->get_meta('_paygent_payment_limit_date', true);
        $res['receipt_print_url'] = $order->get_meta('_paygent_receipt_print_url', true);
        $usable_cvs_company = $cvs_array[$res['usable_cvs_company_id']];
		$payment_limit_date = substr($res['payment_limit_date'], 0, 4).'/'.substr($res['payment_limit_date'], 4, 2).'/'.substr($res['payment_limit_date'], -2);

        $slip_label = apply_filters('paygent_cs_slip_label', $this->cs_slip_label);
        $descriptions = apply_filters('paygent_cs_description_to_customer', $this->cs_description_to_customer);

		echo '<h3>' . __( 'Convenience store payment details', 'woocommerce-for-paygent-payment-main' ) . '</h3>' . PHP_EOL;
		echo '<p>'.PHP_EOL;
        echo __( 'Convenience store : ', 'woocommerce-for-paygent-payment-main' ).$usable_cvs_company.'<br />'.PHP_EOL;
		echo $slip_label[$res['usable_cvs_company_id']] .' : '.$res['receipt_number'].'<br />'.PHP_EOL;
		if($res['usable_cvs_company_id'] == '00C001'){//Seven Eleven
			echo __( 'URL : ', 'woocommerce-for-paygent-payment-main' ).$res['receipt_print_url'].'<br />'.PHP_EOL;
		}
        echo  __( 'limit Date : ', 'woocommerce-for-paygent-payment-main') .$payment_limit_date.'<br />'.PHP_EOL
        .'</p>'.PHP_EOL;

        echo '<div style="border:1px solid #737373;padding:0 15px;"><h4>'. sprintf(__( 'For %s Users', 'woocommerce-for-paygent-payment-main' ),$usable_cvs_company).'</h4><p>'.PHP_EOL;
        echo $descriptions[$res['usable_cvs_company_id']];
        echo '</p></div>'.PHP_EOL;
		echo '<br />';
    }

    /**
     * Get Convini Payment details and place into a list format
     *
     * @param int $order_id
     */
    public function paygent_cs_thankyou( $order_id ){
        $order = wc_get_order($order_id);
        $cs_stores = $this->cs_stores;
        $cvs_id = $order->get_meta( '_paygent_cvs_id', true );
        $receipt_number = $order->get_meta( '_paygent_receipt_number', true );
        $receipt_print_url = $order->get_meta('_paygent_receipt_print_url', true);
        $payment_method = $order->get_payment_method();
        $slip_label = apply_filters('paygent_cs_slip_label', $this->cs_slip_label);

        if( $payment_method == 'paygent_cs' and isset($cvs_id)){
            echo '<header class="title"><h3>'.__('Payment Detail', 'woocommerce-for-paygent-payment-main').'</h3></header>';
            echo '<table class="shop_table order_details">';
            echo '<tr><th>'.__('CVS Payment', 'woocommerce-for-paygent-payment-main').'</th><td>'.$cs_stores[$cvs_id].'</td></tr>'.PHP_EOL;
            echo '<tr><th>'.$slip_label[$cvs_id].'</th><td>'.$receipt_number.'</td></tr>'.PHP_EOL;
            if($cvs_id == '00C001'){
                echo '<tr><th>'.__( 'Receipt Print Url', 'woocommerce-for-paygent-payment-main' ).'</th><td><a href="'.$receipt_print_url.'" target="_blank">'.$receipt_print_url.'</a></td></tr>'.PHP_EOL;
            }
            echo '<tr><th>'.__('How to pay at a convenience store', 'woocommerce-for-paygent-payment-main').'</th><td>'.$this->cs_description_to_customer[$cvs_id].'</td></tr>'.PHP_EOL;
            echo '</table>';
        }
    }

    /**
     * Change the label of type D for Lawson and Ministop
     *
     * @param array $label_array
     * @return array
     */
    function change_slip_label($label_array){
        if($this->cs_connection_type == 'd'){
            $label_array['00C002'] = __('Payment receipt number', 'woocommerce-for-paygent-payment-main');
            $label_array['00C004'] = __('Payment receipt number', 'woocommerce-for-paygent-payment-main');
        }
        return $label_array;
    }

    /**
     * Change the description of type D for Lawson and Ministop
     *
     * @param array $description_array
     * @return array
     */
    function change_description_to_customer($description_array){
        if(isset($this->cs_connection_type) && $this->cs_connection_type == 'd'){
            $description_array['00C002'] = __('Make a note of the reported "Payment receipt number" and go to Lawson or Ministop store. Please enter the number into the multimedia terminal Loppi or MINISTOPLoppi installed in the store and pay at the cash register with the application ticket issued.', 'woocommerce-for-paygent-payment-main');
            $description_array['00C004'] = __('Make a note of the reported "Payment receipt number" and go to Lawson or Ministop store. Please enter the number into the multimedia terminal Loppi or MINISTOPLoppi installed in the store and pay at the cash register with the application ticket issued.', 'woocommerce-for-paygent-payment-main');
        }
       return $description_array;
    }

    /**
     * Custom Paygent Convenience store payment order received text.
     *
     * @since 2.1.0
     * @param string   $text Default text.
     * @param WC_Order $order Order data.
     * @return string
     */
    public function paygent_cs_order_received_text( $text, $order ) {
        if ( $order && $this->id === $order->get_payment_method() ) {
            if(isset($this->order_received_text)){
                return wc_clean($this->order_received_text);
            }else{
                return esc_html__( 'Thank you. Your order has been received. Please pay at the convenience store by the specified method.', 'woocommerce-for-paygent-payment-main' );
            }
        }

        return $text;
    }
}
/**
 * Add the gateway to woocommerce
 *
 * @param array $methods
 * @return array
 */
function add_wc_paygent_cs_gateway( $methods ) {
	$methods[] = 'WC_Gateway_Paygent_CS';
	return $methods;
}
add_filter( 'woocommerce_payment_gateways', 'add_wc_paygent_cs_gateway' );

/**
 * Edit the available gateway to woocommerce
 *
 * @param array $methods
 * @return array
 */
function edit_available_gateways_cs( $methods ) {
	$currency = get_woocommerce_currency();
	if($currency !='JPY'){
	unset($methods['paygent_cs']);
	}
	return $methods;
}
add_filter( 'woocommerce_available_payment_gateways', 'edit_available_gateways_cs' );
