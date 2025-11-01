<?php
if (!defined('ABSPATH')) exit;

class Vendor_Raw_Price_Saver_Optimized {
    
    private static $batch_size = 50;
    private static $api_delay = 100000; // 0.1 ثانیه
    
    /**
     * ذخیره قیمت‌های خام از فروشنده - نسخه پشتیبانی از چند برند
     */
    public static function save_raw_prices_optimized($vendor_id, $brand_ids = []) {
        $meta = Vendor_Meta_Handler::get_vendor_meta($vendor_id);
        
        set_time_limit(300);
        ini_set('memory_limit', '256M');
        wp_suspend_cache_addition(true);
        wp_defer_term_counting(true);
        
        // لاگ برندهای انتخاب شده
        $brands_text = empty($brand_ids) ? 'همه برندها' : implode(', ', $brand_ids);
        Vendor_Logger::log_info("شروع همگام‌سازی قیمت - فروشنده: {$vendor_id}, برندها: {$brands_text}", $vendor_id);
        
        try {
            $local_products = self::get_local_products_optimized($brand_ids, $vendor_id);
            
            if (empty($local_products)) {
                Vendor_Logger::log_warning("هیچ محصول محلی برای همگام‌سازی قیمت پیدا نشد", null, $vendor_id);
                return 0;
            }
            
            Vendor_Logger::log_debug("پیداشدن " . count($local_products) . " محصول محلی برای پردازش", $vendor_id);
            
            $result = self::process_streaming_updates($meta, $vendor_id, $local_products);
            
            if ($result['saved_count'] > 0) {
                Vendor_Logger::log_success(
                    0, 
                    'price_sync_completed', 
                    $vendor_id, 
                    "همگام‌سازی قیمت تکمیل شد: {$result['saved_count']} محصول از {$result['processed_count']} محصول ذخیره شد"
                );
            } else {
                Vendor_Logger::log_info("هیچ قیمتی برای ذخیره پیدا نشد", $vendor_id);
            }
            
            return $result['saved_count'];
            
        } finally {
            wp_defer_term_counting(false);
            self::cleanup_memory();
        }
    }
    
    /**
     * پردازش استریمینگ - جلوگیری از تجمع حافظه
     */
    private static function process_streaming_updates($meta, $vendor_id, $local_products) {
        $total_saved = 0;
        $total_processed = 0;
        
        foreach (array_chunk($local_products, self::$batch_size) as $batch_index => $batch) {
            $batch_number = $batch_index + 1;
            
            // پردازش هر batch
            $batch_result = self::process_single_batch($meta, $vendor_id, $batch, $batch_number);
            $total_saved += $batch_result['saved_count'];
            $total_processed += $batch_result['processed_count'];
            
            // پاکسازی حافظه بعد از هر batch
            self::cleanup_memory();
            
            // تاخیر هوشمند - فقط بین batchها
            if ($batch_index < count($local_products) / self::$batch_size - 1) {
                usleep(self::$api_delay);
            }
        }
        
        Vendor_Logger::log_debug("پردازش استریمینگ تکمیل شد: {$total_saved} ذخیره شده از {$total_processed} پردازش شده", $vendor_id);
        
        return [
            'saved_count' => $total_saved,
            'processed_count' => $total_processed
        ];
    }
    
