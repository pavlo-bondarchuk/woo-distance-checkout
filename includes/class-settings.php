<?php

class WDC_Settings
{

    const SETTINGS_GROUP = 'wdc_settings_group';
    const SETTINGS_PAGE = 'wdc_settings_page';

    private $defaults = array(
        'google_maps_api_key'        => '',
        'google_maps_server_api_key' => '',
        'tax_api_key'                => '',
        'store_address'              => '',
        'rate_per_mile'              => 3.00,
        'base_fee'                   => 30.00,
        'rounding_step'              => 5.00,
        'minimum_shipping'           => 0.00,
        'maximum_delivery_distance'  => 70,
        'taxable_shipping'           => 'yes',
        'debug_mode'                 => 'no',
        'api_timeout'                => 10,
        'fallback_mode'              => 'block_checkout',
        'calculation_mode'           => 'mock',
        'mock_scenario'              => 'happy_path',
        'out_of_delivery_area_message' => '',
        // Deprecated explicitly adjustable mock fields kept for backward compatibility
        'mock_distance_miles'        => 12,
        'mock_delivery_tax_rate'     => 8.25,
        'mock_pickup_tax_rate'       => 7.75,
        'mock_delivery_available'    => 1,
        'mock_address_complete'      => 1,
    );

    public function register_settings()
    {
        register_setting(
            self::SETTINGS_GROUP,
            'wdc_settings',
            array(
                'type'              => 'object',
                'sanitize_callback' => array($this, 'sanitize_settings'),
            )
        );
    }

    public function sanitize_settings($input)
    {
        if (! is_array($input)) {
            $input = array();
        }

        $sanitized = array();

        $sanitized['google_maps_api_key'] = isset($input['google_maps_api_key']) ? sanitize_text_field($input['google_maps_api_key']) : '';
        $sanitized['google_maps_server_api_key'] = isset($input['google_maps_server_api_key']) ? sanitize_text_field($input['google_maps_server_api_key']) : '';
        $sanitized['tax_api_key']         = isset($input['tax_api_key']) ? sanitize_text_field($input['tax_api_key']) : '';
        $sanitized['store_address']       = isset($input['store_address']) ? sanitize_textarea_field($input['store_address']) : '';

        $sanitized['rate_per_mile'] = $this->validate_positive_float(
            $input['rate_per_mile'] ?? null,
            $this->defaults['rate_per_mile']
        );

        $sanitized['base_fee'] = $this->validate_positive_float(
            $input['base_fee'] ?? null,
            $this->defaults['base_fee']
        );

        $sanitized['minimum_shipping'] = $this->validate_non_negative_float(
            $input['minimum_shipping'] ?? null,
            $this->defaults['minimum_shipping']
        );

        $sanitized['rounding_step'] = $this->validate_positive_float(
            $input['rounding_step'] ?? null,
            $this->defaults['rounding_step']
        );

        $sanitized['maximum_delivery_distance'] = $this->validate_positive_int(
            $input['maximum_delivery_distance'] ?? null,
            $this->defaults['maximum_delivery_distance']
        );

        $sanitized['api_timeout'] = $this->validate_positive_int(
            $input['api_timeout'] ?? null,
            $this->defaults['api_timeout']
        );

        $sanitized['fallback_mode'] = $this->validate_fallback_mode(
            $input['fallback_mode'] ?? null
        );

        $sanitized['out_of_delivery_area_message'] = isset($input['out_of_delivery_area_message'])
            ? sanitize_textarea_field($input['out_of_delivery_area_message'])
            : '';

        $sanitized['taxable_shipping'] = isset($input['taxable_shipping']) && 'yes' === $input['taxable_shipping'] ? 'yes' : 'no';
        $sanitized['debug_mode']       = isset($input['debug_mode']) && 'yes' === $input['debug_mode'] ? 'yes' : 'no';

        $sanitized['calculation_mode'] = $this->validate_calculation_mode(
            $input['calculation_mode'] ?? null
        );

        $sanitized['mock_scenario'] = $this->validate_mock_scenario(
            $input['mock_scenario'] ?? null
        );

        // Keep deprecated mock fields available for backward compatibility (not used as main driver)
        $sanitized['mock_distance_miles'] = $this->validate_non_negative_float(
            $input['mock_distance_miles'] ?? null,
            $this->defaults['mock_distance_miles']
        );

        $sanitized['mock_delivery_tax_rate'] = $this->validate_non_negative_float(
            $input['mock_delivery_tax_rate'] ?? null,
            $this->defaults['mock_delivery_tax_rate']
        );

        $sanitized['mock_pickup_tax_rate'] = $this->validate_non_negative_float(
            $input['mock_pickup_tax_rate'] ?? null,
            $this->defaults['mock_pickup_tax_rate']
        );

        $sanitized['mock_delivery_available'] = isset($input['mock_delivery_available']) && 'yes' === $input['mock_delivery_available'] ? 'yes' : 'no';
        $sanitized['mock_address_complete']   = isset($input['mock_address_complete']) && 'yes' === $input['mock_address_complete'] ? 'yes' : 'no';

        return $sanitized;
    }

