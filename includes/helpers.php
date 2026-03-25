<?php

if (! function_exists('wdc_get_settings')) {
    function wdc_get_settings()
    {
        return new WDC_Settings();
    }
}

if (! function_exists('wdc_get_logger')) {
    function wdc_get_logger()
    {
        return new WDC_Logger();
    }
}

if (! function_exists('wdc_get_distance_service')) {
    function wdc_get_distance_service()
    {
        return new WDC_Distance_Service();
    }
}

if (! function_exists('wdc_get_tax_service')) {
    function wdc_get_tax_service()
    {
        return new WDC_Tax_Service();
    }
}

if (! function_exists('wdc_get_address_resolver')) {
    function wdc_get_address_resolver()
    {
        return new WDC_Address_Resolver();
    }
}
