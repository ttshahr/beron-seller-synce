<?php
if (!defined('ABSPATH')) exit;

class Vendor_Sync_Batch_Processor {
    
    public static function process_batch($vendor_id, $cat_id, $sync_type, $page = 1, $per_page = 50) {
        $meta = Vendor_Meta_Handler::get_vendor_meta($vendor_id);
        $product_ids = self::get_product_ids_paginated($cat_id, $page, $per_page);
        
        $results = [
            'processed' => 0,
            'success' => 0,
            'failed' => 0,
            'next_page' => 0
        ];
        
        foreach ($product_ids as $product_id) {
            try {
                $success = self::process_single_product($product_id, $meta, $sync_type);
                $success ? $results['success']++ : $results['failed']++;
                $results['processed']++;
                
                // استراحت بین درخواست‌های API
                usleep(500000); // 0.5 ثانیه
                
            } catch (Exception $e) {
                $results['failed']++;
                Vendor_Logger::log_error($e->getMessage(), $product_id);
            }
        }
        
        // بررسی آیا صفحه بعدی وجود دارد
        $next_page_ids = self::get_product_ids_paginated($cat_id, $page + 1, 1);
        $results['next_page'] = !empty($next_page_ids) ? $page + 1 : 0;
        
        return $results;
    }
    
    private static function get_product_ids_paginated($cat_id, $page, $per_page) {
        $args = [
            'post_type' => 'product',
            'posts_per_page' => $per_page,
            'offset' => ($page - 1) * $per_page,
            'post_status' => 'publish',
            'fields' => 'ids', // فقط ID ها
            'no_found_rows' => true, // صرفه‌جویی در پردازش
            'update_post_meta_cache' => false, // غیرفعال کش متا
            'update_post_term_cache' => false // غیرفعال کش تاکسونومی
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