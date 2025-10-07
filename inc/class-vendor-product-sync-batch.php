<?php
if (!defined('ABSPATH')) exit;

class Vendor_Product_Sync_Batch {

    public function __construct() {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_ajax_vendor_sync_batch', [$this, 'ajax_sync_batch']);
    }

    public function enqueue_scripts($hook) {
        if ($hook !== 'toplevel_page_vendor-sync') return;
        wp_enqueue_script(
            'vendor-sync-progress',
            plugin_dir_url(__DIR__) . '../assets/js/vendor-sync-progress.js',
            ['jquery'],
            '1.0',
            true
        );
        wp_localize_script('vendor-sync-progress', 'vendorSync', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vendor_sync_nonce')
        ]);
    }

    public function ajax_sync_batch() {
        check_ajax_referer('vendor_sync_nonce', 'nonce');

        $vendor_id = intval($_POST['vendor_id']);
        $cat_id = sanitize_text_field($_POST['product_cat']);
        $sync_type = sanitize_text_field($_POST['sync_type']);
        $offset = intval($_POST['offset']);
        $batch_size = 20; // تعداد محصول در هر درخواست Ajax

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
            'post_type' => 'product',
            'posts_per_page' => $batch_size,
            'offset' => $offset,
            'post_status' => 'publish',
        ];

        if ($cat_id !== 'all') {
            $args['tax_query'] = [[
                'taxonomy' => 'product_cat',
                'terms' => [$cat_id],
            ]];
        }

        $products = get_posts($args);
        $total_processed = 0;

        foreach ($products as $product) {
            $sku = get_post_meta($product->ID, '_sku', true);
            if (!$sku) continue;

            $vendor_product = Vendor_API_Handler::get_product_by_sku($meta, $sku);
            if (!$vendor_product) continue;

            $product_obj = wc_get_product($product->ID);

            // مرحله 1: ذخیره قیمت همکاری در متای _seller_list_price
            if ($sync_type === 'price' || $sync_type === 'both') {
                Vendor_Price_Processor::update_prices($product_obj, $vendor_product, $meta);
            }

            // موجودی
            if ($sync_type === 'stock' || $sync_type === 'both') {
                if ($meta['stock_type'] === 'managed') {
                    $product_obj->set_manage_stock(true);
                    $product_obj->set_stock_quantity(intval($vendor_product['stock_quantity']));
                } else {
                    $status = $vendor_product['stock_status'] === 'instock' ? 'instock' : 'outofstock';
                    $product_obj->set_stock_status($status);
                }
                $product_obj->save();
            }

            $total_processed++;
        }

        $total_products = wp_count_posts('product')->publish;
        $percent = min(100, round(($offset + $total_processed) / $total_products * 100));

        wp_send_json_success([
            'processed' => $total_processed,
            'percent' => $percent,
            'next_offset' => $offset + $batch_size,
            'total' => $total_products,
            'done' => ($offset + $total_processed) >= $total_products
        ]);
    }
}

new Vendor_Product_Sync_Batch();
