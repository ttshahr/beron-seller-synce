<?php
if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/class-vendor-api-handler.php';
require_once __DIR__ . '/class-vendor-price-processor.php';

class Vendor_Product_Sync_Manager {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_sync_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
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

    public function enqueue_scripts($hook) {
        if ($hook !== 'toplevel_page_vendor-sync') return;
        wp_enqueue_script(
            'vendor-sync-progress',
            plugin_dir_url(__FILE__) . '../assets/js/vendor-sync-progress.js',
            ['jquery'],
            null,
            true
        );
    }

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
                                <option value="<?php echo $vendor->ID; ?>">
                                    <?php echo esc_html($vendor->display_name); ?> (<?php echo $vendor->user_login; ?>)
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
                                <option value="<?php echo $cat->term_id; ?>"><?php echo esc_html($cat->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </table>

            <div id="progress-container" style="display: none; margin-top: 20px;">
                <div id="progress-bar" style="width: 0%; height: 20px; background-color: green;"></div>
                <p id="progress-text">در حال پردازش: 0%</p>
            </div>
            <button id="sync-button" class="button button-primary">شروع بروزرسانی</button>
        </div>
        <?php
    }

    /**
     * متد AJAX برای پردازش دسته‌ای محصولات
     */
    public function handle_sync_request_batch() {
        if (!current_user_can('manage_woocommerce')) wp_send_json_error('دسترسی غیرمجاز');

        $step = isset($_POST['step']) ? intval($_POST['step']) : 1;
        $batch_size = 50;
        $offset = ($step - 1) * $batch_size;

        $vendor_id = isset($_POST['vendor_id']) ? intval($_POST['vendor_id']) : 0;
        $cat_id = isset($_POST['product_cat']) ? sanitize_text_field($_POST['product_cat']) : 'all';

        if (!$vendor_id) wp_send_json_error('فروشنده انتخاب نشده است.');

        $meta = [
            'url' => get_user_meta($vendor_id, 'vendor_website_url', true),
            'key' => get_user_meta($vendor_id, 'vendor_consumer_key', true),
            'secret' => get_user_meta($vendor_id, 'vendor_consumer_secret', true),
            'currency' => get_user_meta($vendor_id, 'vendor_currency', true),
            'stock_type' => get_user_meta($vendor_id, 'vendor_stock_type', true),
            'price_meta_key' => get_user_meta($vendor_id, 'vendor_cooperation_price_meta_key', true),
            'conversion_percent' => get_user_meta($vendor_id, 'vendor_price_conversion_percent', true),
        ];

        $args = [
            'post_type'      => 'product',
            'posts_per_page' => $batch_size,
            'offset'         => $offset,
            'post_status'    => 'publish',
        ];

        if ($cat_id !== 'all') {
            $args['tax_query'] = [[
                'taxonomy' => 'product_cat',
                'terms' => [$cat_id],
            ]];
        }

        $products = get_posts($args);

        foreach ($products as $product_post) {
            $product_obj = wc_get_product($product_post->ID);

            // قیمت همکاری از متای سایت فروشنده
            $vendor_product = Vendor_API_Handler::get_product_by_sku($meta, $product_obj->get_sku());
            if (!$vendor_product) continue;

            // مرحله اول: ذخیره قیمت همکاری در _seller_list_price
            $cooperation_price = 0;
            if (isset($vendor_product['meta_data'])) {
                foreach ($vendor_product['meta_data'] as $m) {
                    if ($m['key'] === $meta['price_meta_key'] && !empty($m['value'])) {
                        $cooperation_price = floatval($m['value']);
                        break;
                    }
                }
            }
            if (!$cooperation_price && isset($vendor_product['price'])) {
                $cooperation_price = floatval($vendor_product['price']);
            }

            if (!$cooperation_price) continue;

            // تبدیل ریال به تومان
            if ($meta['currency'] === 'rial') $cooperation_price /= 10;

            update_post_meta($product_obj->get_id(), '_seller_list_price', $cooperation_price);

            // مرحله دوم: محاسبه قیمت نهایی و ذخیره در regular_price
            $final_price = $cooperation_price * (1 + floatval($meta['conversion_percent']) / 100);
            $final_price = ceil($final_price / 1000) * 1000; // رند به بالا سه رقم
            $product_obj->set_regular_price($final_price);
            $product_obj->save();
        }

        if (count($products) < $batch_size) {
            wp_send_json_success(['message' => 'تمام شد']);
        } else {
            wp_send_json_success(['message' => 'در حال پردازش...', 'next_step' => $step + 1]);
        }
    }
}

new Vendor_Product_Sync_Manager();
