<?php

class WDC_Admin_Order_Display
{

    /**
     * @var bool Prevent duplicate WDC output in the same request
     */
    private $wdc_output_rendered = false;

    /**
     * Register admin hooks for order display
     */
    public function register_hooks($loader)
    {
        $loader->add_action('add_meta_boxes', $this, 'register_metabox');
        $loader->add_action('add_meta_boxes_shop_order', $this, 'register_metabox');
    }

    /**
     * Register the WDC metabox on order screen
     * Compatible with both HPOS and legacy post editing
     */
    public function register_metabox()
    {
        // Legacy shop_order post type
        add_meta_box(
            'wdc_order_data',
            __('WDC Calculation Details', 'woo-distance-checkout'),
            array($this, 'render_metabox'),
            'shop_order',
            'normal',
            'default'
        );

        // HPOS-specific additional screen id; may be used by some WooCommerce versions
        if (function_exists('wc_get_page_screen_id')) {
            add_meta_box(
                'wdc_order_data',
                __('WDC Calculation Details', 'woo-distance-checkout'),
                array($this, 'render_metabox'),
                wc_get_page_screen_id('shop-order'),
                'normal',
                'default'
            );
        }
    }

    /**
     * Render the WDC order metabox
     *
     * @param object|WC_Order|null $post WP_Post object (legacy) or WC_Order or null (HPOS)
     */
    public function render_metabox($post = null)
    {
        if ($this->wdc_output_rendered) {
            return;
        }

        // Get order object from post/order object/global context
        $order = $this->get_order_from_context($post);

        if (! $order) {
            echo '<p>' . esc_html__('Unable to load order data.', 'woo-distance-checkout') . '</p>';
            return;
        }

        $this->wdc_output_rendered = true;
        $this->render_wdc_block($order);
    }

    /**
     * Render the WDC block content for an order
     *
     * @param WC_Order $order
     */
    private function render_wdc_block($order)
    {
        // Check if WDC meta exists on this order
        if (! $this->has_wdc_meta($order)) {
            echo '<p>' . esc_html__('No WDC calculation data stored for this order.', 'woo-distance-checkout') . '</p>';
            return;
        }

        // Fetch all WDC meta fields
        $wdc_data = $this->fetch_wdc_data($order);

        // Render the data in a table format
        $this->render_wdc_table($wdc_data);
    }

    /**
     * Get order object from context (legacy post or HPOS)
     *
     * @param object $post WP_Post object or null
     * @return WC_Order|false
     */
    private function get_order_from_context($post = null)
    {
        global $post;

        // Accept a WC_Order object directly
        if ($post instanceof WC_Order) {
            return $post;
        }

        // If post is provided and is a WP_Post object
        if ($post && isset($post->ID)) {
            return wc_get_order($post->ID);
        }

        // If global post is available
        if (isset($post) && is_object($post) && property_exists($post, 'ID')) {
            return wc_get_order($post->ID);
        }

        // Try to get from request parameters (HPOS)
        if (isset($_GET['id'])) { // phpcs:ignore WordPress.Security.NonceVerification
            $order_id = absint($_GET['id']); // phpcs:ignore WordPress.Security.NonceVerification
            return wc_get_order($order_id);
        }

        return false;
    }

    /**
     * Check if order has WDC meta fields
     *
     * @param WC_Order $order
     * @return bool
     */
    private function has_wdc_meta($order)
    {
        return (bool) $order->get_meta('_wdc_fulfillment_method');
    }

