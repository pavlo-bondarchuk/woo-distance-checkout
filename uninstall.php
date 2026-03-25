<?php

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('wdc_settings');

// TODO: Delete additional plugin data if needed
// - Custom tables
// - Custom post types
// - Cron jobs
