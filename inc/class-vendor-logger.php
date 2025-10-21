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
        'info' => 'sync-general.log', // 🔥 اضافه کردن کلید info
        'warning' => 'sync-warnings.log', // 🔥 اضافه کردن کلید warning
        'api' => 'sync-api.log',
        'debug' => 'sync-debug.log',
        'general' => 'sync-general.log'
    ];
    
    /**
     * مقداردهی اولیه سیستم لاگ‌گیری
     */
    public static function init() {
        self::$log_dir = BERON_SELLER_SYNC_PATH . 'logs/';
        
        // ایجاد پوشه logs اگر وجود ندارد
        if (!file_exists(self::$log_dir)) {
            if (!wp_mkdir_p(self::$log_dir)) {
                error_log('❌ Failed to create log directory: ' . self::$log_dir);
                return false;
            }
            
            // ایجاد فایل .htaccess برای امنیت
            self::create_htaccess();
        }
        
        // ایجاد فایل‌های لاگ اگر وجود ندارند
        foreach (self::$log_files as $file) {
            $log_file = self::$log_dir . $file;
            if (!file_exists($log_file)) {
                if (!self::safe_file_put_contents($log_file, "=== Beron Seller Sync Log - " . date('Y-m-d H:i:s') . " ===\n\n")) {
                    error_log('❌ Failed to create log file: ' . $log_file);
                }
            }
        }
        
        return true;
    }
    
    /**
     * ایجاد فایل htaccess برای محافظت از پوشه logs
     */
    private static function create_htaccess() {
        $htaccess_content = "Order deny,allow\nDeny from all\n";
        return self::safe_file_put_contents(self::$log_dir . '.htaccess', $htaccess_content);
    }
    
    /**
     * متد ایمن برای نوشتن در فایل
     */
    private static function safe_file_put_contents($file_path, $content, $flags = 0) {
        // بررسی اینکه مسیر فایل است نه پوشه
        if (is_dir($file_path)) {
            error_log('❌ Vendor_Logger: Path is a directory, not a file: ' . $file_path);
            return false;
        }
        
        // بررسی وجود پوشه والد
        $dir = dirname($file_path);
        if (!file_exists($dir)) {
            if (!wp_mkdir_p($dir)) {
                error_log('❌ Vendor_Logger: Cannot create directory: ' . $dir);
                return false;
            }
        }
        
        // بررسی قابل نوشتن بودن پوشه
        if (!is_writable($dir)) {
            error_log('❌ Vendor_Logger: Directory not writable: ' . $dir);
            return false;
        }
        
        // نوشتن در فایل با مدیریت خطا
        $result = @file_put_contents($file_path, $content, $flags | LOCK_EX);
        
        if ($result === false) {
            $error = error_get_last();
            error_log('❌ Vendor_Logger: Failed to write to file: ' . $file_path . ' - Error: ' . ($error['message'] ?? 'Unknown'));
            return false;
        }
        
        return true;
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
     * متد اصلی نوشتن در لاگ - با مدیریت خطای بهبود یافته
     */
    private static function write_log($level, $message, $product_id = null, $vendor_id = null) {
        // اطمینان از مقداردهی اولیه
        if (self::$log_dir === null) {
            if (!self::init()) {
                // اگر مقداردهی اولیه شکست خورد، از نوشتن لاگ صرف نظر کن
                return false;
            }
        }
        
        // 🔥 نگاشت سطح‌های لاگ به فایل‌ها
        $log_level_map = [
            self::LOG_LEVEL_ERROR => 'error',
            self::LOG_LEVEL_SUCCESS => 'success',
            self::LOG_LEVEL_INFO => 'info', 
            self::LOG_LEVEL_WARNING => 'warning',
            self::LOG_LEVEL_DEBUG => 'debug',
            'api' => 'api'
        ];
        
        // تبدیل سطح لاگ به کلید فایل
        $file_key = isset($log_level_map[$level]) ? $log_level_map[$level] : $level;
        
        // بررسی وجود فایل لاگ مربوطه
        if (!isset(self::$log_files[$file_key])) {
            error_log('❌ Vendor_Logger: Invalid log level: ' . $level . ' (mapped to: ' . $file_key . ')');
            return false;
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
        
        // نوشتن در فایل لاگ مربوطه با متد ایمن
        $log_file = self::$log_dir . self::$log_files[$file_key];
        $success = self::safe_file_put_contents($log_file, $log_entry, FILE_APPEND);
        
        // همچنین در فایل عمومی هم ثبت شود (به جز خود فایل عمومی)
        if ($success && $file_key !== 'general') {
            $general_log_file = self::$log_dir . self::$log_files['general'];
            self::safe_file_put_contents($general_log_file, $log_entry, FILE_APPEND);
        }
        
        return $success;
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
     * دریافت لاگ‌های اخیر - با مدیریت خطا
     */
    public static function get_recent_logs($level = 'general', $limit = 100) {
        if (self::$log_dir === null) {
            self::init();
        }
        
        // 🔥 نگاشت سطح‌های لاگ
        $log_level_map = [
            self::LOG_LEVEL_ERROR => 'error',
            self::LOG_LEVEL_SUCCESS => 'success',
            self::LOG_LEVEL_INFO => 'info',
            self::LOG_LEVEL_WARNING => 'warning', 
            self::LOG_LEVEL_DEBUG => 'debug',
            'api' => 'api'
        ];
        
        $file_key = isset($log_level_map[$level]) ? $log_level_map[$level] : $level;
        
        if (!isset(self::$log_files[$file_key])) {
            return [];
        }
        
        $log_file = self::$log_dir . self::$log_files[$file_key];
        if (!file_exists($log_file) || !is_readable($log_file)) {
            return [];
        }
        
        $lines = @file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return [];
        }
        
        return array_slice(array_reverse($lines), 0, $limit);
    }
    
    /**
     * پاک کردن لاگ‌های قدیمی - با مدیریت خطا
     */
    public static function cleanup_old_logs($days = 30) {
        if (self::$log_dir === null) {
            self::init();
        }
        
        $cutoff_time = time() - ($days * 24 * 60 * 60);
        
        foreach (self::$log_files as $file) {
            $log_file = self::$log_dir . $file;
            if (!file_exists($log_file) || !is_writable($log_file)) {
                continue;
            }
            
            // ایجاد فایل جدید و کپی کردن خطوط جدید
            $lines = @file($log_file);
            if ($lines === false) {
                continue;
            }
            
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
            
            self::safe_file_put_contents($log_file, implode('', $new_lines));
        }
    }
    
    /**
     * دریافت آمار لاگ‌ها - با مدیریت خطا
     */
    public static function get_log_stats() {
        if (self::$log_dir === null) {
            self::init();
        }
        
        $stats = [];
        foreach (self::$log_files as $type => $file) {
            $log_file = self::$log_dir . $file;
            if (file_exists($log_file) && is_readable($log_file)) {
                $stats[$type] = [
                    'size' => size_format(filesize($log_file)),
                    'lines' => count(file($log_file)),
                    'last_modified' => date('Y-m-d H:i:s', filemtime($log_file))
                ];
            } else {
                $stats[$type] = [
                    'size' => '0 بایت',
                    'lines' => 0,
                    'last_modified' => 'هرگز'
                ];
            }
        }
        
        return $stats;
    }
    
    /**
     * بررسی سلامت سیستم لاگ‌گیری
     */
    public static function health_check() {
        if (self::$log_dir === null) {
            self::init();
        }
        
        $health = [
            'log_dir_exists' => file_exists(self::$log_dir),
            'log_dir_writable' => is_writable(self::$log_dir),
            'files' => []
        ];
        
        foreach (self::$log_files as $type => $file) {
            $log_file = self::$log_dir . $file;
            $health['files'][$type] = [
                'exists' => file_exists($log_file),
                'writable' => is_writable($log_file),
                'readable' => is_readable($log_file),
                'size' => file_exists($log_file) ? filesize($log_file) : 0
            ];
        }
        
        return $health;
    }
}

// مقداردهی اولیه سیستم لاگ‌گیری
add_action('plugins_loaded', ['Vendor_Logger', 'init']);

// پاکسازی لاگ‌های قدیمی هر هفته
add_action('wp_scheduled_cleanup', ['Vendor_Logger', 'cleanup_old_logs']);