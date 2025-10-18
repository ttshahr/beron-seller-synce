<?php
if (!defined('ABSPATH')) exit;

class Vendor_Raw_Price_Saver_Optimized {
    
    public static function save_raw_prices_optimized($vendor_id, $cat_id) {
        $meta = Vendor_Meta_Handler::get_vendor_meta($vendor_id);
        
        // تنظیمات سرعت و حافظه
        set_time_limit(300); // کاهش از 1000 به 300 ثانیه
        ini_set('memory_limit', '512M'); // کاهش از 2048M به 512M
        wp_suspend_cache_addition(true);
        
        Vendor_Logger::log_info("Starting optimized raw price sync for vendor {$vendor_id}", $vendor_id);
        
        // دریافت محصولات محلی (بهینه‌شده)
        $local_products = self::get_local_products_optimized($cat_id, $vendor_id);
        
        if (empty($local_products)) {
            Vendor_Logger::log_warning("No local products found for price sync", null, $vendor_id);
            throw new Exception('هیچ محصولی برای این فروشنده یافت نشد.');
        }
        
        Vendor_Logger::log_info("Found " . count($local_products) . " local products to process", $vendor_id);
        
        // دریافت محصولات از API به صورت دسته‌ای
        $vendor_products_map = self::get_vendor_products_batch($meta, $vendor_id, $local_products);
        
        if (empty($vendor_products_map)) {
            Vendor_Logger::log_error("No matching products found in vendor API", null, $vendor_id);
            throw new Exception('هیچ محصول مطابقی در فروشنده یافت نشد.');
        }
        
        Vendor_Logger::log_info("Vendor products map created with " . count($vendor_products_map) . " matched products", $vendor_id);
        
        // پردازش و ذخیره قیمت‌ها
        $result = self::process_and_save_prices($local_products, $vendor_products_map, $meta, $vendor_id);
        
        Vendor_Logger::log_success(
            0, 
            'price_sync_completed', 
            $vendor_id, 
            "Price sync completed: {$result['saved_count']} products saved from {$result['processed_count']} processed"
        );
        
        return $result['saved_count'];
    }
    
    /**
     * دریافت محصولات محلی بهینه‌شده
     */
    private static function get_local_products_optimized($cat_id, $vendor_id) {
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
        
        // فیلتر بر اساس دسته
        if ($cat_id !== 'all') {
            $sql .= " AND p.ID IN (
                SELECT object_id FROM {$wpdb->term_relationships} 
                WHERE term_taxonomy_id = %d
            )";
            $params[] = intval($cat_id);
        }
        
        $sql .= " ORDER BY p.ID ASC";
        
        $results = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
        
        Vendor_Logger::log_info("Local products query returned " . count($results) . " results", $vendor_id);
        
