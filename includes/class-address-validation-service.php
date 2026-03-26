<?php

/**
 * Strict address validation service using Google Geocoding API.
 *
 * Prevents bad or ambiguous delivery addresses from being accepted
 * by validating that the address is precise and not severely coerced.
 *
 * @since 1.0.0
 */
class WDC_Address_Validation_Service
{
    private $logger;
    private $settings;
    private const GOOGLE_GEOCODING_API_URL = 'https://maps.googleapis.com/maps/api/geocode/json';
    private const VALIDATION_CACHE_KEY_PREFIX = 'wdc_validation_cache_';
    private const VALIDATION_RULE_VERSION = 'v2';
    private const DISTANCE_CACHE_KEY_PREFIX = 'wdc_distance_cache_';

    public function __construct()
    {
        $this->logger   = new WDC_Logger();
        $this->settings = new WDC_Settings();
    }

    /**
     * Generate normalized hash for an address for caching.
     *
     * @param array $address_components Address parts.
     * @return string Normalized address hash.
     */
    private function get_address_hash($address_components)
    {
        $normalized = [
            'street' => strtolower(trim($address_components['street'] ?? '')),
            'city'   => strtolower(trim($address_components['city'] ?? '')),
            'state'  => strtolower(trim($address_components['state'] ?? '')),
            'zip'    => strtolower(trim($address_components['zip'] ?? '')),
            'country' => strtolower(trim($address_components['country'] ?? 'us')),
        ];
        return md5(wp_json_encode($normalized));
    }

    /**
     * Get cached validation result from session.
     *
     * @param string $address_hash Address hash.
     * @return array|null Cached validation result or null if not cached.
     */
    private function get_cached_validation($address_hash)
    {
        if (!isset(WC()->session)) {
            return null;
        }

        $cache_key = $this->get_validation_cache_key($address_hash);
        $this->logger->debug('Address validation: cache key=' . $cache_key . ', version=' . self::VALIDATION_RULE_VERSION);

        $cached_result = WC()->session->get($cache_key);
        if (null === $cached_result) {
            $this->logger->debug('Address validation: cache miss for key ' . $cache_key);
        } else {
            $this->logger->debug('Address validation: cache hit for key ' . $cache_key);
        }

        return $cached_result;
    }

    /**
     * Cache validation result in session.
     *
     * @param string $address_hash Address hash.
     * @param array $result Validation result.
     */
    private function cache_validation($address_hash, $result)
    {
        if (!isset(WC()->session)) {
            return;
        }

        $cache_key = $this->get_validation_cache_key($address_hash);
        WC()->session->set($cache_key, $result);
        $this->logger->debug('Address validation: cached result for key ' . $cache_key);
    }

    /**
     * Build versioned session cache key for address validation results.
     *
     * @param string $address_hash Address hash.
     * @return string Cache key.
     */
    private function get_validation_cache_key($address_hash)
    {
        return self::VALIDATION_CACHE_KEY_PREFIX . self::VALIDATION_RULE_VERSION . '_' . $address_hash;
    }

