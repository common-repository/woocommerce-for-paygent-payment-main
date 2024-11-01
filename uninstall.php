<?php
if( ! defined ('WP_UNINSTALL_PLUGIN') )
exit();
function wc_paygent_delete_plugin(){
	//delete option settings
    $paygent_methods = array(
        'cc',//cc: Credit Card
        'cs',// Convenience store
        'mccc',// Multi-currency Credit Card
        'bn',// Bank Net
        'atm',// ATM
        'mb',// Carrier Payment
        'paidy'// Paidy Payment
    );
    foreach($paygent_methods as $paygent_method){
        $setting_method = 'woocommerce_paygent_'.$paygent_method.'_settings';
        delete_option($setting_method);
        $option_method = 'wc-paygent-'.$paygent_method;
        delete_option($option_method);
    }
	delete_option('wc-paygent-cid');
	delete_option('wc-paygent-cpass');
	delete_option('wc-paygent-mid');
	delete_option('wc-paygent-sid');

    //delete paygent files and directory
    unlink(WP_CONTENT_DIR.'/uploads/wc-paygent/client_cert.pem');
    unlink(WP_CONTENT_DIR.'/uploads/wc-paygent/curl-ca-bundle.crt');
    rmdir(WP_CONTENT_DIR.'/uploads/wc-paygent');
}

wc_paygent_delete_plugin();
?>