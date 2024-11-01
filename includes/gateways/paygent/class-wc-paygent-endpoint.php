<?php
use ArtisanWorkshop\WooCommerce\PluginFramework\v2_0_13 as Framework;

add_action( 'rest_api_init', function () {
    register_rest_route( 'paygent/v1', '/check/', array(
        'methods' => WP_REST_Server::CREATABLE,
        'callback' => 'paygent_check_webhook',
        'permission_callback' => '__return_true',
    ) );
} );

/**
 * Paygent Webhook response.
 * Version: 2.3.0
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response | WP_Error endpoint Paygent webhook response
 */
function paygent_check_webhook( $request ){
    $jp4wc_framework =new Framework\JP4WC_Plugin();

    $body_data = $request->get_body();
    $get_array = $jp4wc_framework->jp4wc_url_to_array($body_data);
	// Debug
	$message_log = 'This is payment notice from Paygent.'. "\n" . $body_data. "\n";
	$jp4wc_framework->jp4wc_debug_log( $message_log, true, 'wc-paygent');

    $payment_status = $get_array['payment_status'];
    $payment_type = $get_array['payment_type'];

    if(isset($get_array['trading_id']) and $get_array['trading_id'] != ''){
        $order_id = preg_replace('/[^0-9]/', '', $get_array['trading_id']);
        $order = wc_get_order($order_id);
		if( $order ){
			$order_payment_method = $order->get_payment_method();
			//Debug check by payment method
			$debug = false;
			if(isset($order_payment_method)){
				$option_name = 'woocommerce_'.$order_payment_method.'_settings';
				$get_setting = get_option($option_name);
				$debug = $get_setting['debug'] ?? false;
			}
			// Debug
			$get_log_array = array();
			foreach($get_array as $key=> $value){
				if(isset($value))$get_log_array[$key]= $value;
			}
			$message_log = 'This is payment notice from Paygent to array'. "\n" . $jp4wc_framework->jp4wc_array_to_message($get_log_array);
			$jp4wc_framework->jp4wc_debug_log( $message_log, $debug, 'wc-paygent');
			if(isset($payment_type)){
				switch ($payment_type) {
					case 01://ATM
						break;
					case 02://Credit Card
						paygent_cc_webhook( $order, $get_array );
						break;
					case 03:// Convenience store(Number)
						paygent_cv_webhook( $order, $get_array );
						break;
					case 05:// Bank Net
						paygent_bn_webhook( $order, $get_array );
						break;
					case 06:// Carrier payment
						paygent_mb_webhook( $order, $get_array );
						break;
					case 22:// Paidy
						paygent_paidy_webhook( $order, $get_array );
						break;
					case 26:// PayPay
						paygent_paypay_webhook( $order, $get_array );
						break;
                    case 17:// Rakuten Pay
                        paygent_rakutenpay_webhook( $order, $get_array );
                        break;
                    }
			}
			$status_array = paygent_payment_status_array();
			if(isset($status_array[$payment_status])){
				$order->add_order_note(sprintf(__('I received a payment information inquiry telegram with a payment status of %s.', 'woocommerce-for-paygent-payment-main'),$status_array[$payment_status].':'.$payment_status ));
			}else{
				$order->add_order_note('No payment status from paygent. payment_status:'.$payment_status.'payment_type:'.$payment_type);
			}
		}else{
			// Debug
			$message_log = __( 'Trading ID was not received.', 'woocommerce-for-paygent-payment-main'). "\n" . $body_data. "\n";
			$jp4wc_framework->jp4wc_debug_log( $message_log, true, 'wc-paygent');
		}

    }else{
        //Debug check by Credit Card Payment
        $option_name = 'woocommerce_paygent_cc_settings';
        $get_setting = get_option($option_name);
	    $debug       = $get_setting['debug'] ?? false;
        if( $get_array['payment_status'] == '10' ) {// Validity confirmed
            $message = __('Validity confirmed', 'woocommerce-for-paygent-payment-main') . "\n";
        }else{
            $message = __('Validity confirmation NG', 'woocommerce-for-paygent-payment-main') . "\n" . 'payment_status:'.$get_array['payment_status'] . "\n";
        }
        $message .= $jp4wc_framework->jp4wc_array_to_message($get_array);
        $jp4wc_framework->jp4wc_debug_log( $message, $debug, 'wc-paygent');
    }

    if ( empty( $request ) ) {
        $message = 'No data,but this site get the request from paygent.';
        $jp4wc_framework->jp4wc_debug_log( $message, 'yes', 'wc-paygent');
    }

    header("Content-type: text/plain; charset=utf-8");
    echo 'result=0';
}

