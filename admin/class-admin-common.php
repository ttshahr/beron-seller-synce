<?php
if (!defined('ABSPATH')) exit;

class Admin_Common {
    
    public static function render_common_stats() {
        $products_with_raw_price = self::count_products_with_meta('_seller_list_price');
        $products_with_final_price = self::count_products_with_meta('_vendor_final_price');
        
        // نمایش پیام‌های نتیجه
        if (isset($_GET['saved'])) {
            echo '<div class="notice notice-success"><p>✅ قیمت‌های خام با موفقیت ذخیره شدند. تعداد: ' . intval($_GET['saved']) . '</p></div>';
        }
        if (isset($_GET['calculated'])) {
            echo '<div class="notice notice-success"><p>✅ قیمت‌های نهایی با موفقیت محاسبه شدند. تعداد: ' . intval($_GET['calculated']) . '</p></div>';
        }
        if (isset($_GET['updated'])) {
            echo '<div class="notice notice-success"><p>✅ موجودی با موفقیت بروزرسانی شد. تعداد: ' . intval($_GET['updated']) . '</p></div>';
        }
        if (isset($_GET['assigned'])) {
            echo '<div class="notice notice-success"><p>✅ محصولات با موفقیت به فروشنده اختصاص داده شدند. تعداد: ' . intval($_GET['assigned']) . '</p></div>';
        }
        if (isset($_GET['error'])) {
            echo '<div class="notice notice-error"><p>❌ خطا در پردازش. لطفا لاگ را بررسی کنید.</p></div>';
        }
        ?>
        <div class="card">
            <h3>📈 آمار همگام‌سازی</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <div style="text-align: center; padding: 15px; background: #f0f9ff; border-radius: 5px;">
                    <div style="font-size: 24px; font-weight: bold; color: #1e40af;"><?php echo $products_with_raw_price; ?></div>
                    <div>محصولات با قیمت خام</div>
                </div>
                <div style="text-align: center; padding: 15px; background: #f0fdf4; border-radius: 5px;">
                    <div style="font-size: 24px; font-weight: bold; color: #15803d;"><?php echo $products_with_final_price; ?></div>
                    <div>محصولات با قیمت نهایی</div>
                </div>
            </div>
        </div>
        <?php
    }
    
    public static function count_products_with_meta($meta_key) {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->postmeta} 
            WHERE meta_key = %s AND meta_value > '0'
        ", $meta_key));
    }
}