        return $results;
    }
    
    /**
     * دریافت محصولات فروشنده به صورت دسته‌ای
     */
    private static function get_vendor_products_batch($meta, $vendor_id, $local_products) {
        if (empty($local_products)) {
            return [];
        }
        
        // استخراج SKUهای منحصربه‌فرد
        $local_skus = [];
        foreach ($local_products as $product) {
            if (!empty($product['sku'])) {
                $clean_sku = trim($product['sku']);
                $local_skus[$clean_sku] = $clean_sku; // استفاده از کلید برای منحصربه‌فرد بودن
            }
        }
        
        if (empty($local_skus)) {
            Vendor_Logger::log_warning("No valid local SKUs found", null, $vendor_id);
            return [];
        }
        
        Vendor_Logger::log_info("Fetching " . count($local_skus) . " unique SKUs from vendor API", $vendor_id);
        
        // دریافت محصولات به صورت دسته‌ای
        $vendor_products = self::fetch_vendor_products_batch($meta, $vendor_id, array_values($local_skus));
        
        // ایجاد map برای دسترسی سریع
        $products_map = [];
        foreach ($vendor_products as $product) {
            if (!empty($product['sku'])) {
                $clean_sku = trim($product['sku']);
                $products_map[$clean_sku] = $product;
            }
        }
        
        Vendor_Logger::log_info("Vendor products map created with " . count($products_map) . " matched products", $vendor_id);
        
        return $products_map;
    }
    
    /**
     * دریافت دسته‌ای محصولات از API
     */
    private static function fetch_vendor_products_batch($meta, $vendor_id, $skus) {
        if (empty($skus)) {
            return [];
        }
        
        $api_url = trailingslashit($meta['url']) . 'wp-json/wc/v3/products';
        $auth = base64_encode($meta['key'] . ':' . $meta['secret']);
        
        $vendor_products = [];
        $batch_size = 20; // کاهش batch size برای جلوگیری از timeout
        
        Vendor_Logger::log_info("Fetching vendor products in batches of {$batch_size}", $vendor_id);
        
        foreach (array_chunk($skus, $batch_size) as $batch_index => $sku_batch) {
            Vendor_Logger::log_info("Processing batch " . ($batch_index + 1) . " with " . count($sku_batch) . " SKUs", $vendor_id);
            
            $batch_products = self::fetch_single_batch($meta, $vendor_id, $sku_batch, $api_url, $auth);
            $vendor_products = array_merge($vendor_products, $batch_products);
            
            // تاخیر بین batch ها
            if (count($skus) > $batch_size) {
                sleep(2); // افزایش تاخیر برای کاهش فشار
            }
        }
        
        return $vendor_products;
    }
    
    /**
     * دریافت یک دسته از محصولات
     */
    private static function fetch_single_batch($meta, $vendor_id, $skus, $api_url, $auth) {
        $products = [];
        $found_count = 0;
        
        // استفاده از include برای دریافت چند محصول در یک درخواست
        $sku_string = implode(',', array_map('urlencode', $skus));
        $request_url = add_query_arg([
            'sku' => $sku_string,
            'per_page' => count($skus)
        ], $api_url);
        
        $response = wp_remote_get($request_url, [
            'headers' => [
                'Authorization' => 'Basic ' . $auth,
                'User-Agent' => 'VendorSync/1.0'
            ],
            'timeout' => 30,
        ]);
        
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $data = json_decode(wp_remote_retrieve_body($response), true);
            if (is_array($data) && !empty($data)) {
                $products = $data;
                $found_count = count($data);
                Vendor_Logger::log_info("Batch API success: {$found_count} products found", $vendor_id);
            } else {
                Vendor_Logger::log_warning("Batch API returned empty data for SKUs: " . implode(', ', $skus), null, $vendor_id);
            }
        } else {
            $error_msg = is_wp_error($response) ? $response->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code($response);
            Vendor_Logger::log_error("Batch API failed: {$error_msg}", null, $vendor_id);
            
            // Fallback: درخواست تکی برای هر SKU
            $products = self::fetch_individual_skus($meta, $vendor_id, $skus, $api_url, $auth);
        }
        
        return $products;
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
                    'User-Agent' => 'VendorSync/1.0'
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
            
            // تاخیر کوچک بین درخواست‌ها
            usleep(500000); // 0.5 ثانیه
        }
        
        return $products;
    }
    
    /**
     * پردازش و ذخیره قیمت‌ها
     */
    private static function process_and_save_prices($local_products, $vendor_products_map, $meta, $vendor_id) {
        $saved_count = 0;
        $processed_count = 0;
        $batch_updates = [];
        
        foreach ($local_products as $index => $local_product) {
            $processed_count++;
            $sku = $local_product['sku'];
            $product_id = $local_product['id'];
            
            if (isset($vendor_products_map[$sku])) {
                $vendor_product = $vendor_products_map[$sku];
                $raw_price = self::extract_raw_price($vendor_product, $meta, $vendor_id);
                
                if ($raw_price > 0) {
                    // ذخیره در batch
                    $batch_updates[] = [
                        'product_id' => $product_id,
                        'sku' => $sku,
                        'raw_price' => $raw_price
                    ];
                    
                    $saved_count++;
                    
                    // اجرای batch هر 50 محصول
                    if (count($batch_updates) >= 50) {
                        self::execute_batch_updates($batch_updates, $vendor_id);
                        $batch_updates = [];
                        
                        // پاکسازی حافظه
                        wp_cache_flush();
                        gc_collect_cycles();
                    }
                } else {
                    Vendor_Logger::log_warning("Invalid price: {$raw_price} for SKU: {$sku}", $product_id, $vendor_id);
                }
            } else {
                Vendor_Logger::log_warning("SKU not found in vendor: {$sku}", $product_id, $vendor_id);
            }
            
            // گزارش پیشرفت
            if ($processed_count % 50 === 0) {
                Vendor_Logger::log_info(
                    "Progress: {$processed_count}/" . count($local_products) . " processed, {$saved_count} saved", 
                    $vendor_id
                );
            }
        }
        
        // اجرای باقی‌مانده batch
        if (!empty($batch_updates)) {
            self::execute_batch_updates($batch_updates, $vendor_id);
        }
        
        return [
            'saved_count' => $saved_count,
            'processed_count' => $processed_count
        ];
    }
    
    /**
     * اجرای به‌روزرسانی‌های دسته‌ای
     */
    private static function execute_batch_updates($batch_updates, $vendor_id) {
        global $wpdb;
        
        foreach ($batch_updates as $update) {
            $product_id = $update['product_id'];
            $raw_price = $update['raw_price'];
            $sku = $update['sku'];
            
            // فقط ذخیره در _seller_list_price
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
        
        Vendor_Logger::log_info("Batch update completed for " . count($batch_updates) . " products", $vendor_id);
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
}