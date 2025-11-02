<?php
if (!defined('ABSPATH')) exit;

class Modal_Activity_Status {
    
    /**
     * نمایش آخرین فعالیت‌های یک بخش خاص
     */
    public static function render_recent($activity_type, $vendor_id = null, $limit = 5) {
        $logs = Vendor_Logger::get_recent_logs('general', 20); // گرفتن لاگ‌های اخیر
        
        // فیلتر کردن لاگ‌های مرتبط
        $filtered_logs = self::filter_logs_by_activity($logs, $activity_type, $vendor_id, $limit);
        
        if (empty($filtered_logs)) {
            echo '<p>هنوز هیچ فعالیتی ثبت نشده است.</p>';
            return;
        }
        
        echo '<div class="activity-status-container">';
        echo '<h4>📊 آخرین فعالیت‌ها</h4>';
        echo '<div class="activity-list" style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #f9f9f9;">';
        
        foreach ($filtered_logs as $log) {
            echo self::format_log_entry($log);
        }
        
        echo '</div>';
        echo '</div>';
        
        // اضافه کردن استایل‌ها
        self::render_styles();
    }
    
    /**
     * فیلتر کردن لاگ‌ها بر اساس نوع فعالیت
     */
    private static function filter_logs_by_activity($logs, $activity_type, $vendor_id, $limit) {
        $filtered = [];
        $type_keywords = self::get_activity_keywords($activity_type);
        
        foreach ($logs as $log) {
            // فیلتر بر اساس کلمات کلیدی
            $has_keyword = false;
            foreach ($type_keywords as $keyword) {
                if (stripos($log, $keyword) !== false) {
                    $has_keyword = true;
                    break;
                }
            }
            
            // فیلتر بر اساس vendor_id اگر مشخص شده
            $has_vendor = true;
            if ($vendor_id) {
                $has_vendor = strpos($log, "Vendor: {$vendor_id}") !== false || 
                             strpos($log, "vendor {$vendor_id}") !== false;
            }
            
            if ($has_keyword && $has_vendor && count($filtered) < $limit) {
                $filtered[] = $log;
            }
        }
        
        return $filtered;
    }
    
    /**
     * کلمات کلیدی برای هر نوع فعالیت
     */
    private static function get_activity_keywords($activity_type) {
        $keywords = [
            'price_sync' => ['price_sync', 'قیمت', 'price', 'همگام‌سازی قیمت'],
            'stock_sync' => ['stock_sync', 'موجودی', 'stock', 'همگام‌سازی موجودی'],
            'profit_calc' => ['profit_calc', 'سود', 'profit', 'محاسبه سود'],
            'price_calc' => ['price_calc', 'محاسبه قیمت', 'calculate', 'final price'],
            'all' => [] // همه لاگ‌ها
        ];
        
        return $keywords[$activity_type] ?? $keywords['all'];
    }
    
    /**
     * فرمت‌دهی هر entry لاگ
     */
    private static function format_log_entry($log) {
        $class = 'activity-entry';
        
        if (strpos($log, 'SUCCESS') !== false || strpos($log, '✅') !== false) {
            $class .= ' activity-success';
        } elseif (strpos($log, 'ERROR') !== false || strpos($log, '❌') !== false) {
            $class .= ' activity-error';
        } elseif (strpos($log, 'WARNING') !== false || strpos($log, '⚠️') !== false) {
            $class .= ' activity-warning';
        } else {
            $class .= ' activity-info';
        }
        
        // کوتاه کردن متن اگر طولانی است
        if (strlen($log) > 150) {
            $log = substr($log, 0, 150) . '...';
        }
        
        return '<div class="' . $class . '">' . esc_html($log) . '</div>';
    }
    
    /**
     * استایل‌های مربوطه
     */
    private static function render_styles() {
        echo '
        <style>
        .activity-entry {
            padding: 8px 10px;
            margin: 4px 0;
            border-radius: 4px;
            font-family: monospace;
            font-size: 11px;
            border-right: 4px solid transparent;
        }
        .activity-success {
            background: #f0fdf4;
            border-right-color: #22c55e;
        }
        .activity-error {
            background: #fef2f2;
            border-right-color: #ef4444;
        }
        .activity-warning {
            background: #fffbeb;
            border-right-color: #f59e0b;
        }
        .activity-info {
            background: #f0f9ff;
            border-right-color: #0ea5e9;
        }
        .activity-list::-webkit-scrollbar {
            width: 6px;
        }
        .activity-list::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 3px;
        }
        </style>
        ';
    }
    
    /**
     * نمایش خلاصه وضعیت (برای کارت‌های کوچک)
     */
    public static function render_summary($activity_type, $vendor_id = null) {
        $logs = Vendor_Logger::get_recent_logs('general', 10);
        $filtered_logs = self::filter_logs_by_activity($logs, $activity_type, $vendor_id, 3);
        
        $last_activity = empty($filtered_logs) ? 'بدون فعالیت' : $filtered_logs[0];
        
        // کوتاه کردن متن
        if (strlen($last_activity) > 80) {
            $last_activity = substr($last_activity, 0, 80) . '...';
        }
        
        echo '<div class="activity-summary">';
        echo '<strong>آخرین فعالیت:</strong><br>';
        echo '<span style="font-size: 12px; color: #666;">' . esc_html($last_activity) . '</span>';
        echo '</div>';
    }
}