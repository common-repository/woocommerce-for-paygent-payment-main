<?php
/**
 * Plugin Name: PAYGENT for WooCommerce
 * Plugin URI: https://wordpress.org/plugins/woocommerce-for-paygent-payment-main/
 * Description: Paygent Payments for WooCommerce in Japan
 * Version: 2.3.2
 * Author: Artisan Workshop
 * Author URI: https://wc.artws.info/
 * Requires at least: 5.0
 * Tested up to: 6.3.1
 * WC requires at least: 3.0.0
 * WC tested up to: 8.3.1
 * 
 * Text Domain: woocommerce-for-paygent-payment-main
 * Domain Path: /i18n/
 * 
 * @package woocommerce-for-paygent-payment-main
 * @category Core
 * @author Artisan Workshop
 */
//use ArtisanWorkshop\WooCommerce\PluginFramework\v2_0_13 as Framework;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'WC_Gateway_Paygent' ) ) :

class WC_Gateway_Paygent{

	/**
	 * Paygent Payment Gateways for WooCommerce version.
	 *
	 * @var string
	 */
	public $version = '2.3.2';

    /**
     * Paygent Payment Gateways for WooCommerce Framework version.
     *
     * @var string
     */
    public $framework_version = '2.0.13';

    /**
     * The single instance of the class.
     *
     * @var object
     */
    protected static $instance = null;

    /**
	 * Paygent Payment Gateways for WooCommerce Constructor.
	 * @access public
	 */
	public function __construct() {
		// handle HPOS compatibility
		add_action( 'before_woocommerce_init', [ $this, 'jp4wc_paygent_handle_hpos_compatibility' ] );
	}

    /**
     * Get class instance.
     *
     * @return object Instance.
     */
    public static function instance() {
        if ( null === static::$instance ) {
            static::$instance = new static();
        }
        return static::$instance;
    }


    /**
     * Init the feature plugin, only if we can detect WooCommerce.
     *
     * @since 2.0.0
     * @version 2.0.0
     */
    public function init() {
        $this->define_constants();
        register_activation_hook( WC_PAYGENT_PLUGIN_FILE, array( $this, 'on_activation' ) );
        register_deactivation_hook( WC_PAYGENT_PLUGIN_FILE, array( $this, 'on_deactivation' ) );
        add_action( 'plugins_loaded', array( $this, 'on_plugins_loaded' ), 20 );
    }

    /**
     * Flush rewrite rules on deactivate.
     *
     * @return void
     */
    public function on_deactivation() {
        flush_rewrite_rules();
    }

    /**
     * Setup plugin once all other plugins are loaded.
     *
     * @return void
     */
    public function on_plugins_loaded() {
        $this->load_plugin_textdomain();
        $this->includes();
    }

    /**
     * Define Constants.
     */
    protected function define_constants() {
        define( 'WC_PAYGENT_PLUGIN_FILE', __FILE__ );
        define( 'WC_PAYGENT_PLUGIN_URL' , plugin_dir_url( __FILE__ ) );
        define( 'WC_PAYGENT_PLUGIN_PATH' , plugin_dir_path( __FILE__ ) );
        define( 'WC_PAYGENT_ABSPATH', dirname( __FILE__ ) . '/' );
        define( 'CLIENT_TEST3_FILE_PATH', WC_PAYGENT_PLUGIN_PATH.'/assets/files/test3-20221218_client_cert.pem' );
        define( 'CLIENT_FILE_PATH' , WP_CONTENT_DIR.'/uploads/wc-paygent/client_cert.pem' );
        define( 'CA_FILE_PATH' , WP_CONTENT_DIR.'/uploads/wc-paygent/curl-ca-bundle.crt' );
        define( 'WC_PAYGENT_VERSION', $this->version );
        define( 'WC_PAYGENT_FRAMEWORK_VERSION', $this->framework_version );
    }

    /**
     * Load Localisation files.
     */
    protected function load_plugin_textdomain() {
        load_plugin_textdomain( 'woocommerce-for-paygent-payment-main', false, basename( dirname( __FILE__ ) ) . '/i18n' );
    }

