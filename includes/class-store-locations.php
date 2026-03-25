<?php

class WDC_Store_Locations
{

    private $settings;

    public function __construct()
    {
        $this->settings = new WDC_Settings();
    }

    public function get_active_store_address()
    {
        return $this->settings->get_setting('store_address');
    }

    public function get_store_by_id($store_id)
    {
        // TODO: Implement when multiple store support is added
        return array(
            'id'      => 1,
            'name'    => 'Main Store',
            'address' => $this->get_active_store_address(),
        );
    }

    public function get_all_stores()
    {
        return array(
            array(
                'id'      => 1,
                'name'    => 'Main Store',
                'address' => $this->get_active_store_address(),
            ),
        );
    }

    public function get_available_stores()
    {
        $stores = $this->get_all_stores();
        $available = array();

        foreach ($stores as $store) {
            if (! empty($store['address'])) {
                $available[] = $store;
            }
        }

        return $available;
    }

    public function get_default_store()
    {
        return 1;
    }

    public function get_selected_store()
    {
        if (! isset(WC()->session)) {
            return $this->get_default_store();
        }

        $store_id = WC()->session->get('wdc_selected_store');

        if (! $this->store_exists($store_id)) {
            return $this->get_default_store();
        }

        return intval($store_id);
    }

    public function set_selected_store($store_id)
    {
        if (! isset(WC()->session)) {
            return false;
        }

        $store_id = intval($store_id);

        if (! $this->store_exists($store_id)) {
            return false;
        }

        WC()->session->set('wdc_selected_store', $store_id);
        return true;
    }

    public function store_exists($store_id)
    {
        $stores = $this->get_all_stores();
        foreach ($stores as $store) {
            if ($store['id'] === intval($store_id)) {
                return true;
            }
        }
        return false;
    }
}
