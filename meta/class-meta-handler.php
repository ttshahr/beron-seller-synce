<?php
if (!defined('ABSPATH')) exit;

class Vendor_Meta_Handler {
    
    public static function get_vendor_meta($vendor_id) {
        return [
            'url' => get_user_meta($vendor_id, 'vendor_website_url', true),
            'key' => get_user_meta($vendor_id, 'vendor_consumer_key', true),
            'secret' => get_user_meta($vendor_id, 'vendor_consumer_secret', true),
            'currency' => get_user_meta($vendor_id, 'vendor_currency', true),
            'stock_type' => get_user_meta($vendor_id, 'vendor_stock_type', true),
            'price_meta_key' => get_user_meta($vendor_id, 'vendor_cooperation_price_meta_key', true),
            'conversion_percent' => get_user_meta($vendor_id, 'vendor_price_conversion_percent', true),
        ];
    }
    
    public static function validate_vendor_meta($meta) {
        $errors = [];
        
        if (empty($meta['url'])) {
            $errors[] = 'آدرس وبسایت فروشنده تنظیم نشده است.';
        }
        
        if (empty($meta['key'])) {
            $errors[] = 'کلید API فروشنده تنظیم نشده است.';
        }
        
        if (empty($meta['secret'])) {
            $errors[] = 'رمز API فروشنده تنظیم نشده است.';
        }
        
        return $errors;
    }
    
    public static function get_vendor_display_name($vendor_id) {
        $vendor = get_userdata($vendor_id);
        return $vendor ? $vendor->display_name : 'فروشنده ناشناس';
    }
    
    /**
     * جدید: بررسی آیا کاربر فروشنده است
     */
    public static function is_vendor_user($user_id) {
        $user = get_userdata($user_id);
        return $user && (in_array('hamkar', $user->roles) || in_array('seller', $user->roles));
    }
}