    /**
     * Validate a delivery address using Google Geocoding API.
     *
     * Checks:
     * - Address is not empty/incomplete
     * - Geocoding returns exactly one result (not ambiguous)
     * - Result location is precise (ROOFTOP or RANGE_INTERPOLATED)
     * - Input address components were not severely rewritten (not coerced)
     *
     * @param array $address_components Structured address with keys: street, city, state, zip, country.
     * @return array Validation result: {
     *   is_valid: bool,
     *   confidence: float 0-1,
     *   message: string,
     *   coerced: bool,
     *   location_type: string
     * }
     */
    public function validate_delivery_address($address_components)
    {
        $result = [
            'is_valid'     => false,
            'confidence'   => 0,
            'message'      => 'Address validation not performed',
            'coerced'      => false,
            'location_type' => '',
            'provider_error' => false,
        ];

        if (empty($address_components['street']) || empty($address_components['city']) || empty($address_components['zip'])) {
            $result['message'] = 'Address components incomplete (missing street, city, or zip)';
            $this->logger->debug('Address validation failed: ' . $result['message']);
            return $result;
        }

        $address_hash = $this->get_address_hash($address_components);

        $address_string = $this->build_address_string($address_components);
        $this->logger->debug('Address validation: testing address "' . $address_string . '" (hash: ' . $address_hash . ')');

        if ($this->settings->get_setting('calculation_mode') === 'mock') {
            $result['is_valid']   = true;
            $result['confidence'] = 1.0;
            $result['message']    = 'Mock mode: address validation skipped';
            $this->logger->debug('Address validation: mock mode - validation skipped');
            return $result;
        }

        $cached = $this->get_cached_validation($address_hash);
        if ($cached !== null) {
            return $cached;
        }

        $geocoding_result = $this->geocode_address($address_string, $address_hash);

        if (empty($geocoding_result['success'])) {
            $result['message'] = $geocoding_result['message'] ?? 'Geocoding API failed';
            $result['provider_error'] = true;
            $this->logger->debug('Address validation: provider error (not cached): ' . $result['message']);
            return $result;
        }

        $validation = $this->analyze_geocoding_result($geocoding_result, $address_components);
        $this->logger->debug('Address validation result: ' . wp_json_encode($validation));

        $this->cache_validation($address_hash, $validation);

        return $validation;
    }

    /**
     * Build a single address string from components.
     *
     * @param array $components Address parts.
     * @return string Formatted address string.
     */
    private function build_address_string($components)
    {
        $parts = [
            $components['street'] ?? '',
            $components['city'] ?? '',
            $components['state'] ?? '',
            $components['zip'] ?? '',
            $components['country'] ?? 'US',
        ];

        return trim(implode(', ', array_filter($parts)));
    }

    /**
     * Call Google Geocoding API to validate address.
     *
     * @param string $address_string Full address string to geocode.
     * @param string $address_hash Normalized address hash for debugging.
     * @return array API result: { success: bool, results: array, message: string }
     */
    private function geocode_address($address_string, $address_hash = '')
    {
        $api_key = $this->settings->get_setting('google_maps_api_key');

        // DEBUG: Log key retrieval
        $key_length = strlen((string) $api_key);
        $this->logger->debug('Address validation: geocoding API key retrieval - key_length=' . $key_length . ', settings_key=google_maps_api_key');

        if (empty($api_key)) {
            $this->logger->error('Address validation: Google Maps API key not configured');
            return [
                'success' => false,
                'message' => 'Google API key not configured',
            ];
        }

        // Trim and validate key
        $api_key = trim((string) $api_key);
        if (empty($api_key)) {
            $this->logger->error('Address validation: Google Maps API key is empty after trimming');
            return [
                'success' => false,
                'message' => 'Google API key is invalid',
            ];
        }

        $this->logger->debug('Address validation: geocoding API key loaded successfully - length=' . strlen($api_key));

        // Build query args
        $query_args = [
            'address' => $address_string,
            'key'     => $api_key,
        ];

        $url = add_query_arg($query_args, self::GOOGLE_GEOCODING_API_URL);

        // Log diagnostic info (masked URL)
        $masked_url = self::GOOGLE_GEOCODING_API_URL . '?address=' . urlencode($address_string) . '&key=***MASKED***';
        $this->logger->debug('Address validation: geocoding request - url=' . $masked_url . ', address_hash=' . $address_hash);

        $response = wp_remote_get($url, ['timeout' => 5]);

        // Log HTTP response code
        $http_code = wp_remote_retrieve_response_code($response);
        $this->logger->debug('Address validation: HTTP response code=' . $http_code);

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $this->logger->error('Address validation: geocoding request failed - error=' . $error_message);
            return [
                'success' => false,
                'message' => 'Geocoding API request failed: ' . $error_message,
            ];
        }

        $body = wp_remote_retrieve_body($response);
        $this->logger->debug('Address validation: response body length=' . strlen($body) . ' bytes');
        $this->logger->debug('Address validation: raw geocode response body=' . $body);

        $data = json_decode($body, true);

