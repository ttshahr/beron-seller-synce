<?php
if (!defined('ABSPATH')) exit;

class Vendor_Raw_Price_Saver_Optimized {
    
    public static function save_raw_prices_optimized($vendor_id, $cat_id) {
        $meta = Vendor_Meta_Handler::get_vendor_meta($vendor_id);
        
        set_time_limit(1000);
        ini_set('memory_limit', '2048M');
        wp_suspend_cache_addition(true);
        
        Vendor_Logger::log_info("Starting raw price sync for vendor {$vendor_id}", $vendor_id);
        
        // دریافت محصولات محلی
        $local_products = self::get_local_products_with_sku($cat_id, $vendor_id);
        
        if (empty($local_products)) {
            Vendor_Logger::log_warning("No local products found for price sync", null, $vendor_id);
            throw new Exception('هیچ محصولی برای این فروشنده یافت نشد.');
        }
        
        Vendor_Logger::log_info("Found " . count($local_products) . " local products to process", $vendor_id);
        
        // 🆕 جدید: دریافت فقط محصولات مورد نیاز از API
        $vendor_products_map = self::get_vendor_products_map($meta, $vendor_id, $local_products);
        
        if (empty($vendor_products_map)) {
            Vendor_Logger::log_error("No matching products found in vendor API", null, $vendor_id);
            throw new Exception('هیچ محصول مطابقی در فروشنده یافت نشد.');
        }
        
        Vendor_Logger::log_info("Vendor products map created with " . count($vendor_products_map) . " matched products", $vendor_id);
        
        $saved_count = 0;
        $processed_count = 0;
        
        foreach ($local_products as $index => $local_product) {
            $processed_count++;
            $sku = $local_product['sku'];
            $product_id = $local_product['id'];
            
            if (isset($vendor_products_map[$sku])) {
                $vendor_product = $vendor_products_map[$sku];
                $raw_price = self::extract_raw_price($vendor_product, $meta, $vendor_id);
                
                if ($raw_price > 0) {
                    // ذخیره قیمت‌ها
                    $saved1 = update_post_meta($product_id, '_seller_list_price', $raw_price);
                    $saved2 = update_post_meta($product_id, '_vendor_raw_price', $raw_price);
                    $saved3 = update_post_meta($product_id, '_vendor_last_sync', current_time('mysql'));
                    $saved4 = update_post_meta($product_id, '_vendor_id', $vendor_id);
                    
                    if ($saved1 !== false) {
                        $saved_count++;
                        Vendor_Logger::log_success(
                            $product_id, 
                            'price_saved', 
                            $vendor_id, 
                            "Price saved: {$raw_price} for SKU: {$sku} - Product assigned to vendor"
                        );
                    } else {
                        Vendor_Logger::log_error("Failed to save price for product: {$product_id}", $product_id, $vendor_id);
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
                wp_cache_flush();
                gc_collect_cycles();
            }
        }
        
        Vendor_Logger::log_success(
            0, 
            'price_sync_completed', 
            $vendor_id, 
            "Price sync completed: {$saved_count} products saved from {$processed_count} processed"
        );
        
        return $saved_count;
    }
    
    /**
     * 🆕 جدید: دریافت map محصولات فروشنده (بهینه‌شده)
     */
    private static function get_vendor_products_map($meta, $vendor_id, $local_products) {
        if (empty($local_products)) {
            return [];
        }
        
        Vendor_Logger::log_info("Fetching vendor products for " . count($local_products) . " local products", $vendor_id);
        
        // استخراج SKUهای محلی
        $local_skus = [];
        foreach ($local_products as $product) {
            if (!empty($product['sku'])) {
                $local_skus[] = $product['sku'];
            }
        }
        
        if (empty($local_skus)) {
            Vendor_Logger::log_warning("No local SKUs found", null, $vendor_id);
            return [];
        }
        
        Vendor_Logger::log_info("Found " . count($local_skus) . " local SKUs to check", $vendor_id);
        
        // دریافت فقط محصولات مورد نیاز از API
        $vendor_products = self::get_specific_vendor_products($meta, $vendor_id, $local_skus);
        
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
     * 🆕 جدید: دریافت محصولات خاص از API بر اساس SKU
     */
    private static function get_specific_vendor_products($meta, $vendor_id, $skus) {
        if (empty($skus)) {
            return [];
        }
        
        $vendor_products = [];
        $batch_size = 50;
        
        Vendor_Logger::log_info("Fetching " . count($skus) . " specific products from vendor API", $vendor_id);
        
        foreach (array_chunk($skus, $batch_size) as $batch_index => $sku_batch) {
            Vendor_Logger::log_info("Processing SKU batch " . ($batch_index + 1), $vendor_id);
            
            $batch_products = self::get_vendor_products_by_skus($meta, $vendor_id, $sku_batch);
            $vendor_products = array_merge($vendor_products, $batch_products);
            
            // تاخیر بین batch ها
            if (count($skus) > $batch_size) {
                sleep(1);
            }
        }
        
        return $vendor_products;
    }
    
    /**
     * 🆕 جدید: دریافت محصولات فروشنده بر اساس SKUهای خاص
     */
    private static function get_vendor_products_by_skus($meta, $vendor_id, $skus) {
        $api_url = trailingslashit($meta['url']) . 'wp-json/wc/v3/products';
        $auth = base64_encode($meta['key'] . ':' . $meta['secret']);
        
        $products = [];
        $found_count = 0;
        $not_found_count = 0;
        
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
                    $found_count++;
                    Vendor_Logger::log_debug("Found vendor product for SKU: {$clean_sku}", null, $vendor_id);
                } else {
                    $not_found_count++;
                    Vendor_Logger::log_warning("Vendor product not found for SKU: {$clean_sku}", null, $vendor_id);
                }
            } else {
                $not_found_count++;
                $error_msg = is_wp_error($response) ? $response->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code($response);
                Vendor_Logger::log_error("API error for SKU {$clean_sku}: {$error_msg}", null, $vendor_id);
            }
            
            // تاخیر کوچک بین درخواست‌ها
            usleep(200000); // 0.2 ثانیه
        }
        
        Vendor_Logger::log_info("SKU batch result: {$found_count} found, {$not_found_count} not found", $vendor_id);
        
        return $products;
    }
    
    private static function get_local_products_with_sku($cat_id, $vendor_id) {
        global $wpdb;
        
        $sql = "SELECT p.ID as id, pm.meta_value as sku 
                FROM {$wpdb->posts} p 
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
                WHERE p.post_type = 'product' 
                AND p.post_status = 'publish' 
                AND pm.meta_key = '_sku' 
                AND pm.meta_value != ''";
        
        // فیلتر بر اساس دسته
        if ($cat_id !== 'all') {
            $sql .= " AND p.ID IN (
                SELECT object_id FROM {$wpdb->term_relationships} 
                WHERE term_taxonomy_id = {$cat_id}
            )";
        }
        
        // فیلتر بر اساس مالک
        $vendor_user = get_userdata($vendor_id);
        if ($vendor_user) {
            $sql .= " AND p.post_author = {$vendor_id}";
        }
        
        $results = $wpdb->get_results($sql, ARRAY_A);
        
        Vendor_Logger::log_info("Local products query returned " . count($results) . " results", $vendor_id);
        
        return $results;
    }
    
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