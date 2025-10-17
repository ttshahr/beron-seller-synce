<?php
if (!defined('ABSPATH')) exit;

class Vendor_API_Optimizer {
    
    private $vendor_id;
    private $meta;
    
    public function __construct($vendor_id = null) {
        if ($vendor_id) {
            $this->vendor_id = $vendor_id;
            $this->meta = Vendor_Meta_Handler::get_vendor_meta($vendor_id);
        }
    }
    
    /**
     * دریافت تک محصول بر اساس SKU
     */
    public static function get_product_by_sku($meta, $sku) {
        $start_time = microtime(true);
        $api_url = trailingslashit($meta['url']) . 'wp-json/wc/v3/products';
        $auth = base64_encode($meta['key'] . ':' . $meta['secret']);

        $response = wp_remote_get(add_query_arg('sku', $sku, $api_url), [
            'headers' => ['Authorization' => 'Basic ' . $auth],
            'timeout' => 20,
        ]);

        $response_time = round(microtime(true) - $start_time, 2);
        $success = !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;

        // ثبت لاگ درخواست API
        Vendor_Logger::log_api_request($api_url, $sku, $success, null, $response_time);

        if (is_wp_error($response)) {
            Vendor_Logger::log_error('API Error for SKU ' . $sku . ': ' . $response->get_error_message(), null, null);
            return null;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        $product = (!empty($data) && isset($data[0])) ? $data[0] : null;
        
        if (!$product) {
            Vendor_Logger::log_warning('Product not found for SKU: ' . $sku, null, null);
        }
        
        return $product;
    }
    
    /**
     * دریافت دسته‌ای محصولات
     */
    public static function get_products_batch($meta, $page = 1, $per_page = 100, $vendor_id = null) {
        $start_time = microtime(true);
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
            'timeout' => 300,
        ]);
        
        $response_time = round(microtime(true) - $start_time, 2);
        $success = !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;

        // ثبت لاگ درخواست API
        Vendor_Logger::log_api_request($api_url, "Batch Page {$page}", $success, $vendor_id, $response_time);
        
        if (is_wp_error($response)) {
            Vendor_Logger::log_error('API Batch Request Failed: ' . $response->get_error_message(), null, $vendor_id);
            return null;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            Vendor_Logger::log_error('API Batch Response Error: HTTP ' . $response_code, null, $vendor_id);
            return null;
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        if (is_array($data)) {
            Vendor_Logger::log_info("Batch page {$page} fetched: " . count($data) . " products", $vendor_id);
        }
        
        return is_array($data) ? $data : null;
    }
    
    /**
     * دریافت تمام محصولات فروشنده
     */
    public static function get_all_products($meta, $vendor_id = null) {
        Vendor_Logger::log_info('Starting to fetch all products from vendor', $vendor_id);
        
        $all_products = [];
        $page = 1;
        $max_pages = 50;
        $total_products = 0;
        
        do {
            $products = self::get_products_batch($meta, $page, 100, $vendor_id);
            
            if (!empty($products) && !isset($products['code'])) {
                $all_products = array_merge($all_products, $products);
                $batch_count = count($products);
                $total_products += $batch_count;
                
                Vendor_Logger::log_success(
                    0, 
                    'api_batch_fetched', 
                    $vendor_id, 
                    "Page {$page} - {$batch_count} products (Total: {$total_products})"
                );
                
                $page++;
                
                // تاخیر هوشمند
                $delay = $batch_count < 50 ? 1 : 2;
                sleep($delay);
                
            } else {
                Vendor_Logger::log_error('Error fetching page ' . $page, null, $vendor_id);
                break;
            }
            
        } while (count($products) === 100 && $page <= $max_pages);
        
        Vendor_Logger::log_success(
            0, 
            'api_complete', 
            $vendor_id, 
            "API fetch completed. Total products: " . count($all_products)
        );
        
        return $all_products;
    }
    
    /**
     * تست اتصال به API فروشنده
     */
    public static function test_connection($meta, $vendor_id = null) {
        Vendor_Logger::log_info('Testing API connection', $vendor_id);
        
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
            $error_msg = $response->get_error_message();
            Vendor_Logger::log_error('Connection test failed: ' . $error_msg, null, $vendor_id);
            return [
                'success' => false, 
                'error' => $error_msg,
                'details' => 'خطا در ارتباط با سرور فروشنده'
            ];
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        
        if ($response_code === 200) {
            $total_products = wp_remote_retrieve_header($response, 'x-wp-total');
            Vendor_Logger::log_success(0, 'connection_test', $vendor_id, "Successful - Products: " . ($total_products ?: 'Unknown'));
            
            return [
                'success' => true, 
                'total_products' => $total_products ? intval($total_products) : 'نامشخص',
                'message' => 'اتصال موفق - تعداد محصولات: ' . $total_products
            ];
        } elseif ($response_code === 401) {
            Vendor_Logger::log_error('Connection test failed: Authentication invalid', null, $vendor_id);
            return [
                'success' => false, 
                'error' => 'احراز هویت نامعتبر',
                'details' => 'کلید یا رمز API نادرست است'
            ];
        } elseif ($response_code === 404) {
            Vendor_Logger::log_error('Connection test failed: API URL not found', null, $vendor_id);
            return [
                'success' => false, 
                'error' => 'آدرس API یافت نشد',
                'details' => 'آدرس وبسایت فروشنده نادرست است'
            ];
        } else {
            Vendor_Logger::log_error('Connection test failed: HTTP ' . $response_code, null, $vendor_id);
            return [
                'success' => false, 
                'error' => 'خطای HTTP: ' . $response_code,
                'details' => wp_remote_retrieve_body($response)
            ];
        }
    }
    
    /**
     * دریافت اطلاعات پایه فروشنده
     */
    public static function get_vendor_info($meta, $vendor_id = null) {
        Vendor_Logger::log_info('Fetching vendor information', $vendor_id);
        
        $connection_test = self::test_connection($meta, $vendor_id);
        
        if (!$connection_test['success']) {
            return $connection_test;
        }
        
        // دریافت اولین محصول برای بررسی ساختار داده
        $products = self::get_products_batch($meta, 1, 1, $vendor_id);
        
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
                if ($price > 100000) {
                    $info['currency'] = 'rial';
                } else {
                    $info['currency'] = 'toman';
                }
            }
        }
        
        Vendor_Logger::log_info('Vendor info analysis completed', $vendor_id);
        
        return $info;
    }
}