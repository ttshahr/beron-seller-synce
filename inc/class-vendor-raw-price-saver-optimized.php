<?php
if (!defined('ABSPATH')) exit;

class Vendor_Raw_Price_Saver_Optimized {
    
    public static function save_raw_prices_optimized($vendor_id, $cat_id) {
        $meta = Vendor_Meta_Handler::get_vendor_meta($vendor_id);
        
        set_time_limit(600);
        ini_set('memory_limit', '512M');
        wp_suspend_cache_addition(true);
        
        Vendor_Logger::log_success(0, 'process_started', 
            'شروع ذخیره قیمت‌های خام برای فروشنده: ' . $vendor_id);
        
        // دریافت تمام محصولات از API یکجا
        $vendor_products = Vendor_API_Optimizer::get_all_products($meta);
        if (empty($vendor_products)) {
            throw new Exception('هیچ محصولی از API فروشنده دریافت نشد.');
        }
        
        Vendor_Logger::log_success(0, 'vendor_products_received', 
            'تعداد محصولات دریافت شده از فروشنده: ' . count($vendor_products));
        
        // ایجاد نقشه SKU به محصول برای جستجوی سریع
        $vendor_products_map = [];
        foreach ($vendor_products as $vp) {
            if (!empty($vp['sku'])) {
                $clean_sku = trim($vp['sku']);
                $vendor_products_map[$clean_sku] = $vp;
            }
        }
        
        Vendor_Logger::log_success(0, 'vendor_products_map_created', 
            'تعداد محصولات در map: ' . count($vendor_products_map));
        
        // دریافت محصولات محلی
        $local_products = self::get_local_products_with_sku($cat_id, $vendor_id);
        
        Vendor_Logger::log_success(0, 'local_products_found', 
            'تعداد محصولات محلی پیدا شده: ' . count($local_products));
        
        $saved_count = 0;
        $skus_processed = [];
        
        foreach ($local_products as $index => $local_product) {
            $sku = $local_product['sku'];
            $product_id = $local_product['id'];
            
            $skus_processed[] = $sku;
            
            if (isset($vendor_products_map[$sku])) {
                $vendor_product = $vendor_products_map[$sku];
                $raw_price = self::extract_raw_price($vendor_product, $meta);
                
                Vendor_Logger::log_success($product_id, 'price_extracted', 
                    'قیمت استخراج شده: ' . $raw_price . ' برای SKU: ' . $sku);
                
                if ($raw_price > 0) {
                    // ذخیره در متای مورد نظر شما
                    $saved1 = update_post_meta($product_id, '_seller_list_price', $raw_price);
                    $saved2 = update_post_meta($product_id, '_vendor_raw_price', $raw_price);
                    $saved3 = update_post_meta($product_id, '_vendor_last_sync', current_time('mysql'));
                    
                    // 🆕 همچنین فروشنده رو هم اختصاص بده
                    $saved4 = update_post_meta($product_id, '_vendor_id', $vendor_id);
                    
                    if ($saved1 !== false) {
                        $saved_count++;
                        Vendor_Logger::log_success($product_id, 'price_saved', 
                            'قیمت ذخیره شد: ' . $raw_price . ' در _seller_list_price - محصول به فروشنده اختصاص داده شد');
                    } else {
                        Vendor_Logger::log_error('خطا در ذخیره قیمت برای محصول: ' . $product_id, $product_id);
                    }
                } else {
                    Vendor_Logger::log_error('قیمت نامعتبر: ' . $raw_price . ' برای SKU: ' . $sku, $product_id);
                }
            } else {
                Vendor_Logger::log_success($product_id, 'sku_not_found_in_vendor', 
                    'SKU در فروشنده یافت نشد: ' . $sku);
            }
            
            // آزادسازی حافظه
            if ($index % 50 === 0) {
                wp_cache_flush();
                gc_collect_cycles();
                
                Vendor_Logger::log_success(0, 'progress', 
                    'پردازش ' . $index . ' از ' . count($local_products) . ' - ذخیره شده: ' . $saved_count);
            }
        }
        
        // گزارش نهایی
        Vendor_Logger::log_success(0, 'process_completed', 
            'ذخیره قیمت‌ها کامل شد. تعداد ذخیره شده: ' . $saved_count . ' از ' . count($local_products));
        
        if (!empty($skus_processed)) {
            Vendor_Logger::log_success(0, 'skus_processed', 
                'تعداد SKUهای پردازش شده: ' . count($skus_processed));
        }
        
        return $saved_count;
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
        
        // اگر دسته انتخاب شده، فیلتر کن
        if ($cat_id !== 'all') {
            $sql .= " AND p.ID IN (
                SELECT object_id FROM {$wpdb->term_relationships} 
                WHERE term_taxonomy_id = {$cat_id}
            )";
        }
        
        // 🆕 از نویسنده (author) برای فیلتر کردن استفاده کن
        $vendor_user = get_userdata($vendor_id);
        if ($vendor_user) {
            $sql .= " AND p.post_author = {$vendor_id}";
        }
        
        Vendor_Logger::log_success(0, 'sql_query', 'کوئری اجرا شده: ' . $sql);
        
        $results = $wpdb->get_results($sql, ARRAY_A);
        
        Vendor_Logger::log_success(0, 'local_products_query', 
            'تعداد محصولات محلی با SKU: ' . count($results));
        
        return $results;
    }
    
    private static function extract_raw_price($vendor_product, $meta) {
        $price_meta_key = $meta['price_meta_key'];
        $cooperation_price = 0;
        
        // استخراج قیمت از متادیتا
        if (isset($vendor_product['meta_data'])) {
            foreach ($vendor_product['meta_data'] as $m) {
                if ($m['key'] === $price_meta_key && !empty($m['value'])) {
                    $cooperation_price = floatval($m['value']);
                    Vendor_Logger::log_success(0, 'price_from_meta', 
                        'قیمت از متا استخراج شد: ' . $cooperation_price . ' با کلید: ' . $price_meta_key);
                    break;
                }
            }
        }
        
        // Fallback به قیمت معمولی
        if (!$cooperation_price && isset($vendor_product['price'])) {
            $cooperation_price = floatval($vendor_product['price']);
            Vendor_Logger::log_success(0, 'price_from_regular', 
                'قیمت از regular_price استخراج شد: ' . $cooperation_price);
        }
        
        // تبدیل ریال به تومان اگر نیاز باشد
        if ($meta['currency'] === 'rial' && $cooperation_price > 0) {
            $old_price = $cooperation_price;
            $cooperation_price = $cooperation_price / 10;
            Vendor_Logger::log_success(0, 'currency_conversion', 
                'تبدیل ریال به تومان: ' . $old_price . ' → ' . $cooperation_price);
        }
        
        Vendor_Logger::log_success(0, 'final_price', 
            'قیمت نهایی: ' . $cooperation_price);
        
        return $cooperation_price;
    }
}