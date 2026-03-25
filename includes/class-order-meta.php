<?php

class WDC_Order_Meta
{

    const META_PREFIX = '_wdc_';

    private $logger;

    public function __construct()
    {
        $this->logger = new WDC_Logger();
    }

    public function save_delivery_method($order_id, $method)
    {
        $key = self::META_PREFIX . 'delivery_method';
        update_post_meta($order_id, $key, sanitize_text_field($method));
        $this->logger->info('Saved delivery method for order ' . $order_id . ': ' . $method);
    }

    public function save_selected_store($order_id, $store_id)
    {
        $key = self::META_PREFIX . 'selected_store';
        update_post_meta($order_id, $key, intval($store_id));
        $this->logger->info('Saved selected store for order ' . $order_id . ': ' . $store_id);
    }

    public function save_distance($order_id, $distance)
    {
        $key = self::META_PREFIX . 'distance';
        update_post_meta($order_id, $key, floatval($distance));
        $this->logger->info('Saved distance for order ' . $order_id . ': ' . $distance);
    }

    public function save_shipping_cost($order_id, $cost)
    {
        $key = self::META_PREFIX . 'shipping_cost';
        update_post_meta($order_id, $key, floatval($cost));
        $this->logger->info('Saved shipping cost for order ' . $order_id . ': ' . $cost);
    }

