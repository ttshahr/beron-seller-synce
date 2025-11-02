<?php
if (!defined('ABSPATH')) exit;

class Auto_Sync_Scheduler {
    
    private static $execution_order = [
        'price_sync',      // 1. دریافت قیمت‌های خام
        'price_calc',      // 2. محاسبه قیمت نهایی  
        'stock_sync'       // 3. بروزرسانی موجودی
    ];
    
    /**
     * اجرای کامل سینک برای همه فروشندگان
     */
    public static function run_full_sync() {
        
        set_time_limit(0); // حذف محدودیت زمان
        ini_set('memory_limit', '1024M');
        
        $vendors = get_users(['role__in' => ['hamkar', 'seller']]);
        
        foreach ($vendors as $vendor) {
            Vendor_Logger::log_info("🔄 Starting auto-sync for vendor: {$vendor->display_name}", $vendor->ID);
            
            try {
                // اجرای مراحل به ترتیب
                foreach (self::$execution_order as $step) {
                    self::execute_sync_step($step, $vendor->ID);
                }
                
                Vendor_Logger::log_success(0, 'auto_sync_completed', $vendor->ID, "Auto sync completed successfully");
                
            } catch (Exception $e) {
                Vendor_Logger::log_error("Auto sync failed for vendor {$vendor->ID}: " . $e->getMessage(), null, $vendor->ID);
                // ادامه به فروشنده بعدی حتی اگر یکی خطا داد
                continue;
            }
            
            // تاخیر بین فروشندگان برای کاهش load
            sleep(10);
        }
    }
    
    /**
     * اجرای هر مرحله سینک
     */
    private static function execute_sync_step($step, $vendor_id) {
        $meta = Vendor_Meta_Handler::get_vendor_meta($vendor_id);
        
        switch ($step) {
            case 'price_sync':
                Vendor_Logger::log_info("📥 Step 1: Syncing raw prices", $vendor_id);
                $saved_count = Vendor_Raw_Price_Saver_Optimized::save_raw_prices_optimized($vendor_id, []);
                Vendor_Logger::log_info("✅ Raw prices synced: {$saved_count} products", $vendor_id);
                break;
                
            case 'price_calc':
                Vendor_Logger::log_info("🧮 Step 2: Calculating final prices", $vendor_id);
                $calculated_count = Vendor_Price_Calculator::calculate_final_prices($vendor_id, [], 15); // 15% پیش‌فرض
                Vendor_Logger::log_info("✅ Final prices calculated: {$calculated_count} products", $vendor_id);
                break;
                
            case 'stock_sync':
                Vendor_Logger::log_info("📦 Step 3: Syncing stock", $vendor_id);
                $updated_count = Vendor_Stock_Updater_Optimized::update_stocks($vendor_id, []);
                Vendor_Logger::log_info("✅ Stock synced: {$updated_count} products", $vendor_id);
                break;
        }
        
        // تاخیر بین مراحل
        sleep(5);
    }
    
    /**
     * هوک برای اجرای زمان‌بندی شده
     */
    public static function schedule_auto_sync() {
        if (!wp_next_scheduled('vendor_auto_sync_daily')) {
            wp_schedule_event(time(), 'twicedaily', 'vendor_auto_sync_daily');
            // wp_schedule_event(time(), 'hourly', 'vendor_auto_sync_daily');
            // wp_schedule_event(time(), 'daily', 'vendor_auto_sync_daily');

        }
    }
}