    /**
     * Fetch all WDC meta from order
     *
     * @param WC_Order $order
     * @return array
     */
    private function fetch_wdc_data($order)
    {
        return array(
            'fulfillment_method'   => $order->get_meta('_wdc_fulfillment_method'),
            'store_id'             => $order->get_meta('_wdc_store_id'),
            'store_name'           => $order->get_meta('_wdc_store_name'),
            'store_address'        => $order->get_meta('_wdc_store_address'),
            'customer_address'     => $order->get_meta('_wdc_customer_address'),
            'distance_miles'       => $order->get_meta('_wdc_distance_miles'),
            'shipping_amount'      => $order->get_meta('_wdc_shipping_amount'),
            'sales_tax_amount'     => $order->get_meta('_wdc_sales_tax_amount'),
            'shipping_tax_amount'  => $order->get_meta('_wdc_shipping_tax_amount'),
            'tax_rate'             => $order->get_meta('_wdc_tax_rate'),
            'tax_source_type'      => $order->get_meta('_wdc_tax_source_type'),
            'calculation_success'  => $order->get_meta('_wdc_calculation_success'),
            'calculation_message'  => $order->get_meta('_wdc_calculation_message'),
            'calculation_mode'     => $order->get_meta('_wdc_calculation_mode'),
            'mock_scenario'        => $order->get_meta('_wdc_mock_scenario'),
            'tax_fallback_used'    => $order->get_meta('_wdc_tax_fallback_used'),
        );
    }

