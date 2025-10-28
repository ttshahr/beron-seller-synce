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
            
            Vendor_Logger::log_info("🚀 شروع بروزرسانی موجودی برای فروشنده {$vendor_id}", $vendor_id);
            
            // دریافت محصولات محلی بر اساس نویسنده
            $local_products = self::get_local_products_by_author($vendor_id, $cat_id);
            
            if (empty($local_products)) {
                throw new Exception('هیچ محصولی برای این فروشنده یافت نشد.');
            }
            
            Vendor_Logger::log_info("📦 تعداد {$local_products} محصول محلی برای فروشنده {$vendor_id} پیدا شد", $vendor_id);
            
            // پردازش با Bulk API
            $result = self::process_with_bulk_api($meta, $vendor_id, $local_products);
            
            $total_time = round(microtime(true) - $start_time, 2);
            
            if ($result['updated_count'] > 0) {
                Vendor_Logger::log_success(
                    0, 
                    'stock_update_completed', 
                    $vendor_id, 
                    "✅ بروزرسانی موجودی تکمیل شد: {$result['updated_count']} محصول از {$result['processed_count']} محصول در {$total_time} ثانیه بروز شد"
                );
            } else {
                Vendor_Logger::log_info("ℹ️ هیچ بروزرسانی موجودی لازم نبود. {$result['processed_count']} محصول بررسی شدند", $vendor_id);
            }
            
            return $result['updated_count'];
            
        } catch (Exception $e) {
            Vendor_Logger::log_error("بروزرسانی موجودی شکست خورد: " . $e->getMessage(), null, $vendor_id);
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
        
        Vendor_Logger::log_debug("پیداشدن {$results} محصول محلی برای فروشنده {$vendor_id}" . ($cat_id !== 'all' ? " در دسته‌بندی {$cat_id}" : ""), $vendor_id);
        
        return $results;
    }
    
    /**
     * پردازش با Bulk API
     */
    private static function process_with_bulk_api($meta, $vendor_id, $local_products) {
        $total_updated = 0;
        $total_processed = 0;
        $total_batches = ceil(count($local_products) / self::$batch_size);
        
        Vendor_Logger::log_info("🔄 پردازش در {$total_batches} بسته", $vendor_id);
        
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
                Vendor_Logger::log_warning("بسته {$batch_number}: هیچ SKU معتبری پیدا نشد", null, $vendor_id);
                continue;
            }
            
            Vendor_Logger::log_debug("بسته {$batch_number}: پردازش SKUها - " . implode(', ', array_slice($batch_skus, 0, 5)) . (count($batch_skus) > 5 ? " و " . (count($batch_skus) - 5) . " مورد دیگر" : ""), null, $vendor_id);
            
            // دریافت محصولات فروشنده با Bulk API
            $vendor_products = self::fetch_vendor_products_bulk($meta, $vendor_id, $batch_skus, $batch_number);
            
            // پردازش و بروزرسانی
            $batch_updated = self::process_batch_updates($vendor_products, $product_sku_map, $meta, $vendor_id, $batch_number);
            $total_updated += $batch_updated;
            $total_processed += count($batch);
            
            // فقط اگر بروزرسانی انجام شده باشد لاگ کنیم
            if ($batch_updated > 0) {
                Vendor_Logger::log_info("✅ بسته {$batch_number}: {$batch_updated} محصول از " . count($batch) . " محصول بروز شد", $vendor_id);
            } else {
                Vendor_Logger::log_debug("ℹ️ بسته {$batch_number}: هیچ بروزرسانی لازم نبود. " . count($batch) . " محصول بررسی شدند", $vendor_id);
            }
            
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
    private static function process_batch_updates($vendor_products, $product_sku_map, $meta, $vendor_id, $batch_number) {
        $updated_count = 0;
        $batch_updates = [];
        $not_found_skus = [];
        
        Vendor_Logger::log_debug("پردازش بسته {$batch_number} با " . count($vendor_products) . " محصول فروشنده", $vendor_id);
        
        foreach ($vendor_products as $vendor_product) {
            if (!empty($vendor_product['sku'])) {
                $clean_sku = trim($vendor_product['sku']);
                
                if (isset($product_sku_map[$clean_sku])) {
                    $product_id = $product_sku_map[$clean_sku];
                    
                    // آماده‌سازی داده‌های بروزرسانی
                    $update_data = self::prepare_stock_update_data($product_id, $vendor_product, $meta, $vendor_id);
                    
                    if ($update_data['should_update']) {
                        $batch_updates[] = $update_data;
                    }
                } else {
                    $not_found_skus[] = $clean_sku;
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
        
        // لاگ SKUهای پیدا نشده (فقط اگر وجود داشته باشند)
        if (!empty($not_found_skus)) {
            Vendor_Logger::log_warning(
                "بسته {$batch_number}: " . count($not_found_skus) . " SKU در محصولات محلی پیدا نشدند: " . 
                implode(', ', array_slice($not_found_skus, 0, 5)) . 
                (count($not_found_skus) > 5 ? " و " . (count($not_found_skus) - 5) . " مورد دیگر" : ""), 
                null, 
                $vendor_id
            );
        }
        
        Vendor_Logger::log_debug("پردازش بسته {$batch_number} تکمیل شد: {$updated_count} محصول بروز شد", $vendor_id);
        
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
        
        // مقدار پیش‌فرض برای stock_type اگر تنظیم نشده
        $stock_type = isset($meta['stock_type']) ? $meta['stock_type'] : 'status';
        
        if ($stock_type === 'managed') {
            // مدیریت عددی موجودی
            $new_stock = intval($vendor_product['stock_quantity'] ?? 0);
            $new_status = ($new_stock > 0) ? 'instock' : 'outofstock';
            $new_manage_stock = 'yes';
        } else {
            // مدیریت وضعیتی موجودی - stock باید خالی باشد
            $vendor_stock_status = $vendor_product['stock_status'] ?? 'outofstock';
            $new_status = ($vendor_stock_status === 'instock' || $vendor_stock_status === 'onbackorder') ? 'instock' : 'outofstock';
            $new_stock = ''; // مهم: خالی بگذاریم نه 1
            $new_manage_stock = 'no';
        }
        
        // بررسی نیاز به بروزرسانی
        if ($current_stock != $new_stock || $current_status != $new_status || $current_manage_stock != $new_manage_stock) {
            $should_update = true;
            
            Vendor_Logger::log_debug(
                "بروزرسانی موجودی - محصول {$product_id}: " .
                "موجودی '{$current_stock}' → '{$new_stock}', " .
                "وضعیت '{$current_status}' → '{$new_status}', " .
                "مدیریت '{$current_manage_stock}' → '{$new_manage_stock}'", 
                $product_id, 
                $vendor_id
            );
        }
        
        return [
            'product_id' => $product_id,
            'should_update' => $should_update,
            'meta_updates' => [
                '_stock' => $new_stock,
                '_stock_status' => $new_status,
                '_manage_stock' => $new_manage_stock
            ],
            'log_message' => "موجودی: '{$current_stock}' → '{$new_stock}', وضعیت: {$current_status} → {$new_status}, مدیریت: {$current_manage_stock} → {$new_manage_stock}"
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
                    "بروزرسانی سریع موجودی شکست خورد: " . $e->getMessage(), 
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
        
        //Vendor_Logger::log_info("🌐 بسته {$batch_number}: دریافت " . count($skus) . " SKU با API گروهی", $vendor_id);
        
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
                throw new Exception('خطای API: ' . $response->get_error_message());
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code !== 200) {
                throw new Exception('خطای HTTP: ' . $response_code);
            }
            
            $data = json_decode(wp_remote_retrieve_body($response), true);
            if (!is_array($data)) {
                throw new Exception('فرمت پاسخ نامعتبر است');
            }
            
            //Vendor_Logger::log_info("📊 بسته {$batch_number}: " . count($data) . " محصول از طریق API گروهی پیدا شد", $vendor_id);
            return $data;
            
        } catch (Exception $e) {
            Vendor_Logger::log_error("API گروهی شکست خورد: " . $e->getMessage(), null, $vendor_id);
            
            // Fallback: درخواست‌های تکی
            Vendor_Logger::log_info("استفاده از روش جایگزین برای بسته {$batch_number}", $vendor_id);
            return self::fetch_vendor_products_fallback($meta, $vendor_id, $skus, $api_url, $auth);
        }
    }
    
    /**
     * Fallback: درخواست‌های تکی
     */
    private static function fetch_vendor_products_fallback($meta, $vendor_id, $skus, $api_url, $auth) {
        $products = [];
        $failed_skus = [];
        
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
                    } else {
                        $failed_skus[] = $clean_sku;
                    }
                } else {
                    $failed_skus[] = $clean_sku;
                }
                
            } catch (Exception $e) {
                $failed_skus[] = $clean_sku;
            }
            
            usleep(50000); // تاخیر کم
        }
        
        // لاگ خلاصه نتایج fallback
        if (!empty($failed_skus)) {
            Vendor_Logger::log_warning(
                "روش جایگزین: " . count($failed_skus) . " SKU پیدا نشدند: " . 
                implode(', ', array_slice($failed_skus, 0, 5)) . 
                (count($failed_skus) > 5 ? " و " . (count($failed_skus) - 5) . " مورد دیگر" : ""), 
                null, 
                $vendor_id
            );
        }
        
        Vendor_Logger::log_info("روش جایگزین: " . count($products) . " محصول پیدا شد", $vendor_id);
        
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