<?php

class WDC_Address_Resolver
{

    public function get_checkout_address()
    {
        // Priority 1: WC()->customer — populated from post_data by WC before woocommerce_cart_calculate_fees.
        $customer = WC()->customer;
        if ($customer && is_object($customer)) {
            $address = array(
                'street'  => $customer->get_billing_address_1(),
                'street2' => $customer->get_billing_address_2(),
                'city'    => $customer->get_billing_city(),
                'state'   => $customer->get_billing_state(),
                'zip'     => $customer->get_billing_postcode(),
                'country' => $customer->get_billing_country(),
            );

            if ($this->validate_address($this->normalize_address($address))) {
                return $this->normalize_address($address);
            }
        }

        // Priority 2: parse $_POST['post_data'] — billing fields encoded as URL string
        // during update_order_review AJAX (fields are not top-level $_POST keys).
        $post_data_address = $this->parse_post_data_address();
        if ($post_data_address && $this->validate_address($post_data_address)) {
            return $post_data_address;
        }

        // Priority 3: top-level $_POST keys (direct AJAX calls, non-standard contexts).
        if (! empty($_POST['billing_address_1'])) { // phpcs:ignore WordPress.Security.NonceVerification
            $address = array(
                'street'  => sanitize_text_field(wp_unslash($_POST['billing_address_1'])),
                'street2' => isset($_POST['billing_address_2']) ? sanitize_text_field(wp_unslash($_POST['billing_address_2'])) : '',
                'city'    => isset($_POST['billing_city']) ? sanitize_text_field(wp_unslash($_POST['billing_city'])) : '',
                'state'   => isset($_POST['billing_state']) ? sanitize_text_field(wp_unslash($_POST['billing_state'])) : '',
                'zip'     => isset($_POST['billing_postcode']) ? sanitize_text_field(wp_unslash($_POST['billing_postcode'])) : '',
                'country' => isset($_POST['billing_country']) ? sanitize_text_field(wp_unslash($_POST['billing_country'])) : '',
            );
            return $this->normalize_address($address);
        }

        // Priority 4: WC()->checkout->get_value() fallback.
        if (isset(WC()->checkout) && is_object(WC()->checkout)) {
            $address = array(
                'street'  => WC()->checkout->get_value('billing_address_1'),
                'street2' => WC()->checkout->get_value('billing_address_2'),
                'city'    => WC()->checkout->get_value('billing_city'),
                'state'   => WC()->checkout->get_value('billing_state'),
                'zip'     => WC()->checkout->get_value('billing_postcode'),
                'country' => WC()->checkout->get_value('billing_country'),
            );
            return $this->normalize_address($address);
        }

        return $this->normalize_address(array());
    }

    /**
     * Parse billing address fields from $_POST['post_data'] URL-encoded string.
     *
     * During update_order_review AJAX, WooCommerce encodes all checkout form fields
     * into a single post_data string rather than individual $_POST keys.
     *
     * @return array|null Normalized address or null if post_data absent/empty.
     */
    private function parse_post_data_address()
    {
        if (empty($_POST['post_data'])) { // phpcs:ignore WordPress.Security.NonceVerification
            return null;
        }

        $post_data = array();
        wp_parse_str(wp_unslash($_POST['post_data']), $post_data); // phpcs:ignore WordPress.Security.NonceVerification

        if (empty($post_data['billing_address_1'])) {
            return null;
        }

        return $this->normalize_address(array(
            'street'  => sanitize_text_field($post_data['billing_address_1']),
            'street2' => isset($post_data['billing_address_2']) ? sanitize_text_field($post_data['billing_address_2']) : '',
            'city'    => isset($post_data['billing_city']) ? sanitize_text_field($post_data['billing_city']) : '',
            'state'   => isset($post_data['billing_state']) ? sanitize_text_field($post_data['billing_state']) : '',
            'zip'     => isset($post_data['billing_postcode']) ? sanitize_text_field($post_data['billing_postcode']) : '',
            'country' => isset($post_data['billing_country']) ? sanitize_text_field($post_data['billing_country']) : '',
        ));
    }

    public function normalize_address($address)
    {
        if (! is_array($address)) {
            return null;
        }

        $normalized = array(
            'street'  => sanitize_text_field($address['street'] ?? ''),
            'street2' => sanitize_text_field($address['street2'] ?? ''),
            'city'    => sanitize_text_field($address['city'] ?? ''),
            'state'   => sanitize_text_field($address['state'] ?? ''),
            'zip'     => sanitize_text_field($address['zip'] ?? ''),
            'country' => sanitize_text_field($address['country'] ?? ''),
        );

        return $normalized;
    }

    public function validate_address($address)
    {
        $required_fields = array('street', 'city', 'state', 'zip', 'country');

        foreach ($required_fields as $field) {
            if (empty($address[$field])) {
                return false;
            }
        }

        return true;
    }

    public function address_to_string($address)
    {
        $parts = array();

        if (! empty($address['street'])) {
            $parts[] = $address['street'];
        }
        if (! empty($address['street2'])) {
            $parts[] = $address['street2'];
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

        return implode(', ', $parts);
    }

    public function get_customer_address($customer_id = null)
    {
        if (is_null($customer_id)) {
            $customer_id = get_current_user_id();
        }

        $address = $this->get_customer_address_from_db($customer_id);
        return $this->normalize_address($address);
    }

    public function get_checkout_address_from_post()
    {
        $post_data_address = $this->parse_post_data_address();
        if ($post_data_address) {
            return $post_data_address;
        }

        $address = array(
            'street'  => isset($_POST['billing_address_1']) ? sanitize_text_field(wp_unslash($_POST['billing_address_1'])) : '', // phpcs:ignore WordPress.Security.NonceVerification
            'street2' => isset($_POST['billing_address_2']) ? sanitize_text_field(wp_unslash($_POST['billing_address_2'])) : '', // phpcs:ignore WordPress.Security.NonceVerification
            'city'    => isset($_POST['billing_city']) ? sanitize_text_field(wp_unslash($_POST['billing_city'])) : '', // phpcs:ignore WordPress.Security.NonceVerification
            'state'   => isset($_POST['billing_state']) ? sanitize_text_field(wp_unslash($_POST['billing_state'])) : '', // phpcs:ignore WordPress.Security.NonceVerification
            'zip'     => isset($_POST['billing_postcode']) ? sanitize_text_field(wp_unslash($_POST['billing_postcode'])) : '', // phpcs:ignore WordPress.Security.NonceVerification
            'country' => isset($_POST['billing_country']) ? sanitize_text_field(wp_unslash($_POST['billing_country'])) : '', // phpcs:ignore WordPress.Security.NonceVerification
        );

        return $this->normalize_address($address);
    }

    public function get_normalized_address()
    {
        return $this->get_checkout_address();
    }

    public function is_address_complete_for_delivery()
    {
        $address = $this->get_normalized_address();

        if (! $address) {
            return false;
        }

        return $this->validate_address($address);
    }

    private function get_customer_address_from_db($customer_id)
    {
        if (! $customer_id) {
            return array();
        }

        $customer = new WC_Customer($customer_id);

        return array(
            'street'  => $customer->get_billing_address_1(),
            'street2' => $customer->get_billing_address_2(),
            'city'    => $customer->get_billing_city(),
            'state'   => $customer->get_billing_state(),
            'zip'     => $customer->get_billing_postcode(),
            'country' => $customer->get_billing_country(),
        );
    }
}