    /**
     * Format and display WDC data in table format
     *
     * @param array $wdc_data
     */
    private function render_wdc_table($wdc_data)
    {
        $fulfillment_method = $this->get_safe_value($wdc_data['fulfillment_method']);
        $is_delivery         = 'delivery' === $fulfillment_method;
        $is_pickup           = 'pickup' === $fulfillment_method;

        echo '<table class="wdc-order-data-table" style="width: 100%; border-collapse: collapse;">';

        // Core fulfillment info
        echo '<tr>';
        echo '<th style="text-align: left; padding: 8px; border-bottom: 1px solid #ddd; font-weight: bold; width: 30%;">' . esc_html__('Fulfillment Method', 'woo-distance-checkout') . '</th>';
        echo '<td style="padding: 8px; border-bottom: 1px solid #ddd;">' . esc_html(ucfirst($fulfillment_method)) . '</td>';
        echo '</tr>';

        // Store information (relevant for both delivery and pickup)
        echo '<tr>';
        echo '<th style="text-align: left; padding: 8px; border-bottom: 1px solid #ddd; font-weight: bold;">' . esc_html__('Store ID', 'woo-distance-checkout') . '</th>';
        echo '<td style="padding: 8px; border-bottom: 1px solid #ddd;">' . esc_html($this->get_safe_value($wdc_data['store_id'])) . '</td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th style="text-align: left; padding: 8px; border-bottom: 1px solid #ddd; font-weight: bold;">' . esc_html__('Store Name', 'woo-distance-checkout') . '</th>';
        echo '<td style="padding: 8px; border-bottom: 1px solid #ddd;">' . esc_html($this->get_safe_value($wdc_data['store_name'])) . '</td>';
        echo '</tr>';

        // Store & Customer Address (delivery only)
        if ($is_delivery) {
            echo '<tr>';
            echo '<th style="text-align: left; padding: 8px; border-bottom: 1px solid #ddd; font-weight: bold;" valign="top">' . esc_html__('Store Address', 'woo-distance-checkout') . '</th>';
            echo '<td style="padding: 8px; border-bottom: 1px solid #ddd;">' . $this->format_address($wdc_data['store_address']) . '</td>';
            echo '</tr>';

            echo '<tr>';
            echo '<th style="text-align: left; padding: 8px; border-bottom: 1px solid #ddd; font-weight: bold;" valign="top">' . esc_html__('Customer Address', 'woo-distance-checkout') . '</th>';
            echo '<td style="padding: 8px; border-bottom: 1px solid #ddd;">' . $this->format_address($wdc_data['customer_address']) . '</td>';
            echo '</tr>';

            // Distance and shipping (delivery only)
            echo '<tr>';
            echo '<th style="text-align: left; padding: 8px; border-bottom: 1px solid #ddd; font-weight: bold;">' . esc_html__('Distance', 'woo-distance-checkout') . '</th>';
            echo '<td style="padding: 8px; border-bottom: 1px solid #ddd;">' . esc_html($this->get_distance_display($wdc_data['distance_miles'])) . '</td>';
            echo '</tr>';

            echo '<tr>';
            echo '<th style="text-align: left; padding: 8px; border-bottom: 1px solid #ddd; font-weight: bold;">' . esc_html__('Shipping Amount', 'woo-distance-checkout') . '</th>';
            echo '<td style="padding: 8px; border-bottom: 1px solid #ddd;">' . $this->format_money($wdc_data['shipping_amount']) . '</td>';
            echo '</tr>';

            // Tax info
            echo '<tr>';
            echo '<th style="text-align: left; padding: 8px; border-bottom: 1px solid #ddd; font-weight: bold;">' . esc_html__('Tax Rate', 'woo-distance-checkout') . '</th>';
            echo '<td style="padding: 8px; border-bottom: 1px solid #ddd;">' . esc_html($this->get_tax_rate_display($wdc_data['tax_rate'])) . '</td>';
            echo '</tr>';

            echo '<tr>';
            echo '<th style="text-align: left; padding: 8px; border-bottom: 1px solid #ddd; font-weight: bold;">' . esc_html__('Sales Tax Amount', 'woo-distance-checkout') . '</th>';
            echo '<td style="padding: 8px; border-bottom: 1px solid #ddd;">' . $this->format_money($wdc_data['sales_tax_amount']) . '</td>';
            echo '</tr>';

            echo '<tr>';
            echo '<th style="text-align: left; padding: 8px; border-bottom: 1px solid #ddd; font-weight: bold;">' . esc_html__('Shipping Tax Amount', 'woo-distance-checkout') . '</th>';
            echo '<td style="padding: 8px; border-bottom: 1px solid #ddd;">' . $this->format_money($wdc_data['shipping_tax_amount']) . '</td>';
            echo '</tr>';

            echo '<tr>';
            echo '<th style="text-align: left; padding: 8px; border-bottom: 1px solid #ddd; font-weight: bold;">' . esc_html__('Tax Source', 'woo-distance-checkout') . '</th>';
            echo '<td style="padding: 8px; border-bottom: 1px solid #ddd;">' . esc_html(ucfirst(str_replace('_', ' ', $wdc_data['tax_source_type']))) . '</td>';
            echo '</tr>';
        } elseif ($is_pickup) {
            // Pickup note: shipping not required
            echo '<tr>';
            echo '<th style="text-align: left; padding: 8px; border-bottom: 1px solid #ddd; font-weight: bold;">' . esc_html__('Shipping Required', 'woo-distance-checkout') . '</th>';
            echo '<td style="padding: 8px; border-bottom: 1px solid #ddd;">' . esc_html__('No', 'woo-distance-checkout') . '</td>';
            echo '</tr>';
        }

        // Calculation status (both delivery and pickup)
        echo '<tr>';
        echo '<th style="text-align: left; padding: 8px; border-bottom: 1px solid #ddd; font-weight: bold;">' . esc_html__('Calculation Success', 'woo-distance-checkout') . '</th>';
        echo '<td style="padding: 8px; border-bottom: 1px solid #ddd;">';
        echo $wdc_data['calculation_success'] ? esc_html__('Yes', 'woo-distance-checkout') : esc_html__('No', 'woo-distance-checkout');
        echo '</td>';
        echo '</tr>';

        if ($wdc_data['calculation_message']) {
            echo '<tr>';
            echo '<th style="text-align: left; padding: 8px; border-bottom: 1px solid #ddd; font-weight: bold;" valign="top">' . esc_html__('Calculation Message', 'woo-distance-checkout') . '</th>';
            echo '<td style="padding: 8px; border-bottom: 1px solid #ddd;">' . esc_html($wdc_data['calculation_message']) . '</td>';
            echo '</tr>';
        }

        // Tax fallback status
        if ('yes' === $wdc_data['tax_fallback_used']) {
            echo '<tr style="background-color: #fff3cd;">';
            echo '<th style="text-align: left; padding: 8px; border-bottom: 1px solid #ddd; font-weight: bold; color: #856404;">⚠️ ' . esc_html__('Tax Fallback Used', 'woo-distance-checkout') . '</th>';
            echo '<td style="padding: 8px; border-bottom: 1px solid #ddd; color: #856404;">' . esc_html__('Tax could not be automatically calculated. Manual review may be needed.', 'woo-distance-checkout') . '</td>';
            echo '</tr>';
        }

        // Calculation mode & scenario (debug info)
        echo '<tr>';
        echo '<th style="text-align: left; padding: 8px; border-bottom: 1px solid #ddd; font-weight: bold;">' . esc_html__('Calculation Mode', 'woo-distance-checkout') . '</th>';
        echo '<td style="padding: 8px; border-bottom: 1px solid #ddd;">' . esc_html(ucfirst($wdc_data['calculation_mode'])) . '</td>';
        echo '</tr>';

        if ($wdc_data['mock_scenario']) {
            echo '<tr>';
            echo '<th style="text-align: left; padding: 8px; border-bottom: 1px solid #ddd; font-weight: bold;">' . esc_html__('Mock Scenario', 'woo-distance-checkout') . '</th>';
            echo '<td style="padding: 8px; border-bottom: 1px solid #ddd;">' . esc_html(ucfirst(str_replace('_', ' ', $wdc_data['mock_scenario']))) . '</td>';
            echo '</tr>';
        }

        echo '</table>';
    }