/**
 * Paygent update status by endpoint action.
 *
 * @param object $order WP_Order
 * @param string $status default set are pending, on-hold, processing, completed, cancelled, refunded, failed
 */
function paygent_update_status_webhook( $order, $status ){
    if($status == 'not_set') return;
    $current_status = $order->get_status();
    $order_type = $order->get_type();
    $active_flag = false;
    if( $current_status != $status ) {
        $normal_flag = true;
        if($order_type == 'shop_order'){
            $base_status = array(
                0 => 'pending',
                1 => 'on-hold',
                2 => 'processing',
                3 => 'completed'
            );
        }elseif($order_type == 'shop_subscription'){
            $base_status = array(
                0 => 'pending',
                1 => 'on-hold',
                2 => 'pending-cancel',
                3 => 'active',
                4 => 'expired',
                5 => 'cancelled'
            );
            if($current_status == 'active')$active_flag = true;
        }else{
            $order->add_order_note( __( 'This order type does not support Paygent payment.', 'woocommerce-for-paygent-payment-main' ) );
            return;
        }
        if( $active_flag && $status == 'processing' ){
            $order->add_order_note( __( 'Since the subscription is activated, the payment order is also activated.', 'woocommerce-for-paygent-payment-main' ) );
            return;
        }
        $current_status_id = array_search( $order->get_status(), $base_status );
        $all_statuses = wc_get_order_statuses();
        if( in_array( $status, $base_status ) && $current_status_id ){
            $status_id = array_search( $status, $base_status );
            if( (int)$status_id < (int)$current_status_id ){
                $order->add_order_note( __( 'Order status change due to Paygent notification is abnormal. Please confirm it.', 'woocommerce-for-paygent-payment-main') );
                $normal_flag = false;
            }
        }
        $next_status = '';
        $current_status_title = $all_statuses['wc-' . $current_status];
        $status_title = $all_statuses['wc-' . $status];
        if($status == 'all_refunded'){
            $status_title = __('Sales canceled', 'woocommerce-for-paygent-payment-main');
            $next_status = 'refunded';
        }
        $status_message = sprintf( __( 'The current status of this order is %s. I received an application from Paygent and changed the status to %s.', 'woocommerce-for-paygent-payment-main' ), $current_status_title, $status_title );
        if( $next_status == 'refunded' ){
            $order->update_status( $next_status, $status_message );
        }elseif( $status == 'refunded' || $normal_flag === false ){
            $order->add_order_note( $status_message );
        }else{
            $order->update_status( $status, $status_message );
        }
    }
}

/**
 * Paygent Credit Card response.
 *
 * @param object $order WP_Order.
 * @param array $get_array
 * @return string $message
 */
function paygent_cc_webhook( $order, $get_array ){
    if( isset( $get_array['payment_status'] ) ){
		$payment_status = $get_array['payment_status'];
        switch( $payment_status ){
            case '10':// Apply
	            paygent_update_status_webhook( $order, 'pending' );
                break;
            case '11':// Approval failure
                paygent_update_status_webhook($order, 'cancelled');
                break;
            case '13':// 3D secure failure
                paygent_update_status_webhook($order, 'cancelled');
                break;
            case '20':// Authority OK
                paygent_update_status_webhook($order, 'processing');
                break;
            case '32':// Approval revoked
                paygent_update_status_webhook($order, 'cancelled');
                break;
            case '33':// Authorization expired
                paygent_update_status_webhook($order, 'cancelled');
                break;
            case '40':// Sales Completed
                $paygent_cc = new WC_Gateway_Paygent_CC();
                if( $paygent_cc->paymentaction != 'sale' ){
                    paygent_update_status_webhook($order, 'completed');
                }
                break;
            case '60':// Sales canceled
                if($order->get_transaction_id() == $get_array['payment_id']){
                    paygent_update_status_webhook($order, 'all_refunded' );
                }else{
                    paygent_update_status_webhook($order, 'refunded' );
                }
                break;
        }
    }
}

