<?php
if (!defined('ABSPATH')) exit;

class Admin_Pages {
    
    public static function render_main_page() {
        ?>
        <div class="wrap">
            <h1>مدیریت همگام‌سازی محصولات فروشندگان</h1>
            
            <?php 
            Admin_Dashboard::render_dashboard_stats();
            Admin_Dashboard::render_vendors_list();
            ?>
            
            <div class="card" style="margin-top: 20px;">
                <h2>🚀 دسترسی سریع</h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 15px;">
                    <a href="<?php echo admin_url('admin.php?page=vendor-sync-prices'); ?>" class="button button-primary" style="text-align: center; padding: 15px;">
                        📥 دریافت قیمت‌های خام
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=vendor-sync-calculate'); ?>" class="button button-secondary" style="text-align: center; padding: 15px;">
                        🧮 محاسبه قیمت نهایی
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=vendor-sync-stocks'); ?>" class="button button-secondary" style="text-align: center; padding: 15px;">
                        📦 بروزرسانی موجودی
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=vendor-sync-debug'); ?>" class="button" style="text-align: center; padding: 15px;">
                        🔧 دیباگ
                    </a>
                </div>
            </div>
        </div>
        <?php
    }
    
    public static function render_price_sync_page() {
        Admin_Price_Pages::render_price_sync_page();
    }
    
    public static function render_calculate_page() {
        Admin_Calculate_Pages::render_calculate_page();
    }
    
    public static function render_stocks_page() {
        Admin_Stock_Pages::render_stocks_page();
    }
    
    public static function render_debug_page() {
        Admin_Debug_Pages::render_debug_page();
    }
    
    public static function render_profit_page() {
        $profit_calculator = new Sale_Profit_Calculator();
        $profit_calculator->render_page();
    }
}