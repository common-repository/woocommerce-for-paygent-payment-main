<?php
/**
 * Paygent Payment Gateway
 *
 * Admin Page control
 *
 * @version		2.2.0
 * @package 	Admin Screen
 * @author 		ArtisanWorkshop
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use ArtisanWorkshop\WooCommerce\PluginFramework\v2_0_13 as Framework;

class WC_Admin_Screen_Paygent {

    /**
     * Error messages.
     *
     * @var array
     */
    public $errors   = array();

    /**
     * Update messages.
     *
     * @var array
     */
    public $messages = array();

	/**
	 * Framework.
	 *
	 * @var object
	 */
	public $jp4wc_plugin;

	public $prefix;
	public $post_prefix;

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'wc_admin_paygent_menu' ) ,55 );
		add_action( 'admin_notices', array( $this, 'paygent_file_check' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'admin_init', array( $this, 'wc_setting_paygent_init') );
		$this->prefix =  'wc-paygent-';
		$this->post_prefix =  'paygent_';
		$this->jp4wc_plugin = new Framework\JP4WC_Plugin();
	}

	/**
	 * Admin Menu
	 */
	public function wc_admin_paygent_menu() {
		$page = add_submenu_page( 'woocommerce', __( 'Paygent Setting', 'woocommerce-for-paygent-payment-main' ), __( 'Paygent Setting', 'woocommerce-for-paygent-payment-main' ), 'manage_woocommerce', 'jp4wc-paygent-output', array( $this, 'wc_paygent_output' ) );
	}

	/**
	 * Admin Screen output
	 */
	public function wc_paygent_output() {
		$tab = ! empty( $_GET['tab'] ) && $_GET['tab'] == 'info' ? 'info' : 'setting';
		include('views/html-admin-screen.php');
	}

	/**
	 * Admin page for Setting
	 */
	public function admin_paygent_setting_page() {
		include('views/html-admin-setting-screen.php');
	}

	/**
	 * Admin page for infomation
	 */
	public function admin_paygent_info_page() {
		include('views/html-admin-info-screen.php');
	}

    /**
     * Check require files set in this site and notify the user.
     */
	public function paygent_file_check(){
	    if(isset($_GET['page']) && $_GET['page'] == 'jp4wc-paygent-output')
       // * Check if Client Cert file and CA Cert file and notify the user.
		if (!file_exists(CLIENT_FILE_PATH) or !file_exists(CA_FILE_PATH) or !file_exists(CLIENT_FILE_PATH)){
			$cilent_test_msg = '';
			$cilent_msg = '';
			$ca_msg = '';
			if(!file_exists(CLIENT_FILE_PATH)) $cilent_msg = __('Client Cert File(.pem) for Production environment do not exist. ', 'woocommerce-for-paygent-payment-main' );
			if(!file_exists(CA_FILE_PATH)) $ca_msg = __('CA Cert File(.crt) do not exist. ', 'woocommerce-for-paygent-payment-main' );
			echo '<div class="error"><ul><li>' . $cilent_test_msg . $cilent_msg . $ca_msg . '</li></ul></div>';
		}
       // * Check if Client Cert file and CA Cert file uploaded files is fault.
		if (isset($this->pem_error_message) or isset($this->crt_error_message)){
			if($this->pem_error_message) $cilent_msg = $this->pem_error_message;
			if($this->crt_error_message) $ca_msg = $this->crt_error_message;
			echo '<div class="error"><ul><li>' . __('Mistake your uploaded file.', 'woocommerce-for-paygent-payment-main' ) .$cilent_msg.$ca_msg. '</li></ul></div>';
		}
	}

	function wc_setting_paygent_init(){
		global $woocommerce;
		register_setting(
		    'wc_paygent_options',
            'wc_paygent_options_name',
            array( $this, 'validate_options' )
        );
		// Basic Setting
		add_settings_section(
		    'wc_paygent_basic',
            __( 'Basic Setting', 'woocommerce-for-paygent-payment-main' ),
            '',
            'wc_paygent_options'
        );
		add_settings_field(
		    'wc_paygent_basic_ip_address',
            __( 'Server IP address', 'woocommerce-for-paygent-payment-main' ),
            array( $this, 'wc_paygent_basic_ip_address' ),
            'wc_paygent_options',
            'wc_paygent_basic'
        );
        add_settings_field(
            'wc_paygent_basic_notification_url',
            __( 'Payment information difference notification URL', 'woocommerce-for-paygent-payment-main' ),
            array( $this, 'wc_paygent_basic_notification_url' ),
            'wc_paygent_options',
            'wc_paygent_basic'
        );
		add_settings_field(
		    'wc_paygent_basic_site_id',
            __( 'Site ID', 'woocommerce-for-paygent-payment-main' ),
            array( $this, 'wc_paygent_basic_site_id' ),
            'wc_paygent_options',
            'wc_paygent_basic'
        );
		add_settings_field(
		    'wc_paygent_basic_cac_file',
            __( 'CA Cert File', 'woocommerce-for-paygent-payment-main' ),
            array( $this, 'wc_paygent_basic_cac_file' ),
            'wc_paygent_options',
            'wc_paygent_basic'
        );
        add_settings_field(
            'wc_paygent_basic_testmode',
            __( 'Test Mode', 'woocommerce-for-paygent-payment-main' ),
            array( $this, 'wc_paygent_basic_testmode' ),
            'wc_paygent_options',
            'wc_paygent_basic'
        );
		add_settings_field(
			'wc_paygent_hash_check',
			__( 'Telegram hash check', 'woocommerce-for-paygent-payment-main' ),
			array( $this, 'wc_paygent_hash_check' ),
			'wc_paygent_options',
			'wc_paygent_basic'
		);

		// Production environment Setting
		add_settings_section(
		    'wc_paygent_production',
            __( 'Production environment', 'woocommerce-for-paygent-payment-main' ),
            '',
            'wc_paygent_options'
        );
		add_settings_field(
		    'wc_paygent_merchant_id',
            __( 'Merchant ID', 'woocommerce-for-paygent-payment-main' ),
            array( $this, 'wc_paygent_merchant_id' ),
            'wc_paygent_options',
            'wc_paygent_production'
        );
		add_settings_field(
		    'wc_paygent_connect_id',
            __( 'Connect ID', 'woocommerce-for-paygent-payment-main' ),
            array( $this, 'wc_paygent_connect_id' ),
            'wc_paygent_options',
            'wc_paygent_production'
        );
		add_settings_field(
		    'wc_paygent_connect_password',
            __( 'Connect Password', 'woocommerce-for-paygent-payment-main' ),
            array( $this, 'wc_paygent_connect_password' ),
            'wc_paygent_options',
            'wc_paygent_production'
        );
		add_settings_field(
		    'wc_paygent_token_key',
            __( 'Token Generation Key', 'woocommerce-for-paygent-payment-main' ),
            array( $this, 'wc_paygent_token_key' ),
            'wc_paygent_options',
            'wc_paygent_production'
        );
		add_settings_field(
		    'wc_paygent_client_cert_file',
            __( 'Client Cert File', 'woocommerce-for-paygent-payment-main' ),
            array( $this, 'wc_paygent_client_cert_file' ),
            'wc_paygent_options',
            'wc_paygent_production'
        );
		add_settings_field(
			'wc_paygent_hash_code',
			__( 'Telegram hash check Code', 'woocommerce-for-paygent-payment-main' ),
			array( $this, 'wc_paygent_hash_code' ),
			'wc_paygent_options',
			'wc_paygent_production'
		);
		add_settings_field(
			'wc_paygent_debug',
			__( 'Paygent debug Mode', 'woocommerce-for-paygent-payment-main' ),
			array( $this, 'wc_paygent_debug' ),
			'wc_paygent_options',
			'wc_paygent_production'
		);

		// Test environment Setting
		add_settings_section(
		    'wc_paygent_test',
            __( 'Test environment', 'woocommerce-for-paygent-payment-main' ),
            '',
            'wc_paygent_options'
        );
		add_settings_field(
		    'wc_paygent_test_merchant_id',
            __( 'Merchant ID', 'woocommerce-for-paygent-payment-main' ),
            array( $this, 'wc_paygent_test_merchant_id' ),
            'wc_paygent_options',
            'wc_paygent_test'
        );
		add_settings_field(
		    'wc_paygent_test_connect_id',
            __( 'Connect ID', 'woocommerce-for-paygent-payment-main' ),
            array( $this, 'wc_paygent_test_connect_id' ),
            'wc_paygent_options',
            'wc_paygent_test'
        );
		add_settings_field(
		    'wc_paygent_test_connect_password',
            __( 'Connect Password', 'woocommerce-for-paygent-payment-main' ),
            array( $this, 'wc_paygent_test_connect_password' ),
            'wc_paygent_options',
            'wc_paygent_test'
        );
		add_settings_field(
		    'wc_paygent_test_token_key',
            __( 'Token Generation Key', 'woocommerce-for-paygent-payment-main' ),
            array( $this, 'wc_paygent_test_token_key' ),
            'wc_paygent_options',
            'wc_paygent_test'
        );
/*        add_settings_field(
            'wc_paygent_test_client_cert_file',
            __( 'Client Cert File', 'woocommerce-for-paygent-payment-main' ),
            array( $this, 'wc_paygent_test_client_cert_file' ),
            'wc_paygent_options',
            'wc_paygent_test'
        );*/
		add_settings_field(
			'wc_paygent_test_hash_code',
			__( 'Test Telegram hash check Code', 'woocommerce-for-paygent-payment-main' ),
			array( $this, 'wc_paygent_test_hash_code' ),
			'wc_paygent_options',
			'wc_paygent_test'
		);

		// Paygent Payment Method Setting
		add_settings_section(
		    'wc_paygent_payment_method',
            __( 'Paygent Payment Method', 'woocommerce-for-paygent-payment-main' ),
            '',
            'wc_paygent_options'
        );
        add_settings_field(
            'wc_paygent_prefix_order',
            __( 'Prefix of Order Number', 'woocommerce-for-paygent-payment-main' ),
            array( $this, 'wc_paygent_prefix_order' ),
            'wc_paygent_options',
            'wc_paygent_payment_method'
        );
		add_settings_field(
		    'wc_paygent_payment_cc',
            __( 'Credit Card', 'woocommerce-for-paygent-payment-main' ),
            array( $this, 'wc_paygent_payment_cc' ),
            'wc_paygent_options',
            'wc_paygent_payment_method'
        );
		add_settings_field(
			'wc_paygent_payment_paidy',
			__( 'Paidy Payment', 'woocommerce-for-paygent-payment-main' ),
			array( $this, 'wc_paygent_payment_paidy' ),
			'wc_paygent_options',
			'wc_paygent_payment_method'
		);
		add_settings_field(
			'wc_paygent_payment_paypay',
			__( 'PayPay Payment', 'woocommerce-for-paygent-payment-main' ),
			array( $this, 'wc_paygent_payment_paypay' ),
			'wc_paygent_options',
			'wc_paygent_payment_method'
		);
		add_settings_field(
			'wc_paygent_payment_rakutenpay',
			__( 'RakutenPay Payment', 'woocommerce-for-paygent-payment-main' ),
			array( $this, 'wc_paygent_payment_rakutenpay' ),
			'wc_paygent_options',
			'wc_paygent_payment_method'
		);
		add_settings_field(
		    'wc_paygent_payment_cs',
            __( 'Convenience store', 'woocommerce-for-paygent-payment-main' ),
            array( $this, 'wc_paygent_payment_cs' ),
            'wc_paygent_options',
            'wc_paygent_payment_method'
        );
		add_settings_field(
		    'wc_paygent_payment_bn',
            __( 'Bank Net', 'woocommerce-for-paygent-payment-main' ),
            array( $this, 'wc_paygent_payment_bn' ),
            'wc_paygent_options',
            'wc_paygent_payment_method'
        );
		add_settings_field(
		    'wc_paygent_payment_atm',
            __( 'ATM Payment', 'woocommerce-for-paygent-payment-main' ),
            array( $this, 'wc_paygent_payment_atm' ),
            'wc_paygent_options',
            'wc_paygent_payment_method'
        );
		add_settings_field(
		    'wc_paygent_payment_mb',
            __( 'Carrier Payment', 'woocommerce-for-paygent-payment-main' ),
            array( $this, 'wc_paygent_payment_mb' ),
            'wc_paygent_options',
            'wc_paygent_payment_method'
        );
		add_settings_field(
		    'wc_paygent_payment_mccc',
            __( 'Multi-currency Credit Card', 'woocommerce-for-paygent-payment-main' ),
            array( $this, 'wc_paygent_payment_mccc' ),
            'wc_paygent_options',
            'wc_paygent_payment_method'
        );

		if( isset( $_POST['_wpnonce']) and isset($_GET['page']) and $_GET['page'] == 'jp4wc-paygent-output' ){
			$paygent_auths = array( 'mid', 'cid', 'cpass', 'tokenkey', 'hash_code' );
			foreach($paygent_auths as $paygent_auth){
				$post_auth = $paygent_auth;
				$option_auth = $this->prefix.$paygent_auth;
				if(isset($_POST[$post_auth]) && $_POST[$post_auth]){
					update_option( $option_auth, $_POST[$post_auth]);
				}else{
					update_option( $option_auth, '');
				}
				$post_test_auth = 'test-'.$paygent_auth;
				$option_test_auth = $this->prefix.'test-'.$paygent_auth;
				if(isset($_POST[$post_test_auth]) && $_POST[$post_test_auth]){
					update_option( $option_test_auth, $_POST[$post_test_auth]);
				}else{
					update_option( $option_test_auth, '');
				}
			}
			//Save selected client-cert-file
            update_option( 'wc-paygent-test-client-cert-file', $_POST['test-client-cert-file']);
			//Site ID Setting
			if(isset($_POST['sid'])){
				update_option( $this->prefix.'sid', wc_clean($_POST['sid']));
			}else{
                update_option( $this->prefix.'sid', '');
            }
            //Test mode Setting
            if(isset($_POST['testmode'])){
                update_option( $this->prefix.'testmode', $_POST['testmode']);
            }else{
                update_option( $this->prefix.'testmode', '');
            }
			//Telegram hash check Setting
			if(isset($_POST['hash_check'])){
				update_option( $this->prefix.'hash_check', $_POST['hash_check']);
			}else{
				update_option( $this->prefix.'hash_check', '');
			}
            //Prefix of Order Number Setting
            if(isset($_POST['prefix_order']) && $_POST['prefix_order']){
                update_option( $this->prefix.'prefix_order', $_POST['prefix_order']);
            }
			//Paygent debug mode
			if(isset($_POST['debug'])){
				update_option( $this->prefix.'debug', $_POST['debug']);
			}else{
				update_option( $this->prefix.'debug', '');
			}
            $wc_paybent_dir = WP_CONTENT_DIR.'/uploads/wc-paygent';
            if( !is_dir( $wc_paybent_dir ) ){
                if( mkdir( $wc_paybent_dir, 0755 ) ){
                    $message = __('wc-paygent folder created.', 'woocommerce-for-paygent-payment-main');
                    $this->jp4wc_plugin->jp4wc_debug_log($message, 'yes', 'wc-paygent');
                }else{
                    $message = __('wc-paygent folder could not be created.', 'woocommerce-for-paygent-payment-main');
                    $this->jp4wc_plugin->jp4wc_debug_log($message, 'yes', 'wc-paygent');
                }
            }
            //Client Cert File upload
			if(isset($_FILES["clientc_file"])){
				if(substr($_FILES["clientc_file"]["name"], strrpos($_FILES["clientc_file"]["name"], '.') + 1)=='pem'){
					if (move_uploaded_file($_FILES["clientc_file"]["tmp_name"], WP_CONTENT_DIR.'/uploads/wc-paygent/client_cert.pem')) {
				    	chmod(WP_CONTENT_DIR.'/uploads/wc-paygent/client_cert.pem' , 0644);
					} else {
                        $this->jp4wc_plugin->add_error( __( 'Client Cert File have not been uploaded.', 'woocommerce-for-paygent-payment-main' ), $this );
					}
				}else{
					if($_FILES["clientc_file"]["name"]){//error_message
						$this->pem_error_message = __('Uploaded flie is not Client Cert File. Please check .pem file.', 'woocommerce-for-paygent-payment-main' );
					}
				}
			}
			//CA Cert File upload
			if(isset($_FILES["cac_file"])){
				if(substr($_FILES["cac_file"]["name"], strrpos($_FILES["cac_file"]["name"], '.') + 1)=='crt'){
					if (move_uploaded_file($_FILES["cac_file"]["tmp_name"], WP_CONTENT_DIR.'/uploads/wc-paygent/curl-ca-bundle.crt')) {
					    chmod(WP_CONTENT_DIR.'/uploads/wc-paygent/curl-ca-bundle.crt', 0644);
					} else {
                        $this->jp4wc_plugin->add_error( __( 'CA Cert File have not been uploaded.', 'woocommerce-for-paygent-payment-main' ), $this );
					}
				}else{
					if($_FILES["cac_file"]["name"]){//error_message
						$this->crt_error_message = __('Uploaded flie is not CA Cert File. Please check .crt file.', 'woocommerce-for-paygent-payment-main' );
					}
				}
			}
			$paygent_methods = array(
			    'cc',//cc: Credit Card
				'paidy',// Paidy Payment
				'paypay',// PayPay Payment
				'rakutenpay',// RakutenPay Payment
                'cs',// Convenience store
                'bn',// Bank Net
                'atm',// ATM
                'mb',// Carrier Payment
				'mccc',// Multi-currency Credit Card
            );
			foreach($paygent_methods as $paygent_method){
					$option_method = $this->prefix.$paygent_method;
				$post_paygent = $paygent_method;
				$setting_method = 'woocommerce_paygent_'.$paygent_method.'_settings';

				$woocommerce_paygent_setting = get_option($setting_method);
				if(isset($_POST[$post_paygent]) && $_POST[$post_paygent]){
					update_option( $option_method, $_POST[$post_paygent]);
					if(isset($woocommerce_paygent_setting)){
						$woocommerce_paygent_setting['enabled'] = 'yes';
						update_option( $setting_method, $woocommerce_paygent_setting);
					}
				}else{
					update_option( $option_method, '');
					if(isset($woocommerce_paygent_setting)){
						$woocommerce_paygent_setting['enabled'] = 'no';
						update_option( $setting_method, $woocommerce_paygent_setting);
					}
				}
			}
            $this->jp4wc_plugin->add_message( __( 'Your settings have been saved.', 'woocommerce-for-paygent-payment-main' ), $this );
		}
	}

    /**
	 * Merchant ID input field.
	 */
	public function wc_paygent_merchant_id(){
		$title = __( 'Merchant ID', 'woocommerce-for-paygent-payment-main' );
        $description = $this->jp4wc_plugin->jp4wc_description_input_pattern( $title );
		$this->jp4wc_plugin->jp4wc_input_text('mid', $description, 20, '', $this->prefix);
	}

	/**
	 * Connect ID input field.
	 */
	public function wc_paygent_connect_id(){
		$title = __( 'Connect ID', 'woocommerce-for-paygent-payment-main' );
        $description = $this->jp4wc_plugin->jp4wc_description_input_pattern( $title );
		$this->jp4wc_plugin->jp4wc_input_text('cid', $description, 20, '', $this->prefix);
	}

	/**
	 * Connect Password input field.
	 */
	public function wc_paygent_connect_password(){
		$title = __( 'Connect Password', 'woocommerce-for-paygent-payment-main' );
        $description = $this->jp4wc_plugin->jp4wc_description_input_pattern( $title );
		$this->jp4wc_plugin->jp4wc_input_text('cpass', $description, 20, '', $this->prefix);
	}

	/**
	 * Connect Password input field.
	 */
	public function wc_paygent_token_key(){
		$title = __( 'Token Generation Key', 'woocommerce-for-paygent-payment-main' );
		$description = $this->jp4wc_plugin->jp4wc_description_input_pattern( $title );
		$this->jp4wc_plugin->jp4wc_input_text('tokenkey', $description, 40, '', $this->prefix);
	}

	/**
	 * Client Cert File upload field.
	 */
	public function wc_paygent_client_cert_file(){
	?>
	<input type="file" name="clientc_file" size="30" >
    <p class="description">
	<?php if(!file_exists(CLIENT_FILE_PATH)){
		echo __( 'Please select Client Cert File (pem) from local.', 'woocommerce-for-paygent-payment-main' );
		}else{
		echo __( 'If you want to change Client Cert File, please select New Client Cert File (pem) from local.', 'woocommerce-for-paygent-payment-main' );}
		?>
	</p>
	<?php
	}

	/**
	 * Test Merchant ID input field.
	 */
	public function wc_paygent_test_merchant_id(){
		$title = __( 'Merchant ID', 'woocommerce-for-paygent-payment-main' );
        $description = $this->jp4wc_plugin->jp4wc_description_input_pattern( $title );
		$this->jp4wc_plugin->jp4wc_input_text('test-mid', $description, 20, '', $this->prefix);
	}

	/**
	 * Test Connect ID input field.
	 */
	public function wc_paygent_test_connect_id(){
		$title = __( 'Connect ID', 'woocommerce-for-paygent-payment-main' );
        $description = $this->jp4wc_plugin->jp4wc_description_input_pattern( $title );
		$this->jp4wc_plugin->jp4wc_input_text('test-cid', $description, 20, '', $this->prefix);
	}

	/**
	 * Test Connect Password input field.
	 */
	public function wc_paygent_test_connect_password(){
		$title = __( 'Connect Password', 'woocommerce-for-paygent-payment-main' );
        $description = $this->jp4wc_plugin->jp4wc_description_input_pattern( $title );
		$this->jp4wc_plugin->jp4wc_input_text('test-cpass', $description, 20, '', $this->prefix);
	}

	/**
	 * Test Connect Password input field.
	 */
	public function wc_paygent_test_token_key(){
		$title = __( 'Token Generation Key', 'woocommerce-for-paygent-payment-main' );
        $description = $this->jp4wc_plugin->jp4wc_description_input_pattern( $title );
		$this->jp4wc_plugin->jp4wc_input_text('test-tokenkey', $description, 40, '', $this->prefix);
	}

    /**
     * Test Client cert file select field.
     */
