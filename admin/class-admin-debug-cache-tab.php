<?php
if (!defined('ABSPATH')) exit;

class Admin_Debug_Cache_Tab {
    
    private static $cache_result = '';
    
    public static function render() {
        // اگر درخواست پاکسازی کش ارسال شده
        if (isset($_POST['flush_cache']) && wp_verify_nonce($_POST['_wpnonce'], 'flush_cache')) {
            self::$cache_result = self::flush_all_caches();
        }
        ?>
        
        <div class="cache-cleaner-container">
            
            <!-- کارت اصلی پاکسازی کش -->
            <div class="card full-width-card">
                <h2>🧹 پاکسازی کامل کش سیستم</h2>
                
                <?php if (self::$cache_result): ?>
                    <div class="notice notice-success is-dismissible">
                        <p><?php echo esc_html(self::$cache_result); ?></p>
                    </div>
                <?php endif; ?>
                
                <div class="cache-info-section">
                    <div class="cache-warning">
                        <h3>⚠️ توجه مهم</h3>
                        <p>این عملیات کش‌های زیر را پاکسازی می‌کند:</p>
                        <ul>
                            <li>✅ کش وردپرس (Object Cache)</li>
                            <li>✅ کش ووکامرس (Product Transients)</li>
                            <li>✅ کش محصولات ویژه و حراج</li>
                            <li>✅ کش افزونه Advanced Bulk Edit</li>
                            <li>✅ کش تمام محصولات و واریانت‌ها</li>
                            <li>✅ کش جستجو و فیلترها</li>
                        </ul>
                        <p><strong>زمان اجرا:</strong> بسته به تعداد محصولات، ممکن است چند ثانیه طول بکشد.</p>
                    </div>
                    
                    <div class="cache-stats">
                        <h3>📊 وضعیت فعلی کش</h3>
                        <div class="stats-grid">
                            <div class="stat-item">
                                <span class="stat-label">تعداد محصولات:</span>
                                <span class="stat-value"><?php echo self::get_products_count(); ?></span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label">تعداد واریانت‌ها:</span>
                                <span class="stat-value"><?php echo self::get_variations_count(); ?></span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label">حافظه مصرفی:</span>
                                <span class="stat-value"><?php echo self::get_memory_usage(); ?></span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label">آخرین پاکسازی:</span>
                                <span class="stat-value"><?php echo self::get_last_cache_flush(); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <form method="post" onsubmit="return confirm('⚠️ آیا مطمئن هستید که می‌خواهید تمام کش‌های سیستم پاک شوند؟ این عمل ممکن است موقتاً سرعت سایت را کاهش دهد.');">
                    <?php wp_nonce_field('flush_cache'); ?>
                    <input type="hidden" name="flush_cache" value="1">
                    
                    <div style="text-align: center; margin: 30px 0;">
                        <?php submit_button('🚀 شروع پاکسازی کامل کش‌ها', 'primary large', 'flush_cache_btn', false); ?>
                    </div>
                </form>
                
                <div class="cache-tips">
                    <h3>💡 راهنمایی</h3>
                    <p>پس از عملیات همگام‌سازی قیمت یا موجودی، بهتر است کش پاکسازی شود تا تغییرات بلافاصله در سایت نمایش داده شوند.</p>
                </div>
            </div>
            
            <!-- کارت لاگ پاکسازی -->
            <div class="card full-width-card">
                <h3>📝 تاریخچه پاکسازی کش</h3>
                <div class="cache-logs">
                    <?php echo self::get_cache_flush_logs(); ?>
                </div>
            </div>
            
        </div>

        <style>
            .cache-cleaner-container {
                display: flex;
                flex-direction: column;
                gap: 20px;
            }

            .cache-info-section {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 20px;
                margin: 20px 0;
            }

            @media (max-width: 768px) {
                .cache-info-section {
                    grid-template-columns: 1fr;
                }
            }

            .cache-warning {
                background: #fff3cd;
                border: 1px solid #ffeaa7;
                border-radius: 8px;
                padding: 20px;
            }

            .cache-warning h3 {
                color: #856404;
                margin-top: 0;
            }

            .cache-warning ul {
                margin: 10px 0;
                padding-right: 20px;
            }

            .cache-warning li {
                margin-bottom: 5px;
            }

            .cache-stats {
                background: #f8f9fa;
                border: 1px solid #e9ecef;
                border-radius: 8px;
                padding: 20px;
            }

            .cache-stats h3 {
                margin-top: 0;
                color: #495057;
            }

            .stats-grid {
                display: grid;
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .stat-item {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 8px 0;
                border-bottom: 1px solid #dee2e6;
            }

            .stat-item:last-child {
                border-bottom: none;
            }

            .stat-label {
                font-weight: 600;
                color: #6c757d;
            }

            .stat-value {
                font-weight: 700;
                color: #0073aa;
                background: #e7f3ff;
                padding: 2px 8px;
                border-radius: 4px;
                font-size: 12px;
            }

            .cache-tips {
                background: #d1ecf1;
                border: 1px solid #bee5eb;
                border-radius: 8px;
                padding: 15px;
                margin-top: 20px;
            }

            .cache-tips h3 {
                color: #0c5460;
                margin-top: 0;
            }

            .cache-logs {
                max-height: 200px;
                overflow-y: auto;
                background: #f8f9fa;
                border: 1px solid #e9ecef;
                border-radius: 4px;
                padding: 15px;
                font-family: 'Courier New', monospace;
                font-size: 12px;
            }

            .cache-log-entry {
                padding: 5px 0;
                border-bottom: 1px solid #dee2e6;
            }

            .cache-log-entry:last-child {
                border-bottom: none;
            }

            .cache-log-time {
                color: #6c757d;
                font-weight: 600;
            }

            .cache-log-message {
                color: #495057;
            }

            .button-large {
                font-size: 16px;
                padding: 12px 30px;
                height: auto;
            }
        </style>
        <?php
    }
    
    /**
     * پاکسازی کامل کش‌های سیستم
     */
    public static function flush_all_caches() {
        global $wpdb;
        
        $steps = [];
        $start_time = microtime(true);
        
        try {
            // 1. پاکسازی کش وردپرس
            wp_cache_flush();
            $steps[] = 'کش وردپرس پاک شد';
            
            // 2. پاکسازی ترنزینت‌های ووکامرس
            delete_transient('wc_products_onsale');
            delete_transient('wc_featured_products');
            delete_transient('wc_count_comments');
            $steps[] = 'ترنزینت‌های ووکامرس پاک شدند';
            
            // 3. پاکسازی کش ووکامرس
            if (function_exists('wc_delete_product_transients')) {
                do_action('woocommerce_flush_product_transients');
                $steps[] = 'کش محصولات ووکامرس پاک شد';
            }
            
            // 4. پاکسازی کش تمام محصولات
            $product_ids = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type IN ('product','product_variation')");
            if ($product_ids) {
                foreach ($product_ids as $pid) {
                    if (function_exists('wc_delete_product_transients')) {
                        wc_delete_product_transients($pid);
                    }
                    clean_post_cache($pid);
                }
                $steps[] = sprintf('کش %d محصول پاک شد', count($product_ids));
            }
            
            // 5. پاکسازی کش افزونه Advanced Bulk Edit
            $table_like = $wpdb->prefix . "abep_%";
            $temp_tables = $wpdb->get_col($wpdb->prepare("SHOW TABLES LIKE %s", $table_like));
            if ($temp_tables) {
                foreach ($temp_tables as $table) {
                    $wpdb->query("TRUNCATE TABLE {$table}");
                }
                $steps[] = sprintf('کش افزونه Advanced Bulk Edit (%d جدول) پاک شد', count($temp_tables));
            }
            
            // 6. بازسازی نسخه ترنزینت محصول
            if (class_exists('WC_Cache_Helper')) {
                WC_Cache_Helper::get_transient_version('product', true);
                $steps[] = 'نسخه کش محصولات بازسازی شد';
            }
            
            // 7. پاکسازی کش ترم‌ها و دسته‌ها
            $taxonomies = ['product_cat', 'product_tag', 'product_brand'];
            foreach ($taxonomies as $taxonomy) {
                delete_transient("wc_{$taxonomy}_children");
            }
            $steps[] = 'کش دسته‌بندی‌ها پاک شد';
            
            // 8. پاکسازی کش جستجو
            if (function_exists('wc_regenerate_size_attributes_lookup_table')) {
                delete_transient('wc_attribute_lookup_table_exists');
            }
            
            $execution_time = round(microtime(true) - $start_time, 2);
            
            // ثبت در لاگ
            Vendor_Logger::log_info(sprintf(
                'Cache flushed successfully - %d steps - %s seconds',
                count($steps),
                $execution_time
            ));
            
            // ذخیره لاگ پاکسازی
            self::log_cache_flush($steps, $execution_time);
            
            return sprintf(
                '✅ پاکسازی کش با موفقیت انجام شد! (%d مرحله - %s ثانیه)',
                count($steps),
                $execution_time
            );
            
        } catch (Exception $e) {
            Vendor_Logger::log_error('Cache flush failed: ' . $e->getMessage());
            return '❌ خطا در پاکسازی کش: ' . $e->getMessage();
        }
    }
    
    /**
     * دریافت تعداد محصولات
     */
    private static function get_products_count() {
        global $wpdb;
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish'");
        return number_format($count) . ' محصول';
    }
    
    /**
     * دریافت تعداد واریانت‌ها
     */
    private static function get_variations_count() {
        global $wpdb;
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'product_variation' AND post_status = 'publish'");
        return number_format($count) . ' واریانت';
    }
    
    /**
     * دریافت مصرف حافظه
     */
    private static function get_memory_usage() {
        return size_format(memory_get_usage(true));
    }
    
    /**
     * دریافت آخرین زمان پاکسازی کش
     */
    private static function get_last_cache_flush() {
        $last_flush = get_option('beron_last_cache_flush', 'هرگز');
        return $last_flush;
    }
    
    /**
     * ثبت لاگ پاکسازی کش
     */
    private static function log_cache_flush($steps, $execution_time) {
        $log_entry = [
            'time' => current_time('mysql'),
            'steps' => $steps,
            'execution_time' => $execution_time,
            'user' => get_current_user_id()
        ];
        
        $logs = get_option('beron_cache_flush_logs', []);
        array_unshift($logs, $log_entry);
        $logs = array_slice($logs, 0, 10); // فقط 10 لاگ آخر
        
        update_option('beron_cache_flush_logs', $logs);
        update_option('beron_last_cache_flush', current_time('Y-m-d H:i:s'));
    }
    
    /**
     * دریافت لاگ‌های پاکسازی کش
     */
    private static function get_cache_flush_logs() {
        $logs = get_option('beron_cache_flush_logs', []);
        
        if (empty($logs)) {
            return '<p>هنوز هیچ پاکسازی کشی انجام نشده است.</p>';
        }
        
        $output = '';
        foreach ($logs as $log) {
            $output .= '<div class="cache-log-entry">';
            $output .= '<span class="cache-log-time">' . date('Y-m-d H:i:s', strtotime($log['time'])) . '</span> - ';
            $output .= '<span class="cache-log-message">' . count($log['steps']) . ' مرحله (' . $log['execution_time'] . ' ثانیه)</span>';
            $output .= '</div>';
        }
        
        return $output;
    }
}