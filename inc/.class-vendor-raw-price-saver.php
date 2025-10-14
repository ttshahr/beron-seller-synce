<?php
if (!defined('ABSPATH')) exit;

class Vendor_Raw_Price_Saver {
    
    public static function save_raw_prices($vendor_id, $cat_id) {
        $meta = Vendor_Meta_Handler::get_vendor_meta($vendor_id);
        
        // افزایش محدودیت‌ها
        set_time_limit(300);
        ini_set('memory_limit', '256M');
        
        $product_ids = self::get_product_ids($cat_id);
        $saved_count = 0;
        
        foreach ($product_ids as $index => $product_id) {
            $sku = get_post_meta($product_id, '_sku', true);
            if (!$sku) continue;
            
            $vendor_product = Vendor_API_Handler::get_product_by_sku($meta, $sku);
            if (!$vendor_product) continue;
            
            // ذخیره قیمت خام فروشنده
            $raw_price = self::extract_raw_price($vendor_product, $meta);
            if ($raw_price > 0) {
                update_post_meta($product_id, '_vendor_raw_price', $raw_price);
                update_post_meta($product_id, '_vendor_last_sync', current_time('mysql'));
                $saved_count++;
            }
            
            // آزادسازی حافظه
            if ($index % 50 === 0) {
                wp_cache_flush();
                gc_collect_cycles();
            }
        }
        
        return $saved_count;
    }
    
    private static function extract_raw_price($vendor_product, $meta) {
        $price_meta_key = $meta['price_meta_key'];
        $cooperation_price = 0;
        
        // استخراج قیمت از متادیتا
        if (isset($vendor_product['meta_data'])) {
            foreach ($vendor_product['meta_data'] as $m) {
                if ($m['key'] === $price_meta_key && !empty($m['value'])) {
                    $cooperation_price = floatval($m['value']);
                    break;
                }
            }
        }
        
        // Fallback به قیمت معمولی
        if (!$cooperation_price && isset($vendor_product['price'])) {
            $cooperation_price = floatval($vendor_product['price']);
        }
        
        // تبدیل ریال به تومان اگر نیاز باشد
        if ($meta['currency'] === 'rial') {
            $cooperation_price = $cooperation_price / 10;
        }
        
        return $cooperation_price;
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