<?php

$settings = new WDC_Settings();
$current_settings = $settings->get_all_settings();

?>

<div class="wrap wdc-settings-wrap">
    <h1><?php echo esc_html__('Woo Distance Checkout Settings', 'woo-distance-checkout'); ?></h1>

    <?php settings_errors('wdc_settings'); ?>

    <form method="post" action="">
        <?php wp_nonce_field('wdc_settings_nonce'); ?>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="wdc-google-maps-api-key">
                        <?php echo esc_html__('Google Frontend API Key', 'woo-distance-checkout'); ?>
                    </label>
                </th>
                <td>
                    <input
                        type="password"
                        id="wdc-google-maps-api-key"
                        name="wdc_settings[google_maps_api_key]"
                        value="<?php echo esc_attr($current_settings['google_maps_api_key']); ?>"
                        class="regular-text" />
                    <p class="description">
                        <?php echo esc_html__('Used for checkout address autocomplete.', 'woo-distance-checkout'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="wdc-google-maps-server-api-key">
                        <?php echo esc_html__('Google Backend API Key', 'woo-distance-checkout'); ?>
                    </label>
                </th>
                <td>
                    <input
                        type="password"
                        id="wdc-google-maps-server-api-key"
                        name="wdc_settings[google_maps_server_api_key]"
                        value="<?php echo esc_attr($current_settings['google_maps_server_api_key']); ?>"
                        class="regular-text" />
                    <p class="description">
                        <?php echo esc_html__('Used for Geocoding and Distance Matrix requests from the server.', 'woo-distance-checkout'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="wdc-tax-api-key">
                        <?php echo esc_html__('RapidAPI Tax API Key', 'woo-distance-checkout'); ?>
                    </label>
                </th>
                <td>
                    <input
                        type="password"
                        id="wdc-tax-api-key"
                        name="wdc_settings[tax_api_key]"
                        value="<?php echo esc_attr($current_settings['tax_api_key']); ?>"
                        class="regular-text" />
                    <p class="description">
                        <?php echo esc_html__('Your RapidAPI key for Sales Tax Rates API. Get it from RapidAPI.', 'woo-distance-checkout'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="wdc-store-address">
                        <?php echo esc_html__('Store Address', 'woo-distance-checkout'); ?>
                    </label>
                </th>
                <td>
                    <textarea
                        id="wdc-store-address"
                        name="wdc_settings[store_address]"
                        rows="4"
                        class="large-text"><?php echo esc_textarea($current_settings['store_address']); ?></textarea>
                    <p class="description">
                        <?php echo esc_html__('Full address of your main store location.', 'woo-distance-checkout'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="wdc-rate-per-mile">
                        <?php echo esc_html__('Rate per Mile', 'woo-distance-checkout'); ?>
                    </label>
                </th>
                <td>
                    <input
                        type="number"
                        id="wdc-rate-per-mile"
                        name="wdc_settings[rate_per_mile]"
                        value="<?php echo esc_attr($current_settings['rate_per_mile']); ?>"
                        step="0.01"
                        min="0"
                        class="small-text" />
                    <p class="description">
                        <?php echo esc_html__('Shipping cost per mile.', 'woo-distance-checkout'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="wdc-base-fee">
                        <?php echo esc_html__('Base Shipping Fee', 'woo-distance-checkout'); ?>
                    </label>
                </th>
                <td>
                    <input
                        type="number"
                        id="wdc-base-fee"
                        name="wdc_settings[base_fee]"
                        value="<?php echo esc_attr($current_settings['base_fee']); ?>"
                        step="0.01"
                        min="0"
                        class="small-text" />
                    <p class="description">
                        <?php echo esc_html__('Base fee applied to all deliveries.', 'woo-distance-checkout'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="wdc-minimum-shipping">
                        <?php echo esc_html__('Minimum Shipping Cost', 'woo-distance-checkout'); ?>
                    </label>
                </th>
                <td>
                    <input
                        type="number"
                        id="wdc-minimum-shipping"
                        name="wdc_settings[minimum_shipping]"
                        value="<?php echo esc_attr($current_settings['minimum_shipping']); ?>"
                        step="0.01"
                        min="0"
                        class="small-text" />
                    <p class="description">
                        <?php echo esc_html__('Minimum shipping cost threshold.', 'woo-distance-checkout'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="wdc-rounding-step">
                        <?php echo esc_html__('Rounding Step', 'woo-distance-checkout'); ?>
                    </label>
                </th>
                <td>
                    <input
                        type="number"
                        id="wdc-rounding-step"
                        name="wdc_settings[rounding_step]"
                        value="<?php echo esc_attr($current_settings['rounding_step']); ?>"
                        step="1"
                        min="1"
                        class="small-text" />
                    <p class="description">
                        <?php echo esc_html__('Round up to the nearest whole dollar amount. For example, 5 rounds up to the nearest $5.', 'woo-distance-checkout'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="wdc-maximum-delivery-distance">
                        <?php echo esc_html__('Maximum Delivery Distance (miles)', 'woo-distance-checkout'); ?>
                    </label>
                </th>
                <td>
                    <input
                        type="number"
                        id="wdc-maximum-delivery-distance"
                        name="wdc_settings[maximum_delivery_distance]"
                        value="<?php echo esc_attr($current_settings['maximum_delivery_distance']); ?>"
                        min="1"
                        class="small-text" />
                    <p class="description">
                        <?php echo esc_html__('Maximum distance for delivery option availability.', 'woo-distance-checkout'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="wdc-out-of-delivery-area-message">
                        <?php echo esc_html__('Out of delivery area message', 'woo-distance-checkout'); ?>
                    </label>
                </th>
                <td>
                    <textarea
                        id="wdc-out-of-delivery-area-message"
                        name="wdc_settings[out_of_delivery_area_message]"
                        rows="3"
                        class="large-text"><?php echo esc_textarea($current_settings['out_of_delivery_area_message']); ?></textarea>
                    <p class="description">
                        <?php echo esc_html__('Shown to customers when their address is outside the allowed delivery distance. Leave empty to use the default message.', 'woo-distance-checkout'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="wdc-taxable-shipping">
                        <?php echo esc_html__('Taxable Shipping', 'woo-distance-checkout'); ?>
                    </label>
                </th>
                <td>
                    <input
                        type="checkbox"
                        id="wdc-taxable-shipping"
                        name="wdc_settings[taxable_shipping]"
                        value="yes"
                        <?php checked($current_settings['taxable_shipping'], 'yes'); ?> />
                    <label for="wdc-taxable-shipping">
                        <?php echo esc_html__('Enable tax calculation on shipping costs.', 'woo-distance-checkout'); ?>
                    </label>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="wdc-debug-mode">
                        <?php echo esc_html__('Debug Mode', 'woo-distance-checkout'); ?>
                    </label>
                </th>
                <td>
                    <input
                        type="checkbox"
                        id="wdc-debug-mode"
                        name="wdc_settings[debug_mode]"
                        value="yes"
                        <?php checked($current_settings['debug_mode'], 'yes'); ?> />
                    <label for="wdc-debug-mode">
                        <?php echo esc_html__('Enable debug logging.', 'woo-distance-checkout'); ?>
                    </label>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="wdc-api-timeout">
                        <?php echo esc_html__('API Timeout (seconds)', 'woo-distance-checkout'); ?>
                    </label>
                </th>
                <td>
                    <input
                        type="number"
                        id="wdc-api-timeout"
                        name="wdc_settings[api_timeout]"
                        value="<?php echo esc_attr($current_settings['api_timeout']); ?>"
                        min="1"
                        class="small-text" />
                    <p class="description">
                        <?php echo esc_html__('Timeout in seconds for external API requests.', 'woo-distance-checkout'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="wdc-fallback-mode">
                        <?php echo esc_html__('Fallback Mode', 'woo-distance-checkout'); ?>
                    </label>
                </th>
                <td>
                    <select
                        id="wdc-fallback-mode"
                        name="wdc_settings[fallback_mode]"
                        class="regular-text">
                        <option value="block_checkout" <?php selected($current_settings['fallback_mode'], 'block_checkout'); ?>>
                            <?php echo esc_html__('Block Checkout', 'woo-distance-checkout'); ?>
                        </option>
                        <option value="hide_delivery" <?php selected($current_settings['fallback_mode'], 'hide_delivery'); ?>>
                            <?php echo esc_html__('Hide Delivery Option', 'woo-distance-checkout'); ?>
                        </option>
                        <option value="manual_review" <?php selected($current_settings['fallback_mode'], 'manual_review'); ?>>
                            <?php echo esc_html__('Manual Review', 'woo-distance-checkout'); ?>
                        </option>
                    </select>
                    <p class="description">
                        <?php echo esc_html__('Behavior when distance/tax service is unavailable.', 'woo-distance-checkout'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="wdc-calculation-mode">
                        <?php echo esc_html__('Calculation Mode', 'woo-distance-checkout'); ?>
                    </label>
                </th>
                <td>
                    <select
                        id="wdc-calculation-mode"
                        name="wdc_settings[calculation_mode]"
                        class="regular-text">
                        <option value="live" <?php selected($current_settings['calculation_mode'], 'live'); ?>>
                            <?php echo esc_html__('Live API', 'woo-distance-checkout'); ?>
                        </option>
                        <option value="mock" <?php selected($current_settings['calculation_mode'], 'mock'); ?>>
                            <?php echo esc_html__('Mock Data', 'woo-distance-checkout'); ?>
                        </option>
                    </select>
                    <p class="description">
                        <?php echo esc_html__('Choose between live API calculations or mock data for testing.', 'woo-distance-checkout'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="wdc-mock-scenario">
                        <?php echo esc_html__('Mock Scenario', 'woo-distance-checkout'); ?>
                    </label>
                </th>
                <td>
                    <select
                        id="wdc-mock-scenario"
                        name="wdc_settings[mock_scenario]"
                        class="regular-text">
                        <option value="happy_path" <?php selected($current_settings['mock_scenario'], 'happy_path'); ?>>
                            <?php echo esc_html__('Happy Path (delivery succeeds)', 'woo-distance-checkout'); ?>
                        </option>
                        <option value="incomplete_address" <?php selected($current_settings['mock_scenario'], 'incomplete_address'); ?>>
                            <?php echo esc_html__('Incomplete Address', 'woo-distance-checkout'); ?>
                        </option>
                        <option value="out_of_delivery_zone" <?php selected($current_settings['mock_scenario'], 'out_of_delivery_zone'); ?>>
                            <?php echo esc_html__('Out of Delivery Zone', 'woo-distance-checkout'); ?>
                        </option>
                        <option value="api_failure" <?php selected($current_settings['mock_scenario'], 'api_failure'); ?>>
                            <?php echo esc_html__('API Failure', 'woo-distance-checkout'); ?>
                        </option>
                    </select>
                    <p class="description">
                        <?php echo esc_html__('Active only when Calculation Mode is set to Mock Data.', 'woo-distance-checkout'); ?>
                    </p>
                    <div class="inside wdc-mock-legend" style="margin-top:1em;padding:0.8em 1em;border-left:4px solid #0073aa;background:#f1f1f1;">
                        <strong><?php echo esc_html__('Mock Mode Quick Guide', 'woo-distance-checkout'); ?></strong>
                        <p style="margin:0.5em 0 0;">
                            <?php echo esc_html__('Mock mode uses real plugin inputs for business logic (Rate per Mile, Base Fee, Minimum Shipping, Rounding, Max Distance, Store Address, fulfillment method, checkout/session data). Only provider responses are simulated.', 'woo-distance-checkout'); ?>
                        </p>
                        <p style="margin:0.5em 0 0;"><small><?php echo esc_html__('Scenario behavior:', 'woo-distance-checkout'); ?></small></p>
                        <ul style="margin:0.25em 0 0 1em; padding:0; list-style-type: disc;">
                            <li><?php echo esc_html__('Happy Path — distance and tax provider responses succeed', 'woo-distance-checkout'); ?></li>
                            <li><?php echo esc_html__('Incomplete Address — address validation fails in provider path', 'woo-distance-checkout'); ?></li>
                            <li><?php echo esc_html__('Out of Delivery Zone — distance provider returns 100 miles (beyond max)', 'woo-distance-checkout'); ?></li>
                            <li><?php echo esc_html__('API Failure — provider response is simulated as failure', 'woo-distance-checkout'); ?></li>
                        </ul>
                        <p style="margin:0.5em 0 0;"><small><?php echo esc_html__('Example built-in mock defaults: happy_path distance = 12 mi, out_of_delivery_zone distance = 100 mi, delivery tax=8.25%, pickup tax=7.75%', 'woo-distance-checkout'); ?></small></p>
                    </div>
                </td>
            </tr>
        </table>

        <?php submit_button(); ?>
    </form>
</div>