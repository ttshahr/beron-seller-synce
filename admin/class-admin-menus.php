<?php
if (!defined('ABSPATH')) exit;

class Admin_Menus {
    
    public function __construct() {
        add_action('admin_menu', [$this, 'add_sync_menus']);
    }
    
    public function add_sync_menus() {
        // منوی اصلی
        add_menu_page(
            'همگام‌سازی فروشندگان',
            'همگام‌سازی فروشندگان',
            'manage_woocommerce',
            'vendor-sync',
            ['Admin_Pages', 'render_main_page'],
            'dashicons-update',
            56
        );
        
        // زیرمنوها
        add_submenu_page(
            'vendor-sync',
            'دریافت قیمت‌های خام',
            'دریافت قیمت‌ها',
            'manage_woocommerce',
            'vendor-sync-prices',
            ['Admin_Pages', 'render_sync_prices_page']
        );
        
        add_submenu_page(
            'vendor-sync',
            'محاسبه قیمت نهایی',
            'محاسبه قیمت‌ها',
            'manage_woocommerce',
            'vendor-sync-calculate',
            ['Admin_Pages', 'render_calculate_page']
        );
        
        add_submenu_page(
            'vendor-sync',
            'بروزرسانی موجودی',
            'بروزرسانی موجودی',
            'manage_woocommerce',
            'vendor-sync-stocks',
            ['Admin_Pages', 'render_stocks_page']
        );
        
        add_submenu_page(
            'vendor-sync',
            'دیباگ همگام‌سازی',
            'دیباگ',
            'manage_woocommerce',
            'vendor-sync-debug',
            ['Admin_Pages', 'render_debug_page']
        );
        
        add_submenu_page(
            'vendor-sync',
            'محاسبه سود فروش',
            'محاسبه سود فروش',
            'manage_woocommerce',
            'vendor-sync-profit',
            ['Admin_Pages', 'render_profit_page'] // این متد باید در Admin_Pages تعریف شود
        );
    }
}

new Admin_Menus();