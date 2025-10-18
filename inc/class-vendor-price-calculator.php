<?php
if (!defined('ABSPATH')) exit;

class Vendor_Price_Calculator {
    
    public static function calculate_final_prices($vendor_id, $cat_id) {
        $conversion_percent = floatval(get_user_meta($vendor_id, 'vendor_price_conversion_percent', true));
        $vendor_name = self::get_vendor_name($vendor_id);
        
        Vendor_Logger::log_info(
            "Starting final price calculation for vendor: {$vendor_name} ({$vendor_id}) - Percent: {$conversion_percent}%",
            $vendor_id
        );
        
        $product_ids = self::get_product_ids_with_seller_price($cat_id, $vendor_id);
        
        if (empty($product_ids)) {
            Vendor_Logger::log_warning("No products with seller price found for calculation", null, $vendor_id);
            throw new Exception('هیچ محصولی با قیمت فروشنده برای محاسبه یافت نشد.');
        }
        
        Vendor_Logger::log_info("Found " . count($product_ids) . " products with seller price", $vendor_id);
        
        $updated_count = 0;
        $error_count = 0;
        $processed_count = 0;
        
        foreach ($product_ids as $index => $product_id) {
            $processed_count++;
            
            try {
                // ✅ تغییر: استفاده از _seller_list_price به جای _vendor_raw_price
                $seller_price = get_post_meta($product_id, '_seller_list_price', true);
                if (!$seller_price || $seller_price <= 0) {
                    Vendor_Logger::log_warning("Seller price not found or invalid for product", $product_id, $vendor_id);
                    continue;
                }
                
                // محاسبه قیمت نهایی
                $final_price = self::calculate_single_price($seller_price, $conversion_percent);
                
                // ذخیره در محصول
                $product = wc_get_product($product_id);
                if ($product) {
                    $old_price = $product->get_regular_price();
                    $product->set_regular_price($final_price);
                    
                    // ✅ محاسبه و ذخیره سود فروش
                    $sale_profit = $final_price - $seller_price;
                    $product->update_meta_data('_sale_profit', $sale_profit);
                    
                    $saved = $product->save();
                    
                    if ($saved) {
                        $updated_count++;
                        
                        // ✅ ذخیره زمان بروزرسانی
                        update_post_meta($product_id, '_colleague_price_update_time', current_time('mysql'));
                        
                        Vendor_Logger::log_success(
                            $product_id,
                            'price_calculated',
                            $vendor_id,
                            "Price calculated: {$seller_price} → {$final_price} (Profit: {$sale_profit})"
                        );
                    } else {
                        $error_count++;
                        Vendor_Logger::log_error("Failed to save product price", $product_id, $vendor_id);
                    }
                } else {
                    $error_count++;
                    Vendor_Logger::log_error("Product not found", $product_id, $vendor_id);
                }
                
            } catch (Exception $e) {
                $error_count++;
                Vendor_Logger::log_error(
                    "Price calculation failed: " . $e->getMessage(),
                    $product_id,
                    $vendor_id
                );
            }
            
            // گزارش پیشرفت و بهینه‌سازی حافظه
            if (($index + 1) % 50 === 0) {
                Vendor_Logger::log_info(
                    "Calculation progress: " . ($index + 1) . "/" . count($product_ids) . 
                    " - Updated: {$updated_count}, Errors: {$error_count}",
                    $vendor_id
                );
                wp_cache_flush();
                gc_collect_cycles();
            }
        }
        
        // گزارش نهایی
        Vendor_Logger::log_success(
            0,
            'price_calculation_completed',
            $vendor_id,
            "Price calculation completed: {$updated_count} updated, {$error_count} errors from {$processed_count} processed"
        );
        
        return $updated_count;
    }
    
    /**
     * محاسبه قیمت تک محصول
     */
    public static function calculate_single_price($seller_price, $conversion_percent) {
        // محاسبه قیمت با درنظرگیری درصد تبدیل
        $final_price = $seller_price * (1 + ($conversion_percent / 100));
        
        // گرد کردن به مضرب 1000 تومان
        $final_price = ceil($final_price / 1000) * 1000;
        
        return $final_price;
    }
    
    /**
     * ✅ تغییر: جستجوی محصولات بر اساس _seller_list_price
     */
    private static function get_product_ids_with_seller_price($cat_id, $vendor_id) {
        global $wpdb;
        
        Vendor_Logger::log_debug("Querying products with seller price for category: {$cat_id}", null, $vendor_id);
        
        $sql = "SELECT p.ID FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_type = 'product' 
                AND p.post_status = 'publish'
                AND pm.meta_key = '_seller_list_price'
                AND CAST(pm.meta_value AS DECIMAL(10,2)) > 0";
        
        // فیلتر بر اساس دسته
        if ($cat_id !== 'all') {
            $sql .= " AND p.ID IN (
                SELECT object_id FROM {$wpdb->term_relationships} 
                WHERE term_taxonomy_id = %d
            )";
            $sql = $wpdb->prepare($sql, intval($cat_id));
        } else {
            $sql = $wpdb->prepare($sql);
        }
        
