<?php

/**
 * Store Selector Template for Checkout
 *
 * Displays a dropdown to select pickup store when fulfillment method is set to "Self Pickup"
 *
 * @package WooDistanceCheckout
 * @var array $available_stores Array of stores with id, name, address keys
 * @var int $selected_store Currently selected store ID
 */

if (! defined('ABSPATH')) {
    exit;
}
?>
<div id="wdc-store-selector" class="wdc-store-selector <?php echo $is_pickup ? 'wdc-store-selector--visible' : 'wdc-store-selector--hidden'; ?>">
    <h3><?php esc_html_e('Select Pickup Store', 'woo-distance-checkout'); ?></h3>

    <?php if (empty($available_stores)) : ?>
        <p class="wdc-store-selector-message"><?php esc_html_e('No pickup store configured.', 'woo-distance-checkout'); ?></p>
    <?php else : ?>
        <select
            id="wdc-store-selector-input"
            class="wdc-store-selector-input"
            name="wdc_store_id">
            <?php foreach ($available_stores as $store) : ?>
                <option
                    value="<?php echo intval($store['id']); ?>"
                    <?php selected($selected_store, $store['id']); ?>>
                    <?php echo esc_html($store['name']); ?> - <?php echo esc_html($store['address']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    <?php endif; ?>
</div>