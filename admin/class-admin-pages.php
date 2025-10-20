<?php
if (!defined('ABSPATH')) exit;

class Admin_Pages {
    
public static function render_main_page() {
    ?>
    <div class="wrap">
        <h1>مدیریت همگام‌سازی محصولات فروشندگان</h1>
        
        <?php 
        // نمایش داشبورد
        Admin_Dashboard::render_dashboard_stats();
        Admin_Dashboard::render_vendors_list();
        ?>
        
        <div class="card" style="margin-top: 20px;">
            <h2>🚀 دسترسی سریع</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 15px;">
                <a href="<?php echo admin_url('admin.php?page=vendor-sync-prices'); ?>" class="button button-primary" style="text-align: center; padding: 15px;">
                    📥 دریافت قیمت‌های خام
                </a>
                <a href="<?php echo admin_url('admin.php?page=vendor-sync-calculate'); ?>" class="button button-secondary" style="text-align: center; padding: 15px;">
                    🧮 محاسبه قیمت نهایی
                </a>
                <a href="<?php echo admin_url('admin.php?page=vendor-sync-stocks'); ?>" class="button button-secondary" style="text-align: center; padding: 15px;">
                    📦 بروزرسانی موجودی
                </a>
                <a href="<?php echo admin_url('admin.php?page=vendor-sync-debug'); ?>" class="button" style="text-align: center; padding: 15px;">
                    🔧 دیباگ
                </a>
            </div>
        </div>
    </div>
    <?php
}
    
    public static function render_sync_prices_page() {
        ?>
        <div class="wrap">
            <h1>دریافت قیمت‌های خام از فروشنده</h1>
            <?php self::render_common_stats(); ?>
            <?php self::render_sync_prices_form(); ?>
        </div>
        <?php
    }
    
    public static function render_calculate_page() {
        ?>
        <div class="wrap">
            <h1>محاسبه قیمت‌های نهایی</h1>
            <?php self::render_common_stats(); ?>
            <?php self::render_calculate_form(); ?>
        </div>
        <?php
    }
    
    public static function render_stocks_page() {
        ?>
        <div class="wrap">
            <h1>بروزرسانی موجودی از فروشنده</h1>
            <?php self::render_common_stats(); ?>
            <?php self::render_stocks_form(); ?>
        </div>
        <?php
    }
    
