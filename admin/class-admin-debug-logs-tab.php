<?php
if (!defined('ABSPATH')) exit;

class Admin_Debug_Logs_Tab {
    
    public static function render() {
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
                        <div class="log-viewer-terminal">
                            <div class="terminal-header">
                                <div class="terminal-controls">
                                    <span class="control close"></span>
                                    <span class="control minimize"></span>
                                    <span class="control maximize"></span>
                                </div>
                                <span class="terminal-title">terminal.log</span>
                            </div>
                            <div class="terminal-body">
                                <?php foreach ($logs as $log_line): ?>
                                    <div class="log-line-terminal">
                                        <?php echo self::format_log_line($log_line); ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="no-logs-message">
                            <span class="no-logs-icon">📝</span>
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
        <?php
    }
    
    /**
     * فرمت‌دهی خطوط لاگ برای نمایش ترمینال
     */
    private static function format_log_line($log_line) {
        $log_line = esc_html($log_line);
        
        // حذف ایموجی‌ها و استفاده از رنگ‌بندی ساده
        if (strpos($log_line, '[ERROR]') !== false) {
            return '<span style="color: #f48771;">' . $log_line . '</span>';
        } elseif (strpos($log_line, '[SUCCESS]') !== false) {
            return '<span style="color: #85cc95;">' . $log_line . '</span>';
        } elseif (strpos($log_line, '[WARNING]') !== false) {
            return '<span style="color: #e2c08d;">' . $log_line . '</span>';
        } elseif (strpos($log_line, '[INFO]') !== false) {
            return '<span style="color: #79b8ff;">' . $log_line . '</span>';
        } elseif (strpos($log_line, '[DEBUG]') !== false) {
            return '<span style="color: #b392f0;">' . $log_line . '</span>';
        } elseif (strpos($log_line, '[API]') !== false) {
            return '<span style="color: #56b6c2;">' . $log_line . '</span>';
        }
        
        return '<span style="color: #d4d4d4;">' . $log_line . '</span>';
    }
    
    /**
     * پاک کردن تمام فایل‌های لاگ
     */
    public static function clear_log_files() {
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