<?php
if (!defined('ABSPATH')) exit;

class Admin_Common {
    
    public static function render_common_stats() {
        // فقط نمایش پیام‌های نتیجه - بدون کارت آمار
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
    }
    
    // متد count_products_with_meta حذف شد چون استفاده نمی‌شود
}