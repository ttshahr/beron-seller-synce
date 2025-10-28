<?php
if (!defined('ABSPATH')) exit;

class Vendor_Logger {
    
    const LOG_LEVEL_ERROR = 'error';
    const LOG_LEVEL_SUCCESS = 'success'; 
    const LOG_LEVEL_INFO = 'info';
    const LOG_LEVEL_WARNING = 'warning';
    const LOG_LEVEL_DEBUG = 'debug';
    const LOG_LEVEL_API = 'api';
    
    private static $instance = null;
    private $log_dir;
    private $log_files = [
        'error' => 'sync-errors.log',
        'success' => 'sync-success.log', 
        'warning' => 'sync-warnings.log',
        'api' => 'sync-api.log',
        'debug' => 'sync-debug.log',
        'general' => 'sync-general.log'
    ];
    
    /**
     * Singleton pattern - جلوگیری از ایجاد multiple instances
     */
    private function __construct() {
        $this->log_dir = BERON_SELLER_SYNC_PATH . 'logs/';
        $this->init_log_system();
    }
    
    /**
     * دریافت instance واحد
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * مقداردهی اولیه سیستم لاگ - یکبار اجرا می‌شود
     */
    private function init_log_system() {
        // ایجاد پوشه logs اگر وجود ندارد
        if (!file_exists($this->log_dir)) {
            wp_mkdir_p($this->log_dir);
            $this->create_htaccess();
        }
        
        // ایجاد فایل‌های لاگ
        foreach ($this->log_files as $file) {
            $log_file = $this->log_dir . $file;
            if (!file_exists($log_file)) {
                $this->safe_file_put_contents($log_file, "=== Beron Seller Sync Log - " . date('Y-m-d H:i:s') . " ===\n\n");
            }
        }
    }
    
    /**
     * ایجاد فایل htaccess برای امنیت
     */
    private function create_htaccess() {
        $htaccess_content = "Order deny,allow\nDeny from all\n";
        $this->safe_file_put_contents($this->log_dir . '.htaccess', $htaccess_content);
    }
    
    /**
     * متد اصلی و یکپارچه برای ثبت لاگ
     */
    private function log($level, $message, $product_id = null, $vendor_id = null, $additional_info = '') {
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "[{$timestamp}] - " . strtoupper($level);
        
        // اضافه کردن اطلاعات فروشنده
        if ($vendor_id) {
            $vendor_name = $this->get_vendor_name($vendor_id);
            $log_entry .= " - Vendor: {$vendor_name} (ID: {$vendor_id})";
        }
        
        // اضافه کردن اطلاعات محصول
        if ($product_id) {
            $product_name = $this->get_product_name($product_id);
            $log_entry .= " - Product: {$product_name} (ID: {$product_id})";
        }
        
        $log_entry .= " - {$message}";
        
        // اضافه کردن اطلاعات تکمیلی
        if ($additional_info) {
            $log_entry .= " - {$additional_info}";
        }
        
        $log_entry .= PHP_EOL;
        
        // تعیین فایل‌های مقصد بر اساس سطح لاگ
        $target_files = $this->get_target_files($level);
        
        // نوشتن در فایل‌های مقصد
        foreach ($target_files as $file_key) {
            $log_file = $this->log_dir . $this->log_files[$file_key];
            $this->safe_file_put_contents($log_file, $log_entry, FILE_APPEND);
        }
        
        return true;
    }
    
    /**
     * تعیین فایل‌های مقصد بر اساس سطح لاگ
     */
    private function get_target_files($level) {
        $mapping = [
            self::LOG_LEVEL_ERROR => ['error', 'general'],
            self::LOG_LEVEL_SUCCESS => ['success', 'general'],
            self::LOG_LEVEL_WARNING => ['warning', 'general'],
            self::LOG_LEVEL_API => ['api', 'general'],
            self::LOG_LEVEL_DEBUG => ['debug', 'general'],
            self::LOG_LEVEL_INFO => ['general'] // فقط در فایل عمومی
        ];
        
        return isset($mapping[$level]) ? $mapping[$level] : ['general'];
    }
    
    /**
     * ==================== متدهای عمومی (همانند نسخه قبلی) ====================
     */
    
