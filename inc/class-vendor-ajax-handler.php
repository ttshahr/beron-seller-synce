<?php
if (!defined('ABSPATH')) exit;

class Vendor_Ajax_Handler {
    
    public function __construct() {
        // ثبت هندلرهای AJAX
        add_action('wp_ajax_start_sync_prices', [$this, 'start_sync_prices']);
        add_action('wp_ajax_get_sync_progress', [$this, 'get_sync_progress']);
        add_action('wp_ajax_start_calculate_prices', [$this, 'start_calculate_prices']);
        add_action('wp_ajax_get_calculate_progress', [$this, 'get_calculate_progress']);
        add_action('wp_ajax_start_update_stocks', [$this, 'start_update_stocks']);
        add_action('wp_ajax_get_stocks_progress', [$this, 'get_stocks_progress']);
        add_action('wp_ajax_get_assignment_status', [$this, 'get_assignment_status']);
        
        Vendor_Logger::log_info('Vendor AJAX Handler initialized');
    }
    
    /**
     * شروع همگام‌سازی قیمت‌های خام
     */
    public function start_sync_prices() {
        try {
            if (!current_user_can('manage_woocommerce')) {
                throw new Exception('دسترسی غیرمجاز');
            }
            
            $vendor_id = intval($_POST['vendor_id']);
            $cat_id = sanitize_text_field($_POST['product_cat']);
            
            Vendor_Logger::log_info(
                "Starting price sync via AJAX - Vendor: {$vendor_id}, Category: {$cat_id}"
            );
            
            // اعتبارسنجی داده‌ها
            if (empty($vendor_id)) {
                throw new Exception('فروشنده انتخاب نشده است');
            }
            
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
            
            Vendor_Logger::log_info("Batch created for price sync: {$batch_id}", $vendor_id);
            
            // شروع پردازش غیرهمزمان
            $this->process_sync_prices_batch($batch_id);
            
            wp_send_json_success([
                'batch_id' => $batch_id,
                'message' => 'همگام‌سازی قیمت‌ها شروع شد'
            ]);
            
        } catch (Exception $e) {
            Vendor_Logger::log_error("AJAX price sync failed: " . $e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }
    
    public function get_sync_progress() {
        try {
            $batch_id = sanitize_text_field($_POST['batch_id']);
            $batch_data = get_option('vendor_batch_' . $batch_id);
            
            if (!$batch_data) {
                throw new Exception('Batch not found');
            }
            
            $response = [
                'processed' => $batch_data['processed'],
                'total' => $batch_data['total_products'],
                'status' => $batch_data['status'],
                'percentage' => $batch_data['total_products'] > 0 ? 
                    round(($batch_data['processed'] / $batch_data['total_products']) * 100) : 0
            ];
            
            Vendor_Logger::log_debug("Sync progress checked: " . json_encode($response));
            
            wp_send_json_success($response);
            
        } catch (Exception $e) {
            Vendor_Logger::log_error("Progress check failed: " . $e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }
    
    private function process_sync_prices_batch($batch_id) {
        try {
            $batch_data = get_option('vendor_batch_' . $batch_id);
            $vendor_id = $batch_data['vendor_id'];
            
            Vendor_Logger::log_info("Processing price sync batch: {$batch_id}", $vendor_id);
            
            // محاسبه تعداد کل محصولات
            $total_products = $this->get_products_count($batch_data['cat_id'], $vendor_id);
            $batch_data['total_products'] = $total_products;
            update_option('vendor_batch_' . $batch_id, $batch_data);
            
            Vendor_Logger::log_info("Total products for sync: {$total_products}", $vendor_id);
            
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
            
            Vendor_Logger::log_success(
                0,
                'ajax_price_sync_completed',
                $vendor_id,
                "AJAX price sync completed: {$saved_count} products saved"
            );
            
        } catch (Exception $e) {
            Vendor_Logger::log_error("Batch processing failed: " . $e->getMessage());
            
            // آپدیت وضعیت خطا
            $batch_data = get_option('vendor_batch_' . $batch_id);
            $batch_data['status'] = 'failed';
            $batch_data['error'] = $e->getMessage();
            update_option('vendor_batch_' . $batch_id, $batch_data);
        }
    }
    
    /**
     * شروع محاسبه قیمت‌های نهایی
     */
    public function start_calculate_prices() {
        try {
            if (!current_user_can('manage_woocommerce')) {
                throw new Exception('دسترسی غیرمجاز');
            }
            
            $vendor_id = intval($_POST['vendor_id']);
            $cat_id = sanitize_text_field($_POST['product_cat']);
            
            Vendor_Logger::log_info(
                "Starting price calculation via AJAX - Vendor: {$vendor_id}, Category: {$cat_id}",
                $vendor_id
            );
            
            // پردازش مستقیم (بدون batch)
            $calculated_count = Vendor_Price_Calculator::calculate_final_prices($vendor_id, $cat_id);
            
            Vendor_Logger::log_success(
                0,
                'ajax_calculation_completed',
                $vendor_id,
                "AJAX price calculation completed: {$calculated_count} products updated"
            );
            
            wp_send_json_success([
                'message' => "محاسبه قیمت‌های نهایی انجام شد. تعداد: {$calculated_count} محصول"
            ]);
            
        } catch (Exception $e) {
            Vendor_Logger::log_error("AJAX price calculation failed: " . $e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }
    
    public function get_calculate_progress() {
        // برای سادگی، محاسبه قیمت همیشه کامل شده در نظر گرفته می‌شود
        wp_send_json_success([
            'processed' => 1,
            'total' => 1,
            'status' => 'completed',
            'percentage' => 100
        ]);
    }
    
    /**
     * شروع بروزرسانی موجودی
     */
    public function start_update_stocks() {
        try {
            if (!current_user_can('manage_woocommerce')) {
                throw new Exception('دسترسی غیرمجاز');
            }
            
            $vendor_id = intval($_POST['vendor_id']);
            $cat_id = sanitize_text_field($_POST['product_cat']);
            
            Vendor_Logger::log_info(
                "Starting stock update via AJAX - Vendor: {$vendor_id}, Category: {$cat_id}",
                $vendor_id
            );
            
            // پردازش مستقیم
            $updated_count = Vendor_Stock_Updater_Optimized::update_stocks($vendor_id, $cat_id);
            
            Vendor_Logger::log_success(
                0,
                'ajax_stock_update_completed',
                $vendor_id,
                "AJAX stock update completed: {$updated_count} products updated"
            );
            
            wp_send_json_success([
                'message' => "بروزرسانی موجودی انجام شد. تعداد: {$updated_count} محصول"
            ]);
            
        } catch (Exception $e) {
            Vendor_Logger::log_error("AJAX stock update failed: " . $e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }
    
    public function get_stocks_progress() {
        // برای سادگی، بروزرسانی موجودی همیشه کامل شده در نظر گرفته می‌شود
        wp_send_json_success([
            'processed' => 1,
            'total' => 1,
            'status' => 'completed',
            'percentage' => 100
        ]);
    }
    
    /**
     * دریافت وضعیت اختصاص محصولات
     */
    public function get_assignment_status() {
        try {
            if (!current_user_can('manage_woocommerce')) {
                throw new Exception('دسترسی غیرمجاز');
            }
            
            $vendor_id = intval($_POST['vendor_id']);
            
            Vendor_Logger::log_debug("Getting assignment status for vendor: {$vendor_id}", $vendor_id);
            
            $status = Vendor_Product_Assigner::get_assignment_status($vendor_id);
            
            wp_send_json_success($status);
            
        } catch (Exception $e) {
            Vendor_Logger::log_error("Assignment status check failed: " . $e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * دریافت تعداد محصولات
     */
    private function get_products_count($cat_id, $vendor_id) {
        global $wpdb;
        
        $sql = "SELECT COUNT(*) FROM {$wpdb->posts} p 
                WHERE p.post_type = 'product' 
                AND p.post_status = 'publish'";
        
        // فیلتر بر اساس دسته
        if ($cat_id !== 'all') {
            $sql .= " AND p.ID IN (
                SELECT object_id FROM {$wpdb->term_relationships} 
                WHERE term_taxonomy_id = {$cat_id}
            )";
        }
        
        // فیلتر بر اساس فروشنده
        $vendor_user = get_userdata($vendor_id);
        if ($vendor_user) {
            $sql .= " AND p.post_author = {$vendor_id}";
        }
        
        return $wpdb->get_var($sql);
    }
}

new Vendor_Ajax_Handler();