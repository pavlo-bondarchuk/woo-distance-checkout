<?php

class WDC_Checkout_Controller
{

    const DELIVERY_METHOD_KEY = 'wdc_delivery_method';
    const SELECTED_STORE_KEY = 'wdc_selected_store';

    protected $settings;
    protected $logger;
    protected $store_locations;
    protected $address_resolver;
    private $last_calculation_state_hash = '';

    /** Per-request calculation state cache. Reset on each new PHP process. */
    private static $state_cache = null;

    public function __construct()
    {
        $this->settings = new WDC_Settings();
        $this->logger   = new WDC_Logger();
        $this->store_locations = new WDC_Store_Locations();
        $this->address_resolver = new WDC_Address_Resolver();
    }

    public function enqueue_checkout_assets()
    {
        if (! is_checkout()) {
            return;
        }

        $google_maps_api_key = trim((string) $this->settings->get_setting('google_maps_api_key', ''));

        $frontend_key_length = strlen($google_maps_api_key);
        $frontend_key_last4 = $frontend_key_length >= 4 ? substr($google_maps_api_key, -4) : $google_maps_api_key;

        if ($this->settings->is_debug_enabled()) {
            $this->logger->debug('Checkout frontend script key source=google_maps_api_key, key_length=' . $frontend_key_length . ', key_last4=' . $frontend_key_last4);
        }

        if ('' !== $google_maps_api_key) {
            wp_enqueue_script(
                'google-maps-places',
                add_query_arg(
                    array(
                        'key'       => $google_maps_api_key,
                        'libraries' => 'places',
                        'loading'   => 'async',
                    ),
                    'https://maps.googleapis.com/maps/api/js'
                ),
                array(),
                null,
                true
            );
        }

        wp_enqueue_script(
            'wdc-checkout',
            WDC_PLUGIN_URL . 'assets/js/checkout.js',
            array('jquery', 'google-maps-places'),
            WDC_PLUGIN_VERSION,
            true
        );

        wp_enqueue_style(
            'wdc-checkout',
            WDC_PLUGIN_URL . 'assets/css/checkout.css',
            array(),
            WDC_PLUGIN_VERSION
        );

        wp_localize_script(
            'wdc-checkout',
            'wdcCheckout',
            array(
                'ajaxurl'                 => admin_url('admin-ajax.php'),
                'nonce'                   => wp_create_nonce('wdc_checkout_nonce'),
                'isCheckoutBlockedForTax' => $this->is_checkout_blocked_for_tax(),
                'outOfZoneState'          => $this->get_out_of_zone_state(),
                'maxDeliveryDistance'     => intval($this->settings->get_setting('maximum_delivery_distance', 25)),
                'googleMapsApiKey'        => $google_maps_api_key,
                'debugMode'               => (string) $this->settings->get_setting('debug_mode', 'no'),
                'pickupStoreAddress'      => $this->get_selected_store_address(),
                'notices'                 => $this->get_localized_notice_strings(),
            )
        );
    }

    /**
     * Suppress native WooCommerce billing field validation for pickup mode.
     *
     * For pickup orders, the billing address is not used for shipping or tax calculation.
     * Tax uses the store address. Therefore, fake or incomplete billing addresses should not
     * block pickup orders.
     *
     * For delivery orders, keep all native WooCommerce validation intact.
     *
     * Hook: woocommerce_after_checkout_validation
     *
     * @param array $posted_data Posted form data.
     * @param WP_Error $errors WooCommerce validation errors.
     */
    public function suppress_billing_validation_for_pickup($posted_data, $errors)
    {
        $state = $this->get_calculation_state();
        $fulfillment_method = isset($state['fulfillment_method']) ? $state['fulfillment_method'] : 'delivery';

        // Only suppress validation for pickup mode
        if ('pickup' !== $fulfillment_method) {
            return;
        }

        // For pickup, remove billing address-related validation errors
        // These are not relevant since pickup uses store address for tax
        $billing_error_codes = [
            'billing_postcode_validation',
            'billing_address_1',
            'billing_address_1_postcode',
            'billing_city',
            'billing_state',
            'billing_postcode',
        ];

        foreach ($billing_error_codes as $error_code) {
            if ($errors->has_errors()) {
                $error_messages = $errors->get_error_messages($error_code);
                if (!empty($error_messages)) {
                    $errors->remove($error_code);
                    $this->logger->debug('WDC: suppressed billing validation error "' . $error_code . '" for pickup mode');
                }
            }
        }
    }

