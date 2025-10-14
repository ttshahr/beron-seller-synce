<?php
if (!defined('ABSPATH')) exit;

class Vendor_Logger {
    
    public static function log_error($message, $product_id = null) {
        $log_entry = date('Y-m-d H:i:s') . ' - ERROR';
        if ($product_id) $log_entry .= ' - Product ID: ' . $product_id;
        $log_entry .= ' - ' . $message . PHP_EOL;
        
        file_put_contents(
            WP_CONTENT_DIR . '/vendor-sync-errors.log', 
            $log_entry, 
            FILE_APPEND | LOCK_EX
        );
    }
    
    public static function log_success($product_id, $action) {
        $log_entry = date('Y-m-d H:i:s') . ' - SUCCESS';
        $log_entry .= ' - Product ID: ' . $product_id;
        $log_entry .= ' - Action: ' . $action . PHP_EOL;
        
        file_put_contents(
            WP_CONTENT_DIR . '/vendor-sync-success.log', 
            $log_entry, 
            FILE_APPEND | LOCK_EX
        );
    }
    
    public static function log_api_request($url, $sku, $success) {
        $log_entry = date('Y-m-d H:i:s') . ' - API Request';
        $log_entry .= ' - URL: ' . $url;
        $log_entry .= ' - SKU: ' . $sku;
        $log_entry .= ' - Status: ' . ($success ? 'SUCCESS' : 'FAILED') . PHP_EOL;
        
        file_put_contents(
            WP_CONTENT_DIR . '/vendor-sync-api.log', 
            $log_entry, 
            FILE_APPEND | LOCK_EX
        );
    }
}