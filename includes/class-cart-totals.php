<?php

class WDC_Cart_Totals
{

    private $settings;
    private $logger;

    public function __construct()
    {
        $this->settings = new WDC_Settings();
        $this->logger   = new WDC_Logger();
    }

    /**
     * Hook: woocommerce_cart_calculate_fees
     *
     * Runs the calculation pipeline and applies the WDC delivery fee to the cart.
     * Does nothing for pickup mode or when the calculation state is incomplete.
     *
     * @param WC_Cart $cart The WooCommerce cart object.
     */
    public function apply_cart_fees($cart)
    {
        $this->logger->debug('=== Cart Totals: apply_cart_fees START ===');

        $checkout_controller = new WDC_Checkout_Controller();
        $state               = $checkout_controller->get_calculation_state();

        $this->logger->debug('Calculation state success: ' . ($state['success'] ? 'yes' : 'no'));
        $this->logger->debug('Fulfillment method: ' . ($state['fulfillment_method'] ?? 'unknown'));
        $this->logger->debug('Shipping required: ' . ($state['shipping_required'] ? 'yes' : 'no'));

        if (! isset($state['success']) || ! $state['success']) {
            $this->logger->debug('Cart totals: no WDC totals due to state failure: ' . ($state['message'] ?? 'unknown'));
            $this->logger->debug('=== Cart Totals: apply_cart_fees END (NO FEES) ===');
            return;
        }

        $shipping_amount = 0.0;
        if ($state['shipping_required'] && isset($state['shipping']['rounded_shipping'])) {
            $shipping_amount = floatval($state['shipping']['rounded_shipping']);
        }

        // Delivery row
        if ($shipping_amount > 0) {
            $cart->add_fee(__('Shipping', 'woo-distance-checkout'), $shipping_amount, false, '');
            $this->logger->debug('✓ Shipping fee added: ' . $shipping_amount);
        } else {
            $this->logger->debug('No shipping fee (pickup or zero amount)');
        }

        // Tax calculation
        $tax_rate = 0;
        $sales_tax_amount = 0;
        $shipping_tax_amount = 0;

        if (isset($state['tax']['success']) && $state['tax']['success'] && isset($state['tax']['tax_rate'])) {
            $tax_rate = floatval($state['tax']['tax_rate']);
            $this->logger->debug('Tax rate available: ' . $tax_rate . '%');

            $subtotal = floatval($cart->get_subtotal());
            $sales_tax_amount = round($subtotal * ($tax_rate / 100), wc_get_price_decimals());

            $taxable_shipping = ('yes' === $this->settings->get_setting('taxable_shipping'));
            if ($taxable_shipping && $shipping_amount > 0) {
                $shipping_tax_amount = round($shipping_amount * ($tax_rate / 100), wc_get_price_decimals());
            }

            if ($sales_tax_amount > 0) {
                $cart->add_fee(__('Sales Tax', 'woo-distance-checkout'), $sales_tax_amount, false, '');
                $this->logger->debug('✓ Sales Tax fee added: ' . $sales_tax_amount . ' @ ' . $tax_rate . '%');
            } else {
                $this->logger->debug('Sales Tax amount is zero, no fee added');
            }

            if ($shipping_tax_amount > 0) {
                $cart->add_fee(__('Shipping Tax', 'woo-distance-checkout'), $shipping_tax_amount, false, '');
                $this->logger->debug('✓ Shipping Tax fee added: ' . $shipping_tax_amount . ' @ ' . $tax_rate . '%');
            } else {
                $this->logger->debug('Shipping Tax amount is zero, no fee added');
            }
        } else {
            $this->logger->debug('Tax calculation failed or no tax rate available');
        }

        $this->logger->debug('=== Cart Totals: apply_cart_fees END (FEES APPLIED) ===');
    }

    public function apply_sales_tax($cart, $tax_amount)
    {
        // TODO: Implement sales tax integration
        $this->logger->info('Applying sales tax: ' . $tax_amount);

        return true;
    }

    public function apply_shipping_tax($cart, $tax_amount)
    {
        // TODO: Implement shipping tax integration
        $this->logger->info('Applying shipping tax: ' . $tax_amount);

        return true;
    }

    public function update_total_rows()
    {
        // TODO: Update WooCommerce total row display
        return true;
    }

    public function get_cart_subtotal()
    {
        if (WC()->cart) {
            return WC()->cart->get_subtotal();
        }
        return 0;
    }

    public function get_cart_total()
    {
        if (WC()->cart) {
            return WC()->cart->get_total('raw');
        }
        return 0;
    }
}