        // ✅ فیلتر بر اساس post_author (فروشنده)
        $sql .= " AND p.post_author = %d";
        $sql = $wpdb->prepare($sql, $vendor_id);
        
        $product_ids = $wpdb->get_col($sql);
        
        Vendor_Logger::log_debug("Found " . count($product_ids) . " products with seller price", null, $vendor_id);
        
        return $product_ids;
    }
    
    /**
     * محاسبه دسته‌ای قیمت‌ها
     */
    public static function batch_calculate_prices($product_ids, $conversion_percent, $vendor_id = null) {
        Vendor_Logger::log_info(
            "Starting batch price calculation for " . count($product_ids) . " products - Percent: {$conversion_percent}%",
            $vendor_id
        );
        
        $updated_count = 0;
        $error_count = 0;
        
        foreach ($product_ids as $product_id) {
            try {
                // ✅ تغییر: استفاده از _seller_list_price
                $seller_price = get_post_meta($product_id, '_seller_list_price', true);
                if (!$seller_price || $seller_price <= 0) {
                    Vendor_Logger::log_warning("Seller price not found for batch product", $product_id, $vendor_id);
                    continue;
                }
                
                $final_price = self::calculate_single_price($seller_price, $conversion_percent);
                $sale_profit = $final_price - $seller_price;
                
                $product = wc_get_product($product_id);
                if ($product) {
                    $old_price = $product->get_regular_price();
                    $product->set_regular_price($final_price);
                    $product->update_meta_data('_sale_profit', $sale_profit);
                    
                    $saved = $product->save();
                    
                    if ($saved) {
                        $updated_count++;
                        update_post_meta($product_id, '_colleague_price_update_time', current_time('mysql'));
                        
                        Vendor_Logger::log_debug(
                            "Batch price calculated: {$seller_price} → {$final_price} (Profit: {$sale_profit})",
                            $product_id,
                            $vendor_id
                        );
                    } else {
                        $error_count++;
                        Vendor_Logger::log_error("Failed to save batch product price", $product_id, $vendor_id);
                    }
                } else {
                    $error_count++;
                    Vendor_Logger::log_error("Batch product not found", $product_id, $vendor_id);
                }
                
            } catch (Exception $e) {
                $error_count++;
                Vendor_Logger::log_error(
                    "Batch price calculation failed: " . $e->getMessage(),
                    $product_id,
                    $vendor_id
                );
            }
        }
        
        Vendor_Logger::log_success(
            0,
            'batch_price_calculation_completed',
            $vendor_id,
            "Batch calculation completed: {$updated_count} updated, {$error_count} errors"
        );
        
        return $updated_count;
    }
    
    /**
     * دریافت نام فروشنده
     */
    private static function get_vendor_name($vendor_id) {
        $vendor = get_userdata($vendor_id);
        return $vendor ? $vendor->display_name : 'Unknown Vendor';
    }
    
    /**
     * بررسی وضعیت محاسبه قیمت
     */
    public static function get_calculation_status($vendor_id, $cat_id) {
        $product_ids = self::get_product_ids_with_seller_price($cat_id, $vendor_id);
        $conversion_percent = floatval(get_user_meta($vendor_id, 'vendor_price_conversion_percent', true));
        
        $status = [
            'total_products' => count($product_ids),
            'conversion_percent' => $conversion_percent,
            'sample_calculation' => []
        ];
        
        // محاسبه نمونه برای 5 محصول اول
        $sample_count = min(5, count($product_ids));
        for ($i = 0; $i < $sample_count; $i++) {
            $product_id = $product_ids[$i];
            // ✅ تغییر: استفاده از _seller_list_price
            $seller_price = get_post_meta($product_id, '_seller_list_price', true);
            if ($seller_price && $seller_price > 0) {
                $final_price = self::calculate_single_price($seller_price, $conversion_percent);
                $status['sample_calculation'][] = [
                    'product_id' => $product_id,
                    'product_name' => get_the_title($product_id),
                    'seller_price' => $seller_price,
                    'final_price' => $final_price,
                    'profit' => $final_price - $seller_price
                ];
            }
        }
        
        Vendor_Logger::log_info(
            "Calculation status checked: {$status['total_products']} products, {$conversion_percent}% conversion",
            $vendor_id
        );
        
        return $status;
    }
    
    /**
     * ✅ جدید: محاسبه سریع قیمت برای یک محصول
     */
    public static function calculate_single_product_price($product_id, $vendor_id = null) {
        $seller_price = get_post_meta($product_id, '_seller_list_price', true);
        
        if (!$seller_price || $seller_price <= 0) {
            return false;
        }
        
        // دریافت درصد تبدیل از مالک محصول
        $product = get_post($product_id);
        $vendor_id = $product->post_author;
        $conversion_percent = floatval(get_user_meta($vendor_id, 'vendor_price_conversion_percent', true));
        
        $final_price = self::calculate_single_price($seller_price, $conversion_percent);
        $sale_profit = $final_price - $seller_price;
        
        return [
            'seller_price' => $seller_price,
            'final_price' => $final_price,
            'profit' => $sale_profit,
            'conversion_percent' => $conversion_percent
        ];
    }
}