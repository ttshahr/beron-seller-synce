<?php
if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/class-vendor-api-handler.php';
require_once __DIR__ . '/class-vendor-price-processor.php';

class Vendor_Product_Sync_Manager {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_sync_menu']);
        add_action('admin_post_sync_vendor_products', [$this, 'handle_sync_request']);
        add_action('wp_ajax_vendor_sync_batch', 'vendor_sync_batch_callback');
        
        function vendor_sync_batch_callback() {
        // دریافت step و سایر داده‌ها
            $step = intval($_POST['step']);
            echo 'Step: ' + $step;
            wp_die();
        };
        
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
        add_action('admin_enqueue_scripts', function($hook) {
        if ($hook === 'toplevel_page_vendor-sync') {
            wp_enqueue_script(
                'vendor-sync-progress',
                plugin_dir_url(__FILE__) . 'assets/js/vendor-sync-progress.js',
                ['jquery'],
                '1.0',
                true
            );
        }
        });
        // تعریف فایل جاوااسکریپت
        add_action('admin_enqueue_scripts', function() {
        wp_enqueue_script(
        'vendor-sync-progress',
        plugin_dir_url(__FILE__) . '../assets/js/vendor-sync-progress.js',
        ['jquery'],
        '1.0',
        true
        );
        });

    }

    public function render_sync_page() {
        $vendors = get_users(['role__in' => ['hamkar', 'seller']]);
        $categories = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
        ?>
        <div class="wrap">
            <h1>مدیریت همگام‌سازی محصولات فروشندگان</h1>
            <form method="post" id="vendor-sync-form" action="#">
                <input type="hidden" name="action" value="sync_vendor_products">

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
                    <tr>
                        <th><label for="sync_type">نوع بروزرسانی</label></th>
                        <td>
                            <select name="sync_type" id="sync_type" required>
                                <option value="both">قیمت و موجودی</option>
                                <option value="price">فقط قیمت</option>
                                <option value="stock">فقط موجودی</option>
                            </select>
                        </td>
                    </tr>
                </table>
                <?php submit_button('شروع بروزرسانی'); ?>
            </form>
        </div>
        <?php
    }

    public function handle_sync_request() {
        if (!current_user_can('manage_woocommerce')) wp_die('دسترسی غیرمجاز');

        $vendor_id = intval($_POST['vendor_id']);
        $cat_id = sanitize_text_field($_POST['product_cat']);
        $sync_type = sanitize_text_field($_POST['sync_type']);

        $meta = [
            'url' => get_user_meta($vendor_id, 'vendor_website_url', true),
            'key' => get_user_meta($vendor_id, 'vendor_consumer_key', true),
            'secret' => get_user_meta($vendor_id, 'vendor_consumer_secret', true),
            'currency' => get_user_meta($vendor_id, 'vendor_currency', true),
            'stock_type' => get_user_meta($vendor_id, 'vendor_stock_type', true),
            'price_meta_key' => get_user_meta($vendor_id, 'vendor_cooperation_price_meta_key', true),
            'conversion_percent' => get_user_meta($vendor_id, 'vendor_price_conversion_percent', true),
        ];

        if (empty($meta['url']) || empty($meta['key']) || empty($meta['secret'])) {
            wp_die('اطلاعات API فروشنده ناقص است.');
        }

        $args = [
            'post_type' => 'product',
            'posts_per_page' => -1,
            'post_status' => 'publish',
        ];

        if ($cat_id !== 'all') {
            $args['tax_query'] = [[
                'taxonomy' => 'product_cat',
                'terms' => [$cat_id],
            ]];
        }

        $products = get_posts($args);

        // مرحله ۱: ذخیره قیمت همکاری خام
        // foreach ($products as $product) {
        //     $sku = get_post_meta($product->ID, '_sku', true);
        //     if (!$sku) continue;

        //     $vendor_product = Vendor_API_Handler::get_product_by_sku($meta, $sku);
        //     if (!$vendor_product) continue;

        //     $product_obj = wc_get_product($product->ID);

        //     if ($sync_type === 'price' || $sync_type === 'both') {
        //         Vendor_Price_Processor::store_cooperation_price($product_obj, $vendor_product, $meta);
        //     }

        //     // موجودی
        //     if ($sync_type === 'stock' || $sync_type === 'both') {
        //         if ($meta['stock_type'] === 'managed') {
        //             $product_obj->set_manage_stock(true);
        //             $product_obj->set_stock_quantity(intval($vendor_product['stock_quantity']));
        //         } else {
        //             $status = $vendor_product['stock_status'] === 'instock' ? 'instock' : 'outofstock';
        //             $product_obj->set_stock_status($status);
        //         }
        //         $product_obj->save();
        //     }
        // }

        // مرحله ۲: اعمال درصد تبدیل و ذخیره در قیمت نهایی
        if ($sync_type === 'price' || $sync_type === 'both') {
            foreach ($products as $product) {
                $product_obj = wc_get_product($product->ID);
                Vendor_Price_Processor::apply_conversion_and_update($product_obj, $meta);
            }
        }

        wp_redirect(admin_url('admin.php?page=vendor-sync&updated=1'));
        exit;
    }
}

new Vendor_Product_Sync_Manager();
