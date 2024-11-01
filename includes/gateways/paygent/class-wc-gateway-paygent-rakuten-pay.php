<?php
/**
 * Paygent Payment Gateway
 *
 * Provides a Paygent RakutenPay Payment Gateway.
 *
 * @class 		WC_Paygent
 * @extends		WC_Gateway_Paygent_RakutenPay
 * @version		2.2.8
 * @package		WooCommerce/Classes/Payment
 * @author		Artisan Workshop
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use ArtisanWorkshop\WooCommerce\PluginFramework\v2_0_13 as Framework;

class WC_Gateway_Paygent_RakutenPay extends WC_Payment_Gateway {
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
     * Set paygent request class
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

		$this->id                = 'paygent_rakutenpay';
		$this->has_fields        = false;
		$this->order_button_text = sprintf(__( 'Proceed to %s', 'woocommerce-for-paygent-payment-main' ), __('Rakuten Pay', 'woocommerce-for-paygent-payment-main' ));

        // Create plugin fields and settings
		$this->init_form_fields();
		$this->init_settings();
		$this->method_title       = __( 'Paygent Rakuten Pay Payment Gateway', 'woocommerce-for-paygent-payment-main' );
		$this->method_description = __( 'Allows payments by Paygent Rakuten Pay in Japan.', 'woocommerce-for-paygent-payment-main' );
		$this->supports = array(
			'products',
			'refunds',
		);
        // When no save setting error at checkout page
		if(is_null($this->title)){
			$this->title = __( 'Please set this payment at Control Panel! ', 'woocommerce-for-paygent-payment-main' ).$this->method_title;
		}
		// Get setting values
		foreach ( $this->settings as $key => $val ) $this->$key = $val;

        //Set JP4WC framework
        $this->jp4wc_framework = new Framework\JP4WC_Plugin();

        include_once( 'includes/class-wc-gateway-paygent-request.php' );
        $this->paygent_request = new WC_Gateway_Paygent_Request();

        //Set Test mode.
        $this->test_mode = get_option('wc-paygent-testmode');
        $this->environment = (get_option('wc-paygent-testmode') != 1) ? 'live': 'sandbox';

        // Actions
		add_action( 'woocommerce_update_options_payment_gateways', array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

        add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'paygent_rakutenpay_redirect_order') );
		add_filter( 'woocommerce_order_button_html', array( $this, 'paygent_rakuten_order_button_html' ) );
		add_action('woocommerce_thankyou_' . $this->id, array($this, 'paygent_rakutenpay_thankyou'), 10, 1);

//        add_action( 'woocommerce_order_status_completed', array( $this, 'order_paidy_status_completed') );
	}
	/**
	* Initialize Gateway Settings Form Fields.
	*/
	function init_form_fields() {

		$this->form_fields = array(
			'enabled'     => array(
				'title'       => __( 'Enable/Disable', 'woocommerce-for-paygent-payment-main' ),
				'label'       => __( 'Enable paygent Rakuten Pay Payment', 'woocommerce-for-paygent-payment-main' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no'
			),
			'title'       => array(
				'title'       => __( 'Title', 'woocommerce-for-paygent-payment-main' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-for-paygent-payment-main' ),
				'default'     => __( 'Rakuten PAY', 'woocommerce-for-paygent-payment-main' )
			),
			'description' => array(
				'title'       => __( 'Description', 'woocommerce-for-paygent-payment-main' ),
				'type'        => 'textarea',
				'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-for-paygent-payment-main' ),
				'default'     => sprintf(__( 'Pay with your %s via Paygent.', 'woocommerce-for-paygent-payment-main' ), __('Rakuten Pay', 'woocommerce-for-paygent-payment-main' )),
			),
			'order_button_text' => array(
				'title'       => __( 'Order Button Text', 'woocommerce-for-paygent-payment-main' ),
				'type'        => 'textarea',
				'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-for-paygent-payment-main' ),
				'default'     => sprintf(__( 'Proceed to %s', 'woocommerce-for-paygent-payment-main' ), __('Rakuten Pay', 'woocommerce-for-paygent-payment-main' )),
			),
			'button_type'       => array(
				'title'       => __( 'Button type', 'woocommerce-for-paygent-payment-main' ),
				'type'        => 'select',
				'class'       => 'wc-button-select',
				'description' => __( 'Select the button you want to display when making a payment decision.', 'woocommerce' ),
				'default'     => 'sale',
				'desc_tip'    => true,
				'options'     => array(
					'00'      => __( 'Red: 240px(width) 40px(height)', 'woocommerce-for-paygent-payment-main' ),
					'01'      => __( 'White: 240px(width) 40px(height)', 'woocommerce-for-paygent-payment-main' ),
					'02'      => __( 'Red: 165px(width) 30px(height)', 'woocommerce-for-paygent-payment-main' ),
					'03'      => __( 'White: 165px(width) 30px(height)', 'woocommerce-for-paygent-payment-main' )
				)
			),
            'debug' => array(
                'title'   => __( 'Debug Mode', 'woocommerce-for-paygent-payment-main' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable Debug Mode', 'woocommerce-for-paygent-payment-main' ),
                'default' => 'no',
                'description' => __( 'Save debug data using WooCommerce logging.', 'woocommerce-for-paygent-payment-main' ),
            ),
		);
	}

    /**
     * Process the payment and return the result
     *
     * @param int $order_id
     * @return array | mixed
     */
    public function process_payment( $order_id ) {
	    $order = wc_get_order( $order_id );
	    //Common header
	    $telegram_kind = '270';// Apply
	    $prefix_order = get_option( 'wc-paygent-prefix_order' );
	    if($prefix_order){
		    $send_data['trading_id'] = $prefix_order.$order_id;
	    }else{
		    $send_data['trading_id'] = 'wc_'.$order_id;
	    }
	    $send_data['payment_id'] = '';
	    $send_data['payment_amount'] = $order->get_total();
	    $send_data['merchandise_type'] = 1;
	    $send_data['pc_mobile_type'] = '0';
        if($this->jp4wc_framework->isSmartPhone()){
	        $send_data['pc_mobile_type'] = 4;
        }
        $send_data['button_type'] = $this->button_type;
	    $send_data['cancel_url'] = $order->get_cancel_order_url_raw();
	    $send_data['return_url'] = $this->get_return_url( $order );
	    // Set Product cost
        $items = $order->get_items();
        $i = 0;
	    $item_total = 0;
	    $error_flag = false;
	    $discount_flag = false;
        foreach ($items as $item){
			if($item['total'] > 0){
				($item['variation_id'] == 0)?$send_data['goods_id['.$i.']'] = $item['product_id']:$send_data['goods_id['.$i.']'] = $item['variation_id'];
				$send_data['goods['.$i.']'] = mb_convert_encoding($item['name'], 'SJIS', 'UTF-8' );
				$send_data['goods_price['.$i.']'] = $item['total']+$item['total_tax']/$item['quantity'];
				$send_data['goods_amount['.$i.']'] = $item['quantity'];
				$item_total += $send_data['goods_price['.$i.']']*$send_data['goods_amount['.$i.']'];
				if(is_float($send_data['goods_price['.$i.']'])){
					$error_flag = true;
				}
				if(isset($item['discount'])){
					$discount_flag = true;
				}
				$i++;
			}
        }
		// Set Shipping cost
		$shippings = $order->get_items('shipping');
		if(isset($shippings)){
			foreach ($shippings as $shipping){
				$shipping_total = $shipping->get_total() + $shipping->get_total_tax();
				if(isset($shipping_total) && $shipping_total != 0){
					$send_data['goods_id['.$i.']'] = 'shipping';
					$send_data['goods['.$i.']'] = mb_convert_encoding($shipping->get_method_title(), 'SJIS', 'UTF-8' );
					$send_data['goods_price['.$i.']'] = $shipping->get_total() + $shipping->get_total_tax();
					$send_data['goods_amount['.$i.']'] = 1;
					$i++;
				}
			}
		}
	    // Set Fee cost
	    $fees = $order->get_items('fee');
	    if(isset($fees)){
		    foreach ($fees as $fee){
			    $fee_total = $fee->get_total() + $fee->get_total_tax();
				if(isset($fee_total) && $fee_total != 0 ){
					$send_data['goods_id['.$i.']'] = 'fee';
					$send_data['goods['.$i.']'] = mb_convert_encoding('手数料', 'SJIS', 'UTF-8' );
					$send_data['goods_price['.$i.']'] = $fee->get_total() + $fee->get_total_tax();
					$send_data['goods_amount['.$i.']'] = 1;
				}
		    }
	    }
        if($error_flag){
	        wc_add_notice( __( 'Rakuten PAY cannot be used with this product due to consumption tax. Please contact the store.', 'woocommerce-for-paygent-payment-main' ), 'error' );
            return array(
	            'result' 	=> 'failure',
	            'redirect'	=> wc_get_checkout_url()
            );
        }elseif($discount_flag){
	        wc_add_notice( __( 'Rakuten PAY cannot be used if there is a coupon discount.', 'woocommerce-for-paygent-payment-main' ), 'error' );
            return array(
	            'result' 	=> 'failure',
	            'redirect'	=> wc_get_checkout_url()
            );
        }
	    $response = $this->paygent_request->send_paygent_request($this->test_mode, $order, $telegram_kind, $send_data, $this->debug);

	    if( isset($response['result']) and $response['result'] == 0 && isset($response['result_array']) ){
		    // Success
		    $order->set_transaction_id( $response['result_array'][0]['payment_id'] );
		    $order->add_meta_data('_paygent_order_id', $response['result_array'][0]['trading_id'], true );
		    $order->add_meta_data('_paygent_rakuten_html', $response['result_array'][0]['redirect_html'], true );
		    $order->save();
	    }else{
		    $this->paygent_request->error_response($response, $order);
		    return array(
			    'result' 	=> 'failure',
			    'redirect'	=> wc_get_checkout_url()
		    );
	    }
        // Return Payment page redirect
        return array(
            'result' 	=> 'success',
            'redirect'	=> $order->get_checkout_payment_url(true)
        );
    }

	/**
	 * Redirect HTML output on payment page
	 */
	public function paygent_rakutenpay_redirect_order( $order_id ){
		$order = wc_get_order($order_id);
		$html = $order->get_meta('_paygent_rakuten_html', true);
		if(isset($html)){
			echo $html;
			$javascript_auto_send_code = '
<script type="text/javascript">
function send_form_submit() {
    document.form.submit();
}
window.onload = send_form_submit;
</script>';
			echo $javascript_auto_send_code;
		}
	}

	/**
	 * @param $order_id
	 *
	 * @return void
	 */
	public function paygent_rakutenpay_thankyou( $order_id ){
		$order = wc_get_order($order_id);
		$payment_method = $order->get_payment_method();
		if($payment_method == $this->id && isset($_GET['rakuten_order_id'])){
			try {
				$order->set_transaction_id( $_GET['payment_id'] );
				$order->add_meta_data('_rakuten_order_id', $_GET['rakuten_order_id']);
				$order->save();
			} catch ( WC_Data_Exception $e ) {
				$order->add_order_note($e->getErrorCode().':'.$e->getMessage());
			}
			$order->update_status('processing', __( 'Rakuten Pay payment was successful.', 'woocommerce-for-paygent-payment-main' ));
		}
	}

	/**
	 * order button html
	 *
	 * @param  string $html
	 * @return  string $html
	 */
	public function paygent_rakuten_order_button_html( $html ){
		if(isset($this->button_type)){
			$image = 'https://checkout.rakuten.co.jp/p/common/img/btn_check_'.$this->button_type.'.gif';
			if( WC()->session->get('chosen_payment_method' ) === $this->id ) {
				echo '<input type="image" src="'.$image.'" id="place_order" alt="' . esc_html( $this->order_button_text ) . '" style="width:auto !important;">';
			}else{
				echo $html;
			}
		}else{
			echo $html;
		}
	}

    /**
     * Process a refund if supported
     * @param  int $order_id
     * @param  float $amount
     * @param  string $reason
     * @return  boolean True or false based on success, or a WP_Error object
     */
    public function process_refund( $order_id, $amount = null, $reason = '' ) {
        $send_data = array();
        $order = wc_get_order( $order_id );
        // Set Order ID for Paygent
        $send_data['trading_id'] = $order_id;

        if($order->get_status() === 'processing'){//Processing to cancel
            $telegram_kind = '340';
            $response = $this->paygent_request->send_paygent_request($this->test_mode, $order, $telegram_kind, $send_data, $this->debug);
            if($response['result'] == '0'){
                $order->add_order_note(__( 'Success the cancel for paygent.', 'woocommerce-for-paygent-payment-main' ));
                return true;
            }else{
                $order->add_order_note(__( 'Failed the cancel for paygent.', 'woocommerce-for-paygent-payment-main' ).$response['responseCode'].':'.$response['responseDetail'].':'.$response['result']);
                return false;
            }
        }elseif($order->get_status() === 'completed'){
            $telegram_kind = '342';
            $response = $this->paygent_request->send_paygent_request($this->test_mode, $order, $telegram_kind, $send_data, $this->debug);
            if($response['result'] == '0'){
                $order->add_order_note(__( 'Success the refund for paygent.', 'woocommerce-for-paygent-payment-main' ));
                return true;
            }else{
                $order->add_order_note(__( 'Failed the cancel for paygent.', 'woocommerce-for-paygent-payment-main' ).$response['responseCode'].$response['responseDetail']);
                return false;
            }
        }else{
            $order->add_order_note(__( 'Failed this order to refund for paygent.', 'woocommerce-for-paygent-payment-main' ));
            return false;
        }
    }

    /**
     * Update Sale from Auth to Paygent System
     *
     * @param int $order_id
     */
    public function order_paidy_status_completed( $order_id ){
        $telegram_kind = '341';
        $this->paygent_request->order_paygent_status_completed($order_id, $telegram_kind, $this);
    }
}

/**
 * Add the gateway to woocommerce
 *
 * @param array $methods
 * @return array $methods
 */
function add_wc_paygent_rakuten_pay_gateway( $methods ) {
    $methods[] = 'WC_Gateway_Paygent_RakutenPay';
    return $methods;
}
add_filter( 'woocommerce_payment_gateways', 'add_wc_paygent_rakuten_pay_gateway' );

/**
 * Edit the available gateway to woocommerce
 *
 * @param array $methods
 * @return array $methods
 */
function edit_available_gateways_paygent_rakuten_pay( $methods ) {
    $currency = get_woocommerce_currency();
    if($currency !='JPY'){
        unset($methods['paygent_rakutenpay']);
    }
    return $methods;
}

add_filter( 'woocommerce_available_payment_gateways', 'edit_available_gateways_paygent_rakuten_pay' );
