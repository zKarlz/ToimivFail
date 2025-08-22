<?php
/**
 * Plugin Name: WC Custom Frame Preview
 * Description: Frame overlay + photo editor for WooCommerce products. Adds a modal editor, replaces the product image preview, and attaches the mockup to Cart/Checkout/Orders (customer + admin). Includes a per-product frames metabox.
 * Version: 1.6.0
 * Author: ChatGPT Codex
 * Text Domain: wc-cfp
 */
if (!defined('ABSPATH')) { exit; }

define('CFP_PATH', plugin_dir_path(__FILE__));
define('CFP_URL',  plugin_dir_url(__FILE__));

require_once CFP_PATH . 'includes/functions.php';
require_once CFP_PATH . 'includes/class-cfp-frontend.php';
require_once CFP_PATH . 'includes/class-cfp-admin.php';

add_action('plugins_loaded', function(){
    if (class_exists('CFP_Frontend')) { CFP_Frontend::init(); }
    if (is_admin() && class_exists('CFP_Admin')) { CFP_Admin::init(); }
});
