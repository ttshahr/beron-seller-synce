<?php
if (!defined('ABSPATH')) exit;

class Vendor_Product_Assigner {
    
    /**
     * اختصاص خودکار فروشنده به محصولات بر اساس SKU
     * (نسخه بهینه‌شده برای حجم‌های بالا)
     */
    public static function assign_vendor_to_products($vendor_id) {
        // افزایش محدودیت‌ها
        set_time_limit(600); // 10 دقیقه
        ini_set('memory_limit', '2048M');
        wp_suspend_cache_addition(true);
        
        $meta = Vendor_Meta_Handler::get_vendor_meta($vendor_id);
        
        Vendor_Logger::log_success(0, 'assignment_started', 
            'شروع اختصاص خودکار برای فروشنده: ' . $vendor_id);
        
        // تست اتصال سریع
        try {
            $connection_test = Vendor_API_Optimizer::test_connection($meta);
            if (!$connection_test['success']) {
                throw new Exception('اتصال به API فروشنده برقرار نشد: ' . 
                    ($connection_test['error'] ?? 'خطای ناشناخته'));
            }
            
            Vendor_Logger::log_success(0, 'connection_ok', 
                'اتصال OK - محصولات: ' . ($connection_test['total_products'] ?? 'نامشخص'));
                
        } catch (Exception $e) {
            throw new Exception('خطا در تست اتصال: ' . $e->getMessage());
        }
        
        // دریافت محصولات فروشنده
        $vendor_products = Vendor_API_Optimizer::get_all_products($meta);
        
        if (empty($vendor_products)) {
            throw new Exception('هیچ محصولی از فروشنده دریافت نشد.');
        }
        
        Vendor_Logger::log_success(0, 'vendor_products_loaded', 
            'تعداد محصولات فروشنده: ' . count($vendor_products));
        
        $assigned_count = 0;
        $batch_size = 200; // پردازش 200 محصول در هر مرحله
        $total_batches = ceil(count($vendor_products) / $batch_size);
        
        Vendor_Logger::log_success(0, 'batch_config', 
            'پیکربندی بچ: ' . $batch_size . ' محصول در ' . $total_batches . ' بچ');
        
        for ($batch = 0; $batch < $total_batches; $batch++) {
            $start_index = $batch * $batch_size;
            $batch_products = array_slice($vendor_products, $start_index, $batch_size);
            
            $batch_assigned = self::process_batch($batch_products, $vendor_id, $batch + 1);
            $assigned_count += $batch_assigned;
            
            Vendor_Logger::log_success(0, 'batch_complete', 
                'بچ ' . ($batch + 1) . ' کامل شد - اختصاص داده شده: ' . $batch_assigned);
            
            // آزادسازی حافظه
            wp_cache_flush();
            gc_collect_cycles();
            
            // تاخیر بین بچ‌ها
            if ($batch < $total_batches - 1) {
                sleep(2);
            }
        }
        
        // گزارش نهایی
        Vendor_Logger::log_success(0, 'assignment_completed', 
            'اختصاص کامل شد. تعداد: ' . $assigned_count . ' از ' . count($vendor_products));
        
        return $assigned_count;
    }
    
    /**
     * پردازش یک بچ از محصولات
     */
    private static function process_batch($batch_products, $vendor_id, $batch_number) {
        $assigned_in_batch = 0;
        
        foreach ($batch_products as $index => $vendor_product) {
            if (empty($vendor_product['sku'])) {
                continue;
            }
            
            $sku = trim($vendor_product['sku']);
            $product_id = self::find_product_by_sku($sku);
            
            if ($product_id) {
                $assigned = self::assign_single_product($product_id, $vendor_id, $sku);
                if ($assigned) {
                    $assigned_in_batch++;
                }
                
                // لاگ پیشرفت
                if (($index + 1) % 50 === 0) {
                    Vendor_Logger::log_success(0, 'batch_progress', 
                        'بچ ' . $batch_number . ' - ' . ($index + 1) . ' از ' . count($batch_products) . ' پردازش شد');
                }
            }
        }
        
        return $assigned_in_batch;
    }
    