    /**
     * پردازش یک batch
     */
    private static function process_single_batch($meta, $vendor_id, $batch_products, $batch_number) {
        $saved_count = 0;
        $processed_count = 0;
        $batch_updates = [];
        
        // استخراج SKUهای این batch
        $batch_skus = [];
        $product_sku_map = [];
        
        foreach ($batch_products as $product) {
            if (!empty($product['sku'])) {
                $clean_sku = trim($product['sku']);
                $batch_skus[] = $clean_sku;
                $product_sku_map[$clean_sku] = $product['id'];
            }
        }
        
        if (empty($batch_skus)) {
            return ['saved_count' => 0, 'processed_count' => 0];
        }
        
        Vendor_Logger::log_debug("بسته {$batch_number}: پردازش " . count($batch_skus) . " SKU", $vendor_id);
        
        // دریافت محصولات فروشنده
        $vendor_products = self::fetch_vendor_products_bulk($meta, $vendor_id, $batch_skus, $batch_number);
        
        // پردازش محصولات
        foreach ($vendor_products as $vendor_product) {
            if (!empty($vendor_product['sku'])) {
                $clean_sku = trim($vendor_product['sku']);
                
                if (isset($product_sku_map[$clean_sku])) {
                    $product_id = $product_sku_map[$clean_sku];
                    $processed_count++;
                    
                    $raw_price = self::extract_raw_price($vendor_product, $meta, $vendor_id);
                    
                    if ($raw_price > 0) {
                        $batch_updates[] = [
                            'product_id' => $product_id,
                            'raw_price' => $raw_price,
                            'sku' => $clean_sku
                        ];
                        $saved_count++;
                    }
                    
                    // اجرای batch هر 20 محصول
                    if (count($batch_updates) >= 20) {
                        self::execute_batch_updates($batch_updates, $vendor_id);
                        $batch_updates = [];
                    }
                }
            }
        }
        
        // اجرای باقی‌مانده batchها
        if (!empty($batch_updates)) {
            self::execute_batch_updates($batch_updates, $vendor_id);
        }
        
        return [
            'saved_count' => $saved_count,
            'processed_count' => count($batch_products)
        ];
    }
    
    /**
     * دریافت محصولات با Bulk API
     */
    private static function fetch_vendor_products_bulk($meta, $vendor_id, $skus, $batch_number) {
        if (empty($skus)) {
            return [];
        }
        
        $api_url = trailingslashit($meta['url']) . 'wp-json/wc/v3/products';
        $auth = base64_encode($meta['key'] . ':' . $meta['secret']);
        
        // روش اصلی: درخواست گروهی
        $sku_string = implode(',', array_map('urlencode', $skus));
        $request_url = add_query_arg([
            'sku' => $sku_string,
            'per_page' => count($skus)
        ], $api_url);
        
        $response = wp_remote_get($request_url, [
            'headers' => [
                'Authorization' => 'Basic ' . $auth,
                'User-Agent' => 'VendorSync/2.0'
            ],
            'timeout' => 25,
        ]);
        
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $data = json_decode(wp_remote_retrieve_body($response), true);
            if (is_array($data) && !empty($data)) {
                Vendor_Logger::log_debug("بسته {$batch_number}: " . count($data) . " محصول از طریق API گروهی پیدا شد", $vendor_id);
                return $data;
            }
        }
        
        // Fallback: درخواست‌های تکی
        $error_msg = is_wp_error($response) ? $response->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code($response);
        Vendor_Logger::log_warning("API گروهی شکست خورد: {$error_msg}, استفاده از روش جایگزین", null, $vendor_id);
        