/*    public function wc_paygent_test_client_cert_file(){
        $title = __( 'Client Cert File', 'woocommerce-for-paygent-payment-main' );
        $description = $this->jp4wc_plugin->jp4wc_description_select_pattern( $title );
        $select_options = array(
            'test2' => 'test2-20180912_client_cert.pem',
            'test' => 'test-20180516_client_cert.pem',
        );
        $this->jp4wc_plugin->jp4wc_input_select('test-client-cert-file', $description, $select_options, $this->prefix);
    }*/

	/**
	 * Basic Site ID input field.
	 */
	public function wc_paygent_basic_site_id(){
		$title = __( 'Site ID', 'woocommerce-for-paygent-payment-main' );
        $description = $this->jp4wc_plugin->jp4wc_description_input_pattern( $title );
        $description .= __( 'If you have only one site please enter 1.', 'woocommerce-for-paygent-payment-main' );
		$this->jp4wc_plugin->jp4wc_input_number('sid', $description, 1, $this->prefix);
	}

	/**
	 * CA Cert File upload field.
	 */
	public function wc_paygent_basic_cac_file(){
	?>
	<input type="file" name="cac_file" size="30" >
    <p class="description">
	<?php if(!file_exists(CA_FILE_PATH)){
		echo __( 'Please select CA Cert(crt) File from local.', 'woocommerce-for-paygent-payment-main' );
		}else{
		echo __( 'If you want to change CA Cert File, please select New CA Cert(crt) File from local.', 'woocommerce-for-paygent-payment-main' );}
		?>
	</p>
	<?php
	}

	/**
	 * IP Address field.
	 */
	public function wc_paygent_basic_ip_address(){
	    echo '<b>'.$_SERVER['SERVER_ADDR'].'</b><br />';
	    echo __( 'Since it is different depending on the rental server, please contact the server company if the test is not completed.', 'woocommerce-for-paygent-payment-main' );
	}

    /**
     * Payment information difference notification URL
     */
    public function wc_paygent_basic_notification_url(){
        $notification_url = get_rest_url().'paygent/v1/check/';
        echo '<b>'.$notification_url.'</b><br />';
        echo __( 'This URL is the payment information difference notification URL set on the Paygent merchant administrator site.', 'woocommerce-for-paygent-payment-main' );
    }

    /**
     * Basic Test Mode field.
     */
    public function wc_paygent_basic_testmode(){
        $title = __( 'Test Mode', 'woocommerce-for-paygent-payment-main' );
        $description = $this->jp4wc_plugin->jp4wc_description_check_pattern( $title );
        $this->jp4wc_plugin->jp4wc_input_checkbox('testmode', $description, $this->prefix);
    }

	/**
	 * Select Telegram hash check
	 */
	public function wc_paygent_hash_check(){
		$title = __( 'Telegram hash check', 'woocommerce-for-paygent-payment-main' );
		$description = $this->jp4wc_plugin->jp4wc_description_check_pattern( $title );
		$this->jp4wc_plugin->jp4wc_input_checkbox('hash_check', $description, $this->prefix);
	}

	/**
	 * Input Telegram hash check code
	 */
	public function wc_paygent_hash_code(){
		$title = __( 'Telegram hash check Code', 'woocommerce-for-paygent-payment-main' );
		$description = $this->jp4wc_plugin->jp4wc_description_input_pattern( $title );
		$this->jp4wc_plugin->jp4wc_input_text('hash_code', $description, 40, '', $this->prefix);
	}

	/**
	 * Input Test Telegram hash check code
	 */
	public function wc_paygent_test_hash_code(){
		$title = __( 'Test Telegram hash check Code', 'woocommerce-for-paygent-payment-main' );
		$description = $this->jp4wc_plugin->jp4wc_description_input_pattern( $title );
		$this->jp4wc_plugin->jp4wc_input_text('test-hash_code', $description, 40, '', $this->prefix);
	}

	/**
	 * Input Test Telegram hash check code
	 */
	public function wc_paygent_debug(){
		$title = __( 'Paygent debug Mode', 'woocommerce-for-paygent-payment-main' );
		$description = $this->jp4wc_plugin->jp4wc_description_check_pattern( $title ).'<br />'. __( 'Check it only for error checking in the production environment. Basically, please uncheck it.', 'woocommerce-for-paygent-payment-main' );
		$this->jp4wc_plugin->jp4wc_input_checkbox('debug', $description, $this->prefix);
	}

	/**
     * Prefix of Order Number option.
     */
    public function wc_paygent_prefix_order() {
        $title = __( 'Prefix of Order Number', 'woocommerce-for-paygent-payment-main' );
        $description = $this->jp4wc_plugin->jp4wc_description_input_pattern( $title );
        $this->jp4wc_plugin->jp4wc_input_text('prefix_order', $description, 20, '', $this->prefix, $this->post_prefix);
    }

	/**
	 * Credit Card payment field.
	 */
	public function wc_paygent_payment_cc(){
		$title = __( 'Credit Card', 'woocommerce-for-paygent-payment-main' );
        $description = $this->jp4wc_plugin->jp4wc_description_check_pattern( $title );
		$this->jp4wc_plugin->jp4wc_input_checkbox('cc', $description, $this->prefix);
	}

	/**
	 * Paidy payment field.
	 */
	public function wc_paygent_payment_paidy(){
		$title = __( 'Paidy Payment', 'woocommerce-for-paygent-payment-main' );
		$description = $this->jp4wc_plugin->jp4wc_description_check_pattern( $title );
		$this->jp4wc_plugin->jp4wc_input_checkbox('paidy', $description, $this->prefix);
	}

	/**
	 * PayPay payment field.
	 */
	public function wc_paygent_payment_paypay(){
		$title = __( 'PayPay Payment', 'woocommerce-for-paygent-payment-main' );
		$description = $this->jp4wc_plugin->jp4wc_description_check_pattern( $title );
		$this->jp4wc_plugin->jp4wc_input_checkbox('paypay', $description, $this->prefix);
	}

	/**
	 * RakutenPay payment field.
	 */
	public function wc_paygent_payment_rakutenpay(){
		$title = __( 'RakutenPay Payment', 'woocommerce-for-paygent-payment-main' );
		$description = $this->jp4wc_plugin->jp4wc_description_check_pattern( $title );
		$this->jp4wc_plugin->jp4wc_input_checkbox('rakutenpay', $description, $this->prefix);
	}

	/**
	 * Convenience store payment field.
	 */
	public function wc_paygent_payment_cs(){
		$title = __( 'Convenience store', 'woocommerce-for-paygent-payment-main' );
        $description = $this->jp4wc_plugin->jp4wc_description_check_pattern( $title );
		$this->jp4wc_plugin->jp4wc_input_checkbox('cs', $description, $this->prefix);
	}

	/**
	 * Bank Net payment field.
	 */
	public function wc_paygent_payment_bn(){
		$title = __( 'Bank Net', 'woocommerce-for-paygent-payment-main' );
        $description = $this->jp4wc_plugin->jp4wc_description_check_pattern( $title );
		$this->jp4wc_plugin->jp4wc_input_checkbox('bn', $description, $this->prefix);
	}

	/**
	 * ATM Payment payment field.
	 */
	public function wc_paygent_payment_atm(){
		$title = __( 'ATM Payment', 'woocommerce-for-paygent-payment-main' );
        $description = $this->jp4wc_plugin->jp4wc_description_check_pattern( $title );
		$this->jp4wc_plugin->jp4wc_input_checkbox('atm', $description, $this->prefix);
	}

	/**
	 * Carrier Payment payment field.
	 */
	public function wc_paygent_payment_mb(){
		$title = __( 'Carrier Payment', 'woocommerce-for-paygent-payment-main' );
        $description = $this->jp4wc_plugin->jp4wc_description_check_pattern( $title );
		$this->jp4wc_plugin->jp4wc_input_checkbox('mb', $description, $this->prefix);
	}

	/**
	 * Multi-currency Credit Card payment field.
	 */
	public function wc_paygent_payment_mccc(){
		$title = __( 'Multi-currency Credit Card', 'woocommerce-for-paygent-payment-main' );
        $description = $this->jp4wc_plugin->jp4wc_description_check_pattern( $title );
		$this->jp4wc_plugin->jp4wc_input_checkbox('mccc', $description, $this->prefix);
	}
	
	/**
	 * Validate options.
	 * 
	 * @param array $input
	 * @return array
	 */
	public function validate_options( $input ) {
	}

	/**
	 * This function is similar to the function in the Settings API, only the output HTML is changed.
	 * Print out the settings fields for a particular settings section
	 *
	 * @global $wp_settings_fields Storage array of settings fields and their pages/sections
	 *
	 * @since 0.1
	 *
	 * @param string $page Slug title of the admin page who's settings fields you want to show.
	 * @param string $section Slug title of the settings section who's fields you want to show.
	 */
	function do_settings_sections( $page ) {
		global $wp_settings_sections, $wp_settings_fields;
	 
		if ( ! isset( $wp_settings_sections[$page] ) )
			return;
	 
		foreach ( (array) $wp_settings_sections[$page] as $section ) {
			echo '<div id="" class="stuffbox postbox '.$section['id'].'">';
			echo '<button type="button" class="handlediv button-link" aria-expanded="true"><span class="screen-reader-text">' . __('Toggle panel', 'woocommerce-for-japan') . '</span><span class="toggle-indicator" aria-hidden="true"></span></button>';
			if ( $section['title'] )
				echo "<h3 class=\"hndle\"><span>{$section['title']}</span></h3>\n";
	 
			if ( $section['callback'] )
				call_user_func( $section['callback'], $section );

			if ( ! isset( $wp_settings_fields ) || !isset( $wp_settings_fields[$page] ) || !isset( $wp_settings_fields[$page][$section['id']] ) )
				continue;
			echo '<div class="inside"><table class="form-table">';
			do_settings_fields( $page, $section['id'] );
			echo '</table></div>';
			echo '</div>';
		}
	}

	/**
	 * Enqueue admin scripts and styles.
	 * 
	 * @global $page
	 */
	public function admin_enqueue_scripts( $page ) {
		global $pagenow;
		wp_enqueue_script( 'wc-paygent-admin-script', plugins_url( 'views/js/admin-settings.js', __FILE__ ), array( 'jquery', 'jquery-ui-core', 'jquery-ui-button', 'jquery-ui-slider' ), WC_PAYGENT_VERSION );
		wp_enqueue_script( 'postbox' );
        wp_register_style( 'wc-paygent-admin', plugins_url( 'views/css/admin-wc-paygent.css', __FILE__ ), false, WC_PAYGENT_VERSION );
        wp_enqueue_style( 'wc-paygent-admin' );
	}
}

new WC_Admin_Screen_Paygent();