    /**
     * Hook: woocommerce_checkout_order_created
     *
     * Saves WDC fulfillment and calculation state to order meta when an order is placed.
     *
     * @param WC_Order $order The newly created order.
     */
    public function save_order_state(WC_Order $order)
    {
        $order_id = $order->get_id();

        $this->logger->debug('WDC order meta persistence started for order ' . $order_id);

        $settings          = new WDC_Settings();
        $calculation_mode  = $settings->get_setting('calculation_mode', 'mock');
        $mock_scenario     = $settings->get_setting('mock_scenario', 'happy_path');

        $coordinator = new WDC_Calculation_Coordinator();
        $state       = $coordinator->calculate_current_checkout();

        $fulfillment_method = isset($state['fulfillment_method']) ? sanitize_text_field($state['fulfillment_method']) : 'delivery';
        $store             = isset($state['store']) && is_array($state['store']) ? $state['store'] : array();
        $store_id          = isset($store['id']) ? intval($store['id']) : 0;
        $store_name        = isset($store['name']) ? sanitize_text_field($store['name']) : '';
        $store_address     = isset($store['address']) ? sanitize_textarea_field($store['address']) : '';

        $customer_address  = isset($state['customer_address']) && is_array($state['customer_address']) ? $state['customer_address'] : array();
        $customer_address_json = wp_json_encode($customer_address);

        $distance_miles = null;
        if (isset($state['distance']['distance_miles'])) {
            $distance_miles = floatval($state['distance']['distance_miles']);
        }

        $shipping_amount = 0.0;
        if (isset($state['shipping']['rounded_shipping']) && is_numeric($state['shipping']['rounded_shipping'])) {
            $shipping_amount = floatval($state['shipping']['rounded_shipping']);
        }

        $tax_rate        = null;
        $tax_source_type = null;
        if (isset($state['tax']['success']) && $state['tax']['success'] && isset($state['tax']['tax_rate'])) {
            $tax_rate        = floatval($state['tax']['tax_rate']);
            $tax_source_type = sanitize_text_field($state['tax']['source_type'] ?? '');
        } elseif (isset($state['tax']['source_type'])) {
            $tax_source_type = sanitize_text_field($state['tax']['source_type']);
        }

        $calculation_success = isset($state['success']) ? (bool) $state['success'] : false;
        $calculation_message = isset($state['message']) ? sanitize_text_field($state['message']) : '';
        $shipping_required   = isset($state['shipping_required']) ? (bool) $state['shipping_required'] : false;
        $address_complete    = isset($state['address_complete']) ? (bool) $state['address_complete'] : false;

        // Totals are ideally sourced from current cart fees to reflect final checkout values.
        $sales_tax_amount    = 0.0;
        $shipping_tax_amount = 0.0;

        if (isset(WC()->cart) && method_exists(WC()->cart, 'get_fees')) {
            foreach (WC()->cart->get_fees() as $fee) {
                if ($fee->name === __('Sales Tax', 'woo-distance-checkout')) {
                    $sales_tax_amount = floatval($fee->amount);
                }
                if ($fee->name === __('Shipping Tax', 'woo-distance-checkout')) {
                    $shipping_tax_amount = floatval($fee->amount);
                }
            }
        }

        // Fallback to calculated values from state if fees are not present (or for non-standard hooks).
        if ($sales_tax_amount <= 0 && $calculation_success && $tax_rate !== null && $tax_rate > 0) {
            $subtotal = floatval($order->get_subtotal());
            $sales_tax_amount = round($subtotal * ($tax_rate / 100), wc_get_price_decimals());
        }

        if ($shipping_tax_amount <= 0 && $calculation_success && $tax_rate !== null && $tax_rate > 0) {
            $taxable_shipping = ('yes' === $settings->get_setting('taxable_shipping'));
            if ($taxable_shipping && $shipping_amount > 0) {
                $shipping_tax_amount = round($shipping_amount * ($tax_rate / 100), wc_get_price_decimals());
            }
        }

        $order->update_meta_data('_wdc_fulfillment_method', $fulfillment_method);
        $order->update_meta_data('_wdc_store_id', $store_id);
        $order->update_meta_data('_wdc_store_name', $store_name);
        $order->update_meta_data('_wdc_store_address', $store_address);

        $order->update_meta_data('_wdc_customer_address', $customer_address_json);

        $order->update_meta_data('_wdc_distance_miles', is_null($distance_miles) ? 0.0 : $distance_miles);
        $order->update_meta_data('_wdc_shipping_amount', floatval($shipping_amount));
        $order->update_meta_data('_wdc_sales_tax_amount', floatval($sales_tax_amount));
        $order->update_meta_data('_wdc_shipping_tax_amount', floatval($shipping_tax_amount));
        $order->update_meta_data('_wdc_tax_rate', is_null($tax_rate) ? 0.0 : floatval($tax_rate));
        $order->update_meta_data('_wdc_tax_source_type', $tax_source_type);

        $order->update_meta_data('_wdc_calculation_success', $calculation_success ? 'yes' : 'no');
        $order->update_meta_data('_wdc_calculation_message', $calculation_message);
        $order->update_meta_data('_wdc_mock_scenario', $calculation_mode === 'mock' ? sanitize_text_field($mock_scenario) : '');
        $order->update_meta_data('_wdc_calculation_mode', sanitize_text_field($calculation_mode));

        $order->update_meta_data('_wdc_shipping_required', $shipping_required ? 'yes' : 'no');
        $order->update_meta_data('_wdc_address_complete', $address_complete ? 'yes' : 'no');

        // Extract explicit fallback flags from tax response (or default to false)
        $tax_fallback_used   = isset($state['tax']['fallback_used']) && true === $state['tax']['fallback_used'] ? 'yes' : 'no';
        $tax_fallback_mode   = isset($state['tax']['fallback_mode']) ? sanitize_text_field($state['tax']['fallback_mode']) : '';
        $tax_fallback_reason = isset($state['tax']['fallback_reason']) ? sanitize_text_field($state['tax']['fallback_reason']) : '';

        $this->logger->debug('WDC tax fallback flags (before save): used=' . $tax_fallback_used . ', mode=' . $tax_fallback_mode . ', reason=' . $tax_fallback_reason);

        $order->update_meta_data('_wdc_tax_fallback_used', $tax_fallback_used);
        $order->update_meta_data('_wdc_tax_fallback_mode', $tax_fallback_mode);
        $order->update_meta_data('_wdc_tax_fallback_reason', $tax_fallback_reason);

        // Ensure delivery orders have shipping address data in the order object
        if ('delivery' === $fulfillment_method) {
            $this->ensure_delivery_shipping_address($order, $customer_address);
        }

        $order->save();

        $this->logger->debug('WDC order meta after save: _wdc_tax_fallback_used=' . $order->get_meta('_wdc_tax_fallback_used') . ', _wdc_tax_fallback_mode=' . $order->get_meta('_wdc_tax_fallback_mode') . ', _wdc_tax_fallback_reason=' . $order->get_meta('_wdc_tax_fallback_reason'));

        $debug_payload = array(
            'fulfillment_method' => $fulfillment_method,
            'store_id' => $store_id,
            'store_name' => $store_name,
            'distance_miles' => $distance_miles,
            'shipping_amount' => $shipping_amount,
            'sales_tax_amount' => $sales_tax_amount,
            'shipping_tax_amount' => $shipping_tax_amount,
            'tax_rate' => $tax_rate,
            'tax_source_type' => $tax_source_type,
            'calculation_success' => $calculation_success,
            'calculation_message' => $calculation_message,
            'mock_scenario' => $mock_scenario,
            'calculation_mode' => $calculation_mode,
        );

        $this->logger->debug('WDC order meta saved payload: ' . wp_json_encode($debug_payload));
    }

