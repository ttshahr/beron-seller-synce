<?php
if (!defined('ABSPATH')) exit;

class Vendor_API_Handler {

    public static function get_product_by_sku($meta, $sku) {
        $api_url = trailingslashit($meta['url']) . 'wp-json/wc/v3/products';
        $auth = base64_encode($meta['key'] . ':' . $meta['secret']);

        $response = wp_remote_get(add_query_arg('sku', $sku, $api_url), [
            'headers' => ['Authorization' => 'Basic ' . $auth],
            'timeout' => 20,
        ]);

        if (is_wp_error($response)) return null;

        $data = json_decode(wp_remote_retrieve_body($response), true);
        return (!empty($data) && isset($data[0])) ? $data[0] : null;
    }
}
