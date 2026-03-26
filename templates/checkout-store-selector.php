<?php

/**
 * Store Selector Template for Checkout
 *
 * Displays a store selector for pickup, or a static store address when only one store exists.
 *
 * @package WooDistanceCheckout
 * @var array $available_stores Array of stores with id, name, address keys
 * @var int $selected_store Currently selected store ID
 */

if (! defined('ABSPATH')) {
    exit;
}

$store_count = count($available_stores);
$single_store = 1 === $store_count ? reset($available_stores) : null;
?>
<div id="wdc-store-selector" class="wdc-store-selector <?php echo $is_pickup ? 'wdc-store-selector--visible' : 'wdc-store-selector--hidden'; ?>">
    <h3><?php echo 1 === $store_count ? esc_html__('Pickup Location', 'woo-distance-checkout') : esc_html__('Select Pickup Store', 'woo-distance-checkout'); ?></h3>

    <?php if (empty($available_stores)) : ?>
        <p class="wdc-store-selector-message"><?php esc_html_e('No pickup store configured.', 'woo-distance-checkout'); ?></p>
    <?php elseif (1 === $store_count) : ?>
        <div class="wdc-store-selector-badge">
            <p><?php echo esc_html($single_store['address']); ?></p>
        </div>
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