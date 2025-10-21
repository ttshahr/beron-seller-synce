<?php
if (!defined('ABSPATH')) exit;

class Admin_Stock_Pages {
    
    public static function render_stocks_page() {
        ?>
        <div class="wrap">
            <h1>بروزرسانی موجودی از فروشنده</h1>
            <?php Admin_Common::render_common_stats(); ?>
            <?php self::render_stocks_form(); ?>
        </div>
        <?php
    }
    
    public static function render_stocks_form() {
        $vendors = get_users(['role__in' => ['hamkar', 'seller']]);
        $categories = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
        $current_vendor_id = isset($_GET['vendor_id']) ? intval($_GET['vendor_id']) : 0;
        ?>
        
        <div class="card" style="margin-bottom: 20px; background: #f0f6ff; border-left: 4px solid #1e40af;">
            <h3 style="color: #1e40af; margin-top: 0;">🤖 مدیریت اختصاص محصولات</h3>
            
            <?php if ($current_vendor_id): 
                $status = Vendor_Product_Assigner::get_assignment_status($current_vendor_id);
            ?>
                <div style="background: white; padding: 15px; border-radius: 5px; margin: 10px 0;">
                    <h4>📊 وضعیت فعلی:</h4>
                    <ul>
                        <li>اتصال: <?php echo $status['connection']['success'] ? '✅ متصل' : '❌ قطع'; ?></li>
                        <li>محصولات فروشنده: <strong><?php echo $status['vendor_products_count']; ?></strong></li>
                        <li>محصولات اختصاص داده شده: <strong><?php echo $status['assigned_products_count']; ?></strong></li>
                        <li>محصولات با قیمت (بدون اختصاص): <strong><?php echo $status['products_with_price_unassigned']; ?></strong></li>
                    </ul>
                    <p><strong>پیشنهاد:</strong> <?php echo $status['recommendation']; ?></p>
                </div>
            <?php endif; ?>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 15px;">
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <input type="hidden" name="action" value="assign_vendor_products">
                    <input type="hidden" name="vendor_id" id="assign_vendor_id">
                    <button type="submit" class="button button-primary" style="width: 100%;">
                        🔄 اختصاص خودکار
                    </button>
                    <p style="font-size: 12px; margin: 5px 0 0 0;">همه محصولات را بررسی می‌کند</p>
                </form>
                
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <input type="hidden" name="action" value="assign_smart_vendor_products">
                    <input type="hidden" name="vendor_id" id="assign_smart_vendor_id">
                    <button type="submit" class="button button-secondary" style="width: 100%;">
                        🧠 اختصاص هوشمند
                    </button>
                    <p style="font-size: 12px; margin: 5px 0 0 0;">فقط محصولات با قیمت را اختصاص می‌دهد</p>
                </form>
            </div>
        </div>

        <div class="card">
            <h2>📦 بروزرسانی موجودی از فروشنده</h2>
            <p>این عملیات موجودی محصولات را از فروشنده دریافت و بروزرسانی می‌کند.</p>
            
            <div id="stock-report-container" style="display: none; margin-bottom: 15px; padding: 15px; background: #f0f9ff; border-radius: 5px; border-left: 4px solid #1e40af;">
                <h4 style="margin-top: 0;">📊 پیش‌نمایش بروزرسانی:</h4>
                <div id="stock-report-content"></div>
                <button type="button" id="hide-report" class="button" style="margin-top: 10px;">بستن</button>
            </div>

            <button type="button" id="preview-stock-update" class="button button-secondary" style="margin-bottom: 15px;">
                🔍 پیش‌نمایش بروزرسانی
            </button>
            
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <input type="hidden" name="action" value="update_vendor_stocks">
                
                <table class="form-table">
                    <tr>
                        <th><label for="stock_vendor_id">انتخاب فروشنده</label></th>
                        <td>
                            <select name="vendor_id" id="stock_vendor_id" required style="min-width: 300px;">
                                <option value="">-- انتخاب فروشنده --</option>
                                <?php foreach ($vendors as $vendor): 
                                    $product_count = Vendor_Product_Assigner::get_vendor_real_products_count($vendor->ID);
                                ?>
                                    <option value="<?php echo $vendor->ID; ?>" <?php selected($current_vendor_id, $vendor->ID); ?>>
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
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat->term_id; ?>"><?php echo esc_html($cat->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">در صورت انتخاب "همه دسته‌ها"، سیستم از مالک محصول برای فیلتر کردن استفاده می‌کند.</p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('شروع بروزرسانی موجودی', 'primary', 'submit', true); ?>
            </form>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // مدیریت اختصاص محصولات
            $('#stock_vendor_id').on('change', function() {
                $('#assign_vendor_id').val($(this).val());
                $('#assign_smart_vendor_id').val($(this).val());
            });
            
            // پیش‌نمایش بروزرسانی موجودی
            $('#preview-stock-update').on('click', function() {
                var vendorId = $('#stock_vendor_id').val();
                var categoryId = $('#stock_product_cat').val();
                
                if (!vendorId) {
                    alert('لطفا فروشنده را انتخاب کنید');
                    return;
                }
                
                $('#stock-report-content').html('در حال بررسی...');
                $('#stock-report-container').show();
                
                // AJAX call برای پیش‌نمایش
                $.post(ajaxurl, {
                    action: 'preview_stock_update',
                    vendor_id: vendorId,
                    category_id: categoryId,
                    security: '<?php echo wp_create_nonce("preview_stock_nonce"); ?>'
                }, function(response) {
                    $('#stock-report-content').html(response);
                });
            });
            
            $('#hide-report').on('click', function() {
                $('#stock-report-container').hide();
            });
        });
        </script>
        <?php
    }
}