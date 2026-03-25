<?php

class WDC_Tax_Service
{

    private $settings;
    private $logger;
    private const TAX_CACHE_KEY_PREFIX = 'wdc_tax_cache_';

    public function __construct()
    {
        $this->settings = new WDC_Settings();
        $this->logger   = new WDC_Logger();
    }

    /**
     * Get cached tax result from session by ZIP code.
     *
     * @param string $zip ZIP code.
     * @return array|null Cached tax result or null if not cached.
     */
    private function get_cached_tax_by_zip($zip)
    {
        if (!isset(WC()->session)) {
            return null;
        }
        $cache_key = self::TAX_CACHE_KEY_PREFIX . sanitize_text_field($zip);
        return WC()->session->get($cache_key);
    }

    /**
     * Cache tax result in session by ZIP code.
     *
     * @param string $zip ZIP code.
     * @param array $result Tax result.
     */
    private function cache_tax_by_zip($zip, $result)
    {
        if (!isset(WC()->session)) {
            return;
        }
        $cache_key = self::TAX_CACHE_KEY_PREFIX . sanitize_text_field($zip);
        WC()->session->set($cache_key, $result);
        $this->logger->debug('Tax service: cached tax result for zip=' . $zip);
    }

    public function get_tax_rate_for_delivery($customer_address)
    {
        if ($this->settings->is_live_mode()) {
            // Check cache by ZIP code
            $zip = $customer_address['zip'] ?? '';
            if (!empty($zip)) {
                $cached = $this->get_cached_tax_by_zip($zip);
                if ($cached !== null) {
                    $this->logger->debug('Tax service: using cached rate for delivery zip=' . $zip);
                    return $cached;
                }
            }

            // Call live API
            $result = $this->get_live_tax_rate('delivery', $customer_address);

            // Cache only real successful responses — never cache fallback/failure results.
            if (!empty($zip) && ! empty($result['success']) && empty($result['fallback_used'])) {
                $this->cache_tax_by_zip($zip, $result);
            }

            return $result;
        }

        return $this->get_mock_tax_rate('delivery', $customer_address);
    }

    public function get_tax_rate_for_pickup($store_address)
    {
        if ($this->settings->is_live_mode()) {
            // Check cache by ZIP code
            $zip = $store_address['zip'] ?? '';
            if (!empty($zip)) {
                $cached = $this->get_cached_tax_by_zip($zip);
                if ($cached !== null) {
                    $this->logger->debug('Tax service: using cached rate for pickup zip=' . $zip);
                    return $cached;
                }
            }

            // Call live API
            $result = $this->get_live_tax_rate('pickup', $store_address);

            // Cache only real successful responses — never cache fallback/failure results.
            if (!empty($zip) && ! empty($result['success']) && empty($result['fallback_used'])) {
                $this->cache_tax_by_zip($zip, $result);
            }

            return $result;
        }

        return $this->get_mock_tax_rate('pickup', $store_address);
    }

    public function get_sales_tax_rate($address)
    {
        return $this->get_tax_rate_for_delivery($address);
    }

    public function get_shipping_tax_rate($address)
    {
        return $this->get_tax_rate_for_delivery($address);
    }

    private function get_live_tax_rate($type, $address)
    {
        // Extract ZIP code from address
        $zip_code = $this->extract_zip_from_address($address);
        $address_string = is_array($address) ? implode(', ', $address) : $address;

        $this->logger->debug('Live tax rate requested for ' . $type . ': ' . $address_string);

        if (empty($zip_code)) {
            $this->logger->error('Live tax: ZIP code could not be extracted from address: ' . $address_string);
            return array(
                'success'           => false,
                'origin'            => 'live',
                'tax_rate'          => null,
                'source_type'       => $type,
                'message'           => 'ZIP code could not be extracted from address.',
                'fallback_used'     => false,
                'fallback_mode'     => '',
                'fallback_reason'   => '',
            );
        }

        $this->logger->debug('Live tax: extracted ZIP code: ' . $zip_code);

        // Get API key
        $api_key = (string) $this->settings->get_setting('tax_api_key', '');

        if (empty(trim($api_key))) {
            $this->logger->error('Live tax: Tax API key is not configured');
            return $this->handle_api_failure($type, 'Tax API key is not configured.');
        }

        // Call RapidAPI
        $tax_rate = $this->call_rapidapi_tax($zip_code, $api_key);

        if (is_null($tax_rate)) {
            $this->logger->error('Live tax: API call failed for ZIP ' . $zip_code);
            return $this->handle_api_failure($type, 'Tax API request failed.');
        }

        $this->logger->debug('Live tax: received rate ' . $tax_rate . '% for ZIP ' . $zip_code);

        return array(
            'success'           => true,
            'origin'            => 'live',
            'tax_rate'          => (float) $tax_rate,
            'source_type'       => $type,
            'message'           => 'Tax rate retrieved from API.',
            'fallback_used'     => false,
            'fallback_mode'     => '',
            'fallback_reason'   => '',
        );
    }