    /**
     * Include WC_Gateway_Paygent classes.
     */
    private function includes() {
        //load framework
        $version_text = 'v'.str_replace('.', '_', WC_PAYGENT_FRAMEWORK_VERSION);
        if ( ! class_exists( '\\ArtisanWorkshop\\WooCommerce\\PluginFramework\\'.$version_text.'\\JP4WC_Plugin' ) ) {
            require_once WC_PAYGENT_ABSPATH.'includes/jp4wc-framework/class-jp4wc-framework.php';
        }
        // Admin Setting Screen
        require_once( WC_PAYGENT_ABSPATH.'includes/admin/class-wc-admin-screen-paygent.php' );
        // load autoload
        require_once( WC_PAYGENT_ABSPATH.'vendor/autoload.php' );
        include_once( WC_PAYGENT_ABSPATH.'includes/gateways/paygent/includes/class-wc-gateway-paygent-request.php' );
        // Credit Card
        require_once ( WC_PAYGENT_ABSPATH.'includes/gateways/paygent/class-wc-gateway-paygent-cc.php' );
        require_once ( WC_PAYGENT_ABSPATH.'includes/gateways/paygent/class-wc-gateway-paygent-addon-cc.php' );
        // Convenience store
        require_once ( WC_PAYGENT_ABSPATH.'includes/gateways/paygent/class-wc-gateway-paygent-cs.php' );
        // Paidy Payment
        require_once ( WC_PAYGENT_ABSPATH.'includes/gateways/paygent/class-wc-gateway-paygent-paidy.php' );
        // Webhook Endpoint
        require_once ( WC_PAYGENT_ABSPATH.'includes/gateways/paygent/class-wc-paygent-endpoint.php' );
        // Rakuten Pay
	    require_once ( WC_PAYGENT_ABSPATH.'includes/gateways/paygent/class-wc-gateway-paygent-rakuten-pay.php' );
        // PayPay
	    require_once ( WC_PAYGENT_ABSPATH.'includes/gateways/paygent/class-wc-gateway-paygent-paypay.php' );
        // Carrier Payment
        if(get_option('wc-paygent-mb')) {
            require_once ( WC_PAYGENT_ABSPATH.'includes/gateways/paygent/class-wc-gateway-paygent-mb.php' );
	        require_once ( WC_PAYGENT_ABSPATH.'includes/gateways/paygent/class-wc-gateway-paygent-addon-mb.php' );
        }
        // Bank Net
        if(get_option('wc-paygent-bn')) require_once ( WC_PAYGENT_ABSPATH.'includes/gateways/paygent/class-wc-gateway-paygent-bn.php' );
        // ATM Payment
        if(get_option('wc-paygent-atm')) require_once ( WC_PAYGENT_ABSPATH.'includes/gateways/paygent/class-wc-gateway-paygent-atm.php' );
        // Multi-currency Credit Card// Multi-currency Credit Card
        if(get_option('wc-paygent-mccc')) require_once ( WC_PAYGENT_ABSPATH.'includes/gateways/paygent/class-wc-gateway-paygent-mccc.php' );
    }

    /**
     * Attached to activate_{ plugin_basename( __FILES__ ) } by register_activation_hook()
     * @static
     */
	public static function on_activation(){
		$wc_paygent_dir = WP_CONTENT_DIR.'/uploads/wc-paygent';
		if( !is_dir( $wc_paygent_dir ) ){
            $context = array( 'source' => 'wc-paygent' );
            $logger = wc_get_logger();
            if( mkdir( $wc_paygent_dir, 0755 ) ){
                $logger->info( __('wc-paygent folder created.', 'woocommerce-for-paygent-payment-main'), $context );
            }else{
                $logger->notice( __('wc-paygent folder could not be created.', 'woocommerce-for-paygent-payment-main'), $context );
            }
        }
    }

	/**
	 * Declares HPOS compatibility if the plugin is compatible with HPOS.
	 *
	 * @internal
	 *
	 * @since 2.6.0
	 */
	public function jp4wc_paygent_handle_hpos_compatibility() {

		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
            $slug = dirname( plugin_basename( __FILE__ ) );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables',trailingslashit( $slug ) . $slug . '.php' , true );
		}
	}
}
endif;

/**
 * Load plugin functions.
 */
add_action( 'plugins_loaded', 'WC_Gateway_Paygent_plugin');

function WC_Gateway_Paygent_plugin() {
    if ( is_woocommerce_active() ) {
        WC_Gateway_Paygent::instance()->init();
    } else {
        add_action( 'admin_notices', 'WC_Gateway_Paygent_fallback_notice' );
    }
}

function WC_Gateway_Paygent_fallback_notice() {
    ?>
    <div class="error">
        <ul>
            <li><?php echo __( 'Paygent Payment Gateways for WooCommerce is enabled but not effective. It requires WooCommerce in order to work.', 'woocommerce-for-japan' );?></li>
        </ul>
    </div>
    <?php
}

/**
 * WC Detection
 */
if ( ! function_exists( 'is_woocommerce_active' ) ) {
    function is_woocommerce_active() {
        if ( ! isset($active_plugins) ) {
            $active_plugins = (array) get_option( 'active_plugins', array() );

            if ( is_multisite() )
                $active_plugins = array_merge( $active_plugins, get_site_option( 'active_sitewide_plugins', array() ) );
        }
        return in_array( 'woocommerce/woocommerce.php', $active_plugins ) || array_key_exists( 'woocommerce/woocommerce.php',$active_plugins );
    }
}
