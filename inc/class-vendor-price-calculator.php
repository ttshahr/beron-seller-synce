<?php
if (!defined('ABSPATH')) exit;

class Vendor_Price_Calculator {
    
    public static function calculate_final_prices($vendor_id, $cat_id) {
        $conversion_percent = floatval(get_user_meta($vendor_id, 'vendor_price_conversion_percent', true));
        
        $product_ids = self::get_product_ids_with_raw_price($cat_id);
        $updated_count = 0;
        
        foreach ($product_ids as $product_id) {
            $raw_price = get_post_meta($product_id, '_vendor_raw_price', true);
            if (!$raw_price) continue;
            
            // محاسبه قیمت نهایی
            $final_price = $raw_price * (1 + ($conversion_percent / 100));
            $final_price = ceil($final_price / 1000) * 1000;
            
            // ذخیره در محصول
            $product = wc_get_product($product_id);
            if ($product) {
                $product->set_regular_price($final_price);
                $product->save();
                $updated_count++;
            }
            
            // ذخیره قیمت نهایی در متا برای بررسی
            update_post_meta($product_id, '_vendor_final_price', $final_price);
        }
        
        return $updated_count;
    }
    
    private static function get_product_ids_with_raw_price($cat_id) {
        global $wpdb;
        
        // کوئری مستقیم برای عملکرد بهتر
        $sql = "SELECT p.ID FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_type = 'product' 
                AND p.post_status = 'publish'
                AND pm.meta_key = '_vendor_raw_price'
                AND pm.meta_value > '0'";
        
        if ($cat_id !== 'all') {
            $sql .= " AND p.ID IN (
                SELECT object_id FROM {$wpdb->term_relationships} 
                WHERE term_taxonomy_id = {$cat_id}
            )";
        }
        
        return $wpdb->get_col($sql);
    }
    
    public static function batch_calculate_prices($product_ids, $conversion_percent) {
        $updated_count = 0;
        
        foreach ($product_ids as $product_id) {
            $raw_price = get_post_meta($product_id, '_vendor_raw_price', true);
            if (!$raw_price) continue;
            
            $final_price = $raw_price * (1 + ($conversion_percent / 100));
            $final_price = ceil($final_price / 1000) * 1000;
            
            $product = wc_get_product($product_id);
            if ($product) {
                $product->set_regular_price($final_price);
                $product->save();
                $updated_count++;
            }
        }
        
        return $updated_count;
    }
}