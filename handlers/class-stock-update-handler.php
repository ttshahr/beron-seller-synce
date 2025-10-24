<?php
if (!defined('ABSPATH')) exit;

class Stock_Update_Handler {
    
    public function __construct() {
        add_action('admin_post_update_vendor_stocks', [$this, 'handle_update_stocks_request']);
    }
    
    /**
     * هندلر اصلی بروزرسانی موجودی
     */
    public function handle_update_stocks_request() {
        // بررسی امنیت
        if (!current_user_can('manage_woocommerce')) {
            wp_die('دسترسی غیرمجاز');
        }
        
        if (!isset($_POST['vendor_id'])) {
            wp_die('فروشنده انتخاب نشده است');
        }
        
        // تنظیمات سرور برای پردازش طولانی
        set_time_limit(600);
        ini_set('memory_limit', '512M');
        wp_suspend_cache_addition(true);
        
        $vendor_id = intval($_POST['vendor_id']);
        $cat_id = sanitize_text_field($_POST['product_cat'] ?? 'all');
        
        try {
            Vendor_Logger::log_info("🔄 Starting manual stock update for vendor {$vendor_id}, category: {$cat_id}");
            
            $updated_count = Vendor_Stock_Updater_Optimized::update_stocks($vendor_id, $cat_id);
            
            Vendor_Logger::log_success(
                0, 
                'manual_stock_update_completed', 
                $vendor_id, 
                "Manual stock update completed: {$updated_count} products updated"
            );
            
            // ریدایرکت به همان صفحه با حفظ مقادیر انتخاب شده
            $redirect_url = add_query_arg([
                'page' => 'vendor-sync-stocks',
                'updated' => $updated_count,
                'vendor_id' => $vendor_id,
                'product_cat' => $cat_id
            ], admin_url('admin.php'));
            
            wp_redirect($redirect_url);
            exit;
            
        } catch (Exception $e) {
            Vendor_Logger::log_error("Manual stock update failed: " . $e->getMessage(), null, $vendor_id);
            
            // ریدایرکت به همان صفحه با حفظ مقادیر و نمایش خطا
            $redirect_url = add_query_arg([
                'page' => 'vendor-sync-stocks',
                'error' => 1,
                'vendor_id' => $vendor_id,
                'product_cat' => $cat_id
            ], admin_url('admin.php'));
            
            wp_redirect($redirect_url);
            exit;
        }
    }
}

new Stock_Update_Handler();