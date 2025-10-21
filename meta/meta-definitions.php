<?php
if (!defined('ABSPATH')) exit;

class Meta_Definitions {
    
    // متاهای محصول
    const PRODUCT_META = [
        '_seller_list_price' => [
            'label' => 'قیمت لیست فروشنده (تومان)',
            'type' => 'number',
            'description' => 'قیمتی که فروشنده برای این محصول تعیین کرده است.'
        ],
        '_sale_profit' => [
            'label' => 'سود فروش (تومان)', 
            'type' => 'number',
            'description' => 'میزان سود شما از فروش این محصول.'
        ],
        '_colleague_price_update_time' => [
            'label' => 'زمان بروزرسانی قیمت همکار',
            'type' => 'text',
            'description' => 'آخرین زمانی که قیمت همکار بروزرسانی شده است.'
        ]
    ];
    
    // متاهای کاربر (فروشنده)
    const USER_META = [
        'vendor_website_url' => [
            'label' => 'آدرس سایت فروشنده',
            'type' => 'url'
        ],
        'vendor_consumer_key' => [
            'label' => 'کلید مصرف‌کننده (Consumer Key)',
            'type' => 'text'
        ],
        'vendor_consumer_secret' => [
            'label' => 'رمز مصرف‌کننده (Consumer Secret)', 
            'type' => 'text'
        ],
        'vendor_currency' => [
            'label' => 'واحد پول فروشنده',
            'type' => 'select',
            'options' => ['toman' => 'تومان', 'rial' => 'ریال']
        ],
        'vendor_price_conversion_percent' => [
            'label' => 'درصد تبدیل قیمت همکاری',
            'type' => 'number',
            'step' => '0.01'
        ],
        // 🔥 اضافه کردن متای نوع موجودی
        'vendor_stock_type' => [
            'label' => 'نوع مدیریت موجودی',
            'type' => 'select',
            'options' => [
                'managed' => 'مدیریت عددی موجودی',
                'status' => 'مدیریت وضعیتی موجودی'
            ],
            'description' => 'نحوه مدیریت موجودی محصولات توسط فروشنده'
        ],
        // 🔥 اضافه کردن متای کلید قیمت همکاری
        'vendor_cooperation_price_meta_key' => [
            'label' => 'کلید متای قیمت همکاری',
            'type' => 'text',
            'description' => 'نام متا فیلدی که قیمت همکاری در آن ذخیره می‌شود'
        ]
    ];
}