<?php
if (!defined('ABSPATH')) exit;

class Admin_Debug_Pages {
    
    public static function render_debug_page() {
        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'vendor_debug';
        
        // مدیریت درخواست‌های POST برای تب‌های مختلف
        self::handle_post_requests($current_tab);
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
                <a href="<?php echo add_query_arg(['tab' => 'cache_cleaner']); ?>" class="nav-tab <?php echo $current_tab === 'cache_cleaner' ? 'nav-tab-active' : ''; ?>">
                    پاکسازی کش
                </a>
            </h2>
            
            <?php
            // رندر تب مربوطه
            switch ($current_tab) {
                case 'vendor_debug':
                    Admin_Debug_Vendor_Tab::render();
                    break;
                    
                case 'log_viewer':
                    Admin_Debug_Logs_Tab::render();
                    break;
                    
                case 'cache_cleaner':
                    Admin_Debug_Cache_Tab::render();
                    break;
                    
                default:
                    Admin_Debug_Vendor_Tab::render();
                    break;
            }
            ?>
        </div>
        <?php
    }
    
    /**
     * مدیریت درخواست‌های POST برای تب‌های مختلف
     */
    private static function handle_post_requests($current_tab) {
        // بررسی nonce برای امنیت
        if (!isset($_POST['_wpnonce'])) {
            return;
        }
        
        switch ($current_tab) {
            case 'log_viewer':
                if (isset($_POST['clear_logs']) && wp_verify_nonce($_POST['_wpnonce'], 'clear_logs')) {
                    Admin_Debug_Logs_Tab::clear_log_files();
                }
                break;
                
            case 'cache_cleaner':
                if (isset($_POST['flush_cache']) && wp_verify_nonce($_POST['_wpnonce'], 'flush_cache')) {
                    Admin_Debug_Cache_Tab::flush_all_caches();
                }
                break;
        }
    }
}