    /**
     * Get safe display value
     *
     * @param mixed $value
     * @return string
     */
    private function get_safe_value($value)
    {
        if (empty($value) || is_null($value)) {
            return '—';
        }
        return (string) $value;
    }

    /**
     * Format address from JSON or array
     *
     * @param mixed $address JSON string or array
     * @return string
     */
    private function format_address($address)
    {
        if (empty($address)) {
            return '<em>—</em>';
        }

        // If it's a JSON string, decode it
        if (is_string($address)) {
            $address = json_decode($address, true);
        }

        if (! is_array($address)) {
            return '<em>—</em>';
        }

        // Build address string from components
        $parts = array();
        if (! empty($address['street'])) {
            $parts[] = $address['street'];
        }
        if (! empty($address['city'])) {
            $parts[] = $address['city'];
        }
        if (! empty($address['state'])) {
            $parts[] = $address['state'];
        }
        if (! empty($address['zip'])) {
            $parts[] = $address['zip'];
        }
        if (! empty($address['country'])) {
            $parts[] = $address['country'];
        }

        if (empty($parts)) {
            return '<em>—</em>';
        }

        return esc_html(implode(', ', $parts));
    }

    /**
     * Format distance display
     *
     * @param mixed $distance
     * @return string
     */
    private function get_distance_display($distance)
    {
        if (empty($distance) || is_null($distance)) {
            return '—';
        }
        return (float) $distance . ' ' . esc_html__('miles', 'woo-distance-checkout');
    }

    /**
     * Format tax rate display
     *
     * @param mixed $tax_rate
     * @return string
     */
    private function get_tax_rate_display($tax_rate)
    {
        if (empty($tax_rate) || is_null($tax_rate)) {
            return '—';
        }
        return (float) $tax_rate . '%';
    }

    /**
     * Format money values as plain readable text.
     *
     * @param mixed $amount
     * @return string
     */
    private function format_money($amount)
    {
        if ($amount === '' || $amount === null || $amount === false || ! is_numeric($amount)) {
            return '—';
        }

        $formatted = wc_price((float) $amount);

        // Strip HTML tags to avoid escaped markup output in admin table and keep clean text value
        return esc_html(trim(wp_strip_all_tags($formatted)));
    }
}
