<?php

class WDC_Logger
{

    const LOG_LEVEL_INFO  = 'info';
    const LOG_LEVEL_ERROR = 'error';
    const LOG_LEVEL_DEBUG = 'debug';

    private $settings;
    private $wc_logger;
    private $seen_debug_messages = array();

    public function __construct()
    {
        $this->settings = new WDC_Settings();

        if (function_exists('wc_get_logger')) {
            $this->wc_logger = wc_get_logger();
        }
    }

    public function info($message, $context = array())
    {
        $this->log(self::LOG_LEVEL_INFO, $message, $context);
    }

    public function error($message, $context = array())
    {
        $this->log(self::LOG_LEVEL_ERROR, $message, $context);
    }

    public function debug($message, $context = array())
    {
        $debug_mode = $this->settings->get_setting('debug_mode');

        if ('yes' !== $debug_mode) {
            return;
        }

        $dedupe_key = md5($message . wp_json_encode($context));

        if (isset($this->seen_debug_messages[$dedupe_key])) {
            return;
        }

        $this->seen_debug_messages[$dedupe_key] = true;

        $this->log(self::LOG_LEVEL_DEBUG, $message, $context);
    }

    private function log($level, $message, $context = array())
    {
        if ($this->wc_logger) {
            $this->wc_logger->log($level, $message, array_merge(array('source' => 'woo-distance-checkout'), $context));
        } else {
            $this->fallback_log($level, $message, $context);
        }
    }

    private function fallback_log($level, $message, $context = array())
    {
        $debug_mode = $this->settings->get_setting('debug_mode');

        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[WDC ' . strtoupper($level) . '] ' . $message);
        }
    }
}
