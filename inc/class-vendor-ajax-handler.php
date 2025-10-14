<?php
if (!defined('ABSPATH')) exit;

class Vendor_Ajax_Handler {
    
    public function __construct() {
        add_action('wp_ajax_start_sync_prices', [$this, 'start_sync_prices']);
        add_action('wp_ajax_get_sync_progress', [$this, 'get_sync_progress']);
        add_action('wp_ajax_start_calculate_prices', [$this, 'start_calculate_prices']);
        add_action('wp_ajax_get_calculate_progress', [$this, 'get_calculate_progress']);
        add_action('wp_ajax_start_update_stocks', [$this, 'start_update_stocks']);
        add_action('wp_ajax_get_stocks_progress', [$this, 'get_stocks_progress']);
    }
    
    public function start_sync_prices() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die('دسترسی غیرمجاز');
        }
        
        $vendor_id = intval($_POST['vendor_id']);
        $cat_id = sanitize_text_field($_POST['product_cat']);
        
        // ذخیره اطلاعات برای پردازش
        $batch_id = wp_generate_uuid4();
        $batch_data = [
            'vendor_id' => $vendor_id,
            'cat_id' => $cat_id,
            'type' => 'sync_prices',
            'total_products' => 0,
            'processed' => 0,
            'status' => 'processing',
            'started_at' => current_time('mysql')
        ];
        
        update_option('vendor_batch_' . $batch_id, $batch_data);
        
        // شروع پردازش غیرهمزمان
        $this->process_sync_prices_batch($batch_id);
        
        wp_send_json_success(['batch_id' => $batch_id]);
    }
    
    public function get_sync_progress() {
        $batch_id = sanitize_text_field($_POST['batch_id']);
        $batch_data = get_option('vendor_batch_' . $batch_id);
        
        if (!$batch_data) {
            wp_send_json_error('Batch not found');
        }
        
        wp_send_json_success([
            'processed' => $batch_data['processed'],
            'total' => $batch_data['total_products'],
            'status' => $batch_data['status'],
            'percentage' => $batch_data['total_products'] > 0 ? 
                round(($batch_data['processed'] / $batch_data['total_products']) * 100) : 0
        ]);
    }
    
    private function process_sync_prices_batch($batch_id) {
        // شبیه‌سازی پردازش - در واقعیت باید از WP-Cron یا Background Process استفاده کنید
        $batch_data = get_option('vendor_batch_' . $batch_id);
        
        // محاسبه تعداد کل محصولات
        $total_products = Vendor_Raw_Price_Saver_Optimized::get_total_products_count($batch_data['cat_id']);
        $batch_data['total_products'] = $total_products;
        update_option('vendor_batch_' . $batch_id, $batch_data);
        
        // پردازش واقعی
        $saved_count = Vendor_Raw_Price_Saver_Optimized::save_raw_prices_optimized(
            $batch_data['vendor_id'], 
            $batch_data['cat_id']
        );
        
        // به‌روزرسانی وضعیت
        $batch_data['processed'] = $saved_count;
        $batch_data['status'] = 'completed';
        $batch_data['completed_at'] = current_time('mysql');
        update_option('vendor_batch_' . $batch_id, $batch_data);
    }
    
    // متدهای مشابه برای calculate و stocks...
}

new Vendor_Ajax_Handler();