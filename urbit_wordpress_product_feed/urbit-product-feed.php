<?php
/**
 * Plugin Name: Urbit Product Feed
 * Plugin URI: https://urb-it.com/
 * Description: Urbit Product Feed plugin for Woocommerce.
 * Version: 1.0.3
 * Author: Urb-IT
 * Author URI: https://urb-it.com/
 */

/**
 * Init constaints
 */
require_once dirname(__FILE__) . '/constants.php';

/*
 * Init classes
 */
require_once URBIT_PRODUCT_FEED_CLASS_DIR . '/_init.php';

/*
 * Run plugin
 */
$UPF = new UPF_Core(__FILE__);

/*
 * Run admin
 */
if (is_admin()) {

    function load_product_feed_styles($hook) {
        if($hook != 'urbit_page_product-feed' && $hook != 'urbit-feed_page_product-feed') {
            return;
        }
        wp_enqueue_style( 'bootstrap_css', plugins_url('templates/admin/assets/css/bootstrap.min.css', __FILE__) );
        wp_enqueue_style( 'config_css', plugins_url('templates/admin/assets/css/config.css', __FILE__) );
        wp_enqueue_script( 'bootstrap_js', plugins_url('templates/admin/assets/js/bootstrap.js', __FILE__) );
        wp_enqueue_script( 'jquery', plugins_url('templates/admin/assets/js/jquery-3.2.1.min.js', __FILE__) );
        wp_enqueue_script( 'multiselect', plugins_url('templates/admin/assets/js/multiselect.js', __FILE__) );
        wp_enqueue_script( 'config_js', plugins_url('templates/admin/assets/js/config.js', __FILE__) );
    }
    add_action( 'admin_enqueue_scripts', 'load_product_feed_styles' );

    $UPFAdmin = new UPF_Admin_Core($UPF);
}
