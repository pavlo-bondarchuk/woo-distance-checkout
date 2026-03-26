<?php

class WDC_Distance_Service
{

    private $settings;
    private $logger;
    private const DISTANCE_CACHE_KEY_PREFIX = 'wdc_distance_cache_';

    public function __construct()
    {
        $this->settings = new WDC_Settings();
        $this->logger   = new WDC_Logger();
    }

    /**
     * Generate normalized hash for origin/destination pair for caching.
     *
     * @param array $origin Origin address.
     * @param array $destination Destination address.
     * @return string Normalized address pair hash.
     */
    private function get_distance_pair_hash($origin, $destination)
    {
        $normalized = [
            'origin' => $this->format_address_to_string($origin),
            'destination' => $this->format_address_to_string($destination),
        ];
        return md5(wp_json_encode($normalized));
    }

    /**
     * Get cached distance result from session.
     *
     * @param string $pair_hash Address pair hash.
     * @return array|null Cached distance result or null if not cached.
     */
    private function get_cached_distance($pair_hash)
    {
        if (!isset(WC()->session)) {
            return null;
        }
        $cache_key = self::DISTANCE_CACHE_KEY_PREFIX . $pair_hash;
        return WC()->session->get($cache_key);
    }

    /**
     * Cache distance result in session.
     *
     * @param string $pair_hash Address pair hash.
     * @param array $result Distance result.
     */
    private function cache_distance($pair_hash, $result)
    {
        if (!isset(WC()->session)) {
            return;
        }
        $cache_key = self::DISTANCE_CACHE_KEY_PREFIX . $pair_hash;
        WC()->session->set($cache_key, $result);
        $this->logger->debug('Live distance: cached result for pair_hash ' . $pair_hash);
    }

    public function get_distance($origin_address, $destination_address)
    {
        if ($this->settings->is_live_mode()) {
            $pair_hash = $this->get_distance_pair_hash($origin_address, $destination_address);
            $cached = $this->get_cached_distance($pair_hash);
            if ($cached !== null) {
                $this->logger->debug('Live distance: using cached result for pair_hash ' . $pair_hash);
                return $cached;
            }

            $result = $this->get_live_distance($origin_address, $destination_address);

            if ($result['success']) {
                $this->cache_distance($pair_hash, $result);
            }

            return $result;
        }

        return $this->get_mock_distance($origin_address, $destination_address);
    }

    /**
     * Validate that the API key looks valid for distance requests
     * This is a basic sanity check to help distinguish between missing key vs denied key
     *
     * @param string $api_key
     * @return array|null null if key looks valid, array with error if not
     */
    private function validate_api_key_format($api_key)
    {
        if (empty($api_key)) {
            return array('error' => 'API key is empty');
        }

        // Google API keys are typically alphanumeric with dashes, 39+ characters
        if (strlen($api_key) < 20) {
            return array('error' => 'API key appears too short (length=' . strlen($api_key) . ')');
        }

        if (!preg_match('/^[A-Za-z0-9\-_]+$/', $api_key)) {
            return array('error' => 'API key contains invalid characters');
        }

        return null; // Valid format
    }

    /**
     * Verify that the stored option value is reasonable
     * Logs diagnostic info without exposing the actual key
     */
    private function verify_stored_key_option()
    {
        $option_value = get_option('wdc_settings', array());

        if (!is_array($option_value)) {
            $this->logger->debug('Live distance: stored wdc_settings is not an array, type=' . gettype($option_value));
            return;
        }

        $server_key = isset($option_value['google_maps_server_api_key']) ? trim((string) $option_value['google_maps_server_api_key']) : '';
        $frontend_key = isset($option_value['google_maps_api_key']) ? trim((string) $option_value['google_maps_api_key']) : '';

        if ('' === $server_key && '' === $frontend_key) {
            $this->logger->debug('Live distance: stored keys are empty for both google_maps_server_api_key and google_maps_api_key');
            return;
        }

        $stored_key = '' !== $server_key ? $server_key : $frontend_key;
        $stored_source = '' !== $server_key ? 'google_maps_server_api_key' : 'google_maps_api_key';
        $stored_length = strlen($stored_key);
        $stored_last4 = $stored_length >= 4 ? substr($stored_key, -4) : $stored_key;
        $this->logger->debug('Live distance: stored key source=' . $stored_source . ', length=' . $stored_length . ', last4=' . $stored_last4);
    }

