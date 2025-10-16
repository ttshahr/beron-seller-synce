<?php
if (!defined('ABSPATH')) exit;

class Vendor_Logger {
    
    const LOG_LEVEL_ERROR = 'error';
    const LOG_LEVEL_SUCCESS = 'success';
    const LOG_LEVEL_INFO = 'info';
    const LOG_LEVEL_WARNING = 'warning';
    const LOG_LEVEL_DEBUG = 'debug';
    
    private static $log_dir;
    private static $log_files = [
        'error' => 'sync-errors.log',
        'success' => 'sync-success.log', 
        'api' => 'sync-api.log',
        'debug' => 'sync-debug.log',
        'general' => 'sync-general.log'
    ];
    
    /**
     * مقداردهی اولیه سیستم لاگ‌گیری
     */
    public static function init() {
        self::$log_dir = Beron_Seller_Sync_PATH . 'logs/';
        
        // ایجاد پوشه logs اگر وجود ندارد
        if (!file_exists(self::$log_dir)) {
            wp_mkdir_p(self::$log_dir);
            
            // ایجاد فایل .htaccess برای امنیت
            self::create_htaccess();
        }
        
        // ایجاد فایل‌های لاگ اگر وجود ندارند
        foreach (self::$log_files as $file) {
            $log_file = self::$log_dir . $file;
            if (!file_exists($log_file)) {
                file_put_contents($log_file, "=== Beron Seller Sync Log - " . date('Y-m-d H:i:s') . " ===\n\n");
            }
        }
    }
    
    /**
     * ایجاد فایل htaccess برای محافظت از پوشه logs
     */
    private static function create_htaccess() {
        $htaccess_content = "Order deny,allow\nDeny from all\n";
        file_put_contents(self::$log_dir . '.htaccess', $htaccess_content);
    }
    
    /**
     * ثبت خطا
     */
    public static function log_error($message, $product_id = null, $vendor_id = null) {
        self::write_log(self::LOG_LEVEL_ERROR, $message, $product_id, $vendor_id);
    }
    
    /**
     * ثبت موفقیت
     */
    public static function log_success($product_id, $action, $vendor_id = null, $additional_info = '') {
        $message = "Action: {$action}";
        if ($additional_info) {
            $message .= " - {$additional_info}";
        }
        self::write_log(self::LOG_LEVEL_SUCCESS, $message, $product_id, $vendor_id);
    }
    
    /**
     * ثبت درخواست API
     */
    public static function log_api_request($url, $sku, $success, $vendor_id = null, $response_time = null) {
        $message = "URL: {$url} - SKU: {$sku} - Status: " . ($success ? 'SUCCESS' : 'FAILED');
        if ($response_time) {
            $message .= " - Response Time: {$response_time}s";
        }
        self::write_log('api', $message, null, $vendor_id);
    }
    
    /**
     * ثبت اطلاعات عمومی
     */
    public static function log_info($message, $vendor_id = null) {
        self::write_log(self::LOG_LEVEL_INFO, $message, null, $vendor_id);
    }
    
    /**
     * ثبت هشدار
     */
    public static function log_warning($message, $product_id = null, $vendor_id = null) {
        self::write_log(self::LOG_LEVEL_WARNING, $message, $product_id, $vendor_id);
    }
    
    /**
     * ثبت اطلاعات دیباگ
     */
    public static function log_debug($message, $product_id = null, $vendor_id = null) {
        if (defined('BERON_DEBUG') && BERON_DEBUG) {
            self::write_log(self::LOG_LEVEL_DEBUG, $message, $product_id, $vendor_id);
        }
    }
    
    /**
     * متد اصلی نوشتن در لاگ
     */
    private static function write_log($level, $message, $product_id = null, $vendor_id = null) {
        // اطمینان از مقداردهی اولیه
        if (self::$log_dir === null) {
            self::init();
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "[{$timestamp}] - " . strtoupper($level);
        
        // اضافه کردن اطلاعات فروشنده
        if ($vendor_id) {
            $vendor_name = self::get_vendor_name($vendor_id);
            $log_entry .= " - Vendor: {$vendor_name} (ID: {$vendor_id})";
        }
        
        // اضافه کردن اطلاعات محصول
        if ($product_id) {
            $product_name = self::get_product_name($product_id);
            $log_entry .= " - Product: {$product_name} (ID: {$product_id})";
        }
        
        $log_entry .= " - {$message}" . PHP_EOL;
        
        // نوشتن در فایل لاگ مربوطه
        $log_file = self::$log_dir . self::$log_files[$level];
        file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
        
        // همچنین در فایل عمومی هم ثبت شود
        if ($level !== 'general') {
            $general_log_file = self::$log_dir . self::$log_files['general'];
            file_put_contents($general_log_file, $log_entry, FILE_APPEND | LOCK_EX);
        }
    }
    
    /**
     * دریافت نام فروشنده
     */
    private static function get_vendor_name($vendor_id) {
        $vendor = get_userdata($vendor_id);
        return $vendor ? $vendor->display_name : 'Unknown Vendor';
    }
    
    /**
     * دریافت نام محصول
     */
    private static function get_product_name($product_id) {
        $product = get_post($product_id);
        return $product ? $product->post_title : 'Unknown Product';
    }
    
    /**
     * دریافت لاگ‌های اخیر
     */
    public static function get_recent_logs($level = 'general', $limit = 100) {
        if (self::$log_dir === null) {
            self::init();
        }
        
        $log_file = self::$log_dir . self::$log_files[$level];
        if (!file_exists($log_file)) {
            return [];
        }
        
        $lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        return array_slice(array_reverse($lines), 0, $limit);
    }
    
    /**
     * پاک کردن لاگ‌های قدیمی
     */
    public static function cleanup_old_logs($days = 30) {
        if (self::$log_dir === null) {
            self::init();
        }
        
        $cutoff_time = time() - ($days * 24 * 60 * 60);
        
        foreach (self::$log_files as $file) {
            $log_file = self::$log_dir . $file;
            if (file_exists($log_file)) {
                // ایجاد فایل جدید و کپی کردن خطوط جدید
                $lines = file($log_file);
                $new_lines = [];
                
                foreach ($lines as $line) {
                    if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $matches)) {
                        $log_time = strtotime($matches[1]);
                        if ($log_time >= $cutoff_time) {
                            $new_lines[] = $line;
                        }
                    } else {
                        // اگر تاریخ مشخص نیست، نگه دار
                        $new_lines[] = $line;
                    }
                }
                
                file_put_contents($log_file, implode('', $new_lines));
            }
        }
    }
    
    /**
     * دریافت آمار لاگ‌ها
     */
    public static function get_log_stats() {
        if (self::$log_dir === null) {
            self::init();
        }
        
        $stats = [];
        foreach (self::$log_files as $type => $file) {
            $log_file = self::$log_dir . $file;
            if (file_exists($log_file)) {
                $stats[$type] = [
                    'size' => size_format(filesize($log_file)),
                    'lines' => count(file($log_file)),
                    'last_modified' => date('Y-m-d H:i:s', filemtime($log_file))
                ];
            }
        }
        
        return $stats;
    }
}

// مقداردهی اولیه سیستم لاگ‌گیری
add_action('plugins_loaded', ['Vendor_Logger', 'init']);

// پاکسازی لاگ‌های قدیمی هر هفته
add_action('wp_scheduled_cleanup', ['Vendor_Logger', 'cleanup_old_logs']);