        return self::fetch_individual_skus($meta, $vendor_id, $skus, $api_url, $auth);
    }
    
    /**
     * Fallback: دریافت تکی محصولات
     */
    private static function fetch_individual_skus($meta, $vendor_id, $skus, $api_url, $auth) {
        $products = [];
        
        foreach ($skus as $sku) {
            $clean_sku = trim($sku);
            
            $response = wp_remote_get(add_query_arg('sku', $clean_sku, $api_url), [
                'headers' => [
                    'Authorization' => 'Basic ' . $auth,
                    'User-Agent' => 'VendorSync/2.0'
                ],
                'timeout' => 15,
            ]);
            
            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $data = json_decode(wp_remote_retrieve_body($response), true);
                if (!empty($data) && isset($data[0])) {
                    $products[] = $data[0];
                }
            }
            
            // تاخیر کم بین درخواست‌ها
            usleep(100000); // 0.1 ثانیه
        }
        
        Vendor_Logger::log_debug("روش جایگزین: " . count($products) . " محصول پیدا شد", $vendor_id);
        
        return $products;
    }
    
    /**
     * اجرای به‌روزرسانی‌های دسته‌ای
     */
    private static function execute_batch_updates($batch_updates, $vendor_id) {
        foreach ($batch_updates as $update) {
            $product_id = $update['product_id'];
            $raw_price = $update['raw_price'];
            $sku = $update['sku'];
            
            // ذخیره مستقیم متا - سریع و سبک
            $saved = update_post_meta($product_id, '_seller_list_price', $raw_price);
            update_post_meta($product_id, '_vendor_last_sync', current_time('mysql'));
            
            if ($saved === false) {
                Vendor_Logger::log_error("خطا در ذخیره قیمت برای محصول: {$product_id}", $product_id, $vendor_id);
            }
            
            // لاگ موفقیت فعلاً غیرفعال - در صورت نیاز می‌تواند فعال شود
            // Vendor_Logger::log_success(
            //     $product_id, 
            //     'price_saved', 
            //     $vendor_id, 
            //     "قیمت ذخیره شد: {$raw_price} برای SKU: {$sku}"
            // );
        }
    }
    
    /**
     * استخراج قیمت خام
     */
    private static function extract_raw_price($vendor_product, $meta, $vendor_id) {
        $price_meta_key = $meta['price_meta_key'];
        $cooperation_price = 0;
        
        // استخراج قیمت از متادیتا
        if (isset($vendor_product['meta_data'])) {
            foreach ($vendor_product['meta_data'] as $m) {
                if ($m['key'] === $price_meta_key && !empty($m['value'])) {
                    $cooperation_price = floatval($m['value']);
                    break;
                }
            }
        }
        
        // Fallback به قیمت معمولی
        if (!$cooperation_price && isset($vendor_product['price'])) {
            $cooperation_price = floatval($vendor_product['price']);
        }
        
        // تبدیل ریال به تومان اگر نیاز باشد
        if ($meta['currency'] === 'rial' && $cooperation_price > 0) {
            $cooperation_price = $cooperation_price / 10;
        }
        
        return $cooperation_price;
    }
    
    /**
     * دریافت محصولات - نسخه بهینه‌شده برای چند برند
     */
    private static function get_local_products_optimized($brand_ids, $vendor_id) {
        global $wpdb;
        
        // پایه کوئری مشترک
        $base_sql = "SELECT p.ID as id, pm.meta_value as sku 
                    FROM {$wpdb->posts} p 
                    INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
                    WHERE p.post_type = 'product' 
                    AND p.post_status = 'publish' 
                    AND pm.meta_key = '_sku' 
                    AND pm.meta_value != '' 
                    AND p.post_author = %d";
        
        $params = [$vendor_id];
        
        // اگر برندی انتخاب نشده
        if (empty($brand_ids)) {
            $sql = $base_sql . " ORDER BY p.ID ASC";
            return $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
        }
        
        // اگر برند انتخاب شده - کوئری بهینه‌شده
        $placeholders = implode(',', array_fill(0, count($brand_ids), '%d'));
        $params = array_merge($params, $brand_ids);
        
        $sql = "SELECT DISTINCT p.ID as id, pm.meta_value as sku 
                FROM {$wpdb->posts} p 
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
                INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id 
                WHERE p.post_type = 'product' 
                AND p.post_status = 'publish' 
                AND pm.meta_key = '_sku' 
                AND pm.meta_value != '' 
                AND p.post_author = %d
                AND tr.term_taxonomy_id IN ({$placeholders})
                ORDER BY p.ID ASC";
        
        return $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
    }
    
    /**
     * پاکسازی حافظه
     */
    private static function cleanup_memory() {
        wp_cache_flush();
        gc_collect_cycles();
    }
}