    /**
     * Extract ZIP/postal code from address array or string
     *
     * @param array|string $address
     * @return string|null ZIP code or null if not found
     */
    private function extract_zip_from_address($address)
    {
        if (is_array($address)) {
            // Try common postal code keys
            if (! empty($address['postcode'])) {
                return sanitize_text_field($address['postcode']);
            }
            if (! empty($address['postal_code'])) {
                return sanitize_text_field($address['postal_code']);
            }
            if (! empty($address['zip'])) {
                return sanitize_text_field($address['zip']);
            }
            if (! empty($address['zip_code'])) {
                return sanitize_text_field($address['zip_code']);
            }

            // Last attempt: search all values for 5-digit pattern
            foreach ($address as $value) {
                $value = (string) $value;
                if (preg_match('/\b\d{5}(?:-\d{4})?\b/', $value, $matches)) {
                    return $matches[0];
                }
            }
        } elseif (is_string($address)) {
            // Extract first ZIP pattern from string
            if (preg_match('/\b\d{5}(?:-\d{4})?\b/', $address, $matches)) {
                return $matches[0];
            }
        }

        return null;
    }

    /**
     * Call RapidAPI Sales Tax Rates endpoint
     *
     * @param string $zip_code
     * @param string $api_key RapidAPI key
     * @return float|null Tax rate as percentage (e.g., 8.5) or null on failure
     */
    private function call_rapidapi_tax($zip_code, $api_key)
    {
        $url = 'https://sales-tax-rates1.p.rapidapi.com/v/api/?zip=' . urlencode((string) $zip_code);
        $timeout = (int) $this->settings->get_setting('api_timeout', 10);

        $this->logger->debug('Live tax: calling RapidAPI at ' . $url . ' with timeout ' . $timeout . 's');

        $response = wp_remote_get(
            $url,
            array(
                'timeout' => $timeout,
                'headers' => array(
                    'x-rapidapi-host'  => 'sales-tax-rates1.p.rapidapi.com',
                    'x-rapidapi-key'   => (string) $api_key,
                ),
            )
        );

        if (is_wp_error($response)) {
            $this->logger->error('Live tax: WP error: ' . $response->get_error_message());
            return null;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if (200 !== (int) $response_code) {
            $this->logger->error('Live tax: HTTP ' . absint($response_code));
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (! is_array($data)) {
            $this->logger->error('Live tax: response is not valid JSON');
            return null;
        }

        // RapidAPI Sales Tax Rates API response format: check error flag first
        if (isset($data['error']) && true === $data['error']) {
            $this->logger->error('Live tax: API returned error: ' . wp_json_encode($data));
            return null;
        }

        // Extract the combined_rate from data.data (nested structure)
        $tax_rate = null;

        if (isset($data['data']['combined_rate'])) {
            // RapidAPI returns "8.750" as string, already expressed as percentage
            $tax_rate = (float) $data['data']['combined_rate'];
        } elseif (isset($data['combined_rate'])) {
            // Fallback: check top-level combined_rate
            $tax_rate = (float) $data['combined_rate'];
        } elseif (isset($data['tax_rate'])) {
            $tax_rate = (float) $data['tax_rate'];
        } elseif (isset($data['rate'])) {
            $tax_rate = (float) $data['rate'];
        }

        if (is_null($tax_rate) || $tax_rate < 0) {
            $this->logger->error('Live tax: no valid rate found in response: ' . wp_json_encode($data));
            return null;
        }

        $this->logger->debug('Live tax: parsed rate ' . $tax_rate . '% from response');
        return $tax_rate;
    }

    /**
     * Handle tax API failure using fallback mode setting
     *
     * @param string $type 'delivery' or 'pickup'
     * @param string $message Error message
     * @return array Response array with success=false or handled fallback
     */
    private function handle_api_failure($type, $message)
    {
        $fallback_mode = (string) $this->settings->get_setting('fallback_mode', 'block_checkout');

        if ('manual_review' === $fallback_mode) {
            // In manual review mode, allow the order to proceed with default tax rate
            $this->logger->info('Live tax: API failure in manual_review fallback mode');
            return array(
                'success'           => true,
                'origin'            => 'live',
                'tax_rate'          => 0.0, // Zero tax; will be flagged in order meta for manual review
                'source_type'       => $type,
                'message'           => 'Tax API unavailable; using manual review mode.',
                'fallback_used'     => true,
                'fallback_mode'     => 'manual_review',
                'fallback_reason'   => 'tax_api_failure',
            );
        }

        // block_checkout mode (default): reject the transaction
        $this->logger->info('Live tax: API failure in block_checkout fallback mode');
        return array(
            'success'           => false,
            'origin'            => 'live',
            'tax_rate'          => null,
            'source_type'       => $type,
            'message'           => $message,
            'fallback_used'     => false,
            'fallback_mode'     => 'block_checkout',
            'fallback_reason'   => 'tax_api_failure',
        );
    }

    private function get_mock_tax_rate($type, $address)
    {
        $scenario = $this->settings->get_mock_scenario();

        switch ($scenario) {
            case 'incomplete_address':
                if ('pickup' === $type) {
                    $this->logger->debug('Mock incomplete_address scenario, pickup tax success');
                    return array(
                        'success'           => true,
                        'origin'            => 'mock',
                        'tax_rate'          => 7.75,
                        'source_type'       => 'pickup',
                        'scenario'          => $scenario,
                        'message'           => 'Mock scenario: pickup tax available',
                        'fallback_used'     => false,
                        'fallback_mode'     => '',
                        'fallback_reason'   => '',
                    );
                }

                // For delivery in incomplete_address scenario, always return failure
                $this->logger->debug('Mock incomplete_address scenario, delivery tax failure');
                return array(
                    'success'           => false,
                    'origin'            => 'mock',
                    'tax_rate'          => null,
                    'source_type'       => 'delivery',
                    'scenario'          => $scenario,
                    'message'           => 'Mock scenario: incomplete address for delivery tax',
                    'fallback_used'     => false,
                    'fallback_mode'     => '',
                    'fallback_reason'   => '',
                );

            case 'out_of_delivery_zone':
                $tax_rate = ('pickup' === $type) ? 7.75 : 8.25;
                $this->logger->debug('Mock out_of_delivery_zone scenario, ' . $type . ' tax: ' . $tax_rate . '%');
                return array(
                    'success'           => true,
                    'origin'            => 'mock',
                    'tax_rate'          => $tax_rate,
                    'source_type'       => $type,
                    'scenario'          => $scenario,
                    'message'           => 'Mock scenario: out of delivery zone',
                    'fallback_used'     => false,
                    'fallback_mode'     => '',
                    'fallback_reason'   => '',
                );

            case 'api_failure':
                $this->logger->debug('Mock api_failure scenario for tax: ' . $type);
                return array(
                    'success'           => false,
                    'origin'            => 'mock',
                    'tax_rate'          => null,
                    'source_type'       => $type,
                    'scenario'          => $scenario,
                    'message'           => 'Mock scenario: tax API failure',
                    'fallback_used'     => false,
                    'fallback_mode'     => '',
                    'fallback_reason'   => '',
                );

            case 'happy_path':
            default:
                $tax_rate = ('pickup' === $type) ? 7.75 : 8.25;
                $this->logger->debug('Mock happy_path scenario, ' . $type . ' tax: ' . $tax_rate . '%');
                return array(
                    'success'           => true,
                    'origin'            => 'mock',
                    'tax_rate'          => $tax_rate,
                    'source_type'       => $type,
                    'scenario'          => $scenario,
                    'message'           => 'Mock scenario: happy path',
                    'fallback_used'     => false,
                    'fallback_mode'     => '',
                    'fallback_reason'   => '',
                );
        }
    }

    public function get_store_tax_rate()
    {
        // TODO: Implement tax API integration for store address
        // For now, return placeholder response

        $this->logger->info('Store tax rate requested');

        return array(
            'success'     => true,
            'rate'        => 0.00,
            'error'       => null,
        );
    }
}