    private function get_live_distance($origin_address, $destination_address)
    {
        $this->verify_stored_key_option();

        $server_key = trim((string) $this->settings->get_setting('google_maps_server_api_key', ''));
        $frontend_key = trim((string) $this->settings->get_setting('google_maps_api_key', ''));
        $api_key = $server_key;
        $key_source = 'google_maps_server_api_key';

        if ('' === $api_key && '' !== $frontend_key) {
            $api_key = $frontend_key;
            $key_source = 'google_maps_api_key';
            $this->logger->debug('Using frontend Google key as temporary backend fallback');
        }

        $key_length = strlen((string) $api_key);
        $key_last4 = $key_length >= 4 ? substr((string) $api_key, -4) : (string) $api_key;
        $this->logger->debug('Live distance: distance matrix key source=' . $key_source . ', key_length=' . $key_length . ', key_last4=' . $key_last4);

        // Audit API key retrieval
        if (empty($api_key)) {
            $this->logger->error('Google Maps API key not configured for live distance calculation.');
            return array(
                'success'        => false,
                'origin'         => 'live',
                'distance_miles' => null,
                'message'        => 'Google Maps API key not configured.',
            );
        }

        // Hard-trim and sanitize the API key
        $api_key = trim((string) $api_key);
        if (empty($api_key)) {
            $this->logger->error('Google Maps API key is empty after trimming.');
            return array(
                'success'        => false,
                'origin'         => 'live',
                'distance_miles' => null,
                'message'        => 'Google Maps API key is invalid.',
            );
        }

        // Validate key format
        $key_validation = $this->validate_api_key_format($api_key);
        if ($key_validation !== null) {
            $this->logger->error('Google Maps API key validation failed: ' . $key_validation['error']);
            return array(
                'success'        => false,
                'origin'         => 'live',
                'distance_miles' => null,
                'message'        => 'API key is invalid: ' . $key_validation['error'],
            );
        }

        // Log key diagnostics
        $key_length = strlen($api_key);
        $key_last4 = substr($api_key, -4);
        $this->logger->debug('Live distance: API key diagnostic - source=' . $key_source . ', length=' . $key_length . ', last4=' . $key_last4);

        $timeout = $this->settings->get_setting('api_timeout', 10); // Default 10 seconds

        // Format addresses to strings (ensure they are scalar)
        $origin_string = $this->format_address_to_string($origin_address);
        $destination_string = $this->format_address_to_string($destination_address);

        // Validate are actually strings after formatting
        if (!is_string($origin_string)) {
            $origin_string = (string) $origin_string;
        }
        if (!is_string($destination_string)) {
            $destination_string = (string) $destination_string;
        }

        if (empty(trim($origin_string))) {
            $this->logger->error('Store address is not configured for distance calculation.');
            return array(
                'success'        => false,
                'origin'         => 'live',
                'distance_miles' => null,
                'message'        => 'Store address is not configured.',
            );
        }

        if (empty(trim($destination_string))) {
            $this->logger->error('Customer address is incomplete for distance calculation.');
            return array(
                'success'        => false,
                'origin'         => 'live',
                'distance_miles' => null,
                'message'        => 'Customer address is incomplete.',
            );
        }

        $this->logger->debug('Live distance: origin formatted: ' . $origin_string);
        $this->logger->debug('Live distance: destination formatted: ' . $destination_string);

        $url_base = 'https://maps.googleapis.com/maps/api/distancematrix/json';
        $this->logger->debug('Live distance: endpoint: ' . $url_base);

        // Build args with scalar string values only
        $args = array(
            'origins'      => (string) $origin_string,
            'destinations' => (string) $destination_string,
            'units'        => 'imperial',
            'key'          => (string) $api_key,
        );

        // Use http_build_query with RFC3986 encoding instead of add_query_arg
        $query_string = http_build_query($args, '', '&', PHP_QUERY_RFC3986);
        $request_url = $url_base . '?' . $query_string;

        // Log the exact request URL with masked key
        $masked_url = preg_replace('/key=[^&]*/', 'key=****' . $key_last4, $request_url);
        $this->logger->debug('Live distance: final request URL (masked): ' . $masked_url);
        $this->logger->debug('Live distance: query string length: ' . strlen($query_string));

        $this->logger->info('Making live Google Maps Distance Matrix request for destination: ' . $destination_string);

        $response = wp_remote_get($request_url, array(
            'timeout' => $timeout,
            'headers' => array(
                'User-Agent' => 'Woo Distance Checkout Plugin',
            ),
        ));

        $http_code = wp_remote_retrieve_response_code($response);
        $this->logger->debug('Live distance: HTTP response code: ' . $http_code);

        if (is_wp_error($response)) {
            $this->logger->error('Google Maps API request failed: ' . $response->get_error_message());
            return array(
                'success'        => false,
                'origin'         => 'live',
                'distance_miles' => null,
                'message'        => 'API request failed: ' . $response->get_error_message(),
            );
        }

        $body = wp_remote_retrieve_body($response);
        $this->logger->debug('Live distance: raw response body: ' . substr($body, 0, 1000));

        $data = json_decode($body, true);

        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->error('Failed to parse Google Maps API response as JSON: ' . json_last_error_msg());
            $this->logger->debug('Live distance: raw response body (full): ' . $body);

            return array(
                'success'        => false,
                'origin'         => 'live',
                'distance_miles' => null,
                'message'        => 'Invalid JSON response from Google Maps API.',
            );
        }

