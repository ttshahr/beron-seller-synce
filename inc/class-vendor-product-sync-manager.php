<?php
if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/class-vendor-api-handler.php';
require_once __DIR__ . '/class-vendor-price-processor.php';

class Vendor_Product_Sync_Manager {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_sync_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        // AJAX endpoint برای کاربران لاگین شده (پیشخوان)
        add_action('wp_ajax_sync_vendor_products_batch', [$this, 'handle_sync_request_batch']);
    }

    public function add_sync_menu() {
        add_menu_page(
            'همگام‌سازی فروشندگان',
            'همگام‌سازی فروشندگان',
            'manage_woocommerce',
            'vendor-sync',
            [$this, 'render_sync_page'],
            'dashicons-update',
            56
        );
    }

    /**
     * لود اسکریپت فقط در صفحهٔ این افزونه
     */
    public function enqueue_scripts($hook) {
        // $hook برای صفحه‌ی بالایی که قبلاً تعریف کردیم 'toplevel_page_vendor-sync' است
        if ($hook !== 'toplevel_page_vendor-sync') return;

        // مسیر فایل JS دقیق نسبت به ریشه افزونه
        $script_url = plugin_dir_url( __DIR__ ) . 'assets/js/vendor-sync-progress.js';
        wp_enqueue_script('vendor-sync-progress', $script_url, ['jquery'], '1.0', true);

        // پارامترهایی که JS نیاز داره (ajax_url و nonce)
        wp_localize_script('vendor-sync-progress', 'vendorSync', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('vendor_sync_nonce')
        ]);
    }

    /**
     * صفحهٔ پیشخوان: فرم (شامل انتخاب نوع بروز رسانی)
     */
    public function render_sync_page() {
        $vendors = get_users(['role__in' => ['hamkar', 'seller']]);
        $categories = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
        ?>
        <div class="wrap">
            <h1>مدیریت همگام‌سازی محصولات فروشندگان</h1>

            <table class="form-table">
                <tr>
                    <th><label for="vendor_id">انتخاب فروشنده</label></th>
                    <td>
                        <select name="vendor_id" id="vendor_id" required>
                            <option value="">-- انتخاب فروشنده --</option>
                            <?php foreach ($vendors as $vendor): ?>
                                <option value="<?php echo esc_attr($vendor->ID); ?>">
                                    <?php echo esc_html($vendor->display_name); ?> (<?php echo esc_html($vendor->user_login); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th><label for="product_cat">دسته محصولات</label></th>
                    <td>
                        <select name="product_cat" id="product_cat">
                            <option value="all">همه دسته‌ها</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo esc_attr($cat->term_id); ?>"><?php echo esc_html($cat->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th><label for="sync_type">نوع بروزرسانی</label></th>
                    <td>
                        <select name="sync_type" id="sync_type">
                            <option value="both">قیمت و موجودی</option>
                            <option value="price">فقط قیمت</option>
                            <option value="stock">فقط موجودی</option>
                        </select>
                    </td>
                </tr>
            </table>

            <p class="description">این عملیات دو مرحله‌ای است: در هر batch ابتدا قیمت همکاری گرفته و در <code>_seller_list_price</code> ذخیره می‌شود؛ سپس قیمت نهایی با درصد تبدیل محاسبه و در قیمت عادی (regular_price) قرار می‌گیرد.</p>

            <div id="vendor-sync-controls" style="margin-top:15px;">
                <button id="sync-button" class="button button-primary">شروع بروزرسانی (Batch)</button>
                <span id="vendor-sync-status" style="margin-left:10px;"></span>
            </div>

            <div id="progress-container" style="display:none; margin-top:20px;">
                <div style="width:100%; background:#eee; border-radius:4px; overflow:hidden;">
                    <div id="progress-bar" style="width:0%; height:26px; background:#0073aa; color:#fff; text-align:center; line-height:26px;">0%</div>
                </div>
                <p id="progress-text" style="margin-top:8px;">در حال آماده‌سازی...</p>
            </div>
        </div>
        <?php
    }

    /**
     * متد AJAX برای پردازش دسته‌ای (batch) محصولات
     * درخواست‌ها از JS به اکشن 'sync_vendor_products_batch' فرستاده می‌شوند.
     *
     * پارامترهای POST:
     * - nonce (برای امنیت)
     * - vendor_id
     * - product_cat
     * - sync_type  ('price'|'stock'|'both')
     * - step (عدد شروع از 1)
     *
     * پاسخ JSON:
     * - success:true, data: ['processed'=>N, 'next_step'=>M, 'done'=>bool, 'total'=>total_products]
     */
    public function handle_sync_request_batch() {
        // امنیت
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'vendor_sync_nonce')) {
            wp_send_json_error('خطای امنیتی (nonce).');
        }
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('دسترسی غیرمجاز.');
        }

        $vendor_id = isset($_POST['vendor_id']) ? intval($_POST['vendor_id']) : 0;
        $cat_id    = isset($_POST['product_cat']) ? sanitize_text_field($_POST['product_cat']) : 'all';
        $sync_type = isset($_POST['sync_type']) ? sanitize_text_field($_POST['sync_type']) : 'both';
        $step      = isset($_POST['step']) ? max(1, intval($_POST['step'])) : 1;

        if (!$vendor_id) wp_send_json_error('فروشنده انتخاب نشده است.');

        // اطلاعات فروشنده
        $meta = [
            'url' => get_user_meta($vendor_id, 'vendor_website_url', true),
            'key' => get_user_meta($vendor_id, 'vendor_consumer_key', true),
            'secret' => get_user_meta($vendor_id, 'vendor_consumer_secret', true),
            'currency' => get_user_meta($vendor_id, 'vendor_currency', true),
            'stock_type' => get_user_meta($vendor_id, 'vendor_stock_type', true),
            'price_meta_key' => get_user_meta($vendor_id, 'vendor_cooperation_price_meta_key', true),
            'conversion_percent' => get_user_meta($vendor_id, 'vendor_price_conversion_percent', true),
        ];

        // تنظیمات batch
        $batch_size = 50;
        $offset = ($step - 1) * $batch_size;

        // واکشی محصولات با offset
        $args = [
            'post_type' => 'product',
            'posts_per_page' => $batch_size,
            'offset' => $offset,
            'post_status' => 'publish',
            'fields' => 'ids',
        ];
        if ($cat_id !== 'all') {
            $args['tax_query'] = [[
                'taxonomy' => 'product_cat',
                'terms' => [$cat_id],
            ]];
        }

        $prod_ids = get_posts($args);
        $processed = 0;
        $errors = [];

        // اگر هیچ محصولی یافت نشد، خبر بدیم (ممکن offset بیشتر از تعداد کل باشه)
        if (empty($prod_ids)) {
            // calc total products for client
            $count_args = [
                'post_type' => 'product',
                'post_status' => 'publish',
                'fields' => 'ids',
            ];
            if ($cat_id !== 'all') {
                $count_args['tax_query'] = [[
                    'taxonomy' => 'product_cat',
                    'terms' => [$cat_id],
                ]];
            }
            $total_products = count(get_posts($count_args));
            wp_send_json_success([
                'processed' => 0,
                'next_step' => $step + 1,
                'done' => true,
                'total' => $total_products,
                'message' => 'تمام شد'
            ]);
        }

        // پردازش هر محصول در این batch
        foreach ($prod_ids as $product_id) {
            $product_obj = wc_get_product($product_id);
            if (!$product_obj) continue;

            $sku = $product_obj->get_sku();
            if (empty($sku)) {
                // اگر SKU نداریم، نادیده بگیر
                $processed++;
                continue;
            }

            // گرفتن محصول از سایت فروشنده با SKU
            $vendor_product = Vendor_API_Handler::get_product_by_sku($meta, $sku);
            if (!$vendor_product) {
                $errors[] = "SKU {$sku} : محصول در سایت فروشنده یافت نشد یا خطای API";
                $processed++;
                continue;
            }

            // مرحلهٔ ذخیره قیمت همکاری در _seller_list_price (اگر قیمت انتخاب شده)
            if ($sync_type === 'price' || $sync_type === 'both') {
                // خواندن قیمت همکاری از متای فروشنده (کلید در user meta ذخیره شده)
                $cooperation_price = 0;
                if (!empty($meta['price_meta_key']) && isset($vendor_product['meta_data']) && is_array($vendor_product['meta_data'])) {
                    foreach ($vendor_product['meta_data'] as $m) {
                        if ($m['key'] === $meta['price_meta_key'] && $m['value'] !== '') {
                            $cooperation_price = floatval($m['value']);
                            break;
                        }
                    }
                }

                // اگر متای قیمت نبود، به‌عنوان fallback از price استفاده کن
                if (!$cooperation_price && isset($vendor_product['price'])) {
                    $cooperation_price = floatval($vendor_product['price']);
                }

                if ($cooperation_price) {
                    // تبدیل ریال -> تومان در صورت نیاز
                    if ($meta['currency'] === 'rial') {
                        $cooperation_price = $cooperation_price / 10;
                    }

                    // ذخیره قیمت همکاری به تومان در متای محصول ما
                    update_post_meta($product_id, '_seller_list_price', $cooperation_price);
                }
            }

            // موجودی
            if ($sync_type === 'stock' || $sync_type === 'both') {
                if ($meta['stock_type'] === 'managed') {
                    $product_obj->set_manage_stock(true);
                    if (isset($vendor_product['stock_quantity'])) {
                        $product_obj->set_stock_quantity(intval($vendor_product['stock_quantity']));
                    }
                } else {
                    $status = (isset($vendor_product['stock_status']) && $vendor_product['stock_status'] === 'instock') ? 'instock' : 'outofstock';
                    $product_obj->set_stock_status($status);
                }
                $product_obj->save();
            }

            $processed++;
        }

        // بعد از پردازش این batch: اگر نوع sync شامل 'price' باشه، در مرحلهٔ بعدی JS می‌تونه مجدداً فراخوانی کند تا درصد تبدیل و قیمت نهایی اعمال شود.
        // اما ما در این نسخه، برای سادگی پس از تمام شدن همهٔ batchها، JS یک فراخوان مرحلهٔ دوم می‌زند.
        // برگردوندن نتیجه به JS
        // محاسبهٔ تعداد کل محصولات برای درصد (فقط یک‌بار)
        $count_args = [
            'post_type' => 'product',
            'post_status' => 'publish',
            'fields' => 'ids',
        ];
        if ($cat_id !== 'all') {
            $count_args['tax_query'] = [[
                'taxonomy' => 'product_cat',
                'terms' => [$cat_id],
            ]];
        }
        $total_products = count(get_posts($count_args));

        // آیا این batch آخر بود؟
        $done = ($offset + $processed) >= $total_products;

        wp_send_json_success([
            'processed' => $processed,
            'next_step' => $step + 1,
            'done' => $done,
            'total' => $total_products,
            'message' => $done ? 'batch_done' : 'batch_continue',
            'errors' => $errors,
        ]);
    }
}

new Vendor_Product_Sync_Manager();
