<?php
if (!defined('ABSPATH')) exit;

class Admin_Price_Pages {
    
    public static function render_price_sync_page() {
        ?>
        <div class="wrap">
            <h1>دریافت قیمت‌های خام از فروشنده</h1>
            <?php Admin_Common::render_common_stats(); ?>
            <?php self::render_sync_prices_form(); ?>
        </div>
        <?php
    }
    
    public static function render_sync_prices_form() {
        $vendors = get_users(['role__in' => ['hamkar', 'seller']]);
        ?>
        <div class="card">
            <h2>📥 دریافت قیمت‌های خام از فروشنده</h2>
            <p>این عملیات قیمت‌های اصلی را از فروشنده دریافت و در متای <code>_seller_list_price</code> ذخیره می‌کند.</p>
            
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <input type="hidden" name="action" value="sync_vendor_prices">
                
                <table class="form-table">
                    <tr>
                        <th><label for="vendor_id">انتخاب فروشنده</label></th>
                        <td>
                            <select name="vendor_id" id="vendor_id" required style="min-width: 300px;">
                                <option value="">-- انتخاب فروشنده --</option>
                                <?php foreach ($vendors as $vendor): 
                                    $product_count = Vendor_Product_Assigner::get_vendor_real_products_count($vendor->ID);
                                ?>
                                    <option value="<?php echo $vendor->ID; ?>">
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
                
                <?php submit_button('شروع دریافت قیمت‌های خام', 'primary', 'submit', true); ?>
            </form>
        </div>
        <div>
            <?php 
            // نمایش آخرین فعالیت‌های همگام‌سازی قیمت
            Modal_Activity_Status::render_recent('price_sync', null, 6);
            ?>
        </div>
        <?php
    }
}