    /**
     * اختصاص یک محصول به فروشنده
     */
    public static function assign_single_product($product_id, $vendor_id, $vendor_sku = '') {
        // بررسی وجود محصول
        $product = get_post($product_id);
        if (!$product || $product->post_type !== 'product') {
            Vendor_Logger::log_error('محصول یافت نشد: ' . $product_id, $product_id);
            return false;
        }
        
        // اختصاص فروشنده
        $saved1 = update_post_meta($product_id, '_vendor_id', $vendor_id);
        $saved2 = update_post_meta($product_id, '_vendor_assigned_at', current_time('mysql'));
        
        if ($vendor_sku) {
            $saved3 = update_post_meta($product_id, '_vendor_sku', $vendor_sku);
        }
        
        if ($saved1 !== false) {
            Vendor_Logger::log_success($product_id, 'product_assigned', 
                'محصول به فروشنده اختصاص داده شد. SKU: ' . $vendor_sku);
            return true;
        } else {
            Vendor_Logger::log_error('خطا در اختصاص محصول: ' . $product_id, $product_id);
            return false;
        }
    }
    
    /**
     * جستجوی محصول بر اساس SKU
     */
    private static function find_product_by_sku($sku) {
        global $wpdb;
        
        // جستجوی دقیق
        $product_id = $wpdb->get_var($wpdb->prepare("
            SELECT post_id FROM {$wpdb->postmeta} 
            WHERE meta_key = '_sku' AND meta_value = %s 
            LIMIT 1
        ", $sku));
        
        return $product_id;
    }
    
    /**
     * 🆕 اختصاص هوشمند - فقط محصولاتی که قیمت دارند
     */
    public static function assign_products_with_prices($vendor_id) {
        global $wpdb;
        
        // پیدا کردن محصولاتی که قیمت فروشنده دارند اما هنوز اختصاص داده نشده‌اند
        $sql = "SELECT p.ID as product_id, pm.meta_value as sku
                FROM {$wpdb->posts} p 
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
                LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_vendor_id'
                WHERE p.post_type = 'product' 
                AND p.post_status = 'publish' 
                AND pm.meta_key = '_sku' 
                AND pm.meta_value != ''
                AND pm2.meta_id IS NULL
                AND p.ID IN (
                    SELECT post_id FROM {$wpdb->postmeta} 
                    WHERE meta_key = '_seller_list_price' 
                    AND meta_value > '0'
                )";
        
        $products = $wpdb->get_results($sql, ARRAY_A);
        
        $assigned_count = 0;
        foreach ($products as $product) {
            $assigned = self::assign_single_product($product['product_id'], $vendor_id, $product['sku']);
            if ($assigned) {
                $assigned_count++;
            }
        }
        
        return $assigned_count;
    }
    
    /**
     * دریافت تعداد محصولات یک فروشنده
     */
    public static function get_vendor_products_count($vendor_id) {
        global $wpdb;
        
        return $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->postmeta} 
            WHERE meta_key = '_vendor_id' AND meta_value = %d
        ", $vendor_id));
    }
    
    /**
     * 🆕 بررسی وضعیت اختصاص
     */
    // public static function get_assignment_status($vendor_id) {
    //     global $wpdb;
        
    //     $meta = Vendor_Meta_Handler::get_vendor_meta($vendor_id);
        
    //     // تست اتصال
    //     $connection_test = Vendor_API_Optimizer::test_connection($meta);
        
    //     // تعداد محصولات محلی این فروشنده
    //     $local_count = self::get_vendor_products_count($vendor_id);
        
    //     // تعداد محصولات فروشنده
    //     $vendor_products = Vendor_API_Optimizer::get_all_products($meta);
    //     $vendor_count = $vendor_products ? count($vendor_products) : 0;
        
    //     // تعداد محصولات با قیمت اما بدون اختصاص
    //     $products_with_price = $wpdb->get_var("
    //         SELECT COUNT(DISTINCT p.ID)
    //         FROM {$wpdb->posts} p 
    //         INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
    //         LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_vendor_id'
    //         WHERE p.post_type = 'product' 
    //         AND p.post_status = 'publish' 
    //         AND pm.meta_key = '_seller_list_price' 
    //         AND pm.meta_value > '0'
    //         AND pm2.meta_id IS NULL
    //         AND p.post_author = {$vendor_id}
    //     ");
        
    //     return [
    //         'connection' => $connection_test,
    //         'vendor_products_count' => $vendor_count,
    //         'assigned_products_count' => $local_count,
    //         'products_with_price_unassigned' => $products_with_price,
    //         'recommendation' => self::get_recommendation($vendor_count, $local_count, $products_with_price)
    //     ];
    // }



/**
 * 🆕 بررسی وضعیت اختصاص (بهینه‌شده)
 */
public static function get_assignment_status($vendor_id) {
    global $wpdb;
    
    $meta = Vendor_Meta_Handler::get_vendor_meta($vendor_id);
    
    // تست اتصال (سبک - فقط بررسی اتصال)
    $connection_test = Vendor_API_Optimizer::test_connection($meta);
    
    // تعداد محصولات محلی این فروشنده
    $local_count = self::get_vendor_products_count($vendor_id);
    
    // 🆕 جدید: دریافت تعداد محصولات فروشنده از header API (بدون دانلود محصولات)
    $vendor_count = self::get_vendor_products_count_from_api($meta);
    
    // تعداد محصولات با قیمت اما بدون اختصاص
    $products_with_price = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(DISTINCT p.ID)
        FROM {$wpdb->posts} p 
        INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
        LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_vendor_id'
        WHERE p.post_type = 'product' 
        AND p.post_status = 'publish' 
        AND pm.meta_key = '_seller_list_price' 
        AND pm.meta_value > '0'
        AND pm2.meta_id IS NULL
        AND p.post_author = %d
    ", $vendor_id));
    
    return [
        'connection' => $connection_test,
        'vendor_products_count' => $vendor_count,
        'assigned_products_count' => $local_count,
        'products_with_price_unassigned' => $products_with_price,
        'recommendation' => self::get_recommendation($vendor_count, $local_count, $products_with_price)
    ];
}

/**
 * 🆕 جدید: دریافت تعداد محصولات فروشنده از header API (بدون دانلود محصولات)
 */
private static function get_vendor_products_count_from_api($meta) {
    $api_url = trailingslashit($meta['url']) . 'wp-json/wc/v3/products';
    $auth = base64_encode($meta['key'] . ':' . $meta['secret']);
    
    $response = wp_remote_get(add_query_arg(['per_page' => 1], $api_url), [
        'headers' => [
            'Authorization' => 'Basic ' . $auth,
            'User-Agent' => 'VendorSync/1.0'
        ],
        'timeout' => 10,
    ]);
    
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        return 0;
    }
    
    $total_products = wp_remote_retrieve_header($response, 'x-wp-total');
    return $total_products ? intval($total_products) : 0;
}


    
    /**
     * پیشنهاد هوشمند
     */
    private static function get_recommendation($vendor_count, $assigned_count, $unassigned_with_price) {
        if ($vendor_count === 0) {
            return 'فروشنده محصولی ندارد';
        }
        
        if ($assigned_count === 0) {
            return 'اختصاص خودکار را اجرا کنید';
        }
        
        if ($assigned_count >= $vendor_count * 0.8) {
            return 'وضعیت خوب است';
        }
        
        if ($unassigned_with_price > 0) {
            return 'از اختصاص هوشمند استفاده کنید';
        }
        
        return 'اختصاص خودکار را اجرا کنید';
    }
}