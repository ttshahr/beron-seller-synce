<?php
if (!defined('ABSPATH')) exit;

class Admin_Stock_Pages {
    
    public static function render_stocks_page() {
        ?>
        <div class="wrap">
            <h1>📦 بروزرسانی موجودی از فروشنده</h1>
            <?php Admin_Common::render_common_stats(); ?>
            <?php self::render_stocks_form(); ?>
        </div>
        <?php
    }
    
    public static function render_stocks_form() {
        $vendors = get_users(['role__in' => ['hamkar', 'seller']]);
        $categories = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
        
        // دریافت مقادیر قبلی از POST یا GET
        $selected_vendor = isset($_POST['vendor_id']) ? intval($_POST['vendor_id']) : (isset($_GET['vendor_id']) ? intval($_GET['vendor_id']) : 0);
        $selected_category = isset($_POST['product_cat']) ? sanitize_text_field($_POST['product_cat']) : (isset($_GET['product_cat']) ? sanitize_text_field($_GET['product_cat']) : 'all');
        
        // نمایش پیام‌های نتیجه
        if (isset($_GET['updated'])) {
            $updated_count = intval($_GET['updated']);
            echo '<div class="notice notice-success is-dismissible"><p>✅ ' . $updated_count . ' محصول با موفقیت بروزرسانی شدند.</p></div>';
        }
        
        if (isset($_GET['error'])) {
            echo '<div class="notice notice-error is-dismissible"><p>❌ خطا در بروزرسانی موجودی. لطفا لاگ‌ها را بررسی کنید.</p></div>';
        }
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
                        <th><label for="stock_product_cat">دسته محصولات (اختیاری)</label></th>
                        <td>
                            <select name="product_cat" id="stock_product_cat" style="min-width: 300px;">
                                <option value="all">همه دسته‌ها</option>
                                <?php foreach ($categories as $cat): 
                                    $selected = ($selected_category == $cat->term_id) ? 'selected' : '';
                                ?>
                                    <option value="<?php echo $cat->term_id; ?>" <?php echo $selected; ?>>
                                        <?php echo esc_html($cat->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">در صورت انتخاب دسته خاص، فقط محصولات آن دسته بروزرسانی می‌شوند.</p>
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