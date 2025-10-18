<?php
if (!defined('ABSPATH')) exit;

class Vendor_Product_Sync_Manager {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_sync_menus']);
        add_action('admin_post_sync_vendor_prices', [$this, 'handle_sync_prices_request']);
        add_action('admin_post_calculate_vendor_prices', [$this, 'handle_calculate_request']);
        add_action('admin_post_update_vendor_stocks', [$this, 'handle_update_stocks_request']);
        add_action('admin_post_assign_vendor_products', [$this, 'handle_assign_products_request']);
        add_action('admin_post_assign_smart_vendor_products', [$this, 'handle_smart_assign_request']);
        add_action('admin_post_test_vendor_connection', [$this, 'handle_test_connection_request']);
        add_action('wp_ajax_get_stock_report', [$this, 'ajax_get_stock_report']);
        add_action('admin_footer', [$this, 'add_admin_footer_scripts']);
    }

    public function add_sync_menus() {
        // منوی اصلی
        add_menu_page(
            'همگام‌سازی فروشندگان',
            'همگام‌سازی فروشندگان',
            'manage_woocommerce',
            'vendor-sync',
            [$this, 'render_main_page'],
            'dashicons-update',
            56
        );
        
        // زیرمنوها
        add_submenu_page(
            'vendor-sync',
            'دریافت قیمت‌های خام',
            'دریافت قیمت‌ها',
            'manage_woocommerce',
            'vendor-sync-prices',
            [$this, 'render_sync_prices_page']
        );
        
        add_submenu_page(
            'vendor-sync',
            'محاسبه قیمت نهایی',
            'محاسبه قیمت‌ها',
            'manage_woocommerce',
            'vendor-sync-calculate',
            [$this, 'render_calculate_page']
        );
        
        add_submenu_page(
            'vendor-sync',
            'بروزرسانی موجودی',
            'بروزرسانی موجودی',
            'manage_woocommerce',
            'vendor-sync-stocks',
            [$this, 'render_stocks_page']
        );
        
        add_submenu_page(
            'vendor-sync',
            'دیباگ همگام‌سازی',
            'دیباگ',
            'manage_woocommerce',
            'vendor-sync-debug',
            [$this, 'render_debug_page']
        );
    }
    
    public function render_main_page() {
        $vendors = get_users(['role__in' => ['hamkar', 'seller']]);
        ?>
        <div class="wrap">
            <h1>مدیریت همگام‌سازی محصولات فروشندگان</h1>
            
            <div class="card">
                <h2>📊 آمار کلی</h2>
                <?php $this->render_common_stats(); ?>
            </div>
            
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
            
            <div class="card" style="margin-top: 20px;">
                <h3>فروشندگان فعال</h3>
                <ul>
                    <?php foreach ($vendors as $vendor): 
                        $product_count = Vendor_Product_Assigner::get_vendor_products_count($vendor->ID);
                    ?>
                        <li>
                            <strong><?php echo esc_html($vendor->display_name); ?></strong> 
                            (<?php echo $vendor->user_login; ?>)
                            - محصولات: <?php echo $product_count; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php
    }
    
    public function render_sync_prices_page() {
        ?>
        <div class="wrap">
            <h1>دریافت قیمت‌های خام از فروشنده</h1>
            <?php $this->render_common_stats(); ?>
            <?php $this->render_sync_prices_form(); ?>
        </div>
        <?php
    }
    
    public function render_calculate_page() {
        ?>
        <div class="wrap">
            <h1>محاسبه قیمت‌های نهایی</h1>
            <?php $this->render_common_stats(); ?>
            <?php $this->render_calculate_form(); ?>
        </div>
        <?php
    }
    
    public function render_stocks_page() {
        ?>
        <div class="wrap">
            <h1>بروزرسانی موجودی از فروشنده</h1>
            <?php $this->render_common_stats(); ?>
            <?php $this->render_stocks_form(); ?>
        </div>
        <?php
    }
    
    public function render_debug_page() {
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
    
    private function render_common_stats() {
        $products_with_raw_price = $this->count_products_with_meta('_seller_list_price');
        $products_with_final_price = $this->count_products_with_meta('_vendor_final_price');
        
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
    
    private function render_sync_prices_form() {
        $vendors = get_users(['role__in' => ['hamkar', 'seller']]);
        $categories = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
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
                        <th><label for="product_cat">دسته محصولات (اختیاری)</label></th>
                        <td>
                            <select name="product_cat" id="product_cat" style="min-width: 300px;">
                                <option value="all">همه دسته‌ها</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat->term_id; ?>"><?php echo esc_html($cat->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">در صورت انتخاب دسته خاص، فقط محصولات آن دسته پردازش می‌شوند.</p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('شروع دریافت قیمت‌های خام', 'primary', 'submit', true); ?>
            </form>
        </div>
        <?php
    }
    
    private function render_calculate_form() {
        $vendors = get_users(['role__in' => ['hamkar', 'seller']]);
        $categories = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
        ?>
        <div class="card">
            <h2>🧮 محاسبه قیمت‌های نهایی</h2>
            <p>این عملیات قیمت‌های نهایی را بر اساس درصد سود محاسبه و اعمال می‌کند.</p>
            
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <input type="hidden" name="action" value="calculate_vendor_prices">
                
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
    
    private function render_stocks_form() {
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

        <div class="card" style="margin-bottom: 20px; background: #e8f5e8; border-left: 4px solid #28a745;">
            <h3 style="color: #155724; margin-top: 0;">🔗 تست اتصال API</h3>
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <input type="hidden" name="action" value="test_vendor_connection">
                <input type="hidden" name="vendor_id" id="test_connection_vendor_id">
                <?php submit_button('تست اتصال به فروشنده', 'secondary', 'submit', false); ?>
                <p class="description">بررسی می‌کند آیا به API فروشنده می‌توان متصل شد یا نه.</p>
            </form>
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

    // ==================== هندلرهای اصلی ====================
    
    public function handle_sync_prices_request() {
        if (!current_user_can('manage_woocommerce')) wp_die('دسترسی غیرمجاز');
        
        set_time_limit(600);
        ini_set('memory_limit', '512M');
        wp_suspend_cache_addition(true);

        $vendor_id = intval($_POST['vendor_id']);
        $cat_id = sanitize_text_field($_POST['product_cat']);

        try {
            $saved_count = Vendor_Raw_Price_Saver_Optimized::save_raw_prices_optimized($vendor_id, $cat_id);
            wp_redirect(admin_url('admin.php?page=vendor-sync-prices&saved=' . $saved_count));
            exit;
        } catch (Exception $e) {
            error_log('Vendor Price Sync Error: ' . $e->getMessage());
            wp_redirect(admin_url('admin.php?page=vendor-sync-prices&error=1'));
            exit;
        }
    }
    
    public function handle_calculate_request() {
        if (!current_user_can('manage_woocommerce')) wp_die('دسترسی غیرمجاز');
        
        set_time_limit(300);
        ini_set('memory_limit', '256M');

        $vendor_id = intval($_POST['vendor_id']);
        $cat_id = sanitize_text_field($_POST['product_cat']);

        try {
            $calculated_count = Vendor_Price_Calculator::calculate_final_prices($vendor_id, $cat_id);
            wp_redirect(admin_url('admin.php?page=vendor-sync-calculate&calculated=' . $calculated_count));
            exit;
        } catch (Exception $e) {
            error_log('Price Calculate Error: ' . $e->getMessage());
            wp_redirect(admin_url('admin.php?page=vendor-sync-calculate&error=1'));
            exit;
        }
    }
    
    public function handle_update_stocks_request() {
        if (!current_user_can('manage_woocommerce')) wp_die('دسترسی غیرمجاز');
        
        set_time_limit(600);
        ini_set('memory_limit', '512M');
        wp_suspend_cache_addition(true);

        $vendor_id = intval($_POST['vendor_id']);
        $cat_id = sanitize_text_field($_POST['product_cat']);

        try {
            $updated_count = Vendor_Stock_Updater_Optimized::update_stocks($vendor_id, $cat_id);
            wp_redirect(admin_url('admin.php?page=vendor-sync-stocks&updated=' . $updated_count));
            exit;
        } catch (Exception $e) {
            error_log('Stock Update Error: ' . $e->getMessage());
            wp_redirect(admin_url('admin.php?page=vendor-sync-stocks&error=1'));
            exit;
        }
    }
    
    public function handle_assign_products_request() {
        if (!current_user_can('manage_woocommerce')) wp_die('دسترسی غیرمجاز');
        
        set_time_limit(600);
        ini_set('memory_limit', '512M');
        wp_suspend_cache_addition(true);

        $vendor_id = intval($_POST['vendor_id']);

        try {
            $assigned_count = Vendor_Product_Assigner::assign_vendor_to_products($vendor_id);
            wp_redirect(admin_url('admin.php?page=vendor-sync-stocks&assigned=' . $assigned_count));
            exit;
        } catch (Exception $e) {
            error_log('Product Assignment Error: ' . $e->getMessage());
            wp_redirect(admin_url('admin.php?page=vendor-sync-stocks&error=1'));
            exit;
        }
    }
    
    public function handle_smart_assign_request() {
        if (!current_user_can('manage_woocommerce')) wp_die('دسترسی غیرمجاز');
        
        set_time_limit(300);
        ini_set('memory_limit', '256M');

        $vendor_id = intval($_POST['vendor_id']);

        try {
            $assigned_count = Vendor_Product_Assigner::assign_products_with_prices($vendor_id);
            wp_redirect(admin_url('admin.php?page=vendor-sync-stocks&assigned=' . $assigned_count));
            exit;
        } catch (Exception $e) {
            error_log('Smart Assignment Error: ' . $e->getMessage());
            wp_redirect(admin_url('admin.php?page=vendor-sync-stocks&error=1'));
            exit;
        }
    }
    
    public function handle_test_connection_request() {
        if (!current_user_can('manage_woocommerce')) wp_die('دسترسی غیرمجاز');
        
        $vendor_id = intval($_POST['vendor_id']);
        $meta = Vendor_Meta_Handler::get_vendor_meta($vendor_id);
        
        echo '<div class="wrap">';
        echo '<h1>نتایج تست اتصال</h1>';
        echo '<div class="card">';
        
        try {
            $connection_test = Vendor_API_Optimizer::test_connection($meta);
            
            if ($connection_test['success']) {
                echo '<div style="color: green; font-weight: bold;">✅ اتصال موفق</div>';
                echo '<ul>';
                echo '<li>تعداد محصولات: ' . ($connection_test['total_products'] ?? 'نامشخص') . '</li>';
                echo '<li>پیام: ' . ($connection_test['message'] ?? '') . '</li>';
                echo '</ul>';
            } else {
                echo '<div style="color: red; font-weight: bold;">❌ اتصال ناموفق</div>';
                echo '<ul>';
                echo '<li>خطا: ' . ($connection_test['error'] ?? 'نامشخص') . '</li>';
                echo '<li>جزئیات: ' . ($connection_test['details'] ?? '') . '</li>';
                echo '</ul>';
            }
            
        } catch (Exception $e) {
            echo '<div style="color: red; font-weight: bold;">❌ خطا در تست اتصال</div>';
            echo '<p>' . $e->getMessage() . '</p>';
        }
        
        echo '</div>';
        echo '<a href="' . admin_url('admin.php?page=vendor-sync-stocks') . '" class="button">بازگشت</a>';
        echo '</div>';
        exit;
    }
    
    // ==================== AJAX هندلرها ====================
    
    public function ajax_get_stock_report() {
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('دسترسی غیرمجاز');
        }
        
        $vendor_id = intval($_POST['vendor_id']);
        $cat_id = sanitize_text_field($_POST['product_cat']);
        
        try {
            $report = Vendor_Stock_Updater_Optimized::get_stock_update_report($vendor_id, $cat_id);
            wp_send_json_success($report);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    public function add_admin_footer_scripts() {
        if (strpos(get_current_screen()->id, 'vendor-sync') !== false) {
            ?>
            <script>
            jQuery(document).ready(function($) {
                // پر کردن خودکار همه فیلدها
                $('#stock_vendor_id').on('change', function() {
                    var vendorId = $(this).val();
                    $('#assign_vendor_id').val(vendorId);
                    $('#assign_smart_vendor_id').val(vendorId);
                    $('#test_connection_vendor_id').val(vendorId);
                    
                    // رفرش صفحه برای نمایش وضعیت
                    if (vendorId) {
                        window.location.href = '<?php echo admin_url('admin.php?page=vendor-sync-stocks&vendor_id='); ?>' + vendorId;
                    }
                });
                
                // پیش‌نمایش بروزرسانی
                $('#preview-stock-update').on('click', function() {
                    var vendorId = $('#stock_vendor_id').val();
                    var catId = $('#stock_product_cat').val();
                    
                    if (!vendorId) {
                        alert('لطفا فروشنده را انتخاب کنید');
                        return;
                    }
                    
                    var $button = $(this);
                    $button.prop('disabled', true).text('در حال بررسی...');
                    
                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: {
                            action: 'get_stock_report',
                            vendor_id: vendorId,
                            product_cat: catId
                        },
                        success: function(response) {
                            $button.prop('disabled', false).text('🔍 پیش‌نمایش بروزرسانی');
                            
                            if (response.success) {
                                var report = response.data;
                                var html = '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin-bottom: 15px;">';
                                html += '<div style="text-align: center; padding: 10px; background: #fff; border-radius: 5px; border: 1px solid #e1e1e1;">';
                                html += '<div style="font-size: 18px; font-weight: bold; color: #1e40af;">' + report.total_local_products + '</div>';
                                html += '<div>محصولات محلی</div>';
                                html += '</div>';
                                
                                html += '<div style="text-align: center; padding: 10px; background: #fff; border-radius: 5px; border: 1px solid #e1e1e1;">';
                                html += '<div style="font-size: 18px; font-weight: bold; color: #dc2626;">' + report.total_vendor_products + '</div>';
                                html += '<div>محصولات فروشنده</div>';
                                html += '</div>';
                                
                                html += '<div style="text-align: center; padding: 10px; background: #fff; border-radius: 5px; border: 1px solid #e1e1e1;">';
                                html += '<div style="font-size: 18px; font-weight: bold; color: #15803d;">' + report.matched_products + '</div>';
                                html += '<div>قابل بروزرسانی</div>';
                                html += '</div>';
                                html += '</div>';
                                
                                if (report.matched_products > 0) {
                                    html += '<p style="color: #15803d; font-weight: bold;">✅ ' + report.matched_products + ' محصول برای بروزرسانی موجودی پیدا شد.</p>';
                                } else {
                                    html += '<p style="color: #dc2626; font-weight: bold;">❌ هیچ محصولی برای بروزرسانی پیدا نشد. لطفا ابتدا محصولات را اختصاص دهید.</p>';
                                }
                                
                                $('#stock-report-content').html(html);
                                $('#stock-report-container').show();
                            } else {
                                alert('خطا در دریافت گزارش: ' + response.data);
                            }
                        },
                        error: function() {
                            $button.prop('disabled', false).text('🔍 پیش‌نمایش بروزرسانی');
                            alert('خطا در ارتباط با سرور');
                        }
                    });
                });
                
                $('#hide-report').on('click', function() {
                    $('#stock-report-container').hide();
                });
            });
            </script>
            
            <style>
            #vendor-progress-container {
                background: #fff;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
                padding: 20px;
                margin: 20px 0;
            }
            .vendor-progress-bar {
                width: 100%;
                height: 20px;
                background: #f0f0f1;
                border-radius: 10px;
                overflow: hidden;
                margin: 10px 0;
            }
            .vendor-progress-fill {
                height: 100%;
                background: linear-gradient(90deg, #00a32a, #00ba37);
                border-radius: 10px;
                transition: width 0.3s ease;
                width: 0%;
            }
            .vendor-progress-info {
                display: flex;
                justify-content: space-between;
                font-size: 14px;
                color: #3c434a;
            }
            </style>
            <?php
        }
    }
    
    private function count_products_with_meta($meta_key) {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->postmeta} 
            WHERE meta_key = %s AND meta_value > '0'
        ", $meta_key));
    }
}

new Vendor_Product_Sync_Manager();