        $status = isset($data['status']) ? $data['status'] : 'UNKNOWN';
        $this->logger->debug('Live distance: Google API top-level status: ' . $status);

        if ($status !== 'OK') {
            $error_message = isset($data['error_message']) ? $data['error_message'] : 'Unknown API error';
            $this->logger->error('Google Maps API returned status: ' . $status . ' - ' . $error_message);

            // If REQUEST_DENIED, the issue is likely:
            // 1. API key is invalid/missing (already checked above)
            // 2. Distance Matrix API is not enabled in Google Cloud Console
            // 3. API key has restrictions (e.g., browser-only, IP restrictions, HTTP referrer restrictions)
            // 4. Billing is not enabled on the Google Cloud project
            // 5. API key permissions are too restricted

            if ($status === 'REQUEST_DENIED') {
                $this->logger->error('REQUEST_DENIED: Check Google Cloud Console - verify Distance Matrix API is enabled, key restrictions, and billing is active.');
            }

            return array(
                'success'        => false,
                'origin'         => 'live',
                'distance_miles' => null,
                'message'        => 'API error: ' . $error_message,
            );
        }

        if (empty($data['rows']) || empty($data['rows'][0]['elements']) || empty($data['rows'][0]['elements'][0])) {
            $this->logger->error('Google Maps API response missing distance data.');
            $this->logger->debug('Live distance: response structure: ' . wp_json_encode($data));
            return array(
                'success'        => false,
                'origin'         => 'live',
                'distance_miles' => null,
                'message'        => 'No distance data available for the provided addresses.',
            );
        }

        $element = $data['rows'][0]['elements'][0];
        $element_status = isset($element['status']) ? $element['status'] : 'UNKNOWN';
        $this->logger->debug('Live distance: element status: ' . $element_status);

        if (isset($element['distance']) && is_array($element['distance'])) {
            $this->logger->debug('Live distance: element distance data: ' . wp_json_encode($element['distance']));
        }

