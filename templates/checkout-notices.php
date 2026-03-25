<?php

if (! isset($notice)) {
    $notice = null;
}

?>

<div id="wdc-notices" class="wdc-checkout-notices">
    <?php if ($notice) : ?>
        <div class="wdc-notice wdc-notice-<?php echo esc_attr($notice['type']); ?>">
            <p><?php echo esc_html($notice['message']); ?></p>
        </div>
    <?php endif; ?>
</div>
