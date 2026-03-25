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
        $this->logger->debug('Cart totals: applying WDC values');

        $checkout_controller = new WDC_Checkout_Controller();
        $state               = $checkout_controller->get_calculation_state();

        if (! isset($state['success']) || ! $state['success']) {
            $this->logger->debug('Cart totals: no WDC totals due to state failure: ' . ($state['message'] ?? 'unknown'));
            return;
        }

        $shipping_amount = 0.0;
        if ($state['shipping_required'] && isset($state['shipping']['rounded_shipping'])) {
            $shipping_amount = floatval($state['shipping']['rounded_shipping']);
        }

        // Delivery row
        if ($shipping_amount > 0) {
            $cart->add_fee(__('Shipping', 'woo-distance-checkout'), $shipping_amount, false, '');
            $this->logger->debug('Cart totals: Shipping row added: ' . $shipping_amount);
        } else {
            $this->logger->debug('Cart totals: No shipping row added (pickup or zero shipping)');
        }

        // Tax calculation
        $tax_rate = 0;
        $sales_tax_amount = 0;
        $shipping_tax_amount = 0;

        if (isset($state['tax']['success']) && $state['tax']['success'] && isset($state['tax']['tax_rate'])) {
            $tax_rate = floatval($state['tax']['tax_rate']);

            $subtotal = floatval($cart->get_subtotal());
            $sales_tax_amount = round($subtotal * ($tax_rate / 100), wc_get_price_decimals());

            $taxable_shipping = ('yes' === $this->settings->get_setting('taxable_shipping'));
            if ($taxable_shipping && $shipping_amount > 0) {
                $shipping_tax_amount = round($shipping_amount * ($tax_rate / 100), wc_get_price_decimals());
            }

            if ($sales_tax_amount > 0) {
                $cart->add_fee(__('Sales Tax', 'woo-distance-checkout'), $sales_tax_amount, false, '');
                $this->logger->debug('Cart totals: Sales Tax row added: ' . $sales_tax_amount . ' @ ' . $tax_rate . '%');
            } else {
                $this->logger->debug('Cart totals: Sales Tax amount zero, no row added');
            }

            if ($shipping_tax_amount > 0) {
                $cart->add_fee(__('Shipping Tax', 'woo-distance-checkout'), $shipping_tax_amount, false, '');
                $this->logger->debug('Cart totals: Shipping Tax row added: ' . $shipping_tax_amount . ' @ ' . $tax_rate . '%');
            } else {
                $this->logger->debug('Cart totals: Shipping Tax amount zero, no row added');
            }
        } else {
            $this->logger->debug('Cart totals: Tax row skipped (no tax rate available)');
        }

        $this->logger->debug('Cart totals: final applied amounts -> shipping=' . $shipping_amount . ', sales_tax=' . $sales_tax_amount . ', shipping_tax=' . $shipping_tax_amount);
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
