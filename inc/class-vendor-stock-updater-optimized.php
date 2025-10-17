<?php
if (!defined('ABSPATH')) exit;

class Vendor_Stock_Updater_Optimized {
    
    public static function update_stocks($vendor_id, $cat_id) {
        $meta = Vendor_Meta_Handler::get_vendor_meta($vendor_id);
        
        set_time_limit(1000);
        ini_set('memory_limit', '2048M');
        wp_suspend_cache_addition(true);
        
        Vendor_Logger::log_info("Starting stock update process for vendor {$vendor_id}", $vendor_id);
        
        // دریافت محصولات محلی
        if ($cat_id === 'all') {
            $product_ids = self::get_vendor_products_by_owner($vendor_id);
        } else {
            $product_ids = self::get_vendor_products($vendor_id, $cat_id);
        }
        
        if (empty($product_ids)) {
            Vendor_Logger::log_warning("No local products found for vendor", null, $vendor_id);
            throw new Exception('هیچ محصولی برای این فروشنده یافت نشد.');
        }
        
        Vendor_Logger::log_info("Found " . count($product_ids) . " local products to process", $vendor_id);
        
        // دریافت فقط محصولات مورد نیاز از API
        $vendor_products_map = self::get_vendor_products_map($meta, $vendor_id, $product_ids);
        
        if (empty($vendor_products_map)) {
            Vendor_Logger::log_error("No matching products found in vendor API", null, $vendor_id);
            throw new Exception('هیچ محصول مطابقی در فروشنده یافت نشد.');
        }
        
        Vendor_Logger::log_info("Vendor products map created with " . count($vendor_products_map) . " matched products", $vendor_id);
        
        $updated_count = 0;
        $processed_count = 0;
        
        foreach ($product_ids as $index => $product_id) {
            $processed_count++;
            $sku = get_post_meta($product_id, '_sku', true);
            
            if (!$sku) {
                Vendor_Logger::log_debug("Product {$product_id} has no SKU", $product_id, $vendor_id);
                continue;
            }
            
            // فقط اگر محصول در فروشنده وجود دارد، بروزرسانی کن
            if (isset($vendor_products_map[$sku])) {
                $vendor_product = $vendor_products_map[$sku];
                if (self::update_product_stock($product_id, $vendor_product, $meta, $vendor_id)) {
                    $updated_count++;
                }
            } else {
                Vendor_Logger::log_warning("SKU not found in vendor products: {$sku}", $product_id, $vendor_id);
            }
            
            // گزارش پیشرفت
            if ($processed_count % 50 === 0) {
                Vendor_Logger::log_info(
                    "Progress: {$processed_count}/" . count($product_ids) . " processed, {$updated_count} updated", 
                    $vendor_id
                );
                wp_cache_flush();
                gc_collect_cycles();
            }
        }
        
        Vendor_Logger::log_success(
            0, 
            'stock_update_completed', 
            $vendor_id, 
            "Stock update completed: {$updated_count} products updated from {$processed_count} processed"
        );
        
        return $updated_count;
    }
    
    /**
     * دریافت محصولات بر اساس مالک (author)
     */
    private static function get_vendor_products_by_owner($vendor_id) {
        global $wpdb;
        
        $vendor = get_userdata($vendor_id);
        if (!$vendor) {
            Vendor_Logger::log_error("Vendor user not found: {$vendor_id}", null, $vendor_id);
            return [];
        }
        
        $sql = "SELECT ID FROM {$wpdb->posts} 
                WHERE post_type = 'product' 
                AND post_status = 'publish' 
                AND post_author = %d";
        
        $products = $wpdb->get_col($wpdb->prepare($sql, $vendor_id));
        Vendor_Logger::log_debug("Found " . count($products) . " products by owner", null, $vendor_id);
        
        return $products;
    }
    
    /**
     * دریافت محصولات بر اساس دسته و فروشنده
     */
    private static function get_vendor_products($vendor_id, $cat_id) {
        global $wpdb;
        
        $sql = "SELECT p.ID FROM {$wpdb->posts} p 
                WHERE p.post_type = 'product' 
                AND p.post_status = 'publish' 
                AND p.post_author = %d";
        
        if ($cat_id !== 'all') {
            $sql .= " AND p.ID IN (
                SELECT object_id FROM {$wpdb->term_relationships} 
                WHERE term_taxonomy_id = %d
            )";
            $products = $wpdb->get_col($wpdb->prepare($sql, $vendor_id, $cat_id));
        } else {
            $products = $wpdb->get_col($wpdb->prepare($sql, $vendor_id));
        }
        
        Vendor_Logger::log_debug("Found " . count($products) . " products by category", null, $vendor_id);
        
