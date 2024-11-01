<?php
/**
 * Paygent Payment Gateway
 *
 * Provides a Paygent Paidy Payment Gateway.
 *
 * @class 		WC_Paygent
 * @extends		WC_Gateway_Paygent_Paidy
 * @version		2.1.4
 * @paidy		1.1.8
 * @package		WooCommerce/Classes/Payment
 * @author		Artisan Workshop
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use ArtisanWorkshop\WooCommerce\PluginFramework\v2_0_13 as Framework;

class WC_Gateway_Paygent_Paidy extends WC_Payment_Gateway {
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

		$this->id                = 'paygent_paidy';
		$this->has_fields        = false;
		$this->order_button_text = sprintf(__( 'Proceed to %s', 'woocommerce-for-paygent-payment-main' ), __('Paidy', 'woocommerce-for-paygent-payment-main' ));

        // Create plugin fields and settings
		$this->init_form_fields();
		$this->init_settings();
		$this->method_title       = __( 'Paygent Paidy Payment Gateway', 'woocommerce-for-paygent-payment-main' );
		$this->method_description = __( 'Allows payments by Paygent Paidy in Japan.', 'woocommerce-for-paygent-payment-main' );
		$this->supports = array(
			'products',
			'refunds',
		);
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
        $this->environment = (get_option('wc-paygent-testmode') != 1) ? 'live': 'sandbox';

        // Actions
		add_action( 'woocommerce_update_options_payment_gateways', array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

        add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'paidy_make_order') );
        add_action( 'wp_enqueue_scripts', array( $this, 'paidy_token_scripts_method' ) );

        add_action( 'woocommerce_before_checkout_form', array( $this, 'checkout_reject_to_cancel' ));
        add_action( 'woocommerce_thankyou_' . $this->id , array( $this, 'jp4wc_order_paidy_status_completed' ) );

        add_action( 'woocommerce_order_status_completed', array( $this, 'order_paidy_status_completed') );
	}
	/**
	* Initialize Gateway Settings Form Fields.
	*/
	function init_form_fields() {

		$this->form_fields = array(
			'enabled'     => array(
				'title'       => __( 'Enable/Disable', 'woocommerce-for-paygent-payment-main' ),
				'label'       => __( 'Enable paygent Paidy Payment', 'woocommerce-for-paygent-payment-main' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no'
			),
			'title'       => array(
				'title'       => __( 'Title', 'woocommerce-for-paygent-payment-main' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-for-paygent-payment-main' ),
				'default'     => __( 'Paidy', 'woocommerce-for-paygent-payment-main' )
			),
			'description' => array(
				'title'       => __( 'Description', 'woocommerce-for-paygent-payment-main' ),
				'type'        => 'textarea',
				'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-for-paygent-payment-main' ),
				'default'     => sprintf(__( 'Pay with your %s via Paygent.', 'woocommerce-for-paygent-payment-main' ), __('Paidy', 'woocommerce-for-paygent-payment-main' )),
			),
			'order_button_text' => array(
				'title'       => __( 'Order Button Text', 'woocommerce-for-paygent-payment-main' ),
				'type'        => 'textarea',
				'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-for-paygent-payment-main' ),
				'default'     => sprintf(__( 'Proceed to %s', 'woocommerce-for-paygent-payment-main' ), __('Paidy', 'woocommerce-for-paygent-payment-main' )),
			),
			'api_public_key'     => array(
				'title'       => __( 'API Public Key', 'woocommerce-for-paygent-payment-main' ),
				'type'        => 'text',
				'description' => sprintf(__( 'Please enter %s from Paidy Admin site.', 'woocommerce-for-paygent-payment-main' ),__( 'API Public Key', 'woocommerce-for-paygent-payment-main' )),
				'default'     => ''
			),
			'test_api_public_key'     => array(
				'title'       => __( 'Test API Public Key', 'woocommerce-for-paygent-payment-main' ),
				'type'        => 'text',
				'description' => sprintf(__( 'Please enter %s from Paidy Admin site.', 'woocommerce-for-paygent-payment-main' ), __( 'Test API Public Key', 'woocommerce-for-paygent-payment-main' )),
				'default'     => ''
			),
			'store_name'       => array(
				'title'       => __( 'Store Name', 'woocommerce-for-paygent-payment-main' ),
				'type'        => 'text',
				'description' => __( 'This controls the store name which the user sees during paidy checkout.', 'woocommerce-for-paygent-payment-main' ),
				'default'     => __( 'Paidy', 'woocommerce-for-paygent-payment-main' )
			),
            'logo_image_url' => array(
                'title'       => __( 'Logo Image (168×168 recommend)', 'woocommerce-for-paygent-payment-main' ),
                'type'        => 'image',
                'description' => __( 'URL of a custom logo that can be displayed in the checkout application header. If no value is specified, the Paidy logo will be displayed.', 'woocommerce-for-paygent-payment-main' ),
                'default'     => '',
                'desc_tip'    => true,
                'placeholder' => __( 'Optional', 'woocommerce-for-paygent-payment-main' ),
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
     * UI - Payment page Description fields for Paidy Payment.
     */
    function payment_fields() {
        // Description of payment method from settings
        ?>
        <br />
        <a href="https://paidy.com/consumer" target="_blank" class="jp4wc-paidy-icon">
            <img src="<?php echo WC_PAYGENT_PLUGIN_URL;?>assets/images/checkout_banner_320x100.png" alt="Paidy 翌月まとめてお支払い" style="max-height: none; float: none;">
        </a>
        <br />
        <p class="jp4wc-paidy-description"><?php echo $this->description; ?></p>
        <br />
        <div class="jp4wc-paidy-explanation">
            <ul>
                <li style="list-style: disc !important;">口座振替(支払手数料:無料)</li>
                <li style="list-style: disc !important;">コンビニ(支払手数料:356円税込)</li>
                <li style="list-style: disc !important;">銀行振込(支払手数料:金融機関により異なります)</li>
            </ul>
            Paidyについて詳しくは<a href="https://paidy.com/whatspaidy" target="_blank">こちら</a>。
        </div>
        <?php
    }

    /**
     * Process the payment and return the result
     *
     * @param int $order_id
     * @return array | mixed
     */
    public function process_payment( $order_id ) {
        $order = wc_get_order( $order_id );
        // Return thankyou redirect
        return array(
            'result' 	=> 'success',
            'redirect'	=> $order->get_checkout_payment_url(true)
        );
    }

    /**
     * Make Paidy JavaScript for payment process
     *
     * @param string $order_id
     * @return string
     * @throws Exception
     */
    public function paidy_make_order( $order_id ){
        //Set Order
        $order = wc_get_order( $order_id );
        //Set public key by environment.
        if( $this->environment == 'live' ){
            $api_public_key = $this->api_public_key;
        }else{
            $api_public_key = $this->test_api_public_key;
        }
        //Set logo image url
        if(isset($this->logo_image_url)){
            $logo_image_url = wp_get_attachment_url($this->logo_image_url);
        }else{
            $logo_image_url = 'https://www.paidy.com/images/logo.png';
        }
        $paidy_order_ref = $order_id;
        //Set user id
        if(is_user_logged_in()){
            $user_id = get_current_user_id();
        }else{
            $user_id = 'guest-paidy'.$paidy_order_ref;
        }

        if(version_compare( WC_VERSION, '3.6', '>=' )){
            $jp4wc_countries = new WC_Countries;
            $states = $jp4wc_countries->get_states();
        }else{
            global $states;
        }
        //Set shipping address
        if($order->get_shipping_postcode()){
            $shipping_address['line1'] = $order->get_shipping_address_2();
            $shipping_address['line2'] = $order->get_shipping_address_1();
            $shipping_address['city'] = $order->get_shipping_city();
            $shipping_address['state'] = $states['JP'][$order->get_shipping_state()];
            $shipping_address['zip'] = $order->get_shipping_postcode();
        }else{
            $shipping_address['line1'] = $order->get_billing_address_2();
            $shipping_address['line2'] = $order->get_billing_address_1();
            $shipping_address['city'] = $order->get_billing_city();
            $shipping_address['state'] = $states['JP'][$order->get_billing_state()];
            $shipping_address['zip'] = $order->get_billing_postcode();
        }

        //Get products and coupons information from order
        $order_items = $order->get_items(array('line_item','coupon'));
        $items_count = 0;
        $cart_total = 0;
        $fees = $order->get_fees();
        $items = '';
        $paidy_amount = 0;
        foreach( $order_items as $key => $item){
            if(isset($item['product_id'])) {
                $unit_price = round($item['subtotal'] / $item['quantity'], 0);
                $items .= '{
                    "id":"' . $item['product_id'] . '",
                    "quantity":' . $item['quantity'] . ',
                    "title":"' . $item['name'] . '",
                    "unit_price":' . $unit_price;
                $paidy_amount += $item['quantity']*$unit_price;
            }elseif(isset($item['discount'])){
                $items .= '{
                    "id":"'.$item['code'].'",
                    "quantity":1,
                    "title":"'.$item['name'].'",
                    "unit_price":-'.$item['discount'];
                $paidy_amount -= $item['discount'];
            }
            if ($item === end($order_items) and (!isset($fees))) {
                $items .= '}
';
            }else{
                $items .= '},
                    ';
            }
            $items_count += $item['quantity'];
            $cart_total += $item['subtotal'];
        }
        if(isset( $fees )){
            $i = 1;
            foreach ( $fees as $fee ){
                $items .= '{
                    "id":"fee'.$i.'",
                    "quantity":1,
                    "title":"'.esc_html($fee->get_name()).'",
                    "unit_price":'.esc_html($fee->get_amount());
                $paidy_amount += esc_html($fee->get_amount());
                if ($fee === end($fees)) {
                    $items .= '}
';
                }else{
                    $items .= '},
                    ';
                }
                $i++;
            }
        }
        // Get latest order
        $args = array(
            'customer_id' => $user_id,
            'status' => 'completed',
            'orderby' => 'date',
            'order' => 'DESC'
        );
        $orders = wc_get_orders($args);
        $total_order_amount = 0;
        $order_count = 0;
        foreach($orders as $each_order){
            if( $each_order->get_payment_method() != $this->id ){
                $selected_orders[] = $each_order;
                $total_order_amount += $each_order->get_total();
                $order_count += 1;
            }
        }
        if(isset($selected_orders[1])) {
            foreach ($selected_orders as $each_order) {
                if ($each_order === end($selected_orders)) {
                    $latest_order = $each_order;
                }
            }
        }elseif(isset($selected_orders)){
            $latest_order = $selected_orders[0];
        }else{
            $latest_order = null;
        }
        if(isset($latest_order)){
            $last_order_amount = $latest_order->get_total();
            $day1 = strtotime($latest_order->get_date_created());
            $day2 = strtotime(date_i18n('Y-m-d H:i:s'));
            $diff_day = floor(($day2 - $day1) / (60 * 60 * 24));
            if($diff_day <=0 ){
                $diff_day = 0;
            }
        }else{
            $last_order_amount = 0;
            $diff_day = 0;
        }
        $order_amount = $order->get_total();
        $tax = $order_amount - $paidy_amount - $order->get_shipping_total();
        if( $this->enabled =='yes' and isset($api_public_key) and $api_public_key != '' ):
            ?>
            <script type="text/javascript">
                jQuery(window).on('load', function(){
                    paidyPay();
                })
                var config = {
                    "api_key": "<?php echo $api_public_key;?>",
                    "logo_url": "<?php echo $logo_image_url;?>",
                    "closed": function(callbackData) {
                        /*
                        Data returned in the callback:
                        callbackData.id,
                        callbackData.amount,
                        callbackData.currency,
                        callbackData.created_at,
                        callbackData.status
                        */
                        if(callbackData.status === "rejected"){
                            window.location.href = "<?php echo wc_get_checkout_url().'?status='; ?>" + callbackData.status + "&order_id=<?php echo $order_id;?>";
                        }else if(callbackData.status === "authorized"){
                            window.location.href = "<?php echo $this->get_return_url( $order ).'&transaction_id='; ?>" + callbackData.id;
                        }else{
                            window.location.href = "<?php echo wc_get_checkout_url().'?status='; ?>" + callbackData.status + "&order_id=<?php echo $order_id;?>";
                        }
                    }
                };

                var paidyHandler = Paidy.configure(config);
                function paidyPay() {
                    var payload = {
                        "amount": <?php echo $order_amount;?>,
                        "currency": "JPY",
                        "store_name": "<?php echo wc_clean($this->store_name);?>",
                        "buyer": {
                            "email": "<?php echo $order->get_billing_email(); ?>",
                            "name1": "<?php echo $order->get_billing_last_name().' '.$order->get_billing_first_name();?>",
<?php $billing_yomigana_last_name = $order->get_meta('_billing_yomigana_last_name');
                            if(isset($billing_yomigana_last_name)):?>
                            "name2": "<?php echo $order->get_meta('_billing_yomigana_last_name').' '.$order->get_meta('_billing_yomigana_first_name');?>",
<?php endif; ?>
                            "phone": "<?php echo $order->get_billing_phone(); ?>"
                        },
                        "buyer_data": {
                            "user_id": "<?php echo $user_id; ?>",
                            "order_count": <?php echo $order_count; ?>,
                            "ltv": <?php echo $total_order_amount; ?>,
                            "last_order_amount": <?php echo $last_order_amount; ?>,
                            "last_order_at": <?php echo $diff_day?>
                        },
                        "order": {
                            "items": [
                                <?php echo $items;?>

                            ],
                            "order_ref": "<?php echo $paidy_order_ref; ?>",
                            "shipping": <?php echo $order->get_shipping_total();?>,
                            "tax": <?php echo $tax;?>
                        },
                        "shipping_address": {
                            "line1": "<?php echo $shipping_address['line1'];?>",
                            "line2": "<?php echo $shipping_address['line2'];?>",
                            "city": "<?php echo $shipping_address['city'];?>",
                            "state": "<?php echo $shipping_address['state'];?>",
                            "zip": "<?php echo $shipping_address['zip'];?>"
                        },
                        "description": "<?php echo wc_clean($this->store_name);?>"
                    };
                    paidyHandler.launch(payload);
                }
            </script>
        <?php else: ?>
            <h2><?php echo __('API Public key is not set. Please set an API public key in the admin page.', 'woocommerce-for-paygent-payment-main'); ?></h2>
        <?php endif;
    }

    /**
     * Update Sale from Auth to Paidy System
     *
     * @param string $order_id
     * @return mixed
     */
    public function jp4wc_order_paidy_status_completed( $order_id ){
        $order = wc_get_order( $order_id );
        $current_status = $order->get_status();
        if($current_status == 'pending'){
            // Reduce stock levels
            wc_reduce_stock_levels( $order_id );
            $order->payment_complete($_GET['transaction_id']);
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

    /**
     * Load Paygent Paidy Token javascript
     */
    public function paidy_token_scripts_method() {
        // Image upload.
        wp_enqueue_media();

        $paygent_token_js_link = 'https://apps.paidy.com/';
        if(is_checkout()){
            wp_enqueue_script(
                'paidy-token',
                $paygent_token_js_link,
                array(),
                '',
                false
            );
            // Paidy Payment for Checkout page
            wp_register_style(
                'jp4wc-paidy',
                WC_PAYGENT_PLUGIN_URL . '/assets/css/jp4wc-paidy.css',
                false,
                WC_PAYGENT_VERSION
            );
            wp_enqueue_style( 'jp4wc-paidy' );
        }
    }

    /**
     * Load Paidy javascript for Admin
     */
    public function admin_enqueue_scripts(){
        // Image upload.
        wp_enqueue_media();
        if ( is_admin() && wp_script_is('wc-gateway-ppec-settings') == false && $_GET['section'] =='paidy') {
            wp_enqueue_script(
                'wc-gateway-paidy-settings',
                WC_PAYGENT_PLUGIN_URL . '/assets/js/wc-gateway-paidy-settings.js',
                array('jquery'),
                WC_PAYGENT_VERSION,
                true
            );
        }
    }

    /**
     * Update Cancel from Auth to Paidy System
     *
     * @param object $checkout
     * @return mixed
     */
    public function checkout_reject_to_cancel( $checkout ){
        if( isset($_GET['status']) ){
            if($_GET['status'] == 'closed'){
                $message = __('Once the customer interrupted the payment.. Order ID:', 'woocommerce-for-paygent-payment-main').$_GET['order_id'];
                $this->jp4wc_framework->jp4wc_debug_log($message, $this->debug, 'woocommerce-for-paygent-payment-main');
            }elseif($_GET['status'] == 'rejected' or isset($_GET['order_id'])){
                $reject_message = __('This Paidy payment has been declined. Please select another payment method.', 'woocommerce-for-paygent-payment-main');
                wc_add_notice( $reject_message, 'error');
            }
        }
    }

    /**
     * Generate Image HTML.
     *
     * @param  mixed $key
     * @param  mixed $data
     * @since  1.5.0
     * @return string
     */
    public function generate_image_html( $key, $data ) {
        $field_key = $this->get_field_key( $key );
        $defaults  = array(
            'title'             => '',
            'disabled'          => false,
            'class'             => '',
            'css'               => '',
            'placeholder'       => '',
            'type'              => 'text',
            'desc_tip'          => false,
            'description'       => '',
            'custom_attributes' => array(),
        );

        $data  = wp_parse_args( $data, $defaults );
        $value = $this->get_option( $key );

        // Hide show add remove buttons.
        $maybe_hide_add_style    = '';
        $maybe_hide_remove_style = '';

        // For backwards compatibility (customers that already have set a url)
        $value_is_url            = filter_var( $value, FILTER_VALIDATE_URL ) !== false;

        if ( empty( $value ) || $value_is_url ) {
            $maybe_hide_remove_style = 'display: none;';
        } else {
            $maybe_hide_add_style = 'display: none;';
        }

        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['title'] ); ?> <?php echo $this->get_tooltip_html( $data ); ?></label>
            </th>

            <td class="image-component-wrapper">
                <div class="image-preview-wrapper">
                    <?php
                    if ( ! $value_is_url ) {
                        echo wp_get_attachment_image( $value, 'thumbnail' );
                    } else {
                        echo sprintf( __( 'Already using URL as image: %s', 'woocommerce-for-paygent-payment-main' ), $value );
                    }
                    ?>
                </div>

                <button
                        class="button image_upload"
                        data-field-id="<?php echo esc_attr( $field_key ); ?>"
                        data-media-frame-title="<?php echo esc_attr( __( 'Select a image to upload', 'woocommerce-for-paygent-payment-main' ) ); ?>"
                        data-media-frame-button="<?php echo esc_attr( __( 'Use this image', 'woocommerce-for-paygent-payment-main' ) ); ?>"
                        data-add-image-text="<?php echo esc_attr( __( 'Add image', 'woocommerce-for-paygent-payment-main' ) ); ?>"
                        style="<?php echo esc_attr( $maybe_hide_add_style ); ?>"
                >
                    <?php echo esc_html__( 'Add image', 'woocommerce-for-paygent-payment-main' ); ?>
                </button>

                <button
                        class="button image_remove"
                        data-field-id="<?php echo esc_attr( $field_key ); ?>"
                        style="<?php echo esc_attr( $maybe_hide_remove_style ); ?>"
                >
                    <?php echo esc_html__( 'Remove image', 'woocommerce-for-paygent-payment-main' ); ?>
                </button>

                <input type="hidden"
                       name="<?php echo esc_attr( $field_key ); ?>"
                       id="<?php echo esc_attr( $field_key ); ?>"
                       value="<?php echo esc_attr( $value ); ?>"
                />
            </td>
        </tr>
        <?php

        return ob_get_clean();
    }
}

/**
 * Add the gateway to woocommerce
 *
 * @param array $methods
 * @return array $methods
 */
function add_wc_paygent_paidy_gateway( $methods ) {
    $methods[] = 'WC_Gateway_Paygent_Paidy';
    return $methods;
}
add_filter( 'woocommerce_payment_gateways', 'add_wc_paygent_paidy_gateway' );

/**
 * Edit the available gateway to woocommerce
 *
 * @param array $methods
 * @return array $methods
 */
function edit_available_gateways_paidy( $methods ) {
    $currency = get_woocommerce_currency();
    if($currency !='JPY'){
        unset($methods['paygent_paidy']);
    }
    return $methods;
}

add_filter( 'woocommerce_available_payment_gateways', 'edit_available_gateways_paidy' );