    public static function log_error($message, $product_id = null, $vendor_id = null) {
        self::get_instance()->log(self::LOG_LEVEL_ERROR, $message, $product_id, $vendor_id);
    }
    
    public static function log_success($product_id, $action, $vendor_id = null, $additional_info = '') {
        $message = "Action: {$action}";
        self::get_instance()->log(self::LOG_LEVEL_SUCCESS, $message, $product_id, $vendor_id, $additional_info);
    }
    
    public static function log_api_request($url, $sku, $success, $vendor_id = null, $response_time = null) {
        $message = "URL: {$url} - SKU: {$sku} - Status: " . ($success ? 'SUCCESS' : 'FAILED');
        if ($response_time) {
            $message .= " - Response Time: {$response_time}s";
        }
        self::get_instance()->log(self::LOG_LEVEL_API, $message, null, $vendor_id);
    }
    
    public static function log_info($message, $vendor_id = null) {
        self::get_instance()->log(self::LOG_LEVEL_INFO, $message, null, $vendor_id);
    }
    
    public static function log_warning($message, $product_id = null, $vendor_id = null) {
        self::get_instance()->log(self::LOG_LEVEL_WARNING, $message, $product_id, $vendor_id);
    }
    
    public static function log_debug($message, $product_id = null, $vendor_id = null) {
        if (defined('BERON_DEBUG') && BERON_DEBUG) {
            self::get_instance()->log(self::LOG_LEVEL_DEBUG, $message, $product_id, $vendor_id);
        }
    }
    
    /**
     * ==================== متدهای کمکی ====================
     */
    
    private function safe_file_put_contents($file_path, $content, $flags = 0) {
        if (is_dir($file_path)) {
            return false;
        }
        
        $dir = dirname($file_path);
        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
        }
        
        if (!is_writable($dir)) {
            return false;
        }
        
        $result = @file_put_contents($file_path, $content, $flags | LOCK_EX);
        return $result !== false;
    }
    
    private function get_vendor_name($vendor_id) {
        $vendor = get_userdata($vendor_id);
        return $vendor ? $vendor->display_name : 'Unknown Vendor';
    }
    
    private function get_product_name($product_id) {
        $product = get_post($product_id);
        return $product ? $product->post_title : 'Unknown Product';
    }
    
    /**
     * ==================== متدهای مدیریت لاگ‌ها ====================
     */
    
    public static function get_recent_logs($level = 'general', $limit = 100) {
        $instance = self::get_instance();
        
        $file_mapping = [
            'error' => 'error',
            'success' => 'success', 
            'warning' => 'warning',
            'api' => 'api',
            'debug' => 'debug',
            'info' => 'general',
            'general' => 'general'
        ];
        
        $file_key = isset($file_mapping[$level]) ? $file_mapping[$level] : 'general';
        $log_file = $instance->log_dir . $instance->log_files[$file_key];
        
        if (!file_exists($log_file) || !is_readable($log_file)) {
            return [];
        }
        
        $lines = @file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return [];
        }
        
        return array_slice(array_reverse($lines), 0, $limit);
    }
    
    public static function cleanup_old_logs($days = 30) {
        $instance = self::get_instance();
        $cutoff_time = time() - ($days * 24 * 60 * 60);
        
        foreach ($instance->log_files as $file) {
            $log_file = $instance->log_dir . $file;
            if (!file_exists($log_file) || !is_writable($log_file)) {
                continue;
            }
            
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
                    $new_lines[] = $line;
                }
            }
            
            $instance->safe_file_put_contents($log_file, implode('', $new_lines));
        }
    }
    
    public static function get_log_stats() {
        $instance = self::get_instance();
        $stats = [];
        
        foreach ($instance->log_files as $type => $file) {
            $log_file = $instance->log_dir . $file;
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
    
    public static function health_check() {
        $instance = self::get_instance();
        $health = [
            'log_dir_exists' => file_exists($instance->log_dir),
            'log_dir_writable' => is_writable($instance->log_dir),
            'files' => []
        ];
        
        foreach ($instance->log_files as $type => $file) {
            $log_file = $instance->log_dir . $file;
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

// مقداردهی اولیه و hookها (همانند قبل)
add_action('plugins_loaded', ['Vendor_Logger', 'get_instance']);
add_action('wp_scheduled_cleanup', ['Vendor_Logger', 'cleanup_old_logs']);