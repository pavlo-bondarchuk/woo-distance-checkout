<?php

class WDC_Admin
{

    const MENU_SLUG = 'wdc_settings';

    private $settings;

    public function __construct()
    {
        $this->settings = new WDC_Settings();
    }

    public function add_settings_page()
    {
        add_submenu_page(
            'woocommerce',
            __('Distance Checkout', 'woo-distance-checkout'),
            __('Distance Checkout', 'woo-distance-checkout'),
            'manage_options',
            self::MENU_SLUG,
            array($this, 'render_settings_page')
        );
    }

    public function render_settings_page()
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'woo-distance-checkout'));
        }

        if (isset($_POST['submit']) && check_admin_referer('wdc_settings_nonce')) {
            $this->handle_settings_save();
        }

        include WDC_PLUGIN_DIR . 'admin/views/settings-page.php';
    }

    private function handle_settings_save()
    {
        $settings = isset($_POST['wdc_settings']) ? (array) $_POST['wdc_settings'] : array();
        $settings = $this->settings->sanitize_settings($settings);
        update_option('wdc_settings', $settings);

        add_settings_error(
            'wdc_settings',
            'settings_updated',
            __('Settings saved successfully.', 'woo-distance-checkout'),
            'updated'
        );
    }

    public function enqueue_admin_assets($hook_suffix)
    {
        if (strpos($hook_suffix, self::MENU_SLUG) === false) {
            return;
        }

        wp_enqueue_script(
            'wdc-admin',
            WDC_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            WDC_PLUGIN_VERSION,
            true
        );

        wp_enqueue_style(
            'wdc-admin',
            WDC_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            WDC_PLUGIN_VERSION
        );
    }

    public function register_plugin_action_links()
    {
        $plugin_basename = plugin_basename(WDC_PLUGIN_FILE);
        add_filter(
            'plugin_action_links_' . $plugin_basename,
            array($this, 'add_plugin_action_links'),
            10,
            1
        );
    }

    public function add_plugin_action_links($links)
    {
        $settings_url = admin_url('admin.php?page=' . self::MENU_SLUG);
        $settings_link = '<a href="' . esc_url($settings_url) . '">' . esc_html__('Settings', 'woo-distance-checkout') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
}
