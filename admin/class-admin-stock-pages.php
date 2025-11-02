<?php
if (!defined('ABSPATH')) exit;

class Admin_Stock_Pages {
    
    public static function render_stocks_page() { ?>
    
        <div class="wrap">
            <h1>📦 بروزرسانی موجودی از فروشنده</h1>
            <?php Admin_Common::render_common_stats(); ?>
            <?php self::render_stocks_form(); ?>
        </div> <?php
        
    }
    
    public static function render_stocks_form() {
        $vendors = get_users(['role__in' => ['hamkar', 'seller']]);
        
        // دریافت مقادیر قبلی از POST یا GET
        $selected_vendor = isset($_POST['vendor_id']) ? intval($_POST['vendor_id']) : (isset($_GET['vendor_id']) ? intval($_GET['vendor_id']) : 0);
        ?>
        
        <div class="card">
            <h2>🔄 بروزرسانی موجودی از فروشنده</h2>
            <p>این عملیات موجودی محصولات را از فروشنده دریافت و بروزرسانی می‌کند.</p>
            
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" id="stock-update-form">
                <input type="hidden" name="action" value="update_vendor_stocks">
                
                <table class="form-table">
                    <tr>
                        <th><label for="stock_vendor_id">انتخاب فروشنده</label></th>
                        <td>
                            <select name="vendor_id" id="stock_vendor_id" required style="min-width: 300px;">
                                <option value="">-- انتخاب فروشنده --</option>
                                <?php foreach ($vendors as $vendor): 
                                    $product_count = self::get_vendor_products_count($vendor->ID);
                                    $selected = ($selected_vendor == $vendor->ID) ? 'selected' : '';
                                ?>
                                    <option value="<?php echo $vendor->ID; ?>" <?php echo $selected; ?>>
                                        <?php echo esc_html($vendor->display_name); ?> 
                                        (<?php echo $product_count; ?> محصول)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="product_brand">برند محصولات (اختیاری)</label></th>
                        <td>
                            <?php 
                            Vendor_UI_Components::render_brand_filter([], 'product_brand', [
                                'placeholder' => 'برندها را انتخاب کنید...'
                            ]); 
                            ?>
                            <p class="description">می‌توانید چند برند انتخاب کنید. در صورت عدم انتخاب، همه برندها پردازش می‌شوند.</p>
                        </td>
                    </tr>
                </table>
                
                <div class="stock-info-box" style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; padding: 15px; margin: 20px 0;">
                    <h3 style="margin-top: 0; color: #856404;">💡 نحوه کار سیستم</h3>
                    <ul style="margin-bottom: 0;">
                        <li>✅ محصولات بر اساس <strong>نویسنده (فروشنده)</strong> فیلتر می‌شوند</li>
                        <li>✅ موجودی از API فروشنده دریافت می‌شود</li>
                        <li>✅ بروزرسانی بر اساس SKU یکسان انجام می‌شود</li>
                        <li>✅ زمان آخرین سینک برای هر محصول ذخیره می‌شود</li>
                    </ul>
                </div>
                
                <?php submit_button('🚀 شروع بروزرسانی موجودی', 'primary large', 'submit', true); ?>
            </form>
        </div>
        <div>
            <?php 
            // نمایش آخرین فعالیت‌های بروزرسانی موجودی
            Modal_Activity_Status::render_recent('stock_sync', null, 6);
            ?>
        </div>
        
        <style>
        .stock-info-box ul {
            list-style-type: none;
            padding-right: 0;
        }
        
        .stock-info-box li {
            margin-bottom: 8px;
            padding-right: 10px;
        }
        
        .stock-info-box li:before {
            content: "•";
            color: #28a745;
            font-weight: bold;
            margin-left: 10px;
        }
        </style>
        <?php
    }
    
    /**
     * دریافت تعداد محصولات فروشنده
     */
    private static function get_vendor_products_count($vendor_id) {
        global $wpdb;
        
        $count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$wpdb->posts} 
            WHERE post_type = 'product' 
            AND post_status = 'publish' 
            AND post_author = %d
        ", $vendor_id));
        
        return $count ? $count : 0;
    }
}