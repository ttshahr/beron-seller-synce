<?php
if (!defined('ABSPATH')) exit;

class Price_Calc_Handler {
    
    public function __construct() {
        add_action('admin_post_calculate_vendor_prices', [$this, 'handle_calculate_request']);
    }
    
    public function handle_calculate_request() {
        if (!current_user_can('manage_woocommerce')) wp_die('دسترسی غیرمجاز');
        
        set_time_limit(300);
        ini_set('memory_limit', '256M');

        $vendor_id = intval($_POST['vendor_id']);
        $cat_id = sanitize_text_field($_POST['product_cat']);

        try {
            $calculated_count = Vendor_Price_Calculator::calculate_final_prices($vendor_id, $cat_id);
            wp_redirect(admin_url('admin.php?page=vendor-sync-calculate&calculated=' . $calculated_count));
            exit;
        } catch (Exception $e) {
            error_log('Price Calculate Error: ' . $e->getMessage());
            wp_redirect(admin_url('admin.php?page=vendor-sync-calculate&error=1'));
            exit;
        }
    }
}

new Price_Calc_Handler();