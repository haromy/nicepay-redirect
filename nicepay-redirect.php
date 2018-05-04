<?php
/*
Plugin Name: NICEPay Redirect API V1
Plugin URI: http://nicepay.co.id
Description: NICEPay Credit Card Payment Gateway, it's using API version 1.
Version: 1
Author: NICEPay <codeNinja>
Author URI: http://nicepay.co.id
API docs : http://docs.nicepay.co.id
*/

if (!defined('ABSPATH'))
    exit;

add_action('plugins_loaded', 'woocommerce_nicepay_init', 0);

function woocommerce_nicepay_init()
{
    //Validation class payment gateway woocommerce
    if (!class_exists('WC_Payment_Gateway')) {
		return;
	}

	define( 'PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
	require_once dirname( __FILE__ ) . '/class/nicepay-cc-full.php';
    
    add_filter('woocommerce_payment_gateways', 'add_nicepay_gateway');
}

function add_nicepay_gateway($methods) {
    $methods[] = 'WC_Gateway_NICEPay_CC';
    return $methods;
}