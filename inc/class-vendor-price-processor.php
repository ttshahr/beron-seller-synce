<?php
if (!defined('ABSPATH')) exit;

class Vendor_Price_Processor {

    /**
     * مرحله ۱: ذخیره قیمت همکاری خام در متای محصول
     */
    public static function store_cooperation_price($product_obj, $vendor_product, $meta) {
        $price_meta_key = $meta['price_meta_key'];
        $currency = $meta['currency'];

        // گرفتن قیمت همکاری از متای فروشنده
        $cooperation_price = 0;
        if (isset($vendor_product['meta_data'])) {
            foreach ($vendor_product['meta_data'] as $m) {
                if ($m['key'] === $price_meta_key && !empty($m['value'])) {
                    $cooperation_price = floatval($m['value']);
                    break;
                }
            }
        }

        // fallback به قیمت عادی فروشنده
        if (!$cooperation_price && isset($vendor_product['price'])) {
            $cooperation_price = floatval($vendor_product['price']);
        }

        if (!$cooperation_price) return;

        // تبدیل ریال به تومان
        if ($currency === 'rial') {
            $cooperation_price = $cooperation_price / 10;
        }

        // ذخیره در متای محصول
        update_post_meta($product_obj->get_id(), '_seller_list_price', $cooperation_price);
    }

    /**
     * مرحله ۲: محاسبه قیمت نهایی و ذخیره در regular_price
     */
    public static function apply_conversion_and_update($product_obj, $meta) {
        $conversion_percent = floatval($meta['conversion_percent']);

        $cooperation_price = get_post_meta($product_obj->get_id(), '_seller_list_price', true);
        if (!$cooperation_price) return;

        // محاسبه قیمت نهایی با درصد تبدیل
        $final_price = $cooperation_price * (1 + ($conversion_percent / 100));

        // رند کردن به بالا تا ۳ رقم
        $final_price = ceil($final_price / 1000) * 1000;

        // ذخیره قیمت در محصول
        $product_obj->set_regular_price($final_price);
        $product_obj->save();
    }
}
