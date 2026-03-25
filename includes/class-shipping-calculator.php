<?php

class WDC_Shipping_Calculator
{

    private $settings;
    private $logger;

    public function __construct()
    {
        $this->settings = new WDC_Settings();
        $this->logger   = new WDC_Logger();
    }

    public function calculate_delivery_cost($distance_miles)
    {
        $max_distance = $this->settings->get_setting('maximum_delivery_distance');

        // Check if distance exceeds maximum
        if ($distance_miles > $max_distance) {
            $this->logger->debug('Shipping: delivery distance ' . $distance_miles . ' exceeds max ' . $max_distance);
            return array(
                'success'            => false,
                'raw_shipping'       => null,
                'rounded_shipping'   => null,
                'distance_miles'     => $distance_miles,
                'message'            => 'Delivery is outside the allowed zone.',
            );
        }

        $base_fee           = floatval($this->settings->get_setting('base_fee'));
        $rate_per_mile      = floatval($this->settings->get_setting('rate_per_mile'));
        $minimum_shipping   = floatval($this->settings->get_setting('minimum_shipping'));

        // Formula: (distance_miles * rate_per_mile) + base_fee
        $raw_shipping = ($distance_miles * $rate_per_mile) + $base_fee;

        // Apply minimum shipping threshold
        $raw_shipping = max($raw_shipping, $minimum_shipping);

        // Round up to nearest rounding_step
        $rounded_shipping = $this->round_amount($raw_shipping);

        $this->logger->debug('Shipping calculated: raw=' . $raw_shipping . ' rounded=' . $rounded_shipping . ' distance=' . $distance_miles);

        return array(
            'success'            => true,
            'raw_shipping'       => floatval($raw_shipping),
            'rounded_shipping'   => floatval($rounded_shipping),
            'distance_miles'     => floatval($distance_miles),
            'message'            => 'Shipping calculated successfully.',
        );
    }

    public function calculate_pickup_cost()
    {
        // Pickup does not incur shipping costs
        $this->logger->debug('Pickup shipping: 0.00');

        return array(
            'success'            => true,
            'raw_shipping'       => 0.00,
            'rounded_shipping'   => 0.00,
            'distance_miles'     => 0.00,
            'message'            => 'Pickup does not require shipping.',
        );
    }

    private function round_amount($amount)
    {
        $rounding_step = $this->settings->get_setting('rounding_step');

        if (0 === $rounding_step) {
            return $amount;
        }

        // Round UP to nearest rounding_step using ceil()
        return ceil($amount / $rounding_step) * $rounding_step;
    }
}