    /**
     * Enforce delivery order validity.
     *
     * If fulfillment method is delivery and calculation failed, block the order.
     *
     * Hook: woocommerce_checkout_process
     */
    public function enforce_delivery_order_validity()
    {
        $state = $this->get_calculation_state();
        $fulfillment_method = isset($state['fulfillment_method']) ? $state['fulfillment_method'] : 'delivery';

        if ('delivery' !== $fulfillment_method) {
            return;
        }

        $success = isset($state['success']) ? $state['success'] : false;

        if ($success) {
            return;
        }

        if (isset($state['shipping']['success']) && false === $state['shipping']['success']) {
            $this->logger->error('WDC checkout blocked out-of-zone delivery attempt; top Woo notice suppressed in favor of inline WDC notice');
            return;
        }

        $message = $state['message'] ?? __('Delivery is not available for your address. Please choose pickup or update your address.', 'woo-distance-checkout');

        wc_add_notice($message, 'error');
        $this->logger->error('WDC checkout blocked invalid delivery attempt: ' . $message);
    }

    /**
     * Detect if checkout is currently blocked due to tax API failure in block_checkout mode.
     *
     * Used by frontend to disable Place Order button and show user-friendly error message.
     *
     * @return bool True if checkout should be blocked; false otherwise.
     */
    public function is_checkout_blocked_for_tax()
    {
        $state = $this->get_calculation_state();

        // Check if tax calculation failed
        if (! isset($state['tax']['success']) || true === $state['tax']['success']) {
            return false; // Tax succeeded or no tax state; nothing to do
        }

        // Tax API failed — check fallback mode
        $fallback_mode = (string) $this->settings->get_setting('fallback_mode', 'block_checkout');

        return 'block_checkout' === $fallback_mode;
    }

    public function get_localized_notice_strings()
    {
        return array(
            'blockedHelperText' => __('Checkout is temporarily unavailable because taxes could not be calculated.', 'woo-distance-checkout'),
            'blockedNoticePickup' => __('We are unable to calculate taxes for pickup at this time. Please try again later.', 'woo-distance-checkout'),
            'blockedNoticeDelivery' => __('We are unable to calculate taxes for delivery at this time. Please try again later or choose Self Pickup.', 'woo-distance-checkout'),
            'totalPlaceholder' => __('Total unavailable until taxes are calculated.', 'woo-distance-checkout'),
        );
    }

    /**
     * Detect if address is outside delivery zone (delivery mode only).
     *
     * Returns array with state info for frontend notification.
     *
     * @return array|null Array with 'is_out_of_zone' and 'max_distance', or null if valid or not in delivery mode.
     */
    public function get_out_of_zone_state()
    {
        $state = $this->get_calculation_state();

        // Only check for delivery mode
        if ('delivery' !== $state['fulfillment_method']) {
            return null;
        }

        // Check if calculation succeeded
        if ($state['success']) {
            return null; // Address is within zone
        }

        // Check if failure is specifically due to shipping (out of zone)
        if (isset($state['shipping']['success']) && false === $state['shipping']['success']) {
            $max_distance = intval($this->settings->get_setting('maximum_delivery_distance'));
            return array(
                'is_out_of_zone' => true,
                'max_distance'   => $max_distance,
                'message'        => $this->get_out_of_delivery_area_message(),
            );
        }

        return null;
    }

    /**
     * Get the out-of-delivery-area message to show customers.
     *
     * Returns the admin-configured message, or the built-in default if not set.
     *
     * @return string
     */
    private function get_out_of_delivery_area_message()
    {
        $custom = trim((string) $this->settings->get_setting('out_of_delivery_area_message', ''));
        if ('' !== $custom) {
            return $custom;
        }
        return __('Your address is out of delivery area please call us at (916) 915-9224.', 'woo-distance-checkout');
    }

    /**
     * Enforce tax API failure handling.
     *
     * If tax API fails:
     * - In block_checkout mode: Add error notice to prevent order creation
     * - In manual_review mode: Allow checkout to proceed (warning shown on thank-you page)
     *
     * Hook: woocommerce_checkout_process
     */
    public function enforce_tax_failure_handling()
    {
        $state = $this->get_calculation_state();

        // Check if tax calculation failed
        if (! isset($state['tax']['success']) || true === $state['tax']['success']) {
            return; // Tax succeeded or no tax state; nothing to do
        }

        // Tax API failed
        $fallback_mode = (string) $this->settings->get_setting('fallback_mode', 'block_checkout');
        $message       = isset($state['tax']['message']) ? $state['tax']['message'] : __('Tax calculation failed.', 'woo-distance-checkout');

        if ('block_checkout' === $fallback_mode) {
            // Block the checkout
            $error_message = __('We are unable to calculate taxes for your order at this time. Please try again or contact support.', 'woo-distance-checkout');
            wc_add_notice($error_message, 'error');
            $this->logger->error('WDC checkout blocked due to tax API failure in block_checkout mode: ' . $message);
        } elseif ('manual_review' === $fallback_mode) {
            // Allow checkout; warning will be displayed on thank-you page
            $this->logger->info('WDC order allowed with manual_review fallback for tax API failure: ' . $message);
        }
    }

