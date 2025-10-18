<?php
if (!defined('ABSPATH')) exit;

class Vendor_Stock_Updater_Optimized {
    
    private static $batch_size = 30;
    private static $api_delay = 100000;
    
    public static function update_stocks($vendor_id, $cat_id) {
        $start_time = microtime(true);
        $meta = Vendor_Meta_Handler::get_vendor_meta($vendor_id);
        
        set_time_limit(600);
        ini_set('memory_limit', '512M');
        wp_suspend_cache_addition(true);
        wp_defer_term_counting(true);
        
        Vendor_Logger::log_info("🚀 Starting ULTRA-OPTIMIZED stock update for vendor {$vendor_id}", $vendor_id);
        
        try {
            // دریافت محصولات محلی
            $local_products = self::get_local_products_optimized($vendor_id, $cat_id);
            
            if (empty($local_products)) {
                throw new Exception('هیچ محصولی برای این فروشنده یافت نشد.');
            }
            
            Vendor_Logger::log_info("📦 Found " . count($local_products) . " local products", $vendor_id);
            
            // پردازش با Bulk API مانند price updater
            $result = self::process_with_bulk_api($meta, $vendor_id, $local_products);
            
            $total_time = round(microtime(true) - $start_time, 2);
            Vendor_Logger::log_success(
                0, 
                'stock_update_completed', 
                $vendor_id, 
                "✅ Stock update completed: {$result['updated_count']} updated from {$result['processed_count']} processed in {$total_time}s"
            );
            
            return $result['updated_count'];
            
        } finally {
            wp_defer_term_counting(false);
            self::cleanup_memory();
        }
    }
    
    /**
     * پردازش با Bulk API - شبیه price updater
     */
    private static function process_with_bulk_api($meta, $vendor_id, $local_products) {
        $total_updated = 0;
        $total_processed = 0;
        $total_batches = ceil(count($local_products) / self::$batch_size);
        
        Vendor_Logger::log_info("🔄 Processing in {$total_batches} batches with BULK API", $vendor_id);
        
        foreach (array_chunk($local_products, self::$batch_size) as $batch_index => $batch) {
            $batch_number = $batch_index + 1;
            
            // استخراج SKUهای این batch
            $batch_skus = [];
            $product_sku_map = [];
            
            foreach ($batch as $product) {
                if (!empty($product['sku'])) {
                    $clean_sku = trim($product['sku']);
                    $batch_skus[] = $clean_sku;
                    $product_sku_map[$clean_sku] = $product['ID'];
                }
            }
            
            if (empty($batch_skus)) {
                continue;
            }
            
            // دریافت محصولات فروشنده با Bulk API
            $vendor_products = self::fetch_vendor_products_bulk($meta, $vendor_id, $batch_skus, $batch_number);
            
            // پردازش و بروزرسانی سریع
            $batch_updated = self::process_batch_updates_fast($vendor_products, $product_sku_map, $meta, $vendor_id);
            $total_updated += $batch_updated;
            $total_processed += count($batch);
            
            Vendor_Logger::log_info("✅ Batch {$batch_number}: {$batch_updated}/" . count($batch) . " updated", $vendor_id);
            
            // پاکسازی حافظه
            self::cleanup_memory();
            
            if ($batch_number < $total_batches) {
                usleep(self::$api_delay);
            }
        }
        
        return [
            'updated_count' => $total_updated,
            'processed_count' => $total_processed
        ];
    }
    
