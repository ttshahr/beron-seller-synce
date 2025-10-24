<?php
if (!defined('ABSPATH')) exit;

class Stock_Update_Handler {
    
    public function __construct() {
        add_action('admin_post_update_vendor_stocks', [$this, 'handle_update_stocks_request']);
    }
    
    public function handle_update_stocks_request() {
        if (!current_user_can('manage_woocommerce')) wp_die('دسترسی غیرمجاز');
        
        set_time_limit(600);
        ini_set('memory_limit', '512M');
        wp_suspend_cache_addition(true);

        $vendor_id = intval($_POST['vendor_id']);
        $cat_id = sanitize_text_field($_POST['product_cat']);

        try {
            $updated_count = Vendor_Stock_Updater_Optimized::update_stocks($vendor_id, $cat_id);
            wp_redirect(admin_url('admin.php?page=vendor-sync-stocks&updated=' . $updated_count));
            exit;
        } catch (Exception $e) {
            error_log('Stock Update Error: ' . $e->getMessage());
            wp_redirect(admin_url('admin.php?page=vendor-sync-stocks&error=1'));
            exit;
        }
    }
}

new Stock_Update_Handler();