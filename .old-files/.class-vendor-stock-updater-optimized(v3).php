<?php
if (!defined('ABSPATH')) exit;

class Vendor_Stock_Updater_Optimized {
    
    public static function update_stocks($vendor_id, $cat_id) {
        $meta = Vendor_Meta_Handler::get_vendor_meta($vendor_id);
        
        set_time_limit(600);
        ini_set('memory_limit', '512M');
        wp_suspend_cache_addition(true);
        
        // 🆕 منطق جدید: اگر دسته انتخاب نشده، از مالک محصول استفاده کن
        if ($cat_id === 'all') {
            $product_ids = self::get_vendor_products_by_owner($vendor_id);
        } else {
            $product_ids = self::get_vendor_products($vendor_id, $cat_id);
        }
        
        if (empty($product_ids)) {
            throw new Exception('هیچ محصولی برای این فروشنده یافت نشد.');
        }
        
        Vendor_Logger::log_success(0, 'products_found', 
            'تعداد محصولات پیدا شده: ' . count($product_ids));
        
        $updated_count = 0;
        $vendor_products_map = self::get_vendor_products_map($meta);
        
        foreach ($product_ids as $index => $product_id) {
            $sku = get_post_meta($product_id, '_sku', true);
            if (!$sku) continue;
            
            // 🆕 فقط اگر محصول در فروشنده وجود دارد، بروزرسانی کن
            if (isset($vendor_products_map[$sku])) {
                $vendor_product = $vendor_products_map[$sku];
                if (self::update_product_stock($product_id, $vendor_product, $meta)) {
                    $updated_count++;
                }
                
                // 🆕 حذف از map برای جلوگیری از پردازش تکراری
                unset($vendor_products_map[$sku]);
            }
            
            // آزادسازی حافظه
            if ($index % 100 === 0) {
                wp_cache_flush();
                gc_collect_cycles();
                Vendor_Logger::log_success(0, 'progress', 
                    'پردازش ' . $index . ' از ' . count($product_ids) . ' - بروز شده: ' . $updated_count);
            }
        }
        
        Vendor_Logger::log_success(0, 'update_complete', 
            'بروزرسانی موجودی کامل شد. تعداد: ' . $updated_count);
        
        return $updated_count;
    }
    
    /**
     * 🆕 جدید: دریافت محصولات بر اساس مالک (author)
     */
    private static function get_vendor_products_by_owner($vendor_id) {
        global $wpdb;
        
        // پیدا کردن user_login فروشنده
        $vendor = get_userdata($vendor_id);
        if (!$vendor) {
            return [];
        }
        
        // پیدا کردن محصولاتی که این کاربر نویسنده آنهاست
        $sql = "SELECT ID FROM {$wpdb->posts} 
                WHERE post_type = 'product' 
                AND post_status = 'publish' 
                AND post_author = %d";
        
        return $wpdb->get_col($wpdb->prepare($sql, $vendor_id));
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
            return $wpdb->get_col($wpdb->prepare($sql, $vendor_id, $cat_id));
        }
        
        return $wpdb->get_col($wpdb->prepare($sql, $vendor_id));
    }
    
    /**
     * دریافت map محصولات فروشنده
     */
    private static function get_vendor_products_map($meta) {
        $vendor_products = Vendor_API_Optimizer::get_all_products($meta);
        $products_map = [];
        
        foreach ($vendor_products as $product) {
            if (!empty($product['sku'])) {
                $clean_sku = trim($product['sku']);
                $products_map[$clean_sku] = $product;
            }
        }
        
        Vendor_Logger::log_success(0, 'vendor_products_map', 
            'تعداد محصولات فروشنده در map: ' . count($products_map));
        
        return $products_map;
    }
    
    private static function update_product_stock($product_id, $vendor_product, $meta) {
        $product = wc_get_product($product_id);
        if (!$product) return false;
        
        try {
            $old_stock = $product->get_stock_quantity();
            $old_status = $product->get_stock_status();
            
            if ($meta['stock_type'] === 'managed') {
                $new_stock = intval($vendor_product['stock_quantity']);
                $product->set_manage_stock(true);
                $product->set_stock_quantity($new_stock);
                
                // تنظیم وضعیت موجودی بر اساس quantity
                if ($new_stock > 0) {
                    $product->set_stock_status('instock');
                } else {
                    $product->set_stock_status('outofstock');
                }
                
                $stock_changed = ($old_stock != $new_stock);
                
            } else {
                $new_status = ($vendor_product['stock_status'] === 'instock') ? 'instock' : 'outofstock';
                $product->set_stock_status($new_status);
                $stock_changed = ($old_status != $new_status);
            }
            
            // 🆕 فقط در صورت تغییر، ذخیره کن
            if ($stock_changed) {
                $product->save();
                Vendor_Logger::log_success($product_id, 'stock_updated', 
                    'موجودی بروز شد: ' . $old_stock . ' → ' . $new_stock);
                return true;
            } else {
                Vendor_Logger::log_success($product_id, 'stock_unchanged', 
                    'موجودی تغییر نکرد: ' . $old_stock);
                return false;
            }
            
        } catch (Exception $e) {
            Vendor_Logger::log_error('Stock update failed: ' . $e->getMessage(), $product_id);
            return false;
        }
    }
    
    /**
     * 🆕 جدید: دریافت گزارش بروزرسانی
     */
    public static function get_stock_update_report($vendor_id, $cat_id) {
        $meta = Vendor_Meta_Handler::get_vendor_meta($vendor_id);
        
        if ($cat_id === 'all') {
            $product_ids = self::get_vendor_products_by_owner($vendor_id);
        } else {
            $product_ids = self::get_vendor_products($vendor_id, $cat_id);
        }
        
        $vendor_products_map = self::get_vendor_products_map($meta);
        
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
        
        return $report;
    }
}