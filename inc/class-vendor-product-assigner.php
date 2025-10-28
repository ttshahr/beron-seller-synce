<?php
if (!defined('ABSPATH')) exit;

class Vendor_Product_Assigner {
    
    /**
     * تعداد محصولات واقعی فروشنده (بر اساس نویسنده)
     */
    public static function get_vendor_real_products_count($vendor_id) {
        global $wpdb;
        
        return (int) $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->posts} 
            WHERE post_type = 'product' 
            AND post_status = 'publish'
            AND post_author = %d
        ", $vendor_id));
    }
}