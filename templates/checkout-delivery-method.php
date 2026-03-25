<?php

if (! isset($current_method)) {
    $current_method = 'delivery';
}

?>

<div id="wdc-fulfillment-method" class="wdc-checkout-section">
    <h3><?php echo esc_html__('Delivery Method', 'woo-distance-checkout'); ?></h3>
    <div class="wdc-fulfillment-method-options">
        <label>
            <input
                type="radio"
                name="wdc_fulfillment_method"
                value="delivery"
                <?php checked($current_method, 'delivery'); ?>
                class="wdc-fulfillment-method-input"
                data-method="delivery" />
            <?php echo esc_html__('Delivery', 'woo-distance-checkout'); ?>
        </label>
        <label>
            <input
                type="radio"
                name="wdc_fulfillment_method"
                value="pickup"
                <?php checked($current_method, 'pickup'); ?>
                class="wdc-fulfillment-method-input"
                data-method="pickup" />
            <?php echo esc_html__('Self Pickup', 'woo-distance-checkout'); ?>
        </label>
    </div>
</div>