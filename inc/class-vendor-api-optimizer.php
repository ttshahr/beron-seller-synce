<?php
if (!defined('ABSPATH')) exit;

class Vendor_API_Optimizer {
    
    public static function get_products_batch($meta, $page = 1, $per_page = 100) {
        $api_url = trailingslashit($meta['url']) . 'wp-json/wc/v3/products';
        $auth = base64_encode($meta['key'] . ':' . $meta['secret']);
        
        $args = [
            'page' => $page,
            'per_page' => $per_page,
            'fields' => 'id,sku,price,stock_status,stock_quantity,meta_data'
        ];
        
        $response = wp_remote_get(add_query_arg($args, $api_url), [
            'headers' => [
                'Authorization' => 'Basic ' . $auth,
                'User-Agent' => 'VendorSync/1.0'
            ],
            'timeout' => 45,
        ]);
        
        if (is_wp_error($response)) {
            Vendor_Logger::log_error('API Request Failed: ' . $response->get_error_message());
            return null;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            Vendor_Logger::log_error('API Response Error: HTTP ' . $response_code);
            return null;
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        return is_array($data) ? $data : null;
    }
    
    public static function get_all_products($meta) {
        $all_products = [];
        $page = 1;
        $max_pages = 50; // 50 صفحه = 5000 محصول
        
        do {
            $products = self::get_products_batch($meta, $page, 100);
            
            if (!empty($products) && !isset($products['code'])) {
                $all_products = array_merge($all_products, $products);
                Vendor_Logger::log_success(0, 'api_batch_fetched', 
                    'صفحه ' . $page . ' - ' . count($products) . ' محصول دریافت شد');
                
                $page++;
                
                // تاخیر هوشمند
                $delay = count($products) < 50 ? 1 : 2;
                sleep($delay);
                
            } else {
                Vendor_Logger::log_error('خطا در دریافت صفحه ' . $page);
                break;
            }
            
        } while (count($products) === 100 && $page <= $max_pages);
        
        Vendor_Logger::log_success(0, 'api_complete', 
            'دریافت API کامل شد. تعداد کل: ' . count($all_products) . ' محصول');
        
        return $all_products;
    }
    
    /**
     * 🆕 تست اتصال به API فروشنده
     */
    public static function test_connection($meta) {
        $api_url = trailingslashit($meta['url']) . 'wp-json/wc/v3/products';
        $auth = base64_encode($meta['key'] . ':' . $meta['secret']);
        
        $response = wp_remote_get(add_query_arg(['per_page' => 1], $api_url), [
            'headers' => [
                'Authorization' => 'Basic ' . $auth,
                'User-Agent' => 'VendorSync/1.0'
            ],
            'timeout' => 15,
        ]);
        
        if (is_wp_error($response)) {
            return [
                'success' => false, 
                'error' => $response->get_error_message(),
                'details' => 'خطا در ارتباط با سرور فروشنده'
            ];
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        
        if ($response_code === 200) {
            $total_products = wp_remote_retrieve_header($response, 'x-wp-total');
            return [
                'success' => true, 
                'total_products' => $total_products ? intval($total_products) : 'نامشخص',
                'message' => 'اتصال موفق - تعداد محصولات: ' . $total_products
            ];
        } elseif ($response_code === 401) {
            return [
                'success' => false, 
                'error' => 'احراز هویت نامعتبر',
                'details' => 'کلید یا رمز API نادرست است'
            ];
        } elseif ($response_code === 404) {
            return [
                'success' => false, 
                'error' => 'آدرس API یافت نشد',
                'details' => 'آدرس وبسایت فروشنده نادرست است'
            ];
        } else {
            return [
                'success' => false, 
                'error' => 'خطای HTTP: ' . $response_code,
                'details' => wp_remote_retrieve_body($response)
            ];
        }
    }
    
    /**
     * 🆕 دریافت اطلاعات پایه فروشنده
     */
    public static function get_vendor_info($meta) {
        $connection_test = self::test_connection($meta);
        
        if (!$connection_test['success']) {
            return $connection_test;
        }
        
        // دریافت اولین محصول برای بررسی ساختار داده
        $products = self::get_products_batch($meta, 1, 1);
        
        $info = [
            'success' => true,
            'connection' => $connection_test,
            'sample_product' => !empty($products) ? $products[0] : null,
            'has_meta_data' => false,
            'currency' => 'unknown',
            'price_meta_keys' => []
        ];
        
        if (!empty($products)) {
            $sample = $products[0];
            
            // بررسی وجود متادیتا
            if (isset($sample['meta_data']) && is_array($sample['meta_data'])) {
                $info['has_meta_data'] = true;
                $info['meta_data_count'] = count($sample['meta_data']);
                
                // استخراج کلیدهای متای قیمت
                foreach ($sample['meta_data'] as $meta_item) {
                    if (isset($meta_item['key']) && stripos($meta_item['key'], 'price') !== false) {
                        $info['price_meta_keys'][] = $meta_item['key'];
                    }
                }
            }
            
            // تشخیص ارز
            if (isset($sample['price'])) {
                $price = floatval($sample['price']);
                if ($price > 100000) { // اگر قیمت خیلی بالاست، احتمالاً ریال است
                    $info['currency'] = 'rial';
                } else {
                    $info['currency'] = 'toman';
                }
            }
        }
        
        return $info;
    }
}