<?php

/**
 * WDC_Calculation_Coordinator
 *
 * Orchestrates the internal calculation flow for delivery and pickup modes.
 * Coordinates distance, shipping, and tax calculations into a unified state.
 *
 * Stage 3: Internal calculation engine (no UI rendering).
 */
class WDC_Calculation_Coordinator
{

    private $settings;
    private $logger;
    private $checkout_controller;
    private $store_locations;
    private $address_resolver;
    private $distance_service;
    private $shipping_calculator;
    private $tax_service;
    private $address_validator;

    public function __construct()
    {
        $this->settings             = new WDC_Settings();
        $this->logger               = new WDC_Logger();
        $this->checkout_controller  = new WDC_Checkout_Controller();
        $this->store_locations      = new WDC_Store_Locations();
        $this->address_resolver     = new WDC_Address_Resolver();
        $this->distance_service     = new WDC_Distance_Service();
        $this->shipping_calculator  = new WDC_Shipping_Calculator();
        $this->tax_service          = new WDC_Tax_Service();
        $this->address_validator    = new WDC_Address_Validation_Service();
    }

    /**
     * Build the complete calculation state for the current checkout.
     *
     * @return array Structured calculation state with success/failure responses.
     */
    public function calculate_current_checkout()
    {
        $fulfillment_method = $this->checkout_controller->get_selected_fulfillment_method();
        $this->logger->debug('Calculating checkout: ' . $fulfillment_method);

        if ('pickup' === $fulfillment_method) {
            return $this->calculate_pickup();
        }

        return $this->calculate_delivery();
    }

    /**
     * Calculate delivery state.
     *
     * @return array Delivery calculation state.
     */
    private function calculate_delivery()
    {
        $this->logger->debug('=== Calculation Coordinator: DELIVERY calculation START ===');

        $store_id = $this->store_locations->get_selected_store();
        $store    = $this->store_locations->get_store_by_id($store_id);

        $this->logger->debug('Store ID: ' . $store_id . ', Store: ' . wp_json_encode($store));

        $customer_address = $this->address_resolver->get_checkout_address();
        $this->logger->debug('Customer address resolved: ' . wp_json_encode($customer_address));

        // Validate customer address is complete
        if (! $this->address_resolver->validate_address($customer_address)) {
            $this->logger->error('DELIVERY BLOCKED: customer address incomplete - ' . wp_json_encode($customer_address));
            return array(
                'success'              => false,
                'fulfillment_method'   => 'delivery',
                'store'                => $store,
                'customer_address'     => $customer_address,
                'address_complete'     => false,
                'distance'             => null,
                'shipping'             => null,
                'tax'                  => null,
                'shipping_required'    => false,
                'message'              => 'Delivery address is incomplete.',
            );
        }

        $this->logger->debug('Address validation passed, address: ' . $this->address_resolver->address_to_string($customer_address));

        // Strict address validation for live delivery mode
        // (prevents Google Distance Matrix from coercing bad addresses)
        if ($this->settings->is_live_mode()) {
            $validation_result = $this->address_validator->validate_delivery_address($customer_address);

            if (!$validation_result['is_valid'] && empty($validation_result['provider_error'])) {
                $this->logger->debug('Delivery: strict address validation failed: ' . $validation_result['message']);
                return array(
                    'success'              => false,
                    'fulfillment_method'   => 'delivery',
                    'store'                => $store,
                    'customer_address'     => $customer_address,
                    'address_complete'     => true,
                    'address_validated'    => false,
                    'address_validation_message' => $validation_result['message'],
                    'distance'             => null,
                    'shipping'             => null,
                    'tax'                  => null,
                    'shipping_required'    => false,
                    'message'              => 'Delivery address failed validation: ' . $validation_result['message'],
                );
            }

            if (!empty($validation_result['provider_error'])) {
                $this->logger->debug('Delivery: address validation provider error (allowing checkout): ' . $validation_result['message']);
            }
        }

        // Get store address for distance calculation
        $store_address = $store['address'];

        // Call distance service
        $distance_response = $this->distance_service->get_distance($store_address, $customer_address);
        $this->logger->debug('Distance response: ' . wp_json_encode($distance_response));

        if (! $distance_response['success']) {
            $this->logger->debug('Delivery: distance service failed');
            return array(
                'success'              => false,
                'fulfillment_method'   => 'delivery',
                'store'                => $store,
                'customer_address'     => $customer_address,
                'address_complete'     => true,
                'distance'             => $distance_response,
                'shipping'             => null,
                'tax'                  => null,
                'shipping_required'    => false,
                'message'              => 'Distance calculation failed.',
            );
        }

        // Calculate shipping cost
        $distance_miles = $distance_response['distance_miles'];
        $shipping_response = $this->shipping_calculator->calculate_delivery_cost($distance_miles);
        $this->logger->debug('Shipping response: ' . wp_json_encode($shipping_response));

        if (! $shipping_response['success']) {
            $max_distance = intval($this->settings->get_setting('maximum_delivery_distance'));
            $this->logger->debug('Delivery: distance over limit - distance=' . $distance_miles . ', max=' . $max_distance);
            return array(
                'success'              => false,
                'fulfillment_method'   => 'delivery',
                'store'                => $store,
                'customer_address'     => $customer_address,
                'address_complete'     => true,
                'distance'             => $distance_response,
                'shipping'             => $shipping_response,
                'tax'                  => null,
                'shipping_required'    => false,
                'message'              => sprintf(
                    /* translators: %d: maximum delivery distance in miles */
                    __('Delivery is not available to your address. Maximum delivery distance is %d miles.', 'woo-distance-checkout'),
                    $max_distance
                ),
            );
        }

        // Call tax service for delivery
        $tax_response = $this->tax_service->get_tax_rate_for_delivery($customer_address);
        $this->logger->debug('Tax response: ' . wp_json_encode($tax_response));

        $this->logger->debug('Delivery calculation complete');

        return array(
            'success'              => true,
            'fulfillment_method'   => 'delivery',
            'store'                => $store,
            'customer_address'     => $customer_address,
            'address_complete'     => true,
            'distance'             => $distance_response,
            'shipping'             => $shipping_response,
            'tax'                  => $tax_response,
            'shipping_required'    => true,
            'message'              => 'Delivery calculation completed.',
        );
    }

    /**
     * Calculate pickup state.
     *
     * @return array Pickup calculation state.
     */
    private function calculate_pickup()
    {
        $store_id = $this->store_locations->get_selected_store();
        $store    = $this->store_locations->get_store_by_id($store_id);

        // For pickup, customer address is informational only, not used for tax/shipping
        $customer_address = $this->address_resolver->get_checkout_address();

        // Pickup has no shipping cost
        $shipping_response = $this->shipping_calculator->calculate_pickup_cost();

        // Call tax service for pickup (uses store address)
        $store_address = $store['address'];
        $tax_response = $this->tax_service->get_tax_rate_for_pickup($store_address);

        $this->logger->debug('Pickup calculation complete');

        return array(
            'success'              => true,
            'fulfillment_method'   => 'pickup',
            'store'                => $store,
            'customer_address'     => $customer_address,
            'address_complete'     => null,
            'distance'             => null,
            'shipping'             => $shipping_response,
            'tax'                  => $tax_response,
            'shipping_required'    => false,
            'message'              => 'Pickup calculation completed.',
        );
    }
}