        if ($element_status !== 'OK') {
            $this->logger->error('Google Maps API element status: ' . $element_status);
            return array(
                'success'        => false,
                'origin'         => 'live',
                'distance_miles' => null,
                'message'        => 'Distance calculation failed: ' . $element_status,
            );
        }

        // Extract distance value safely
        if (!isset($element['distance']) || !is_array($element['distance']) || !isset($element['distance']['value'])) {
            $this->logger->error('Google Maps API response missing distance.value in element.');
            $this->logger->debug('Live distance: element data: ' . wp_json_encode($element));
            return array(
                'success'        => false,
                'origin'         => 'live',
                'distance_miles' => null,
                'message'        => 'Distance data malformed in API response.',
            );
        }

        $distance_value = (float) $element['distance']['value']; // in meters

        // Convert meters to miles
        $distance_miles = $distance_value / 1609.34; // 1 mile = 1609.34 meters
        $distance_miles_rounded = round($distance_miles, 2);

        $this->logger->info('Live distance calculated: ' . $distance_miles_rounded . ' miles from ' . $distance_value . ' meters');

        return array(
            'success'        => true,
            'origin'         => 'live',
            'distance_miles' => $distance_miles_rounded,
            'message'        => 'Distance calculated successfully.',
        );
    }

    private function get_mock_distance($origin_address, $destination_address)
    {
        $scenario = $this->settings->get_mock_scenario();

        switch ($scenario) {
            case 'incomplete_address':
                $this->logger->debug('Mock scenario incomplete_address: returning failure regardless of actual address');
                return array(
                    'success'        => false,
                    'origin'         => 'mock',
                    'distance_miles' => null,
                    'message'        => 'Mock scenario: incomplete address',
                    'scenario'       => $scenario,
                );

            case 'out_of_delivery_zone':
                $distance = 100.0;
                $this->logger->debug('Mock scenario out_of_delivery_zone distance: ' . $distance);
                return array(
                    'success'        => true,
                    'origin'         => 'mock',
                    'distance_miles' => $distance,
                    'message'        => 'Mock scenario: out of delivery zone',
                    'scenario'       => $scenario,
                );

            case 'api_failure':
                $this->logger->debug('Mock scenario api_failure for distance.');
                return array(
                    'success'        => false,
                    'origin'         => 'mock',
                    'distance_miles' => null,
                    'message'        => 'Mock scenario: API failure',
                    'scenario'       => $scenario,
                );

            case 'happy_path':
            default:
                $distance = 12.0;
                $this->logger->debug('Mock scenario happy_path distance: ' . $distance);
                return array(
                    'success'        => true,
                    'origin'         => 'mock',
                    'distance_miles' => $distance,
                    'message'        => 'Mock scenario: happy path',
                    'scenario'       => $scenario,
                );
        }
    }

    public function validate_delivery_distance($distance, $max_distance = null)
    {
        if (is_null($max_distance)) {
            $max_distance = $this->settings->get_setting('maximum_delivery_distance');
        }

        if ($distance <= $max_distance) {
            return true;
        }

        return false;
    }

    private function is_address_complete($address)
    {
        if (!is_array($address)) {
            return false;
        }

        $required = array('street', 'city', 'state', 'zip', 'country');

        foreach ($required as $key) {
            if (empty($address[$key])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Format an address array into a comma-separated string for Google Maps API
     * Always returns a scalar string, never an array
     *
     * @param array|string $address
     * @return string
     */
    private function format_address_to_string($address)
    {
        // If already a string, return as-is
        if (is_string($address)) {
            return trim($address);
        }

        // If not an array, cannot format
        if (!is_array($address)) {
            return '';
        }

        $parts = array();

        // Only add non-empty parts
        foreach (array('street', 'street2', 'city', 'state', 'zip', 'country') as $key) {
            if (!empty($address[$key])) {
                $part = $address[$key];
                // Ensure each part is a scalar string
                if (is_scalar($part)) {
                    $parts[] = (string) $part;
                }
            }
        }

        // Join with comma and space, always return scalar string
        $result = implode(', ', $parts);
        return (string) trim($result);
    }
}
