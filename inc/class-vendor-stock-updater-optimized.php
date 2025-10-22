<?php
if (!defined('ABSPATH')) exit;

class Vendor_Stock_Updater_Optimized {
    
    private static $batch_size = 30;
    private static $api_delay = 100000;
    
    public static function update_stocks($vendor_id, $cat_id) {
        $start_time = microtime(true);
        
        try {
            $meta = Vendor_Meta_Handler::get_vendor_meta($vendor_id);
            
            // تنظیمات بهینه‌سازی
            set_time_limit(600);
            ini_set('memory_limit', '512M');
            wp_suspend_cache_addition(true);
            wp_defer_term_counting(true);
            
            Vendor_Logger::log_info("🚀 Starting stock update for vendor {$vendor_id}", $vendor_id);
            
            // دریافت محصولات محلی بر اساس نویسنده
            $local_products = self::get_local_products_by_author($vendor_id, $cat_id);
            
            if (empty($local_products)) {
                throw new Exception('هیچ محصولی برای این فروشنده یافت نشد.');
            }
            
            Vendor_Logger::log_info("📦 Found " . count($local_products) . " local products for vendor {$vendor_id}", $vendor_id);
            
            // پردازش با Bulk API
            $result = self::process_with_bulk_api($meta, $vendor_id, $local_products);
            
            $total_time = round(microtime(true) - $start_time, 2);
            Vendor_Logger::log_success(
                0, 
                'stock_update_completed', 
                $vendor_id, 
                "✅ Stock update completed: {$result['updated_count']} updated from {$result['processed_count']} processed in {$total_time}s"
            );
            
            return $result['updated_count'];
            
        } catch (Exception $e) {
            Vendor_Logger::log_error("Stock update failed: " . $e->getMessage(), null, $vendor_id);
            throw $e;
        } finally {
            // بازگردانی تنظیمات
            wp_defer_term_counting(false);
            self::cleanup_memory();
        }
    }
    
    /**
     * دریافت محصولات بر اساس نویسنده
     */
    private static function get_local_products_by_author($vendor_id, $cat_id) {
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
        
        $results = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
        
        Vendor_Logger::log_debug("Local products query returned " . count($results) . " results", null, $vendor_id);
        
        return $results;
    }
    
    /**
     * پردازش با Bulk API
     */
    private static function process_with_bulk_api($meta, $vendor_id, $local_products) {
        $total_updated = 0;
        $total_processed = 0;
        $total_batches = ceil(count($local_products) / self::$batch_size);
        
        Vendor_Logger::log_info("🔄 Processing in {$total_batches} batches", $vendor_id);
        
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
                Vendor_Logger::log_warning("Batch {$batch_number}: No valid SKUs found", null, $vendor_id);
                continue;
            }
            
            Vendor_Logger::log_debug("Batch {$batch_number}: Processing SKUs - " . implode(', ', $batch_skus), null, $vendor_id);
            
            // دریافت محصولات فروشنده با Bulk API
            $vendor_products = self::fetch_vendor_products_bulk($meta, $vendor_id, $batch_skus, $batch_number);
            
            // پردازش و بروزرسانی
            $batch_updated = self::process_batch_updates($vendor_products, $product_sku_map, $meta, $vendor_id);
            $total_updated += $batch_updated;
            $total_processed += count($batch);
            