    private function validate_positive_float($value, $default)
    {
        if (is_null($value)) {
            return $default;
        }
        $float_value = floatval($value);
        return $float_value >= 0 ? $float_value : $default;
    }

    private function validate_non_negative_float($value, $default)
    {
        if (is_null($value)) {
            return $default;
        }
        $float_value = floatval($value);
        return $float_value >= 0 ? $float_value : $default;
    }

    private function validate_positive_int($value, $default)
    {
        if (is_null($value)) {
            return $default;
        }
        $int_value = intval($value);
        return $int_value > 0 ? $int_value : $default;
    }

    private function validate_fallback_mode($value)
    {
        $allowed_modes = array('block_checkout', 'hide_delivery', 'manual_review');
        if (in_array($value, $allowed_modes, true)) {
            return $value;
        }
        return $this->defaults['fallback_mode'];
    }

    private function validate_calculation_mode($value)
    {
        $allowed_modes = array('live', 'mock');
        if (in_array($value, $allowed_modes, true)) {
            return $value;
        }
        return $this->defaults['calculation_mode'];
    }

    private function validate_mock_scenario($value)
    {
        $allowed_scenarios = array('happy_path', 'incomplete_address', 'out_of_delivery_zone', 'api_failure');
        if (in_array($value, $allowed_scenarios, true)) {
            return $value;
        }
        return $this->defaults['mock_scenario'];
    }

    public function get_mock_scenario()
    {
        return $this->get_setting('mock_scenario', $this->defaults['mock_scenario']);
    }

    public function get_setting($key, $default = null)
    {
        $settings = get_option('wdc_settings', array());

        if (isset($settings[$key])) {
            return $settings[$key];
        }

        if (isset($this->defaults[$key])) {
            return $this->defaults[$key];
        }

        return $default;
    }

    public function get_all_settings()
    {
        $settings = get_option('wdc_settings', array());
        return wp_parse_args($settings, $this->defaults);
    }

    public function get_raw_setting($key)
    {
        $settings = get_option('wdc_settings', array());
        if (array_key_exists($key, $settings)) {
            return $settings[$key];
        }

        return null;
    }

    public function update_setting($key, $value)
    {
        $settings        = get_option('wdc_settings', array());
        $settings[$key] = $value;
        update_option('wdc_settings', $settings);
    }

    public static function get_option($key, $default = null)
    {
        $instance = new self();
        return $instance->get_setting($key, $default);
    }

    public function is_mock_mode()
    {
        return 'mock' === $this->get_setting('calculation_mode');
    }

    public function is_live_mode()
    {
        return 'live' === $this->get_setting('calculation_mode');
    }

    public function is_debug_enabled()
    {
        return 'yes' === $this->get_setting('debug_mode');
    }

    public function get_mock_setting($key, $default = null)
    {
        // Legacy helper, but modern code should not rely on manual mock override values.
        if ('mock_scenario' === $key) {
            return $this->get_mock_scenario();
        }

        return $default;
    }
}