    public function render_delivery_method()
    {
        if (! is_checkout()) {
            return;
        }

        $current_method = $this->get_selected_fulfillment_method();
        include WDC_PLUGIN_DIR . 'templates/checkout-delivery-method.php';
    }

    public function render_store_selector()
    {
        if (! is_checkout()) {
            return;
        }

        $is_pickup        = $this->get_selected_fulfillment_method() === 'pickup';
        $available_stores = $this->store_locations->get_available_stores();
        $selected_store   = $this->get_selected_store();

        include WDC_PLUGIN_DIR . 'templates/checkout-store-selector.php';
    }

    public function render_notices()
    {
        if (! is_checkout()) {
            return;
        }

        $template_file = WDC_PLUGIN_DIR . 'templates/checkout-notices.php';

        if (file_exists($template_file)) {
            $notice = $this->get_checkout_notice();
            include $template_file;
        }
    }

    /**
     * Build a notice array for the current calculation state, or null if nothing to show.
     *
     * @return array|null Array with 'type' and 'message', or null.
     */
    private function get_checkout_notice()
    {
        $state = $this->get_calculation_state();

        // Only show notices for delivery mode
        if ('delivery' !== $state['fulfillment_method']) {
            return null;
        }

        // Address incomplete — nothing to show yet (fields still being filled)
        if (false === $state['address_complete']) {
            return null;
        }

        if (! $state['success']) {
            // Address validation failed
            if (isset($state['address_validated']) && false === $state['address_validated']) {
                return array(
                    'type'    => 'error',
                    'message' => __('We could not validate this delivery address precisely enough. Please check the street, city, and ZIP code, or choose Self Pickup.', 'woo-distance-checkout'),
                );
            }

            // Shipping failed → address is outside the delivery zone
            if (isset($state['shipping']['success']) && false === $state['shipping']['success']) {
                return array(
                    'type'    => 'error',
                    'message' => $this->get_out_of_delivery_area_message(),
                );
            }

            // Distance API failed
            if (isset($state['distance']['success']) && false === $state['distance']['success']) {
                return array(
                    'type'    => 'error',
                    'message' => __('Unable to calculate delivery distance. Please check your address or contact us.', 'woo-distance-checkout'),
                );
            }
        }

        return null;
    }

    public function get_selected_fulfillment_method()
    {
        if (! isset(WC()->session)) {
            return 'delivery';
        }

        $method = WC()->session->get('wdc_fulfillment_method');

        if (! in_array($method, array('delivery', 'pickup'), true)) {
            return 'delivery';
        }

        return $method;
    }

    public function set_selected_fulfillment_method($method)
    {
        if (! isset(WC()->session)) {
            return false;
        }

        if (! in_array($method, array('delivery', 'pickup'), true)) {
            return false;
        }

        WC()->session->set('wdc_fulfillment_method', $method);
        $this->logger->debug('Fulfillment method set to: ' . $method);

        return true;
    }

    public function get_delivery_method()
    {
        return $this->get_selected_fulfillment_method();
    }

    public function get_selected_store()
    {
        return $this->store_locations->get_selected_store();
    }

    private function get_selected_store_details()
    {
        $store_id = $this->get_selected_store();
        $store = $this->store_locations->get_store_by_id($store_id);

        if (! is_array($store)) {
            return null;
        }

        return $store;
    }

    private function get_selected_store_address()
    {
        $store = $this->get_selected_store_details();

        if (! is_array($store) || ! isset($store['address'])) {
            return '';
        }

        return (string) $store['address'];
    }

    public function update_checkout_via_ajax()
    {
        $this->logger->debug('WDC: update_checkout_via_ajax handler reached');

        $posted_data = $_POST;
        $this->logger->debug('WDC: Posted data: ' . json_encode($posted_data));

        check_ajax_referer('wdc_checkout_nonce', 'nonce');
        $this->logger->debug('WDC: Nonce validation passed');

        $fulfillment_method = isset($_POST['fulfillment_method']) ? sanitize_text_field($_POST['fulfillment_method']) : 'delivery';
        $this->logger->debug('WDC: Sanitized fulfillment_method: ' . $fulfillment_method);

        $this->set_selected_fulfillment_method($fulfillment_method);
        self::invalidate_state_cache();

        wp_send_json_success(
            array(
                'message'              => 'Fulfillment method updated',
                'fulfillment_method'   => $fulfillment_method,
                'isCheckoutBlockedForTax' => $this->is_checkout_blocked_for_tax(),
                'outOfZoneState'       => $this->get_out_of_zone_state(),
                'pickupStoreAddress'   => $this->get_selected_store_address(),
            )
        );
    }

