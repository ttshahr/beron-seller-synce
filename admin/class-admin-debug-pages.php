<?php
if (!defined('ABSPATH')) exit;

class Admin_Debug_Pages {
    
    public static function render_debug_page() {
        $vendors = get_users(['role__in' => ['hamkar', 'seller']]);
        $selected_vendor = isset($_GET['vendor_id']) ? intval($_GET['vendor_id']) : 0;
        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'vendor_debug';
        
        // عملیات پاک کردن لاگ‌ها
        if (isset($_POST['clear_logs']) && wp_verify_nonce($_POST['_wpnonce'], 'clear_logs')) {
            self::clear_log_files();
        }
        ?>
        
        <div class="wrap vendor-sync-debug-page">
            <h1>دیباگ همگام‌سازی فروشندگان</h1>
            
            <!-- تب‌ها -->
            <h2 class="nav-tab-wrapper">
                <a href="<?php echo add_query_arg(['tab' => 'vendor_debug']); ?>" class="nav-tab <?php echo $current_tab === 'vendor_debug' ? 'nav-tab-active' : ''; ?>">
                    دیباگ فروشندگان
                </a>
                <a href="<?php echo add_query_arg(['tab' => 'log_viewer']); ?>" class="nav-tab <?php echo $current_tab === 'log_viewer' ? 'nav-tab-active' : ''; ?>">
                    مشاهده و مدیریت لاگ‌ها
                </a>
            </h2>
            
            <?php if ($current_tab === 'vendor_debug'): ?>
                <!-- تب دیباگ فروشندگان -->
                <div class="card full-width-card">
                    <h2>انتخاب فروشنده برای بررسی</h2>
                    <form method="get">
                        <input type="hidden" name="page" value="vendor-sync-debug">
                        <input type="hidden" name="tab" value="vendor_debug">
                        <select name="vendor_id" required style="min-width: 300px;">
                            <option value="">-- انتخاب فروشنده --</option>
                            <?php foreach ($vendors as $vendor): ?>
                                <option value="<?php echo $vendor->ID; ?>" <?php selected($selected_vendor, $vendor->ID); ?>>
                                    <?php echo esc_html($vendor->display_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php submit_button('بررسی', 'primary'); ?>
                    </form>
                </div>
                
                <?php if ($selected_vendor): ?>
                    <?php Vendor_Debug_Helper::render_debug_page($selected_vendor); ?>
                <?php endif; ?>
                
            <?php elseif ($current_tab === 'log_viewer'): ?>
                <!-- تب مشاهده و مدیریت لاگ‌ها -->
                <?php self::render_log_viewer_tab(); ?>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * رندر تب مشاهده و مدیریت لاگ‌ها
     */
    public static function render_log_viewer_tab() {
        $log_types = [
            'error' => 'خطاها',
            'success' => 'موفقیت‌ها', 
            'info' => 'اطلاعات عمومی',
            'warning' => 'هشدارها',
            'api' => 'درخواست‌های API',
            'debug' => 'دیباگ',
            'general' => 'همه رویدادها'
        ];
        
        $selected_log_type = isset($_GET['log_type']) ? sanitize_text_field($_GET['log_type']) : 'general';
        $log_lines = isset($_GET['lines']) ? intval($_GET['lines']) : 100;
        
        // دریافت لاگ‌ها
        $logs = Vendor_Logger::get_recent_logs($selected_log_type, $log_lines);
        $log_stats = Vendor_Logger::get_log_stats();
        $health_check = Vendor_Logger::health_check();
        ?>
        
        <div class="log-viewer-container">
            
            <!-- ستون سمت چپ - نمایش لاگ‌ها -->
            <div class="log-display-section">
                <div class="card full-width-card" style="max-width: none !important; width: 100% !important; height: 100%; display: flex; flex-direction: column;">
                    <h2>👀 مشاهده لاگ‌ها</h2>
                    
                    <!-- فیلترها -->
                    <div class="log-filters">
                        <form method="get" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                            <input type="hidden" name="page" value="vendor-sync-debug">
                            <input type="hidden" name="tab" value="log_viewer">
                            
                            <div style="display: flex; align-items: center; gap: 5px;">
                                <label for="log_type" style="font-weight: bold;">نوع لاگ:</label>
                                <select name="log_type" id="log_type" style="min-width: 150px;">
                                    <?php foreach ($log_types as $key => $name): ?>
                                        <option value="<?php echo esc_attr($key); ?>" <?php selected($selected_log_type, $key); ?>>
                                            <?php echo esc_html($name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div style="display: flex; align-items: center; gap: 5px;">
                                <label for="lines" style="font-weight: bold;">تعداد خطوط:</label>
                                <select name="lines" id="lines" style="min-width: 100px;">
                                    <option value="50" <?php selected($log_lines, 50); ?>>50</option>
                                    <option value="100" <?php selected($log_lines, 100); ?>>100</option>
                                    <option value="200" <?php selected($log_lines, 200); ?>>200</option>
                                    <option value="500" <?php selected($log_lines, 500); ?>>500</option>
                                    <option value="1000" <?php selected($log_lines, 1000); ?>>1000</option>
                                </select>
                            </div>
                            
                            <?php submit_button('مشاهده', 'primary'); ?>
                            
                            <?php if (!empty($logs)): ?>
                                <span style="color: #666; font-size: 13px;">
                                    (<?php echo count($logs); ?> خط نمایش داده می‌شود)
                                </span>
                            <?php endif; ?>
                        </form>
                    </div>
                    
                    <!-- نمایش لاگ‌ها -->
                    <?php if (!empty($logs)): ?>
                        <div class="log-viewer">
                            <?php foreach ($logs as $log_line): ?>
                                <div class="log-line">
                                    <?php echo self::format_log_line($log_line); ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div style="text-align: center; padding: 40px; color: #666; flex: 1; display: flex; flex-direction: column; justify-content: center;">
                            <span style="font-size: 48px;">📝</span>
                            <h3>هیچ لاگی برای نمایش وجود ندارد</h3>
                            <p>لاگ‌های <?php echo esc_html($log_types[$selected_log_type] ?? $selected_log_type); ?> خالی هستند.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- ستون سمت راست - اطلاعات و مدیریت -->
            <div class="log-info-section">
                
                <!-- آمار لاگ‌ها -->
                <div class="card full-width-card" style="max-width: none !important; width: 100% !important;">
                    <h3>📊 آمار فایل‌های لاگ</h3>
                    <div class="log-stats">
                        <?php foreach ($log_stats as $type => $stats): ?>
                            <div class="stat-box">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                                    <strong style="flex: 1;"><?php echo esc_html($log_types[$type] ?? $type); ?></strong>
                                    <span style="background: #0073aa; color: white; padding: 2px 8px; border-radius: 12px; font-size: 12px;">
                                        <?php echo esc_html($stats['lines']); ?> خط
                                    </span>
                                </div>
                                <div style="font-size: 12px; color: #666;">
                                    <div>💾 سایز: <?php echo esc_html($stats['size']); ?></div>
                                    <div>🕒 آخرین تغییر: <?php echo esc_html($stats['last_modified']); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- سلامت سیستم -->
                <div class="card full-width-card" style="max-width: none !important; width: 100% !important;">
                    <h3>🔍 سلامت سیستم لاگ‌گیری</h3>
                    <div style="font-size: 13px;">
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px; padding: 8px; background: <?php echo $health_check['log_dir_exists'] ? '#e8f5e8' : '#ffe8e8'; ?>; border-radius: 4px;">
                            <span><?php echo $health_check['log_dir_exists'] ? '✅' : '❌'; ?></span>
                            <span>پوشه لاگ: <?php echo $health_check['log_dir_exists'] ? 'موجود' : 'مفقود'; ?></span>
                        </div>
                        
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px; padding: 8px; background: <?php echo $health_check['log_dir_writable'] ? '#e8f5e8' : '#ffe8e8'; ?>; border-radius: 4px;">
                            <span><?php echo $health_check['log_dir_writable'] ? '✅' : '❌'; ?></span>
                            <span>قابل نوشتن: <?php echo $health_check['log_dir_writable'] ? 'بله' : 'خیر'; ?></span>
                        </div>
                        
                        <?php foreach ($health_check['files'] as $type => $file_info): ?>
                            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px; padding: 6px; background: #f8f9fa; border-radius: 4px; border-left: 3px solid <?php echo $file_info['exists'] && $file_info['writable'] ? '#28a745' : ($file_info['exists'] ? '#ffc107' : '#dc3545'); ?>;">
                                <span>
                                    <?php echo $file_info['exists'] ? '✅' : '❌'; ?>
                                    <?php echo $file_info['writable'] ? '✍️' : '🔒'; ?>
                                    <?php echo $file_info['readable'] ? '📖' : '🚫'; ?>
                                </span>
                                <span style="flex: 1; font-size: 12px;">
                                    <?php echo esc_html($log_types[$type] ?? $type); ?>
                                </span>
                                <span style="font-size: 11px; color: #666;">
                                    <?php echo size_format($file_info['size']); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- مدیریت لاگ‌ها -->
                <div class="card full-width-card" style="max-width: none !important; width: 100% !important;">
                    <h3>🗑️ مدیریت لاگ‌ها</h3>
                    <p style="font-size: 13px; margin-bottom: 15px;">
                        با کلیک بر روی دکمه زیر، تمام فایل‌های لاگ افزونه پاک خواهند شد.
                    </p>
                    
                    <form method="post" onsubmit="return confirm('⚠️ آیا مطمئن هستید که می‌خواهید تمام فایل‌های لاگ پاک شوند؟ این عمل غیرقابل بازگشت است.');">
                        <?php wp_nonce_field('clear_logs'); ?>
                        <input type="hidden" name="clear_logs" value="1">
                        <?php submit_button('پاک کردن تمام لاگ‌ها', 'delete', 'clear_logs_btn', false); ?>
                    </form>
                    
                    <div style="margin-top: 15px; padding: 10px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px;">
                        <strong>💡 نکته:</strong>
                        <p style="margin: 5px 0 0 0; font-size: 12px;">
                            لاگ‌های قدیمی به صورت خودکار هر هفته پاکسازی می‌شوند.
                        </p>
                    </div>
                </div>
                
            </div>
        </div>

        <style>
            /* بازنویسی استایل کارت برای صفحه لاگ */
            .vendor-sync-debug-page .card,
            .log-viewer-container .card,
            .log-display-section .card,
            .log-info-section .card,
            .full-width-card {
                max-width: none !important;
                width: 100% !important;
            }

            .log-display-section .card {
                margin-top: 0;
            }

            /* استایل‌های اصلی برای مشاهده لاگ */
            .log-viewer-container {
                display: grid;
                grid-template-columns: 1fr 400px;
                gap: 20px;
                align-items: start;
            }

            .log-display-section .card {
                height: 100%;
                display: flex;
                flex-direction: column;
            }

            .log-filters {
                background: #f9f9f9;
                padding: 15px;
                border-radius: 4px;
                margin-bottom: 15px;
                border: 1px solid #e1e1e1;
            }

            .log-viewer {
                background: #1e1e1e !important;
                color: #d4d4d4 !important;
                padding: 15px;
                border-radius: 4px;
                height: calc(100vh - 300px);
                min-height: 500px;
                overflow-y: auto;
                font-family: 'Courier New', monospace;
                font-size: 13px;
                line-height: 1.5;
                border: 1px solid #444;
                flex: 1;
            }

            .log-line {
                border-bottom: 1px solid #333;
                padding: 8px 0;
                white-space: pre-wrap;
                word-break: break-all;
                transition: background-color 0.2s ease;
            }

            .log-line:hover {
                background-color: #2a2a2a;
            }

            /* استایل‌های بخش اطلاعات */
            .log-info-section {
                display: flex;
                flex-direction: column;
                gap: 20px;
            }

            .log-stats {
                max-height: 300px;
                overflow-y: auto;
            }

            .stat-box {
                border: 1px solid #ddd;
                padding: 12px;
                margin-bottom: 8px;
                border-radius: 4px;
                background: #fafafa;
                border-left: 4px solid #0073aa;
                transition: all 0.3s ease;
            }

            .stat-box:hover {
                background: #f0f7ff;
                transform: translateX(-2px);
            }

            /* اسکرول بار برای نمایشگر لاگ */
            .log-viewer::-webkit-scrollbar {
                width: 8px;
            }

            .log-viewer::-webkit-scrollbar-track {
                background: #1e1e1e;
            }

            .log-viewer::-webkit-scrollbar-thumb {
                background: #555;
                border-radius: 4px;
            }

            .log-viewer::-webkit-scrollbar-thumb:hover {
                background: #777;
            }

            /* واکنش‌گرایی */
            @media (max-width: 1200px) {
                .log-viewer-container {
                    grid-template-columns: 1fr !important;
                }
                
                .log-info-section {
                    order: -1;
                }
                
                .log-viewer {
                    height: 400px;
                }
            }

            @media (max-width: 768px) {
                .log-filters form {
                    flex-direction: column;
                    align-items: stretch;
                }
                
                .log-filters form > div {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }
            }
        </style>
        <?php
    }
    
    /**
     * فرمت‌دهی خطوط لاگ برای نمایش زیباتر
     */
    private static function format_log_line($log_line) {
        // رنگ‌بندی بر اساس سطح لاگ
        $log_line = esc_html($log_line);
        
        if (strpos($log_line, '[ERROR]') !== false) {
            return '<span style="color: #f48771;">❌ ' . $log_line . '</span>';
        } elseif (strpos($log_line, '[SUCCESS]') !== false) {
            return '<span style="color: #85cc95;">✅ ' . $log_line . '</span>';
        } elseif (strpos($log_line, '[WARNING]') !== false) {
            return '<span style="color: #e2c08d;">⚠️ ' . $log_line . '</span>';
        } elseif (strpos($log_line, '[INFO]') !== false) {
            return '<span style="color: #79b8ff;">ℹ️ ' . $log_line . '</span>';
        } elseif (strpos($log_line, '[DEBUG]') !== false) {
            return '<span style="color: #b392f0;">🐛 ' . $log_line . '</span>';
        } elseif (strpos($log_line, '[API]') !== false) {
            return '<span style="color: #56b6c2;">🔌 ' . $log_line . '</span>';
        }
        
        return '📝 ' . $log_line;
    }
    
    /**
     * پاک کردن تمام فایل‌های لاگ
     */
    private static function clear_log_files() {
        $log_files = [
            'sync-errors.log',
            'sync-success.log',
            'sync-general.log',
            'sync-warnings.log',
            'sync-api.log',
            'sync-debug.log'
        ];
        
        $log_dir = BERON_SELLER_SYNC_PATH . 'logs/';
        $cleared_count = 0;
        
        foreach ($log_files as $file) {
            $file_path = $log_dir . $file;
            if (file_exists($file_path) && is_writable($file_path)) {
                if (file_put_contents($file_path, "=== Beron Seller Sync Log - Cleared on " . date('Y-m-d H:i:s') . " ===\n\n")) {
                    $cleared_count++;
                }
            }
        }
        
        if ($cleared_count > 0) {
            echo '<div class="notice notice-success is-dismissible"><p>✅ ' . $cleared_count . ' فایل لاگ با موفقیت پاک شدند.</p></div>';
        } else {
            echo '<div class="notice notice-warning is-dismissible"><p>⚠️ هیچ فایل لاگی پاک نشد.</p></div>';
        }
    }
}