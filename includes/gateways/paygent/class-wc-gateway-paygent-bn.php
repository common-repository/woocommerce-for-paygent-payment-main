<?php
/**
 * Paygent Payment Gateway
 *
 * Provides a Paygent Bank Net ASP Payment Gateway.
 *
 * @class 		WC_Paygent
 * @extends		WC_Gateway_Paygent_BN
 * @version		2.0.7
 * @package		WooCommerce/Classes/Payment
 * @author		Artisan Workshop
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use ArtisanWorkshop\WooCommerce\PluginFramework\v2_0_13 as Framework;

class WC_Gateway_Paygent_BN extends WC_Payment_Gateway {

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

		$this->id = 'paygent_bn';
		$this->has_fields = false;
		$this->order_button_text = sprintf(__( 'Proceed to %s', 'woocommerce-for-paygent-payment-main' ), __('Bank Net', 'woocommerce-for-paygent-payment-main' ));
		
        // Create plugin fields and settings
		$this->init_form_fields();
		$this->init_settings();
		$this->method_title = __( 'Paygent Bank Net Payment Gateway', 'woocommerce-for-paygent-payment-main' );
		$this->method_description = __( 'Allows payments by Paygent Bnak Net in Japan.', 'woocommerce-for-paygent-payment-main' );
        // When no save setting error at chackout page
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

        // Actions
		add_action( 'woocommerce_receipt_paygent_bn', array( $this, 'receipt_page' ) );
		add_action( 'woocommerce_update_options_payment_gateways', array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_payment_form' ) );
	}

	/**
	* Initialize Gateway Settings Form Fields.
	*/
	function init_form_fields() {
		$this->form_fields = array(
			'enabled'     => array(
				'title'       => __( 'Enable/Disable', 'woocommerce-for-paygent-payment-main' ),
				'label'       => sprintf(__( 'Enable paygent %s Payment', 'woocommerce-for-paygent-payment-main' ), __('Bank Net', 'woocommerce-for-paygent-payment-main' )),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no'
			),
			'title'       => array(
				'title'       => __( 'Title', 'woocommerce-for-paygent-payment-main' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-for-paygent-payment-main' ),
				'default'     => __( 'Bank Net', 'woocommerce-for-paygent-payment-main' )
			),
			'description' => array(
				'title'       => __( 'Description', 'woocommerce-for-paygent-payment-main' ),
				'type'        => 'textarea',
				'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-for-paygent-payment-main' ),
				'default'     => sprintf(__( 'Pay with your %s via Paygent.', 'woocommerce-for-paygent-payment-main' ), __('Bank Net', 'woocommerce-for-paygent-payment-main' )),
			),
			'order_button_text' => array(
				'title'       => __( 'Order Button Text', 'woocommerce-for-paygent-payment-main' ),
				'type'        => 'textarea',
				'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-for-paygent-payment-main' ),
				'default'     => sprintf(__( 'Proceed to %s', 'woocommerce-for-paygent-payment-main' ), __('Bank Net', 'woocommerce-for-paygent-payment-main' )),
			),
			'claim_kanji_text' => array(
				'title'       => __( 'Invoice detail (Kanji)', 'woocommerce-for-paygent-payment-main' ),
				'type'        => 'text',
				'description' => __( 'This controls the description which invoice detail.', 'woocommerce-for-paygent-payment-main' ).__( 'Double-byte characters are required.', 'woocommerce-for-paygent-payment-main' ),
				'default'     => sprintf(__( 'invoice detail Via %s (kanji)', 'woocommerce-for-paygent-payment-main' ), __('Bank Net', 'woocommerce-for-paygent-payment-main' )),
			),
			'claim_kana_text' => array(
				'title'       => __( 'Invoice detail (kana)', 'woocommerce-for-paygent-payment-main' ).__( 'Half-width characters are required.', 'woocommerce-for-paygent-payment-main' ),
				'type'        => 'text',
				'description' => __( 'This controls the description which invoice detail.', 'woocommerce-for-paygent-payment-main' ),
				'default'     => sprintf(__( 'invoice detail Via %s (kana)', 'woocommerce-for-paygent-payment-main' ), __('Bank Net', 'woocommerce-for-paygent-payment-main' )),
			),
            'receipt_name_kana' => array(
                'title'       => __( 'Invoice shop name (Kana)', 'woocommerce-for-paygent-payment-main' ),
                'type'        => 'text',
                'description' => __( 'This controls the shop name.', 'woocommerce-for-paygent-payment-main' ).__( 'Half-width characters are required.', 'woocommerce-for-paygent-payment-main' ),
                'default'     => sprintf(__( 'invoice shop name Via %s (kana)', 'woocommerce-for-paygent-payment-main' ), __('Bank Net', 'woocommerce-for-paygent-payment-main' )),
            ),
            'receipt_name' => array(
                'title'       => __( 'Invoice shop name (kanji)', 'woocommerce-for-paygent-payment-main' ),
                'type'        => 'text',
                'description' => __( 'This controls the description which invoice detail.', 'woocommerce-for-paygent-payment-main' ).__( 'Double-byte characters are required.', 'woocommerce-for-paygent-payment-main' ),
                'default'     => sprintf(__( 'invoice shop name Via %s (kanji)', 'woocommerce-for-paygent-payment-main' ), __('Bank Net', 'woocommerce-for-paygent-payment-main' )),
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
     * Select Bank user want to pay
    */
	function bank_select() {
		?><select name="bank_code">
		<?php foreach($this->banks as $num => $value){?>
		<option value="<?php echo $num; ?>"><?php echo $value;?></option>
	<?php }?>
		</select><?php 
	}

	/**
	 * Process the payment and return the result.
     * @param int $order_id
     * @throws
     * @return mixed
    */
	function process_payment( $order_id ) {

		$order = wc_get_order( $order_id );
		$user = wp_get_current_user();
        $customer_id = $order_id.'-user';
		if(0 != $user->ID){
			$customer_id   = $user->ID;
		}
		$send_data = array();

		//Check test mode
		$test_mode = get_option('wc-paygent-testmode');

		//Common header part
		$telegram_kind = '060';
        $prefix_order = get_option( 'wc-paygent-prefix_order' );
        if($prefix_order){
            $send_data['trading_id'] = $prefix_order.$order_id;
        }else{
            $send_data['trading_id'] = 'wc_'.$order_id;
        }
		$send_data['payment_id'] = '060';

        $send_data['amount'] = $order->get_total();
		$send_data['claim_kana'] = mb_convert_encoding($this->claim_kana_text, "SJIS", "UTF-8");
		$send_data['claim_kanji'] = mb_convert_encoding($this->claim_kanji_text, "SJIS", "UTF-8");
        $send_data['receipt_name_kana'] = mb_convert_encoding($this->receipt_name_kana, "SJIS", "UTF-8");
        $send_data['receipt_name'] = mb_convert_encoding($this->receipt_name, "SJIS", "UTF-8");

        $send_data['stop_return_url'] = wc_get_checkout_url();
        $send_data['return_url'] = $this->get_return_url( $order );
		
		$response = $this->paygent_request->send_paygent_request($test_mode, $order, $telegram_kind, $send_data, $this->debug);

		// Check response
		if ( $response['result'] == 0 and $response['result_array']) {
			// Success
			$order->add_meta_data( '_paygent_bn_asp_url', $response['result_array'][0]['asp_url'] );
            $order->add_meta_data('_paygent_order_id', $send_data['trading_id'], true );
			// Mark as on-hold (we're awaiting the payment)
			$order->update_status( 'on-hold', __( 'Awaiting Bank Net payment ASP', 'woocommerce-for-paygent-payment-main' ) );

			// Return thank you redirect
			return array (
				'result'   => 'success',
				'redirect' => $response['result_array'][0]['asp_url']
			);
		}else{
			$this->paygent_request->error_response($response, $order);
			return array( 'result' => 'failed' );
		}
	}

    /**
     * Pay Form for thank you page
     *
     * @param  int $order_id
     */
    function thankyou_payment_form($order_id) {
	    $order = wc_get_order($order_id);
		$status = $order->get_status();
		$payment_method = $order->get_payment_method();
		if( $payment_method == $this->id ){
            if($status == 'on-hold' && isset($_POST['payment_id'])){
                wc_reduce_stock_levels( $order_id );
                $order->payment_complete( $_POST['payment_id'] );
            }else{
                $order->set_status( 'failed', __('Failed at Paygent system for Bank Net Payment ASP.', 'woocommerce-for-paygent-payment-main'));
                $order->save();
                echo __('This payment is failed.', 'woocommerce-for-paygent-payment-main' );
            }
        }
	}

	function receipt_page( $order ) {
		echo '<p>' . __( 'Thank you for your order.', 'woocommerce-for-paygent-payment-main' ) . '</p>';
	}

	/**
	 * Get post data if set
	 */
	private function get_post( $name ) {
		if ( isset( $_POST[ $name ] ) ) {
			return wc_clean($_POST[ $name ]);
		}
		return null;
	}
}

/**
 * Add the gateway to woocommerce
 *
 * @param array $methods
 * @return array
 */
function add_wc_paygent_bn_gateway( $methods ) {
    $methods[] = 'WC_Gateway_Paygent_BN';
    return $methods;
}
add_filter( 'woocommerce_payment_gateways', 'add_wc_paygent_bn_gateway' );

/**
 * Edit the available gateway to woocommerce
 *
 * @param array $methods
 * @return array
 */
function edit_available_bn_gateways( $methods ) {
    $currency = get_woocommerce_currency();
    if($currency !='JPY'){
        unset($methods['paygent_bn']);
    }
    return $methods;
}
add_filter( 'woocommerce_available_payment_gateways', 'edit_available_bn_gateways' );