    public function update_store_via_ajax()
    {
        $this->logger->debug('WDC: update_store_via_ajax handler reached');

        $posted_data = $_POST;
        $this->logger->debug('WDC: Posted data: ' . json_encode($posted_data));

        check_ajax_referer('wdc_checkout_nonce', 'nonce');
        $this->logger->debug('WDC: Nonce validation passed');

        // Only allow store selection for pickup method
        $fulfillment_method = $this->get_selected_fulfillment_method();
        $this->logger->debug('WDC: Current fulfillment method: ' . $fulfillment_method);

        if ($fulfillment_method !== 'pickup') {
            $this->logger->debug('WDC: Store selection rejected - not in pickup mode');
            wp_send_json_error(
                array(
                    'message' => 'Store selection only available for pickup method',
                ),
                400
            );
        }

        $store_id = isset($_POST['store_id']) ? intval($_POST['store_id']) : 0;
        $this->logger->debug('WDC: Store ID after intval: ' . $store_id);

        // Validate store exists
        if (! $this->store_locations->store_exists($store_id)) {
            $this->logger->debug('WDC: Store validation failed for ID: ' . $store_id);
            wp_send_json_error(
                array(
                    'message' => 'Invalid store selected',
                ),
                400
            );
        }

        $this->logger->debug('WDC: Store validation passed, updating session');
        // Update the store in session
        $this->store_locations->set_selected_store($store_id);
        self::invalidate_state_cache();

        wp_send_json_success(
            array(
                'message'    => 'Store updated',
                'store_id'   => $store_id,
                'isCheckoutBlockedForTax' => $this->is_checkout_blocked_for_tax(),
                'outOfZoneState'  => $this->get_out_of_zone_state(),
                'pickupStoreAddress' => $this->get_selected_store_address(),
            )
        );
    }

    /**
     * Display thank you warning block if order was created with tax API fallback.
     *
     * Hook: woocommerce_thankyou
     * Renders a dedicated HTML warning block on thank you page when tax calculation failed in manual_review mode.
     * Does NOT use WooCommerce notice queue; renders direct HTML.
     *
     * @param int $order_id The order ID from thank-you page.
     */
    public function display_tax_fallback_thank_you_notice($order_id)
    {
        if (! $order_id || ! is_int($order_id)) {
            $this->logger->debug('WDC thank-you render: skipped - invalid order_id');
            return;
        }

        $order = wc_get_order($order_id);
        if (! $order) {
            $this->logger->debug('WDC thank-you render: skipped - order not found for id ' . $order_id);
            return;
        }

        $tax_fallback_used = $order->get_meta('_wdc_tax_fallback_used');
        $this->logger->debug('WDC thank-you render: order_id=' . $order_id . ', tax_fallback_used=' . var_export($tax_fallback_used, true));

        if ('yes' !== $tax_fallback_used) {
            $this->logger->debug('WDC thank-you render: meta check failed - value is "' . $tax_fallback_used . '" not "yes"');
            return;
        }

        // Render clean customer-facing warning block
?>
        <div class="wdc-tax-fallback-warning" style="margin: 30px 0; padding: 20px; background: #fff8e5; border-left: 4px solid #ffb81c; border-radius: 4px; box-shadow: 0 2px 4px rgba(0,0,0,0.08);">
            <h3 style="margin: 0 0 12px 0; font-size: 16px; font-weight: 600; color: #333;">
                <?php esc_html_e('Tax Calculation Notice', 'woo-distance-checkout'); ?>
            </h3>
            <p style="margin: 0; font-size: 14px; line-height: 1.6; color: #555;">
                <?php esc_html_e('We could not calculate taxes automatically. Your order was submitted and may require manual review.', 'woo-distance-checkout'); ?>
            </p>
        </div>
<?php

        $this->logger->info('WDC: Rendered tax fallback warning block for order ' . $order_id);
    }

    /**
     * Stage 3: Get the internal calculation state for the current checkout.
     *
     * This method is the primary entry point for building the internal calculation state.
     * It uses the calculation coordinator to orchestrate all services.
     *
     * If debug mode is enabled, the calculation state is logged to the plugin logger
     * for developer inspection.
     *
     * @return array The structured calculation state (success and failure states are both valid).
     */
    public function get_calculation_state()
    {
        if (self::$state_cache !== null) {
            $this->logger->debug('Calculation State: returning per-request cache');
            return self::$state_cache;
        }

        $coordinator = new WDC_Calculation_Coordinator();
        $state = $coordinator->calculate_current_checkout();

        if ($this->settings->is_debug_enabled()) {
            $this->logger->debug('Calculation State: ' . wp_json_encode($state));
        }

        self::$state_cache = $state;
        return $state;
    }

    /**
     * Invalidate the per-request calculation state cache.
     * Call when fulfillment method or store changes within the same request.
     */
    public static function invalidate_state_cache()
    {
        self::$state_cache = null;
    }
}
