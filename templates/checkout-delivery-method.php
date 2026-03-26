<?php

if (! isset($current_method)) {
    $current_method = 'delivery';
}

?>

<div id="wdc-fulfillment-method" class="wdc-fulfillment-method">
    <h3><?php echo esc_html__('Choose delivery method', 'woo-distance-checkout'); ?></h3>
    <fieldset class="wdc-fulfillment-method-options" role="radiogroup" aria-label="<?php echo esc_attr__('Choose delivery method', 'woo-distance-checkout'); ?>">
        <label class="wdc-fulfillment-method-option">
            <input
                type="radio"
                name="wdc_fulfillment_method"
                value="delivery"
                <?php checked($current_method, 'delivery'); ?>
                class="wdc-fulfillment-method-input"
                data-method="delivery" />
            <span class="wdc-fulfillment-method-option-content">
                <span class="wdc-fulfillment-method-option-title"><?php echo esc_html__('Delivery', 'woo-distance-checkout'); ?></span>
                <span class="wdc-fulfillment-method-option-description"><?php echo esc_html__('Shipping cost depends on distance', 'woo-distance-checkout'); ?></span>
            </span>
        </label>
        <label class="wdc-fulfillment-method-option">
            <input
                type="radio"
                name="wdc_fulfillment_method"
                value="pickup"
                <?php checked($current_method, 'pickup'); ?>
                class="wdc-fulfillment-method-input"
                data-method="pickup" />
            <span class="wdc-fulfillment-method-option-content">
                <span class="wdc-fulfillment-method-option-title"><?php echo esc_html__('Pickup', 'woo-distance-checkout'); ?></span>
                <span class="wdc-fulfillment-method-option-description"><?php echo esc_html__('Shipping is free, tax uses store address', 'woo-distance-checkout'); ?></span>
            </span>
        </label>
    </fieldset>
</div>