        return $products;
    }
    
    /**
     * دریافت map محصولات فروشنده (بهینه‌شده - فقط محصولات مورد نیاز)
     */
    private static function get_vendor_products_map($meta, $vendor_id, $product_ids) {
        if (empty($product_ids)) {
            return [];
        }
        
        Vendor_Logger::log_info("Fetching vendor products for " . count($product_ids) . " local products", $vendor_id);
        
        // استخراج SKUهای محلی
        $local_skus = self::get_local_skus($product_ids);
        
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
     * دریافت SKUهای محصولات محلی
     */
    private static function get_local_skus($product_ids) {
        global $wpdb;
        
        if (empty($product_ids)) {
            return [];
        }
        
        $placeholders = implode(',', array_fill(0, count($product_ids), '%d'));
        $sql = "SELECT meta_value FROM {$wpdb->postmeta} 
                WHERE post_id IN ($placeholders) 
                AND meta_key = '_sku' 
                AND meta_value != ''";
        
        $skus = $wpdb->get_col($wpdb->prepare($sql, $product_ids));
        
        // حذف مقادیر تکراری و خالی
        $skus = array_filter(array_unique($skus));
        
        return $skus;
    }
    
    /**
     * دریافت محصولات خاص از API بر اساس SKU
     */
    private static function get_specific_vendor_products($meta, $vendor_id, $skus) {
        if (empty($skus)) {
            return [];
        }
        
        $vendor_products = [];
        $batch_size = 50; // پردازش دسته‌ای برای جلوگیری از overload
        
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
     * دریافت محصولات فروشنده بر اساس SKUهای خاص
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
    
    /**
     * بروزرسانی موجودی یک محصول
     */
    private static function update_product_stock($product_id, $vendor_product, $meta, $vendor_id) {
        $product = wc_get_product($product_id);
        if (!$product) {
            Vendor_Logger::log_error("Product not found", $product_id, $vendor_id);
            return false;
        }
        
        try {
            $old_stock = $product->get_stock_quantity();
            $old_status = $product->get_stock_status();
            $sku = get_post_meta($product_id, '_sku', true);
            
            Vendor_Logger::log_debug("Updating stock for product: {$sku}", $product_id, $vendor_id);
            
            if ($meta['stock_type'] === 'managed') {
                $new_stock = intval($vendor_product['stock_quantity'] ?? 0);
                $product->set_manage_stock(true);
                $product->set_stock_quantity($new_stock);
                
                // تنظیم وضعیت موجودی بر اساس quantity
                $new_status = ($new_stock > 0) ? 'instock' : 'outofstock';
                $product->set_stock_status($new_status);
                
                $stock_changed = ($old_stock != $new_stock);
                $change_info = "Stock: {$old_stock} → {$new_stock}, Status: {$old_status} → {$new_status}";
                
            } else {
                $new_status = (($vendor_product['stock_status'] ?? '') === 'instock') ? 'instock' : 'outofstock';
                $product->set_stock_status($new_status);
                $stock_changed = ($old_status != $new_status);
                $change_info = "Status: {$old_status} → {$new_status}";
            }
            
            // فقط در صورت تغییر، ذخیره کن
            if ($stock_changed) {
                $product->save();
                Vendor_Logger::log_success(
                    $product_id, 
                    'stock_updated', 
                    $vendor_id, 
                    "Stock updated - {$change_info}"
                );
                return true;
            } else {
                Vendor_Logger::log_debug(
                    "Stock unchanged for product: {$sku} - {$change_info}", 
                    $product_id, 
                    $vendor_id
                );
                return false;
            }
            
        } catch (Exception $e) {
            Vendor_Logger::log_error(
                "Stock update failed: " . $e->getMessage(), 
                $product_id, 
                $vendor_id
            );
            return false;
        }
    }
    
    /**
     * دریافت گزارش بروزرسانی
     */
    public static function get_stock_update_report($vendor_id, $cat_id) {
        Vendor_Logger::log_info("Generating stock update report", $vendor_id);
        
        $meta = Vendor_Meta_Handler::get_vendor_meta($vendor_id);
        
        if ($cat_id === 'all') {
            $product_ids = self::get_vendor_products_by_owner($vendor_id);
        } else {
            $product_ids = self::get_vendor_products($vendor_id, $cat_id);
        }
        
        $local_skus = self::get_local_skus($product_ids);
        $vendor_products_map = self::get_vendor_products_map($meta, $vendor_id, $product_ids);
        
        $report = [
            'total_local_products' => count($product_ids),
            'total_local_skus' => count($local_skus),
            'total_vendor_products' => count($vendor_products_map),
            'matched_products' => 0,
            'update_candidates' => []
        ];
        
        foreach ($product_ids as $product_id) {
            $sku = get_post_meta($product_id, '_sku', true);
            if (!$sku) continue;
            
            if (isset($vendor_products_map[$sku])) {
                $report['matched_products']++;
                $report['update_candidates'][] = [
                    'product_id' => $product_id,
                    'sku' => $sku,
                    'product_name' => get_the_title($product_id)
                ];
            }
        }
        
        Vendor_Logger::log_info(
            "Stock report generated: {$report['matched_products']} matches out of {$report['total_local_products']} local products", 
            $vendor_id
        );
        
        return $report;
    }
}