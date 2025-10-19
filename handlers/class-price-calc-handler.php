<?php
if (!defined('ABSPATH')) exit;

class Price_Calc_Handler {
    
    public function __construct() {
        add_action('admin_post_calculate_vendor_prices', [$this, 'handle_calculate_request']);
    }
    
    public function handle_calculate_request() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die('دسترسی غیرمجاز');
        }
        
        // بررسی nonce
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'calculate_vendor_prices_nonce')) {
            wp_die('خطای امنیتی');
        }
        
        set_time_limit(300);
        ini_set('memory_limit', '512M');

        $vendor_id = intval($_POST['vendor_id']);
        $cat_id = sanitize_text_field($_POST['product_cat']);
        
        // 🔥 دریافت درصد از کاربر
        $conversion_percent = isset($_POST['conversion_percent']) ? floatval($_POST['conversion_percent']) : 15;

        try {
            // ارسال درصد به کلاس منطق
            $calculated_count = Vendor_Price_Calculator::calculate_final_prices($vendor_id, $cat_id, $conversion_percent);
            
            wp_redirect(admin_url('admin.php?page=vendor-sync-calculate&calculated=' . $calculated_count . '&percent=' . $conversion_percent));
            exit;
            
        } catch (Exception $e) {
            error_log('Price Calculate Error: ' . $e->getMessage());
            Vendor_Logger::log_error('Price calculation failed: ' . $e->getMessage());
            wp_redirect(admin_url('admin.php?page=vendor-sync-calculate&error=1'));
            exit;
        }
    }
}

new Price_Calc_Handler();