            Vendor_Logger::log_info("✅ Batch {$batch_number}: {$batch_updated}/" . count($batch) . " updated", $vendor_id);
            
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
     * پردازش بروزرسانی‌های batch
     */
    private static function process_batch_updates($vendor_products, $product_sku_map, $meta, $vendor_id) {
    $updated_count = 0;
    $batch_updates = [];
    
    Vendor_Logger::log_info("🔄 Starting batch processing with " . count($vendor_products) . " vendor products", $vendor_id);
    
    // 🔥 لاگ تمام محصولات فروشنده
    foreach ($vendor_products as $index => $vendor_product) {
        $sku = $vendor_product['sku'] ?? 'NO_SKU';
        $stock_qty = $vendor_product['stock_quantity'] ?? 'NULL';
        $stock_status = $vendor_product['stock_status'] ?? 'NULL';
        
        Vendor_Logger::log_info("📦 Vendor Product {$index}: SKU={$sku}, Qty={$stock_qty}, Status={$stock_status}", $vendor_id);
    }
    
    foreach ($vendor_products as $vendor_product) {
        if (!empty($vendor_product['sku'])) {
            $clean_sku = trim($vendor_product['sku']);
            
            if (isset($product_sku_map[$clean_sku])) {
                $product_id = $product_sku_map[$clean_sku];
                
                // 🔥 لاگ اطلاعات محصول محلی
                $current_stock = get_post_meta($product_id, '_stock', true);
                $current_status = get_post_meta($product_id, '_stock_status', true);
                $current_manage = get_post_meta($product_id, '_manage_stock', true);
                
                Vendor_Logger::log_info("🏠 Local Product: ID={$product_id}, Current Stock={$current_stock}, Current Status={$current_status}, Manage={$current_manage}", $vendor_id);
                
                // 🔥 لاگ اطلاعات محصول فروشنده
                $vendor_stock_qty = $vendor_product['stock_quantity'] ?? 'NULL';
                $vendor_stock_status = $vendor_product['stock_status'] ?? 'NULL';
                
                Vendor_Logger::log_info("🛒 Vendor Product: SKU={$clean_sku}, Stock Qty={$vendor_stock_qty}, Stock Status={$vendor_stock_status}", $vendor_id);
                
                // آماده‌سازی داده‌های بروزرسانی
                $update_data = self::prepare_stock_update_data($product_id, $vendor_product, $meta, $vendor_id);
                
                if ($update_data['should_update']) {
                    $batch_updates[] = $update_data;
                    Vendor_Logger::log_info("✅ WILL UPDATE Product {$product_id}", $vendor_id);
                } else {
                    Vendor_Logger::log_info("❌ NO UPDATE Product {$product_id} - Values are the same", $vendor_id);
                }
            } else {
                Vendor_Logger::log_warning("🚫 SKU not found in local products: {$clean_sku}", null, $vendor_id);
            }
        }
        
        // اجرای batch
        if (count($batch_updates) >= 5) {
            $updated_count += self::execute_fast_batch_updates($batch_updates, $vendor_id);
            $batch_updates = [];
        }
    }
    
    // اجرای باقی‌مانده
    if (!empty($batch_updates)) {
        $updated_count += self::execute_fast_batch_updates($batch_updates, $vendor_id);
    }
    
    Vendor_Logger::log_info("🎯 Batch processing completed: {$updated_count} products updated", $vendor_id);
    
    return $updated_count;
}
    
/**
 * آماده‌سازی داده‌های بروزرسانی
 */
private static function prepare_stock_update_data($product_id, $vendor_product, $meta, $vendor_id) {
    // دریافت مقادیر فعلی مستقیماً از دیتابیس
    $current_stock = get_post_meta($product_id, '_stock', true);
    $current_status = get_post_meta($product_id, '_stock_status', true);
    $current_manage_stock = get_post_meta($product_id, '_manage_stock', true);
    
    $new_stock = '';
    $new_status = 'outofstock';
    $new_manage_stock = 'no';
    $should_update = false;
    
    // 🔥 مقدار پیش‌فرض برای stock_type اگر تنظیم نشده
    $stock_type = isset($meta['stock_type']) ? $meta['stock_type'] : 'status';
    
    if ($stock_type === 'managed') {
        // مدیریت عددی موجودی
        $new_stock = intval($vendor_product['stock_quantity'] ?? 0);
        $new_status = ($new_stock > 0) ? 'instock' : 'outofstock';
        $new_manage_stock = 'yes';
        
    } else {
        // 🔥 مدیریت وضعیتی موجودی - stock باید خالی باشد
        $vendor_stock_status = $vendor_product['stock_status'] ?? 'outofstock';
        $new_status = ($vendor_stock_status === 'instock' || $vendor_stock_status === 'onbackorder') ? 'instock' : 'outofstock';
        $new_stock = ''; // 🔥 مهم: خالی بگذاریم نه 1
        $new_manage_stock = 'no';
    }
    
    // بررسی نیاز به بروزرسانی
    if ($current_stock != $new_stock || $current_status != $new_status || $current_manage_stock != $new_manage_stock) {
        $should_update = true;
    }
    
    Vendor_Logger::log_debug("Stock update - Product {$product_id}: Stock '{$current_stock}' → '{$new_stock}', Status '{$current_status}' → '{$new_status}', Manage '{$current_manage_stock}' → '{$new_manage_stock}'", $product_id, $vendor_id);
    
    return [
        'product_id' => $product_id,
        'should_update' => $should_update,
        'meta_updates' => [
            '_stock' => $new_stock, // 🔥 برای status خالی می‌شود
            '_stock_status' => $new_status,
            '_manage_stock' => $new_manage_stock
        ],
        'log_message' => "Stock: '{$current_stock}' → '{$new_stock}', Status: {$current_status} → {$new_status}, Manage: {$current_manage_stock} → {$new_manage_stock}"
    ];
}
    
