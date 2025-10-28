<?php
if (!defined('ABSPATH')) exit;

class Vendor_Price_Calculator {
    
    private static $batch_size = 100;
    
    /**
     * متد اصلی محاسبه قیمت‌های نهایی
     */
    public static function calculate_final_prices($vendor_id, $cat_id, $conversion_percent = 15) {
        $vendor_name = self::get_vendor_name($vendor_id);
        
        // تنظیمات بهینه برای حجم بالا
        set_time_limit(600);
        ini_set('memory_limit', '512M');
        wp_suspend_cache_addition(true);
        wp_defer_term_counting(true);
        
        Vendor_Logger::log_info(
            "شروع محاسبه قیمت برای فروشنده: {$vendor_name} - درصد: {$conversion_percent}%",
            $vendor_id
        );
        
        try {
            $product_ids = self::get_product_ids_with_seller_price($cat_id, $vendor_id);
            
            if (empty($product_ids)) {
                Vendor_Logger::log_warning("هیچ محصولی با قیمت فروشنده برای محاسبه یافت نشد", null, $vendor_id);
                return 0;
            }
            
            Vendor_Logger::log_debug("پیداشدن " . count($product_ids) . " محصول با قیمت فروشنده", $vendor_id);
            
            // پردازش دسته‌ای
            $result = self::process_calculation_batches($product_ids, $conversion_percent, $vendor_id);
            
            if ($result['updated_count'] > 0) {
                Vendor_Logger::log_success(
                    0,
                    'price_calculation_completed',
                    $vendor_id,
                    "محاسبه قیمت تکمیل شد: {$result['updated_count']} محصول بروز شد"
                );
            } else {
                Vendor_Logger::log_info("هیچ قیمتی نیاز به بروزرسانی نداشت", $vendor_id);
            }
            
            return $result['updated_count'];
            
        } finally {
            wp_defer_term_counting(false);
            wp_suspend_cache_addition(false);
            self::cleanup_memory();
        }
    }
    
    /**
     * پردازش دسته‌ای برای مدیریت حافظه
     */
    private static function process_calculation_batches($product_ids, $conversion_percent, $vendor_id) {
        $total_updated = 0;
        $total_errors = 0;
        
        foreach (array_chunk($product_ids, self::$batch_size) as $batch_index => $batch) {
            $batch_result = self::process_single_batch($batch, $conversion_percent, $vendor_id);
            $total_updated += $batch_result['updated_count'];
            $total_errors += $batch_result['error_count'];
            
            // پاکسازی حافظه بعد از هر batch
            self::cleanup_memory();
        }
        
        Vendor_Logger::log_debug("پردازش دسته‌ای تکمیل شد: {$total_updated} بروزرسانی, {$total_errors} خطا", $vendor_id);
        
        return [
            'updated_count' => $total_updated,
            'error_count' => $total_errors
        ];
    }
    
    /**
     * پردازش یک batch
     */
    private static function process_single_batch($product_ids, $conversion_percent, $vendor_id) {
        $updated_count = 0;
        $error_count = 0;
        $batch_updates = [];
        
        foreach ($product_ids as $product_id) {
            try {
                $seller_price = get_post_meta($product_id, '_seller_list_price', true);
                if (!$seller_price || $seller_price <= 0) {
                    continue;
                }
                
                // محاسبه قیمت با درصد دلخواه
                $final_price = self::calculate_single_price($seller_price, $conversion_percent);
                $sale_profit = $final_price - $seller_price;
                
                $batch_updates[] = [
                    'product_id' => $product_id,
                    'final_price' => $final_price,
                    'sale_profit' => $sale_profit,
                    'seller_price' => $seller_price
                ];
                
                // اجرای batch هر 20 محصول
                if (count($batch_updates) >= 20) {
                    $batch_updated = self::execute_batch_updates($batch_updates, $vendor_id);
                    $updated_count += $batch_updated;
                    $batch_updates = [];
                }
                
            } catch (Exception $e) {
                $error_count++;
                Vendor_Logger::log_error(
                    "خطا در محاسبه قیمت: " . $e->getMessage(),
                    $product_id,
                    $vendor_id
                );
            }
        }
        
        // اجرای باقی‌مانده batchها
        if (!empty($batch_updates)) {
            $batch_updated = self::execute_batch_updates($batch_updates, $vendor_id);
            $updated_count += $batch_updated;
        }
        
        return [
            'updated_count' => $updated_count,
            'error_count' => $error_count
        ];
    }
    
    /**
     * اجرای بروزرسانی‌های دسته‌ای
     */
    private static function execute_batch_updates($batch_updates, $vendor_id) {
        $updated_count = 0;
        
        foreach ($batch_updates as $update) {
            $product_id = $update['product_id'];
            $final_price = $update['final_price'];
            $sale_profit = $update['sale_profit'];
            $seller_price = $update['seller_price'];
            
            try {
                $result = self::update_product_price_direct($product_id, $final_price, $sale_profit);
                
                if ($result) {
                    $updated_count++;
                    update_post_meta($product_id, '_colleague_price_update_time', current_time('mysql'));
                    
                    // Vendor_Logger::log_success(
                    //     $product_id,
                    //     'price_calculated',
                    //     $vendor_id,
                    //     "قیمت محاسبه شد: {$seller_price} → {$final_price} (سود: {$sale_profit})"
                    // );
                }
                
            } catch (Exception $e) {
                Vendor_Logger::log_error(
                    "خطا در بروزرسانی قیمت: " . $e->getMessage(),
                    $product_id,
                    $vendor_id
                );
            }
        }
        
        return $updated_count;
    }
    
    /**
     * بروزرسانی مستقیم قیمت محصول
     */
    private static function update_product_price_direct($product_id, $final_price, $sale_profit) {
        $price_updated = update_post_meta($product_id, '_regular_price', $final_price);
        $price_updated = update_post_meta($product_id, '_price', $final_price) && $price_updated;
        $profit_updated = update_post_meta($product_id, '_sale_profit', $sale_profit);
        
        return $price_updated && $profit_updated;
    }
    
    /**
     * محاسبه قیمت تک محصول
     */
    public static function calculate_single_price($seller_price, $conversion_percent) {
        $final_price = $seller_price * (1 + ($conversion_percent / 100));
        $final_price = ceil($final_price / 1000) * 1000;
        return $final_price;
    }
    
    /**
     * دریافت محصولات دارای قیمت فروشنده
     */
    private static function get_product_ids_with_seller_price($cat_id, $vendor_id) {
        global $wpdb;
        
        $sql = "SELECT p.ID FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_type = 'product' 
                AND p.post_status = 'publish'
                AND pm.meta_key = '_seller_list_price'
                AND CAST(pm.meta_value AS DECIMAL(10,2)) > 0
                AND p.post_author = %d";
        
        $params = [$vendor_id];
        
        if ($cat_id !== 'all') {
            $sql .= " AND p.ID IN (
                SELECT object_id FROM {$wpdb->term_relationships} 
                WHERE term_taxonomy_id = %d
            )";
            $params[] = intval($cat_id);
        }
        
        $sql .= " ORDER BY p.ID ASC";
        
        return $wpdb->get_col($wpdb->prepare($sql, $params));
    }
    
    /**
     * پاکسازی حافظه
     */
    private static function cleanup_memory() {
        wp_cache_flush();
        gc_collect_cycles();
    }
    
    /**
     * دریافت نام فروشنده
     */
    private static function get_vendor_name($vendor_id) {
        $vendor = get_userdata($vendor_id);
        return $vendor ? $vendor->display_name : 'فروشنده ناشناس';
    }
}