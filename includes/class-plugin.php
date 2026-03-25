<?php

class WDC_Plugin
{

    private static $instance = null;
    private $loader;

    public static function instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        if (! $this->check_dependencies()) {
            return;
        }

        $this->loader = new WDC_Loader();
        $this->load_includes();
        $this->define_hooks();
        $this->loader->run();
    }

    private function check_dependencies()
    {
        if (! function_exists('WC')) {
            add_action('admin_notices', array($this, 'missing_woocommerce_notice'));
            return false;
        }
        return true;
    }

    public function missing_woocommerce_notice()
    {
        if (! current_user_can('manage_options')) {
            return;
        }
        echo '<div class="notice notice-error is-dismissible"><p>';
        echo esc_html__('Woo Distance Checkout requires WooCommerce to be installed and activated.', 'woo-distance-checkout');
        echo '</p></div>';
    }

    private function load_includes()
    {
        require_once WDC_PLUGIN_DIR . 'includes/class-logger.php';
        require_once WDC_PLUGIN_DIR . 'includes/class-settings.php';
        require_once WDC_PLUGIN_DIR . 'includes/class-store-locations.php';
        require_once WDC_PLUGIN_DIR . 'includes/class-address-resolver.php';
        require_once WDC_PLUGIN_DIR . 'includes/class-address-validation-service.php';
        require_once WDC_PLUGIN_DIR . 'includes/class-distance-service.php';
        require_once WDC_PLUGIN_DIR . 'includes/class-tax-service.php';
        require_once WDC_PLUGIN_DIR . 'includes/class-shipping-calculator.php';
        require_once WDC_PLUGIN_DIR . 'includes/class-calculation-coordinator.php';
        require_once WDC_PLUGIN_DIR . 'includes/class-checkout-controller.php';
        require_once WDC_PLUGIN_DIR . 'includes/class-cart-totals.php';
        require_once WDC_PLUGIN_DIR . 'includes/class-order-meta.php';
        require_once WDC_PLUGIN_DIR . 'admin/class-admin.php';
        require_once WDC_PLUGIN_DIR . 'admin/class-admin-order-display.php';
    }

    private function define_hooks()
    {
        $settings = new WDC_Settings();
        $settings->register_settings();

        // AJAX hooks are available in both admin and frontend contexts
        $checkout_controller = new WDC_Checkout_Controller();
        $this->loader->add_action('wp_ajax_wdc_update_fulfillment', $checkout_controller, 'update_checkout_via_ajax');
        $this->loader->add_action('wp_ajax_nopriv_wdc_update_fulfillment', $checkout_controller, 'update_checkout_via_ajax');
        $this->loader->add_action('wp_ajax_wdc_update_store', $checkout_controller, 'update_store_via_ajax');
        $this->loader->add_action('wp_ajax_nopriv_wdc_update_store', $checkout_controller, 'update_store_via_ajax');

        if (is_admin()) {
            $admin = new WDC_Admin();
            $admin->register_plugin_action_links();
            $this->loader->add_action('admin_menu', $admin, 'add_settings_page');
            $this->loader->add_action('admin_enqueue_scripts', $admin, 'enqueue_admin_assets');

            $admin_order_display = new WDC_Admin_Order_Display();
            $admin_order_display->register_hooks($this->loader);
        } else {
            $this->loader->add_action('wp_enqueue_scripts', $checkout_controller, 'enqueue_checkout_assets');
            $this->loader->add_action('woocommerce_review_order_before_payment', $checkout_controller, 'render_delivery_method');
            $this->loader->add_action('woocommerce_review_order_before_payment', $checkout_controller, 'render_store_selector');
            $this->loader->add_action('woocommerce_review_order_before_payment', $checkout_controller, 'render_notices', 11);
            $this->loader->add_action('woocommerce_checkout_process', $checkout_controller, 'enforce_delivery_order_validity');
            $this->loader->add_action('woocommerce_checkout_process', $checkout_controller, 'enforce_tax_failure_handling');
            $this->loader->add_action('woocommerce_after_checkout_validation', $checkout_controller, 'suppress_billing_validation_for_pickup', 10, 2);
            $this->loader->add_action('woocommerce_thankyou', $checkout_controller, 'display_tax_fallback_thank_you_notice', 5);

            $cart_totals = new WDC_Cart_Totals();
            $this->loader->add_action('woocommerce_cart_calculate_fees', $cart_totals, 'apply_cart_fees');

            $order_meta = new WDC_Order_Meta();
            $this->loader->add_action('woocommerce_checkout_order_created', $order_meta, 'save_order_state');
        }
    }
}