    public static function render_debug_page() {
        $vendors = get_users(['role__in' => ['hamkar', 'seller']]);
        $selected_vendor = isset($_GET['vendor_id']) ? intval($_GET['vendor_id']) : 0;
        ?>
        
        <div class="wrap">
            <h1>دیباگ همگام‌سازی فروشندگان</h1>
            
            <div class="card">
                <h2>انتخاب فروشنده برای بررسی</h2>
                <form method="get">
                    <input type="hidden" name="page" value="vendor-sync-debug">
                    <select name="vendor_id" required style="min-width: 300px;">
                        <option value="">-- انتخاب فروشنده --</option>
                        <?php foreach ($vendors as $vendor): ?>
                            <option value="<?php echo $vendor->ID; ?>" <?php selected($selected_vendor, $vendor->ID); ?>>
                                <?php echo esc_html($vendor->display_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php submit_button('بررسی', 'primary'); ?>
                </form>
            </div>
            
            <?php if ($selected_vendor): ?>
                <?php Vendor_Debug_Helper::render_debug_page($selected_vendor); ?>
            <?php endif; ?>
        </div>
        <?php
    }
    
    public static function render_profit_page() {
        // ✅ مستقیماً از کلاس استفاده می‌کنیم
        $profit_calculator = new Sale_Profit_Calculator();
        $profit_calculator->render_page();
    }
    
    public static function render_common_stats() {
        $products_with_raw_price = self::count_products_with_meta('_seller_list_price');
        $products_with_final_price = self::count_products_with_meta('_vendor_final_price');
        
        // نمایش پیام‌های نتیجه
        if (isset($_GET['saved'])) {
            echo '<div class="notice notice-success"><p>✅ قیمت‌های خام با موفقیت ذخیره شدند. تعداد: ' . intval($_GET['saved']) . '</p></div>';
        }
        if (isset($_GET['calculated'])) {
            echo '<div class="notice notice-success"><p>✅ قیمت‌های نهایی با موفقیت محاسبه شدند. تعداد: ' . intval($_GET['calculated']) . '</p></div>';
        }
        if (isset($_GET['updated'])) {
            echo '<div class="notice notice-success"><p>✅ موجودی با موفقیت بروزرسانی شد. تعداد: ' . intval($_GET['updated']) . '</p></div>';
        }
        if (isset($_GET['assigned'])) {
            echo '<div class="notice notice-success"><p>✅ محصولات با موفقیت به فروشنده اختصاص داده شدند. تعداد: ' . intval($_GET['assigned']) . '</p></div>';
        }
        if (isset($_GET['error'])) {
            echo '<div class="notice notice-error"><p>❌ خطا در پردازش. لطفا لاگ را بررسی کنید.</p></div>';
        }
        ?>
        <div class="card">
            <h3>📈 آمار همگام‌سازی</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <div style="text-align: center; padding: 15px; background: #f0f9ff; border-radius: 5px;">
                    <div style="font-size: 24px; font-weight: bold; color: #1e40af;"><?php echo $products_with_raw_price; ?></div>
                    <div>محصولات با قیمت خام</div>
                </div>
                <div style="text-align: center; padding: 15px; background: #f0fdf4; border-radius: 5px;">
                    <div style="font-size: 24px; font-weight: bold; color: #15803d;"><?php echo $products_with_final_price; ?></div>
                    <div>محصولات با قیمت نهایی</div>
                </div>
            </div>
        </div>
        <?php
    }
    
    public static function render_sync_prices_form() {
    $vendors = get_users(['role__in' => ['hamkar', 'seller']]);
    $brands = get_terms([
        'taxonomy' => 'product_brand', // تغییر به تکسونومی سفارشی
        'hide_empty' => false
    ]);
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
                                    $product_count = Vendor_Product_Assigner::get_vendor_products_count($vendor->ID);
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
                        <th><label for="product_brand">برند محصولات (اختیاری)</label></th> <!-- تغییر عنوان -->
                        <td>
                            <select name="product_brand" id="product_brand" style="min-width: 300px;"> <!-- تغییر نام فیلد -->
                                <option value="all">همه برندها</option> <!-- تغییر متن -->
                                <?php foreach ($brands as $brand): ?>
                                    <option value="<?php echo $brand->term_id; ?>"><?php echo esc_html($brand->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">در صورت انتخاب برند خاص، فقط محصولات آن برند پردازش می‌شوند.</p> <!-- تغییر توضیحات -->
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('شروع دریافت قیمت‌های خام', 'primary', 'submit', true); ?>
            </form>
        </div>
        <?php
    }
    
    public static function render_calculate_form() {
        $vendors = get_users(['role__in' => ['hamkar', 'seller']]);
        $categories = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
        
        // اضافه کردن ماژول پیشرفت
        Progress_Manager::render_progress_html('price');
        Progress_Manager::render_progress_script('price');
        ?>
        
        <div class="card">
            <h2>🧮 محاسبه قیمت‌های نهایی</h2>
            
            <form id="price-calc-form">
                <?php wp_nonce_field('calculate_prices_nonce', '_ajax_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th><label for="calc_vendor_id">فروشنده</label></th>
                        <td>
                            <select name="vendor_id" id="calc_vendor_id" required style="min-width: 300px;">
                                <option value="">-- انتخاب فروشنده --</option>
                                <?php foreach ($vendors as $vendor): 
                                    $product_count = Vendor_Product_Assigner::get_vendor_products_count($vendor->ID);
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
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label for="calc_product_cat">دسته محصولات</label></th>
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
                
                <button type="button" id="start-price-calc-ajax" class="button button-primary">
                    شروع محاسبه قیمت (سیستم جدید)
                </button>
            </form>
            
            <div id="price-calc-result" style="margin-top:20px;"></div>
        </div>
    
        <script>
        jQuery(document).ready(function($){
            $('#start-price-calc-ajax').click(function(){
                var vendorId = $('#calc_vendor_id').val();
                var percent = $('#calc_conversion_percent').val();
                var category = $('#calc_product_cat').val();
                var nonce = $('#_ajax_nonce').val();
                
                if (!vendorId || !percent) {
                    alert('لطفا فروشنده و درصد را انتخاب کنید.');
                    return;
                }
                
                // ایجاد Job ID
                var jobId = 'price_calc_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
                
                // نمایش نوار پیشرفت
                $('#progress-container-price').show();
                window.progressManagerPrice.start(jobId);
                
                // فراخوانی AJAX
                $.post(ajaxurl, {
                    action: 'calculate_final_prices_ajax',
                    vendor_id: vendorId,
                    conversion_percent: percent,
                    product_cat: category,
                    job_id: jobId,
                    _wpnonce: nonce
                }, function(response) {
                    if (response.success) {
                        console.log('محاسبه کامل شد:', response.data);
                    } else {
                        console.error('خطا:', response.data);
                        // نمایش خطا به کاربر
                        $('#price-calc-result').html(
                            '<div class="notice notice-error"><p>خطا: ' + response.data + '</p></div>'
                        );
                    }
                }).fail(function(xhr, status, error) {
                    console.error('خطای ارتباط:', error);
                    $('#price-calc-result').html(
                        '<div class="notice notice-error"><p>خطای ارتباط با سرور</p></div>'
                    );
                });
            });
        });
        </script>
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
                                    $product_count = Vendor_Product_Assigner::get_vendor_products_count($vendor->ID);
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
        <?php
    }
    
    private static function count_products_with_meta($meta_key) {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->postmeta} 
            WHERE meta_key = %s AND meta_value > '0'
        ", $meta_key));
    }
}