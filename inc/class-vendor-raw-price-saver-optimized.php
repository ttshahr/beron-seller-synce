<?php
if (!defined('ABSPATH')) exit;

class Vendor_Raw_Price_Saver_Optimized {
    
    private static $batch_size = 50;
    private static $api_delay = 100000; // 0.1 ثانیه
    
    public static function save_raw_prices_optimized($vendor_id, $brand_id) {
        $meta = Vendor_Meta_Handler::get_vendor_meta($vendor_id);
        
        // تنظیمات بهینه‌شده سرعت و حافظه
        set_time_limit(300);
        ini_set('memory_limit', '256M');
        wp_suspend_cache_addition(true);
        wp_defer_term_counting(true);
        
        Vendor_Logger::log_info("🚀 Starting ULTRA-OPTIMIZED price sync for vendor {$vendor_id} with brand filter: {$brand_id}", $vendor_id);
        
        try {
            // دریافت محصولات محلی
            $local_products = self::get_local_products_optimized($brand_id, $vendor_id);
            
            if (empty($local_products)) {
                Vendor_Logger::log_warning("No local products found for price sync with brand: {$brand_id}", null, $vendor_id);
                throw new Exception('هیچ محصولی برای این فروشنده و برند انتخاب شده یافت نشد.');
            }
            
            Vendor_Logger::log_info("📦 Found " . count($local_products) . " local products to process", $vendor_id);
            
            // پردازش استریمینگ با مدیریت حافظه
            $result = self::process_streaming_updates($meta, $vendor_id, $local_products);
            
            Vendor_Logger::log_success(
                0, 
                'price_sync_completed', 
                $vendor_id, 
                "✅ Price sync completed: {$result['saved_count']} products saved from {$result['processed_count']} processed"
            );
            
            return $result['saved_count'];
            
        } finally {
            // بازگردانی تنظیمات
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
        $total_batches = ceil(count($local_products) / self::$batch_size);
        
        Vendor_Logger::log_info("🔄 Processing in {$total_batches} batches", $vendor_id);
        
        foreach (array_chunk($local_products, self::$batch_size) as $batch_index => $batch) {
            $batch_number = $batch_index + 1;
            
            // پردازش هر batch
            $batch_result = self::process_single_batch($meta, $vendor_id, $batch, $batch_number);
            $total_saved += $batch_result['saved_count'];
            $total_processed += $batch_result['processed_count'];
            
            // پاکسازی حافظه بعد از هر batch
            self::cleanup_memory();
            
            // تاخیر هوشمند - فقط بین batchها
            if ($batch_number < $total_batches) {
                usleep(self::$api_delay);
            }
        }
        
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
        
        Vendor_Logger::log_info("🔍 Batch {$batch_number}: Processing " . count($batch_skus) . " SKUs", $vendor_id);
        
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
                    } else {
                        Vendor_Logger::log_warning("Invalid price: {$raw_price} for SKU: {$clean_sku}", $product_id, $vendor_id);
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
        
        Vendor_Logger::log_info("✅ Batch {$batch_number}: {$saved_count}/" . count($batch_products) . " saved", $vendor_id);
        
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
        
        Vendor_Logger::log_info("🌐 Batch {$batch_number}: Fetching " . count($skus) . " SKUs with BULK API", $vendor_id);
        
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
                Vendor_Logger::log_info("📊 Batch {$batch_number}: " . count($data) . " products found via BULK API", $vendor_id);
                return $data;
            }
        }
        