        if (empty($data)) {
            $this->logger->error('Address validation: invalid JSON response - body=' . substr($body, 0, 200));
            return [
                'success' => false,
                'message' => 'Geocoding API returned invalid JSON',
            ];
        }

        // Log Google status
        $google_status = $data['status'] ?? 'UNKNOWN';
        $error_message = $data['error_message'] ?? '';
        $this->logger->debug('Address validation: Google status=' . $google_status . ('' !== $error_message ? ', error_message=' . $error_message : ''));

        if ($google_status !== 'OK') {
            $this->logger->error('Address validation: geocoding failed - status=' . $google_status . ', error=' . $error_message . ', address_hash=' . $address_hash);
            return [
                'success' => false,
                'message' => 'Geocoding status: ' . $google_status . ('' !== $error_message ? ' (' . $error_message . ')' : ''),
            ];
        }

        if (empty($data['results'])) {
            $this->logger->warning('Address validation: no results from geocoding for address_hash=' . $address_hash);
            return [
                'success' => false,
                'message' => 'No geocoding results found for address',
            ];
        }

        $result_count = count($data['results']);
        $this->logger->debug('Address validation: geocoding success - result_count=' . $result_count . ', address_hash=' . $address_hash);

        return [
            'success' => true,
            'results' => $data['results'],
        ];
    }

    /**
     * Analyze geocoding results to determine if address is valid.
     *
     * @param array $geocoding_result API results from geocode_address().
     * @param array $input_components Original input address components.
     * @return array Validation result.
     */
    private function analyze_geocoding_result($geocoding_result, $input_components)
    {
        $results = $geocoding_result['results'] ?? [];

        // Multiple results = ambiguous address
        if (count($results) > 1) {
            return [
                'is_valid'     => false,
                'confidence'   => 0.3,
                'message'      => 'Address is ambiguous (multiple matches found)',
                'coerced'      => false,
                'location_type' => 'MULTIPLE_RESULTS',
            ];
        }

        $first_result  = $results[0];
        $location_type = $first_result['geometry']['location_type'] ?? '';
        $component_flags = $this->get_result_component_flags($first_result);
        $has_street_level_type = $this->has_street_level_result_type($first_result);

        $this->logger->debug(
            'Address validation: parsed geocode result - formatted_address="' . ($first_result['formatted_address'] ?? '') .
                '", place_id="' . ($first_result['place_id'] ?? '') .
                '", location_type="' . $location_type .
                '", address_components=' . count($first_result['address_components'] ?? [])
        );

        // Check location precision
        if ('GEOMETRIC_CENTER' === $location_type) {
            $missing_parts = [];

            if (! $component_flags['street_number']) {
                $missing_parts[] = 'street_number';
            }
            if (! $component_flags['route']) {
                $missing_parts[] = 'route';
            }
            if (! $component_flags['postal_code']) {
                $missing_parts[] = 'postal_code';
            }
            if (! $component_flags['locality']) {
                $missing_parts[] = 'locality';
            }
            if (! $has_street_level_type) {
                $missing_parts[] = 'street-level result type';
            }

            if (empty($missing_parts)) {
                $this->logger->debug('Address validation: GEOMETRIC_CENTER accepted: street-level address with complete components');
            } else {
                $this->logger->debug('Address validation: GEOMETRIC_CENTER rejected: missing ' . implode(', ', $missing_parts));
                return [
                    'is_valid'     => false,
                    'confidence'   => 0.4,
                    'message'      => 'Address location is not precise enough (' . $location_type . ')',
                    'coerced'      => true,
                    'location_type' => $location_type,
                ];
            }
        } elseif (!in_array($location_type, ['ROOFTOP', 'RANGE_INTERPOLATED'], true)) {
            $this->logger->debug('Address validation: ' . $location_type . ' rejected: location type is not precise enough');
            return [
                'is_valid'     => false,
                'confidence'   => 0.4,
                'message'      => 'Address location is not precise enough (' . $location_type . ')',
                'coerced'      => true,
                'location_type' => $location_type,
            ];
        }

        // Check if address was severely coerced (input parts missing/changed in result)
        $coerced = $this->is_address_coerced($input_components, $first_result);

        if ($coerced) {
            return [
                'is_valid'     => false,
                'confidence'   => 0.2,
                'message'      => 'Input address was significantly coerced into a different location',
                'coerced'      => true,
                'location_type' => $location_type,
            ];
        }

        // Address passed validation
        return [
            'is_valid'     => true,
            'confidence'   => 0.95,
            'message'      => 'Address is valid and precise',
            'coerced'      => false,
            'location_type' => $location_type,
        ];
    }

    /**
     * Check if address was materially coerced by Google.
     *
     * Example: "zzzz not a real address" normalized to a nearby city.
     *
     * @param array $input_components Input address parts.
     * @param array $geocoded_result Geocoding result with address_components.
     * @return bool True if address was coerced.
     */
    private function is_address_coerced($input_components, $geocoded_result)
    {
        $address_components = $geocoded_result['address_components'] ?? [];

        // Extract key parts from geocoded result
        $geocoded_parts = [
            'city'    => '',
            'state'   => '',
            'zip'     => '',
            'country' => '',
        ];

        foreach ($address_components as $component) {
            $types = $component['types'] ?? [];

            if (in_array('locality', $types, true)) {
                $geocoded_parts['city'] = strtolower($component['long_name'] ?? '');
            }
            if (in_array('administrative_area_level_1', $types, true)) {
                $geocoded_parts['state'] = strtolower($component['short_name'] ?? '');
            }
            if (in_array('postal_code', $types, true)) {
                $geocoded_parts['zip'] = strtolower($component['long_name'] ?? '');
            }
            if (in_array('country', $types, true)) {
                $geocoded_parts['country'] = strtolower($component['short_name'] ?? 'us');
            }
        }

        // Compare input to geocoded
        $input_city    = strtolower($input_components['city'] ?? '');
        $input_state   = strtolower($input_components['state'] ?? '');
        $input_zip     = strtolower($input_components['zip'] ?? '');

        // If city or zip significantly different, address was coerced
        if (!empty($input_city) && !empty($geocoded_parts['city'])) {
            // Levenshtein distance > 3 means more than 3 character edits needed
            if (levenshtein($input_city, $geocoded_parts['city']) > 3) {
                $this->logger->debug(
                    'Address coercion detected: input city "' . $input_city .
                        '" became "' . $geocoded_parts['city'] . '"'
                );
                return true;
            }
        }

        if (!empty($input_zip) && !empty($geocoded_parts['zip'])) {
            if ($input_zip !== $geocoded_parts['zip']) {
                $this->logger->debug(
                    'Address coercion detected: input zip "' . $input_zip .
                        '" became "' . $geocoded_parts['zip'] . '"'
                );
                return true;
            }
        }

        return false;
    }

    /**
     * Determine which street-level components exist on a geocoding result.
     *
     * @param array $geocoded_result Geocoding result with address_components.
     * @return array<string, bool>
     */
    private function get_result_component_flags($geocoded_result)
    {
        $flags = [
            'street_number' => false,
            'route'         => false,
            'postal_code'   => false,
            'locality'      => false,
        ];

        foreach ($geocoded_result['address_components'] ?? [] as $component) {
            $types = $component['types'] ?? [];

            if (in_array('street_number', $types, true)) {
                $flags['street_number'] = true;
            }
            if (in_array('route', $types, true)) {
                $flags['route'] = true;
            }
            if (in_array('postal_code', $types, true)) {
                $flags['postal_code'] = true;
            }
            if (in_array('locality', $types, true)) {
                $flags['locality'] = true;
            }
        }

        return $flags;
    }

    /**
     * Determine whether Google classified the result as a street-level address.
     *
     * @param array $geocoded_result Geocoding result with types.
     * @return bool True when result types include street_address or premise.
     */
    private function has_street_level_result_type($geocoded_result)
    {
        $result_types = $geocoded_result['types'] ?? [];

        return in_array('street_address', $result_types, true) || in_array('premise', $result_types, true);
    }
}
