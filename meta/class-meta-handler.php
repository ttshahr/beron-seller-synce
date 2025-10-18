<?php
if (!defined('ABSPATH')) exit;

class Vendor_Meta_Handler {
    
    /**
     * دریافت متاهای فروشنده + اعتبارسنجی
     */
    public static function get_vendor_meta($vendor_id) {
        $meta = [];
        
        foreach (Meta_Definitions::USER_META as $key => $definition) {
            $meta[$key] = get_user_meta($vendor_id, $key, true);
        }
        
        return $meta;
    }
    
    /**
     * اعتبارسنجی متاهای ضروری
     */
    public static function validate_vendor_meta($meta) {
        $errors = [];
        
        $required = ['vendor_website_url', 'vendor_consumer_key', 'vendor_consumer_secret'];
        
        foreach ($required as $key) {
            if (empty($meta[$key])) {
                $label = Meta_Definitions::USER_META[$key]['label'];
                $errors[] = "{$label} تنظیم نشده است.";
            }
        }
        
        return $errors;
    }
    
    /**
     * دریافت نام نمایشی فروشنده
     */
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