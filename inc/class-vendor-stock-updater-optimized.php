<?php
if (!defined('ABSPATH')) exit;

class Vendor_Stock_Updater_Optimized {
    
    public static function update_stocks($vendor_id, $cat_id) {
        $meta = Vendor_Meta_Handler::get_vendor_meta($vendor_id);
        
        set_time_limit(600);
        ini_set('memory_limit', '512M');
        wp_suspend_cache_addition(true);
        
        Vendor_Logger::log_info("Starting stock update process", $vendor_id);
        
        // منطق جدید: اگر دسته انتخاب نشده، از مالک محصول استفاده کن
        if ($cat_id === 'all') {
            $product_ids = self::get_vendor_products_by_owner($vendor_id);
        } else {
            $product_ids = self::get_vendor_products($vendor_id, $cat_id);
        }
        
        if (empty($product_ids)) {
            Vendor_Logger::log_warning("No products found for vendor", null, $vendor_id);
            throw new Exception('هیچ محصولی برای این فروشنده یافت نشد.');
        }
        
        Vendor_Logger::log_info("Found " . count($product_ids) . " local products", $vendor_id);
        
        $updated_count = 0;
        $vendor_products_map = self::get_vendor_products_map($meta, $vendor_id);
        
        if (empty($vendor_products_map)) {
            Vendor_Logger::log_error("No vendor products found in API response", null, $vendor_id);
            throw new Exception('هیچ محصولی از فروشنده دریافت نشد.');
        }
        
        Vendor_Logger::log_info("Vendor products map created with " . count($vendor_products_map) . " items", $vendor_id);
        
        foreach ($product_ids as $index => $product_id) {
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
                
                // حذف از map برای جلوگیری از پردازش تکراری
                unset($vendor_products_map[$sku]);
            } else {
                Vendor_Logger::log_warning("SKU not found in vendor products: {$sku}", $product_id, $vendor_id);
            }
            
            // آزادسازی حافظه و گزارش پیشرفت
            if ($index % 100 === 0) {
                wp_cache_flush();
                gc_collect_cycles();
                Vendor_Logger::log_info(
                    "Progress: {$index}/" . count($product_ids) . " processed, {$updated_count} updated", 
                    $vendor_id
                );
            }
        }
        
        Vendor_Logger::log_success(
            0, 
            'stock_update_completed', 
            $vendor_id, 
            "Stock update completed: {$updated_count} products updated from " . count($product_ids) . " total"
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
     * دریافت map محصولات فروشنده
     */
    private static function get_vendor_products_map($meta, $vendor_id) {
        Vendor_Logger::log_info("Fetching vendor products from API", $vendor_id);
        
        $vendor_products = Vendor_API_Optimizer::get_all_products($meta, $vendor_id);
        $products_map = [];
        
        if (empty($vendor_products)) {
            Vendor_Logger::log_error("Empty vendor products response from API", null, $vendor_id);
            return [];
        }
        
        foreach ($vendor_products as $product) {
            if (!empty($product['sku'])) {
                $clean_sku = trim($product['sku']);
                $products_map[$clean_sku] = $product;
            } else {
                Vendor_Logger::log_warning("Vendor product has no SKU: " . ($product['id'] ?? 'unknown'), null, $vendor_id);
            }
        }
        
        Vendor_Logger::log_info("Vendor products map created with " . count($products_map) . " valid SKUs", $vendor_id);
        
        return $products_map;
    }
    
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
                if ($new_stock > 0) {
                    $product->set_stock_status('instock');
                } else {
                    $product->set_stock_status('outofstock');
                }
                
                $stock_changed = ($old_stock != $new_stock);
                $change_info = "Stock: {$old_stock} → {$new_stock}";
                
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
                    "Stock updated successfully - {$change_info}"
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
        
        $vendor_products_map = self::get_vendor_products_map($meta, $vendor_id);
        
        $report = [
            'total_local_products' => count($product_ids),
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