    private function ensure_delivery_shipping_address(WC_Order $order, $customer_address)
    {
        // If shipping is already present, nothing to do.
        if ($order->get_shipping_address_1() || $order->get_shipping_city() || $order->get_shipping_postcode() || $order->get_shipping_country()) {
            return;
        }

        // Prefer order billing fields, fallback to the normalized customer address from WDC calculation.
        $shipping_first_name = $order->get_shipping_first_name() ?: $order->get_billing_first_name();
        $shipping_last_name  = $order->get_shipping_last_name() ?: $order->get_billing_last_name();

        $order->set_shipping_first_name($shipping_first_name);
        $order->set_shipping_last_name($shipping_last_name);

        if (! empty($order->get_billing_address_1())) {
            $order->set_shipping_address_1($order->get_billing_address_1());
            $order->set_shipping_address_2($order->get_billing_address_2());
            $order->set_shipping_city($order->get_billing_city());
            $order->set_shipping_state($order->get_billing_state());
            $order->set_shipping_postcode($order->get_billing_postcode());
            $order->set_shipping_country($order->get_billing_country());
        } elseif (is_array($customer_address) && ! empty($customer_address['street'])) {
            $order->set_shipping_address_1($customer_address['street']);
            $order->set_shipping_address_2($customer_address['street2'] ?? '');
            $order->set_shipping_city($customer_address['city'] ?? '');
            $order->set_shipping_state($customer_address['state'] ?? '');
            $order->set_shipping_postcode($customer_address['zip'] ?? '');
            $order->set_shipping_country($customer_address['country'] ?? '');
        }

        $order->save();
        $this->logger->info('WDC assigned shipping address for delivery order ' . $order->get_id());
    }

    public function save_sales_tax_rate($order_id, $rate)
    {
        $key = self::META_PREFIX . 'sales_tax_rate';
        update_post_meta($order_id, $key, floatval($rate));
    }

    public function save_shipping_tax_rate($order_id, $rate)
    {
        $key = self::META_PREFIX . 'shipping_tax_rate';
        update_post_meta($order_id, $key, floatval($rate));
    }

    public function get_delivery_method($order_id)
    {
        $key = self::META_PREFIX . 'delivery_method';
        return get_post_meta($order_id, $key, true);
    }

    public function get_selected_store($order_id)
    {
        $key = self::META_PREFIX . 'selected_store';
        return get_post_meta($order_id, $key, true);
    }

    public function get_distance($order_id)
    {
        $key = self::META_PREFIX . 'distance';
        return get_post_meta($order_id, $key, true);
    }

    public function get_shipping_cost($order_id)
    {
        $key = self::META_PREFIX . 'shipping_cost';
        return get_post_meta($order_id, $key, true);
    }

    public function get_all_meta($order_id)
    {
        $meta = array(
            'delivery_method'   => $this->get_delivery_method($order_id),
            'selected_store'    => $this->get_selected_store($order_id),
            'distance'          => $this->get_distance($order_id),
            'shipping_cost'     => $this->get_shipping_cost($order_id),
        );

        return $meta;
    }
}