/**
 * Paygent convenience store response.
 *
 * @param object $order WP_Order.
 * @param array $get_array
 * @return string $message
 */
function paygent_cv_webhook( $order, $get_array ){
    if(isset($get_array['payment_status'])){
        switch($get_array['payment_status']){
            case '10':// Apply
                paygent_update_status_webhook($order, 'on-hold');
                break;
            case '12':// Expired payment
                paygent_update_status_webhook($order, 'cancelled');
                break;
            case '40':// Sales Completed
                paygent_update_status_webhook($order, 'processing');
                break;
            case '43':// Breaking news detected
                paygent_update_status_webhook($order, 'processing');
                break;
            case '61':// Breaking news canceled
                paygent_update_status_webhook($order, 'cancelled');
                break;
        }
    }
}

/**
 * Paygent Bank Net response.
 *
 * @param object $order WP_Order.
 * @param array $get_array
 * @return string $message
 */
function paygent_bn_webhook( $order, $get_array ){
    if(isset($get_array['payment_status'])){
        switch($get_array['payment_status']){
            case '10':// Apply
                paygent_update_status_webhook($order, 'on-hold');
                break;
            case '15':// Application interruption
                paygent_update_status_webhook($order, 'cancelled');
                break;
            case '40':// Sales Completed
                paygent_update_status_webhook($order, 'processing');
                break;
        }
    }
}

/**
 * Paygent Carrier payment response.
 *
 * @param object $order WP_Order.
 * @param array $get_array
 * @return string $message
 */
function paygent_mb_webhook( $order, $get_array ){
    if(isset($get_array['payment_status'])){
        if($order->get_type() == 'shop_order'){
            switch($get_array['payment_status']){
                case '10':// Apply
                    paygent_update_status_webhook($order, 'on-hold');
                    break;
                case '15':// Application interruption
                    paygent_update_status_webhook($order, 'cancelled');
                    break;
                case '20':// Authority OK
                    paygent_update_status_webhook($order, 'processing');
                    break;
                case '21':// Authority complete
                    paygent_update_status_webhook($order, 'processing');
                    break;
                case '32':// Approval revoked
                    paygent_update_status_webhook($order, 'cancelled');
                    break;
                case '33':// Authorization expired
                    paygent_update_status_webhook($order, 'cancelled');
                    break;
                case '36':// Sales hold
                    paygent_update_status_webhook($order, 'on-hold');
                    break;
                case '40':// Sales Completed
                    paygent_update_status_webhook($order, 'processing');
                    break;
                case '41':// Sales Completed (no change more)
                    paygent_update_status_webhook($order, 'not_set');
                    break;
                case '43':// Breaking news detected
                    paygent_update_status_webhook($order, 'processing');
                    break;
                case '44':// Sales Completed
                    paygent_update_status_webhook($order, 'processing');
                    break;
                case '60':// Sales canceled
                    paygent_update_status_webhook($order, 'refunded');
                    break;
                case '62':// Cancellation completed
                    paygent_update_status_webhook($order, 'cancelled');
                    break;
            }
        }elseif($order->get_type() == 'shop_subscription'){
            switch($get_array['payment_status']){
                case '10':// Apply
                    paygent_update_status_webhook($order, 'on-hold');
                    break;
                case '15':// Application interruption
                    paygent_update_status_webhook($order, 'cancelled');
                    break;
                case '20':// Authority OK
                    paygent_update_status_webhook($order, 'active');
                    break;
                case '21':// Authority complete
                    paygent_update_status_webhook($order, 'not_set');
                    break;
                case '40':// Sales Completed
                    paygent_update_status_webhook($order, 'active');
                    break;
                case '50':// Sales canceled
                    paygent_update_status_webhook($order, 'cancelled');
                    break;
                case '60':// Sales canceled
                    paygent_update_status_webhook($order, 'cancelled');
                    break;
            }
        }
    }
}

/**
 * Paygent Paidy response.
 *
 * @param object $order WP_Order.
 * @param array $get_array
 * @return string $message
 */
