<?php
if (!defined('ABSPATH')) exit;

class Admin_Calculate_Pages {
    
    public static function render_calculate_page() {
        ?>
        <div class="wrap">
            <h1>محاسبه قیمت‌های نهایی</h1>
            <?php Admin_Common::render_common_stats(); ?>
            <?php self::render_calculate_form(); ?>
        </div>
        <?php
    }
    
    public static function render_calculate_form() {
        $vendors = get_users(['role__in' => ['hamkar', 'seller']]);
        $categories = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
        ?>
        
        <div class="card">
            <h2>🧮 محاسبه قیمت‌های نهایی</h2>
            
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <input type="hidden" name="action" value="calculate_vendor_prices">
                <?php wp_nonce_field('calculate_vendor_prices_nonce', '_wpnonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th><label for="calc_vendor_id">فروشنده</label></th>
                        <td>
                            <select name="vendor_id" id="calc_vendor_id" required style="min-width: 300px;">
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
                        <th><label for="calc_conversion_percent">درصد افزودن به قیمت</label></th>
                        <td>
                            <input type="number" name="conversion_percent" id="calc_conversion_percent" 
                                   value="15" min="0" max="1000" step="0.1" style="width: 150px;" required>
                            <span>%</span>
                            <p class="description">مثلاً برای 15% افزایش قیمت، عدد 15 را وارد کنید</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label for="calc_product_cat">دسته محصولات (اختیاری)</label></th>
                        <td>
                            <select name="product_cat" id="calc_product_cat" style="min-width: 300px;">
                                <option value="all">همه دسته‌ها</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat->term_id; ?>"><?php echo esc_html($cat->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('شروع محاسبه قیمت‌های نهایی', 'primary', 'submit', true); ?>
            </form>
        </div>
        <?php
    }
}