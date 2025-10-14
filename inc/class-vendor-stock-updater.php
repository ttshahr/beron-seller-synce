<?php
if (!defined('ABSPATH')) exit;

class Vendor_Stock_Updater {
    
    public static function update_stocks($vendor_id, $cat_id) {
        $meta = Vendor_Meta_Handler::get_vendor_meta($vendor_id);
        
        // افزایش محدودیت‌ها
        set_time_limit(600);
        ini_set('memory_limit', '512M');
        
        $product_ids = self::get_product_ids($cat_id);
        $updated_count = 0;
        
        foreach ($product_ids as $index => $product_id) {
            $sku = get_post_meta($product_id, '_sku', true);
            if (!$sku) continue;
            
            $vendor_product = Vendor_API_Handler::get_product_by_sku($meta, $sku);
            if (!$vendor_product) continue;
            
            // بروزرسانی موجودی
            if (self::update_product_stock($product_id, $vendor_product, $meta)) {
                $updated_count++;
            }
            
            // آزادسازی حافظه و تاخیر
            if ($index % 30 === 0) {
                wp_cache_flush();
                gc_collect_cycles();
                sleep(2); // تاخیر بیشتر برای API
            }
        }
        
        return $updated_count;
    }
    
    private static function update_product_stock($product_id, $vendor_product, $meta) {
        $product = wc_get_product($product_id);
        if (!$product) return false;
        
        try {
            if ($meta['stock_type'] === 'managed') {
                $product->set_manage_stock(true);
                $product->set_stock_quantity(intval($vendor_product['stock_quantity']));
            } else {
                $status = ($vendor_product['stock_status'] === 'instock') ? 'instock' : 'outofstock';
                $product->set_stock_status($status);
            }
            
            $product->save();
            Vendor_Logger::log_success($product_id, 'stock_updated');
            return true;
            
        } catch (Exception $e) {
            Vendor_Logger::log_error('Stock update failed: ' . $e->getMessage(), $product_id);
            return false;
        }
    }
    
    private static function get_product_ids($cat_id) {
        $args = [
            'post_type' => 'product',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'fields' => 'ids',
            'no_found_rows' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false
        ];
        
        if ($cat_id !== 'all') {
            $args['tax_query'] = [[
                'taxonomy' => 'product_cat',
                'terms' => [$cat_id],
            ]];
        }
        
        return get_posts($args);
    }
}