    /**
     * اجرای بروزرسانی‌های سریع
     */
    private static function execute_fast_batch_updates($batch_updates, $vendor_id) {
        $updated_count = 0;
        
        foreach ($batch_updates as $update) {
            if (!$update['should_update']) {
                continue;
            }
            
            $product_id = $update['product_id'];
            
            try {
                // بروزرسانی مستقیم متاها
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
     * دریافت محصولات با Bulk API
     */
    private static function fetch_vendor_products_bulk($meta, $vendor_id, $skus, $batch_number) {
        $api_url = trailingslashit($meta['url']) . 'wp-json/wc/v3/products';
        $auth = base64_encode($meta['key'] . ':' . $meta['secret']);
        
        Vendor_Logger::log_info("🌐 Batch {$batch_number}: Fetching " . count($skus) . " SKUs with BULK API", $vendor_id);
        
        try {
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
            
            if (is_wp_error($response)) {
                throw new Exception('API Error: ' . $response->get_error_message());
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code !== 200) {
                throw new Exception('HTTP Error: ' . $response_code);
            }
            
            $data = json_decode(wp_remote_retrieve_body($response), true);
            if (!is_array($data)) {
                throw new Exception('Invalid response format');
            }
            
            if (!empty($data)) {
                Vendor_Logger::log_info("📊 Batch {$batch_number}: " . count($data) . " products found via BULK API", $vendor_id);
                return $data;
            } else {
                Vendor_Logger::log_warning("Batch {$batch_number}: No products found via BULK API", null, $vendor_id);
                return [];
            }
            
        } catch (Exception $e) {
            Vendor_Logger::log_error("BULK API failed: " . $e->getMessage(), null, $vendor_id);
            
            // Fallback: درخواست‌های تکی
            Vendor_Logger::log_info("Using fallback method for batch {$batch_number}", $vendor_id);
            return self::fetch_vendor_products_fallback($meta, $vendor_id, $skus, $api_url, $auth);
        }
    }
    
    /**
     * Fallback: درخواست‌های تکی
     */
    private static function fetch_vendor_products_fallback($meta, $vendor_id, $skus, $api_url, $auth) {
        $products = [];
        
        foreach ($skus as $sku) {
            $clean_sku = trim($sku);
            
            try {
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
                        Vendor_Logger::log_debug("Found vendor product for SKU: {$clean_sku}", null, $vendor_id);
                    } else {
                        Vendor_Logger::log_warning("Vendor product not found for SKU: {$clean_sku}", null, $vendor_id);
                    }
                } else {
                    $error_msg = is_wp_error($response) ? $response->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code($response);
                    Vendor_Logger::log_warning("API error for SKU {$clean_sku}: {$error_msg}", null, $vendor_id);
                }
                
            } catch (Exception $e) {
                Vendor_Logger::log_error("Fallback API failed for SKU {$clean_sku}: " . $e->getMessage(), null, $vendor_id);
            }
            
            usleep(50000); // تاخیر کم
        }
        
        Vendor_Logger::log_info("Fallback method found " . count($products) . " products", $vendor_id);
        
        return $products;
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
}