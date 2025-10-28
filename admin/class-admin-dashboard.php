<?php
if (!defined('ABSPATH')) exit;

class Admin_Dashboard {
    
    /**
     * نمایش آمار کلی و داشبورد
     */
    public static function render_dashboard_stats() {
        $vendors = get_users(['role__in' => ['hamkar', 'seller']]);
        $total_products_with_raw_price = self::count_products_with_meta('_seller_list_price');
        $total_products_with_final_price = self::count_products_with_meta('_vendor_final_price');
        $total_vendors_products = 0;
        
        // محاسبه مجموع محصولات همه فروشندگان
        foreach ($vendors as $vendor) {
            $total_vendors_products += Vendor_Product_Assigner::get_vendor_real_products_count($vendor->ID);
        }
        ?>
        
        <div class="card">
            <h2>📊 آمار کلی سیستم</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 15px;">
                <div style="text-align: center; padding: 20px; background: #f0f9ff; border-radius: 8px; border-left: 4px solid #1e40af;">
                    <div style="font-size: 28px; font-weight: bold; color: #1e40af;"><?php echo count($vendors); ?></div>
                    <div style="font-size: 14px; color: #64748b;">فروشنده فعال</div>
                </div>
                
                <div style="text-align: center; padding: 20px; background: #fef7cd; border-radius: 8px; border-left: 4px solid #d97706;">
                    <div style="font-size: 28px; font-weight: bold; color: #d97706;"><?php echo $total_vendors_products; ?></div>
                    <div style="font-size: 14px; color: #64748b;">کل محصولات اختصاص‌یافته</div>
                </div>
                
                <div style="text-align: center; padding: 20px; background: #f0fdf4; border-radius: 8px; border-left: 4px solid #15803d;">
                    <div style="font-size: 28px; font-weight: bold; color: #15803d;"><?php echo $total_products_with_raw_price; ?></div>
                    <div style="font-size: 14px; color: #64748b;">قیمت‌های خام</div>
                </div>
                
                <div style="text-align: center; padding: 20px; background: #fef2f2; border-radius: 8px; border-left: 4px solid #dc2626;">
                    <div style="font-size: 28px; font-weight: bold; color: #dc2626;"><?php echo $total_products_with_final_price; ?></div>
                    <div style="font-size: 14px; color: #64748b;">قیمت‌های نهایی</div>
                </div>
            </div>
        </div>

        <div class="card" style="margin-top: 20px;">
            <h3>📈 نمودار وضعیت سنک</h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div>
                    <h4>وضعیت قیمت‌ها</h4>
                    <div style="background: #f8fafc; padding: 15px; border-radius: 8px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                            <span>قیمت‌های خام:</span>
                            <strong><?php echo $total_products_with_raw_price; ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                            <span>قیمت‌های نهایی:</span>
                            <strong><?php echo $total_products_with_final_price; ?></strong>
                        </div>
                        <div style="height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden; margin: 10px 0;">
                            <?php 
                            $total_processed = $total_products_with_raw_price + $total_products_with_final_price;
                            $max_products = max($total_vendors_products, 1);
                            $raw_percent = ($total_products_with_raw_price / $max_products) * 100;
                            $final_percent = ($total_products_with_final_price / $max_products) * 100;
                            ?>
                            <div style="height: 100%; background: #3b82f6; width: <?php echo $raw_percent; ?>%; float: left;"></div>
                            <div style="height: 100%; background: #10b981; width: <?php echo $final_percent; ?>%; float: left;"></div>
                        </div>
                    </div>
                </div>
                
                <div>
                    <h4>عملکرد اخیر</h4>
                    <div style="background: #f8fafc; padding: 15px; border-radius: 8px;">
                        <?php
                        $recent_logs = Vendor_Logger::get_recent_logs('general', 5);
                        if (!empty($recent_logs)) {
                            echo '<ul style="margin: 0; padding: 0; list-style: none;">';
                            foreach ($recent_logs as $log) {
                                echo '<li style="padding: 5px 0; border-bottom: 1px solid #e2e8f0; font-size: 12px;">' . esc_html($log) . '</li>';
                            }
                            echo '</ul>';
                        } else {
                            echo '<p style="color: #64748b; text-align: center; margin: 0;">هیچ فعالیتی ثبت نشده</p>';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="card" style="margin-top: 20px;">
            <h3>🔧 سلامت سیستم</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
                <div style="background: #f0fdf4; padding: 15px; border-radius: 8px; border: 1px solid #bbf7d0;">
                    <h4 style="margin: 0 0 10px 0; color: #15803d;">✅ پوشه لاگ</h4>
                    <p style="margin: 0; font-size: 13px; color: #64748b;">
                        <?php 
                        $log_dir = BERON_SELLER_SYNC_PATH . 'logs/';
                        echo file_exists($log_dir) ? 'فعال - قابل نوشتن' : 'غیرفعال';
                        ?>
                    </p>
                </div>
                
                <div style="background: #fef7cd; padding: 15px; border-radius: 8px; border: 1px solid #fde68a;">
                    <h4 style="margin: 0 0 10px 0; color: #d97706;">📝 حجم لاگ‌ها</h4>
                    <p style="margin: 0; font-size: 13px; color: #64748b;">
                        <?php
                        $log_stats = Vendor_Logger::get_log_stats();
                        echo isset($log_stats['general']) ? $log_stats['general']['size'] : '0 بایت';
                        ?>
                    </p>
                </div>
                
                <div style="background: #f0f9ff; padding: 15px; border-radius: 8px; border: 1px solid #bae6fd;">
                    <h4 style="margin: 0 0 10px 0; color: #0369a1;">🔄 آخرین سنک</h4>
                    <p style="margin: 0; font-size: 13px; color: #64748b;">
                        <?php
                        if (isset($log_stats['general'])) {
                            echo human_time_diff(strtotime($log_stats['general']['last_modified'])) . ' پیش';
                        } else {
                            'هرگز';
                        }
                        ?>
                    </p>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * نمایش لیست فروشندگان با جزئیات
     */
    public static function render_vendors_list() {
        $vendors = get_users(['role__in' => ['hamkar', 'seller']]);
        ?>
        <div class="card" style="margin-top: 20px;">
            <h3>👥 فروشندگان فعال</h3>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f8fafc;">
                            <th style="padding: 12px; text-align: right; border-bottom: 2px solid #e2e8f0;">فروشنده</th>
                            <th style="padding: 12px; text-align: center; border-bottom: 2px solid #e2e8f0;">محصولات</th>
                            <th style="padding: 12px; text-align: center; border-bottom: 2px solid #e2e8f0;">قیمت خام</th>
                            <th style="padding: 12px; text-align: center; border-bottom: 2px solid #e2e8f0;">قیمت نهایی</th>
                            <th style="padding: 12px; text-align: center; border-bottom: 2px solid #e2e8f0;">وضعیت</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($vendors as $vendor): 
                            $product_count = Vendor_Product_Assigner::get_vendor_real_products_count($vendor->ID);
                            $raw_price_count = self::count_vendor_products_with_meta($vendor->ID, '_seller_list_price');
                            $final_price_count = self::count_vendor_products_with_meta($vendor->ID, '_vendor_final_price');
                            
                            // تشخیص وضعیت
                            if ($raw_price_count == 0 && $final_price_count == 0) {
                                $status = '❌ نیاز به سنک';
                                $status_color = '#ef4444';
                            } elseif ($raw_price_count > 0 && $final_price_count == 0) {
                                $status = '⚠️ نیاز به محاسبه';
                                $status_color = '#f59e0b';
                            } else {
                                $status = '✅ فعال';
                                $status_color = '#10b981';
                            }
                        ?>
                            <tr style="border-bottom: 1px solid #e2e8f0;">
                                <td style="padding: 12px;">
                                    <strong><?php echo esc_html($vendor->display_name); ?></strong>
                                    <br><small style="color: #64748b;"><?php echo $vendor->user_email; ?></small>
                                </td>
                                <td style="padding: 12px; text-align: center; font-weight: bold;"><?php echo $product_count; ?></td>
                                <td style="padding: 12px; text-align: center; color: #3b82f6; font-weight: bold;"><?php echo $raw_price_count; ?></td>
                                <td style="padding: 12px; text-align: center; color: #10b981; font-weight: bold;"><?php echo $final_price_count; ?></td>
                                <td style="padding: 12px; text-align: center; color: <?php echo $status_color; ?>; font-weight: bold;"><?php echo $status; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
    
    /**
     * شمارش محصولات با متای خاص برای یک فروشنده
     */
    private static function count_vendor_products_with_meta($vendor_id, $meta_key) {
        global $wpdb;
        
        return $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT pm.post_id) 
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE p.post_author = %d 
            AND p.post_type = 'product'
            AND p.post_status = 'publish'
            AND pm.meta_key = %s 
            AND pm.meta_value > '0'
        ", $vendor_id, $meta_key));
    }
    
    /**
     * شمارش کلی محصولات با متای خاص
     */
    private static function count_products_with_meta($meta_key) {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->postmeta} 
            WHERE meta_key = %s AND meta_value > '0'
        ", $meta_key));
    }
}