    /**
     * دریافت محصولات با Bulk API - مانند price updater
     */
    private static function fetch_vendor_products_bulk($meta, $vendor_id, $skus, $batch_number) {
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
                'User-Agent' => 'BeronSellerSync/3.1.0'
            ],
            'timeout' => 30,
        ]);
        
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $data = json_decode(wp_remote_retrieve_body($response), true);
            if (is_array($data) && !empty($data)) {
                Vendor_Logger::log_info("📊 Batch {$batch_number}: " . count($data) . " products found via BULK API", $vendor_id);
                return $data;
            }
        }
        
        // Fallback: درخواست‌های تکی
        Vendor_Logger::log_warning("BULK API failed, using fallback method", null, $vendor_id);
        return self::fetch_vendor_products_fallback($meta, $vendor_id, $skus, $api_url, $auth);
    }
    
    /**
     * Fallback: درخواست‌های تکی
     */
    private static function fetch_vendor_products_fallback($meta, $vendor_id, $skus, $api_url, $auth) {
        $products = [];
        
        foreach ($skus as $sku) {
            $clean_sku = trim($sku);
            
            $response = wp_remote_get(add_query_arg('sku', $clean_sku, $api_url), [
                'headers' => [
                    'Authorization' => 'Basic ' . $auth,
                    'User-Agent' => 'BeronSellerSync/3.1.0'
                ],
                'timeout' => 15,
            ]);
            
            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $data = json_decode(wp_remote_retrieve_body($response), true);
                if (!empty($data) && isset($data[0])) {
                    $products[] = $data[0];
                }
            }
            
            usleep(50000); // تاخیر کم
        }
        
        return $products;
    }
    
    /**
     * پردازش سریع batch - بدون آبجکت‌های غیرضروری
     */
    private static function process_batch_updates_fast($vendor_products, $product_sku_map, $meta, $vendor_id) {
        $updated_count = 0;
        $batch_updates = [];
        
        foreach ($vendor_products as $vendor_product) {
            if (!empty($vendor_product['sku'])) {
                $clean_sku = trim($vendor_product['sku']);
                
                if (isset($product_sku_map[$clean_sku])) {
                    $product_id = $product_sku_map[$clean_sku];
                    
                    // آماده‌سازی داده‌های بروزرسانی
                    $update_data = self::prepare_stock_update_data($product_id, $vendor_product, $meta);
                    
                    if ($update_data['should_update']) {
                        $batch_updates[] = $update_data;
                    }
                }
            }
            
            // اجرای batch هر 20 آیتم
            if (count($batch_updates) >= 20) {
                $updated_count += self::execute_fast_batch_updates($batch_updates, $vendor_id);
                $batch_updates = [];
            }
        }
        
        // اجرای باقی‌مانده
        if (!empty($batch_updates)) {
            $updated_count += self::execute_fast_batch_updates($batch_updates, $vendor_id);
        }
        
        return $updated_count;
    }
    
    /**
     * آماده‌سازی داده‌های بروزرسانی - سبک
     */
    private static function prepare_stock_update_data($product_id, $vendor_product, $meta) {
        // دریافت مقادیر فعلی مستقیماً از دیتابیس
        $current_stock = get_post_meta($product_id, '_stock', true);
        $current_status = get_post_meta($product_id, '_stock_status', true);
        
        $new_stock = 0;
        $new_status = 'outofstock';
        $should_update = false;
        
        if ($meta['stock_type'] === 'managed') {
            $new_stock = intval($vendor_product['stock_quantity'] ?? 0);
            $new_status = ($new_stock > 0) ? 'instock' : 'outofstock';
            
            if ($current_stock != $new_stock || $current_status != $new_status) {
                $should_update = true;
            }
        } else {
            $new_status = (($vendor_product['stock_status'] ?? '') === 'instock') ? 'instock' : 'outofstock';
            if ($current_status != $new_status) {
                $should_update = true;
            }
        }
        
        return [
            'product_id' => $product_id,
            'should_update' => $should_update,
            'meta_updates' => [
                '_stock' => $new_stock,
                '_stock_status' => $new_status,
                '_manage_stock' => ($meta['stock_type'] === 'managed') ? 'yes' : 'no'
            ],
            'log_message' => "Stock: {$current_stock} → {$new_stock}, Status: {$current_status} → {$new_status}"
        ];
    }
    
    /**
     * اجرای بروزرسانی‌های سریع - مستقیماً با متا
     */
    private static function execute_fast_batch_updates($batch_updates, $vendor_id) {
        global $wpdb;
        
        $updated_count = 0;
        
        foreach ($batch_updates as $update) {
            if (!$update['should_update']) continue;
            
            $product_id = $update['product_id'];
            
            try {
                // بروزرسانی مستقیم متاها - بسیار سریع‌تر از WC_Product
                foreach ($update['meta_updates'] as $meta_key => $meta_value) {
                    update_post_meta($product_id, $meta_key, $meta_value);
                }
                
                // بروزرسانی زمان سینک
                update_post_meta($product_id, '_vendor_stock_last_sync', current_time('mysql'));
                
                $updated_count++;
                Vendor_Logger::log_success(
                    $product_id, 
                    'stock_updated', 
                    $vendor_id, 
                    $update['log_message']
                );
                
            } catch (Exception $e) {
                Vendor_Logger::log_error(
                    "Fast stock update failed: " . $e->getMessage(), 
                    $product_id, 
                    $vendor_id
                );
            }
        }
        
        return $updated_count;
    }
    
    /**
     * دریافت محصولات محلی بهینه‌شده
     */
    private static function get_local_products_optimized($vendor_id, $cat_id) {
        global $wpdb;
        
        $sql = "SELECT p.ID, pm.meta_value as sku 
                FROM {$wpdb->posts} p 
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
                WHERE p.post_type = 'product' 
                AND p.post_status = 'publish' 
                AND p.post_author = %d 
                AND pm.meta_key = '_sku' 
                AND pm.meta_value != ''";
        
        $params = [$vendor_id];
        
        if ($cat_id !== 'all') {
            $sql .= " AND p.ID IN (
                SELECT object_id FROM {$wpdb->term_relationships} 
                WHERE term_taxonomy_id = %d
            )";
            $params[] = intval($cat_id);
        }
        
        $sql .= " ORDER BY p.ID ASC";
        
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