function paygent_paidy_webhook( $order, $get_array ){
    if(isset($get_array['payment_status'])){
        switch($get_array['payment_status']){
            case '20':// Authority OK
                paygent_update_status_webhook($order, 'processing');
                break;
            case '30':// Requesting sales
                paygent_update_status_webhook($order, 'processing');
                break;
            case '31':// The authority is being canceled
                paygent_update_status_webhook($order, 'cancelled');
                break;
            case '32':// Approval revoked
                paygent_update_status_webhook($order, 'cancelled');
                break;
            case '33':// Authorization expired
                paygent_update_status_webhook($order, 'cancelled');
                break;
            case '40':// Cleared
                paygent_update_status_webhook($order, 'completed');
                break;
            case '41':// Cleared (no change more)
                paygent_update_status_webhook($order, 'not_set');
                break;
        }
    }
}

/**
 * Paygent PayPay response.
 *
 * @param object $order WP_Order.
 * @param array $get_array
 * @return string $message
 */
function paygent_paypay_webhook( $order, $get_array ){
	if(isset($get_array['payment_status'])){
		switch($get_array['payment_status']){
			case '10':// Applied
				paygent_update_status_webhook($order, 'pending');
				break;
			case '15':// Application suspension
				paygent_update_status_webhook($order, 'cancelled');
				break;
			case '40':// Cleared
				paygent_update_status_webhook($order, 'processing');
				break;
			case '41':// Cleared (no change more)
				paygent_update_status_webhook($order, 'not_set');
				break;
			case '60':// Refund
				paygent_update_status_webhook($order, 'all_refunded');
				break;
		}
	}
}

/**
 * Paygent RakutenPay response.
 *
 * @param object $order WP_Order.
 * @param array $get_array
 * @return string $message
 */
function paygent_rakutenpay_webhook( $order, $get_array ){
	if(isset($get_array['payment_status'])){
		switch($get_array['payment_status']){
			case '15':// Application suspension
				paygent_update_status_webhook($order, 'cancelled');
				break;
            case '20':// Authority OK
                paygent_update_status_webhook($order, 'processing');
                break;
            case '40':// Cleared
			    paygent_update_status_webhook($order, 'processing');
				break;
			case '32':// Approval revoked
				paygent_update_status_webhook($order, 'cancelled');
				break;
            case '60':// Refund
                paygent_update_status_webhook($order, 'all_refunded');
                break;
            case '33':// Authorization expired
                paygent_update_status_webhook($order, 'cancelled');
                break;
            case '41':// Sales Completed (no change more)
                paygent_update_status_webhook($order, 'not_set');
                break;
		}
	}
}

function paygent_payment_status_array(){
    return array(
        '10' => __('Apply', 'woocommerce-for-paygent-payment-main'),
        '11' => __('Approval failure', 'woocommerce-for-paygent-payment-main'),
        '12' => __('Expired payment', 'woocommerce-for-paygent-payment-main'),
        '13' => __('3D secure failure', 'woocommerce-for-paygent-payment-main'),
        '15' => __('Application interruption', 'woocommerce-for-paygent-payment-main'),
        '20' => __('Authority OK', 'woocommerce-for-paygent-payment-main'),
        '30' => __('Requesting sales', 'woocommerce-for-paygent-payment-main'),
        '31' => __('The authority is being canceled', 'woocommerce-for-paygent-payment-main'),
        '32' => __('Approval revoked', 'woocommerce-for-paygent-payment-main'),
        '33' => __('Authorization expired', 'woocommerce-for-paygent-payment-main'),
        '36' => __('Sales hold', 'woocommerce-for-paygent-payment-main'),
        '40' => __('Sales Completed', 'woocommerce-for-paygent-payment-main'),
        '41' => __('Sales Completed (no change more)', 'woocommerce-for-paygent-payment-main'),
        '43' => __('Breaking news detected', 'woocommerce-for-paygent-payment-main'),
        '44' => __('Sales Completed', 'woocommerce-for-paygent-payment-main'),
        '60' => __('Sales canceled', 'woocommerce-for-paygent-payment-main'),
        '61' => __('Breaking news canceled', 'woocommerce-for-paygent-payment-main'),
        '62' => __('Cancellation completed', 'woocommerce-for-paygent-payment-main'),
    );
}