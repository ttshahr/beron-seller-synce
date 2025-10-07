<?php
if (!defined('ABSPATH')) exit;

class Vendor_Price_Processor {

    /**
     * محاسبه و ذخیره قیمت‌ها
     */
    public static function update_prices($product_obj, $vendor_product, $meta) {
        $price_meta_key = $meta['price_meta_key'];
        $conversion_percent = floatval($meta['conversion_percent']);
        $currency = $meta['currency'];

        // قیمت همکاری از متای اختصاصی فروشنده یا fallback روی regular_price
        $cooperation_price = 0;
        if (isset($vendor_product['meta_data'])) {
            foreach ($vendor_product['meta_data'] as $m) {
                if ($m['key'] === $price_meta_key && !empty($m['value'])) {
                    $cooperation_price = floatval($m['value']);
                    break;
                }
            }
        }
        if (!$cooperation_price && isset($vendor_product['price'])) {
            $cooperation_price = floatval($vendor_product['price']);
        }

        if (!$cooperation_price) return;

        // اگر ارز فروشنده ریال بود → تبدیل به تومان
        if ($currency === 'rial') {
            $cooperation_price = $cooperation_price / 10;
        }

        // ذخیره قیمت همکاری خام
        update_post_meta($product_obj->get_id(), '_seller_list_price', $cooperation_price);

        // محاسبه قیمت نهایی با درصد تبدیل
        $final_price = $cooperation_price * (1 + ($conversion_percent / 100));

        // رند کردن به بالا تا 3 رقم (مثلاً 132450 → 133000)
        $final_price = ceil($final_price / 1000) * 1000;

        // ذخیره قیمت در محصول
        $product_obj->set_regular_price($final_price);
        $product_obj->save();
    }
}
