<?php
if (!defined('ABSPATH')) exit;

class Product_Assign_Handler {
    
    public function __construct() {
        add_action('admin_post_assign_vendor_products', [$this, 'handle_assign_products_request']);
        add_action('admin_post_assign_smart_vendor_products', [$this, 'handle_smart_assign_request']);
        add_action('admin_post_test_vendor_connection', [$this, 'handle_test_connection_request']);
    }
    
    public function handle_assign_products_request() {
        if (!current_user_can('manage_woocommerce')) wp_die('دسترسی غیرمجاز');
        
        set_time_limit(600);
        ini_set('memory_limit', '512M');
        wp_suspend_cache_addition(true);

        $vendor_id = intval($_POST['vendor_id']);

        try {
            $assigned_count = Vendor_Product_Assigner::assign_vendor_to_products($vendor_id);
            wp_redirect(admin_url('admin.php?page=vendor-sync-stocks&assigned=' . $assigned_count));
            exit;
        } catch (Exception $e) {
            error_log('Product Assignment Error: ' . $e->getMessage());
            wp_redirect(admin_url('admin.php?page=vendor-sync-stocks&error=1'));
            exit;
        }
    }
    
    public function handle_smart_assign_request() {
        if (!current_user_can('manage_woocommerce')) wp_die('دسترسی غیرمجاز');
        
        set_time_limit(300);
        ini_set('memory_limit', '256M');

        $vendor_id = intval($_POST['vendor_id']);

        try {
            $assigned_count = Vendor_Product_Assigner::assign_products_with_prices($vendor_id);
            wp_redirect(admin_url('admin.php?page=vendor-sync-stocks&assigned=' . $assigned_count));
            exit;
        } catch (Exception $e) {
            error_log('Smart Assignment Error: ' . $e->getMessage());
            wp_redirect(admin_url('admin.php?page=vendor-sync-stocks&error=1'));
            exit;
        }
    }
    
    public function handle_test_connection_request() {
        if (!current_user_can('manage_woocommerce')) wp_die('دسترسی غیرمجاز');
        
        $vendor_id = intval($_POST['vendor_id']);
        $meta = Vendor_Meta_Handler::get_vendor_meta($vendor_id);
        
        echo '<div class="wrap">';
        echo '<h1>نتایج تست اتصال</h1>';
        echo '<div class="card">';
        
        try {
            $connection_test = Vendor_API_Optimizer::test_connection($meta);
            
            if ($connection_test['success']) {
                echo '<div style="color: green; font-weight: bold;">✅ اتصال موفق</div>';
                echo '<ul>';
                echo '<li>تعداد محصولات: ' . ($connection_test['total_products'] ?? 'نامشخص') . '</li>';
                echo '<li>پیام: ' . ($connection_test['message'] ?? '') . '</li>';
                echo '</ul>';
            } else {
                echo '<div style="color: red; font-weight: bold;">❌ اتصال ناموفق</div>';
                echo '<ul>';
                echo '<li>خطا: ' . ($connection_test['error'] ?? 'نامشخص') . '</li>';
                echo '<li>جزئیات: ' . ($connection_test['details'] ?? '') . '</li>';
                echo '</ul>';
            }
            
        } catch (Exception $e) {
            echo '<div style="color: red; font-weight: bold;">❌ خطا در تست اتصال</div>';
            echo '<p>' . $e->getMessage() . '</p>';
        }
        
        echo '</div>';
        echo '<a href="' . admin_url('admin.php?page=vendor-sync-stocks') . '" class="button">بازگشت</a>';
        echo '</div>';
        exit;
    }
}

new Product_Assign_Handler();