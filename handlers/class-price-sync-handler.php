<?php
if (!defined('ABSPATH')) exit;

class Price_Sync_Handler {
    
    public function __construct() {
        add_action('admin_post_sync_vendor_prices', [$this, 'handle_sync_prices_request']);
    }
    
        public function handle_sync_prices_request() {
        if (!current_user_can('manage_woocommerce')) wp_die('دسترسی غیرمجاز');
        
        set_time_limit(600);
        ini_set('memory_limit', '512M');
        wp_suspend_cache_addition(true);
    
        $vendor_id = intval($_POST['vendor_id']);
        $brand_id = sanitize_text_field($_POST['product_brand']); // تغییر نام متغیر
    
        try {
            $saved_count = Vendor_Raw_Price_Saver_Optimized::save_raw_prices_optimized($vendor_id, $brand_id); // ارسال brand_id به جای cat_id
            wp_redirect(admin_url('admin.php?page=vendor-sync-prices&saved=' . $saved_count));
            exit;
        } catch (Exception $e) {
            error_log('Vendor Price Sync Error: ' . $e->getMessage());
            wp_redirect(admin_url('admin.php?page=vendor-sync-prices&error=1'));
            exit;
        }
    }
}

new Price_Sync_Handler();