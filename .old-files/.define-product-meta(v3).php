<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // جلوگیری از دسترسی مستقیم
}

class Define_Product_Meta {

    public function __construct() {
        // افزودن فیلدها به صفحه ویرایش محصول
        add_action( 'woocommerce_product_options_general_product_data', [ $this, 'add_custom_product_fields' ] );

        // ذخیره اطلاعات هنگام ذخیره محصول
        add_action( 'woocommerce_admin_process_product_object', [ $this, 'save_custom_product_fields' ] );
    }

    /**
     * افزودن فیلدهای متای سفارشی در صفحه محصول
     */
    public function add_custom_product_fields() {
        echo '<div class="options_group">';

        // فیلد قیمت لیست فروشنده
        woocommerce_wp_text_input( [
            'id'                => '_seller_list_price',
            'label'             => 'قیمت لیست فروشنده (تومان)',
            'desc_tip'          => true,
            'description'       => 'قیمتی که فروشنده برای این محصول تعیین کرده است.',
            'type'              => 'number',
            'custom_attributes' => [ 'step' => 'any', 'min' => '0' ],
        ] );

        // فیلد سود فروش
        woocommerce_wp_text_input( [
            'id'                => '_sale_profit',
            'label'             => 'سود فروش (تومان)',
            'desc_tip'          => true,
            'description'       => 'میزان سود شما از فروش این محصول.',
            'type'              => 'number',
            'custom_attributes' => [ 'step' => 'any', 'min' => '0' ],
        ] );

        // زمان بروزرسانی قیمت همکار
        woocommerce_wp_text_input( [
            'id'          => '_colleague_price_update_time',
            'label'       => 'زمان بروزرسانی قیمت همکار',
            'desc_tip'    => true,
            'description' => 'آخرین زمانی که قیمت همکار بروزرسانی شده است.',
            'type'        => 'text',
        ] );

        // زمان خوانش قیمت همکار
        woocommerce_wp_text_input( [
            'id'          => '_colleague_price_read_time',
            'label'       => 'زمان خوانش قیمت همکار',
            'desc_tip'    => true,
            'description' => 'زمانی که آخرین بار قیمت همکار خوانده شده است.',
            'type'        => 'text',
        ] );

        // زمان خوانش موجودی همکار
        woocommerce_wp_text_input( [
            'id'          => '_colleague_stock_read_time',
            'label'       => 'زمان خوانش موجودی همکار',
            'desc_tip'    => true,
            'description' => 'زمانی که آخرین بار موجودی همکار خوانده شده است.',
            'type'        => 'text',
        ] );

        echo '</div>';
    }

    /**
     * ذخیره اطلاعات متا هنگام ذخیره محصول
     */
    public function save_custom_product_fields( $product ) {
        $meta_fields = [
            '_seller_list_price',
            '_sale_profit',
            '_colleague_price_update_time',
            '_colleague_price_read_time',
            '_colleague_stock_read_time',
        ];

        foreach ( $meta_fields as $field ) {
            if ( isset( $_POST[ $field ] ) ) {
                // چون این متاها مهم نیستند، فقط مقدار خام ذخیره می‌شود
                $product->update_meta_data( $field, $_POST[ $field ] );
            }
        }
    }
}

// راه‌اندازی کلاس
new Define_Product_Meta();
