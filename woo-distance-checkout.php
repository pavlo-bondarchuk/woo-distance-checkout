<?php

/**
 * Plugin Name: Woo Distance Checkout
 * Description: Distance-based checkout for WooCommerce with delivery and pickup options.
 * Version: 1.2
 * Author: Pavlo Bondarchuk
 * Author URI: https://bonddesign.top
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Domain Path: /languages
 * Text Domain: woo-distance-checkout
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 5.0
 */

if (! defined('ABSPATH')) {
    exit;
}

define('WDC_PLUGIN_FILE', __FILE__);
define('WDC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WDC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WDC_PLUGIN_VERSION', '1.1');
define('WDC_MINIMUM_WP_VERSION', '5.0');
define('WDC_MINIMUM_WC_VERSION', '5.0');
define('WDC_MINIMUM_PHP_VERSION', '7.4');

require_once WDC_PLUGIN_DIR . 'includes/class-loader.php';
require_once WDC_PLUGIN_DIR . 'includes/class-plugin.php';
require_once WDC_PLUGIN_DIR . 'includes/helpers.php';

add_action('plugins_loaded', array('WDC_Plugin', 'instance'));