        // Fallback: درخواست‌های تکی
        $error_msg = is_wp_error($response) ? $response->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code($response);
        Vendor_Logger::log_warning("BULK API failed: {$error_msg}, using fallback", null, $vendor_id);
        
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
                    Vendor_Logger::log_debug("Found vendor product for SKU: {$clean_sku}", null, $vendor_id);
                } else {
                    Vendor_Logger::log_warning("Vendor product not found for SKU: {$clean_sku}", null, $vendor_id);
                }
            } else {
                $error_msg = is_wp_error($response) ? $response->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code($response);
                Vendor_Logger::log_error("API error for SKU {$clean_sku}: {$error_msg}", null, $vendor_id);
            }
            
            // تاخیر کم بین درخواست‌ها
            usleep(100000); // 0.1 ثانیه
        }
        
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
            
            if ($saved !== false) {
                Vendor_Logger::log_success(
                    $product_id, 
                    'price_saved', 
                    $vendor_id, 
                    "Price saved: {$raw_price} for SKU: {$sku}"
                );
            } else {
                Vendor_Logger::log_error("Failed to save price for product: {$product_id}", $product_id, $vendor_id);
            }
        }
        
        Vendor_Logger::log_info("✅ Batch update completed for " . count($batch_updates) . " products", $vendor_id);
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
                    Vendor_Logger::log_debug("Price extracted from meta: {$cooperation_price} with key: {$price_meta_key}", null, $vendor_id);
                    break;
                }
            }
        }
        
        // Fallback به قیمت معمولی
        if (!$cooperation_price && isset($vendor_product['price'])) {
            $cooperation_price = floatval($vendor_product['price']);
            Vendor_Logger::log_debug("Price extracted from regular_price: {$cooperation_price}", null, $vendor_id);
        }
        
        // تبدیل ریال به تومان اگر نیاز باشد
        if ($meta['currency'] === 'rial' && $cooperation_price > 0) {
            $old_price = $cooperation_price;
            $cooperation_price = $cooperation_price / 10;
            Vendor_Logger::log_debug("Currency conversion: {$old_price} rial → {$cooperation_price} toman", null, $vendor_id);
        }
        
        Vendor_Logger::log_debug("Final price: {$cooperation_price}", null, $vendor_id);
        
        return $cooperation_price;
    }
    
    /**
     * دریافت محصولات محلی بهینه‌شده با فیلتر بر اساس برند
     */
    private static function get_local_products_optimized($brand_id, $vendor_id) {
        global $wpdb;
        
        $sql = "SELECT p.ID as id, pm.meta_value as sku 
                FROM {$wpdb->posts} p 
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
                WHERE p.post_type = 'product' 
                AND p.post_status = 'publish' 
                AND pm.meta_key = '_sku' 
                AND pm.meta_value != '' 
                AND p.post_author = %d";
        
        $params = [$vendor_id];
        
        // فیلتر بر اساس برند - تغییر به تکسونومی product_brand
        if ($brand_id !== 'all') {
            $sql .= " AND p.ID IN (
                SELECT object_id FROM {$wpdb->term_relationships} 
                WHERE term_taxonomy_id = %d
            )";
            $params[] = intval($brand_id);
        }
        
        $sql .= " ORDER BY p.ID ASC";
        
        $results = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
        
        Vendor_Logger::log_info("📊 Local products query returned " . count($results) . " results (Brand ID: {$brand_id})", $vendor_id);
        
        return $results;
    }
    
    /**
     * پاکسازی حافظه
     */
    private static function cleanup_memory() {
        wp_cache_flush();
        gc_collect_cycles();
        
        // پاکسازی اضافی برای اطمینان
        if (isset($GLOBALS['wpdb']->queries)) {
            $GLOBALS['wpdb']->queries = [];
        }
    }
    
    /**
     * دریافت گزارش وضعیت
     */
    public static function get_price_sync_report($vendor_id, $brand_id) {
        $local_products = self::get_local_products_optimized($brand_id, $vendor_id);
        
        $report = [
            'total_local_products' => count($local_products),
            'total_local_skus' => count(array_column($local_products, 'sku')),
            'message' => 'برای مشاهده جزئیات کامل، عملیات بروزرسانی قیمت را اجرا کنید.'
        ];
        
        Vendor_Logger::log_info("📈 Price sync report generated for brand: {$brand_id}", $vendor_id);
        
        return $report;
    }
}