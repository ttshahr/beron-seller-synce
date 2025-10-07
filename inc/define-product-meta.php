<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // جلوگیری از دسترسی مستقیم
}

class Define_Product_Meta {

    public function __construct() {
        // اضافه کردن فیلدها به صفحه ویرایش محصول در ادمین
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

        echo '</div>';
    }

    /**
     * ذخیره اطلاعات متا هنگام ذخیره محصول
     */
    public function save_custom_product_fields( $product ) {
        if ( isset( $_POST['_seller_list_price'] ) ) {
            $product->update_meta_data( '_seller_list_price', sanitize_text_field( $_POST['_seller_list_price'] ) );
        }

        if ( isset( $_POST['_sale_profit'] ) ) {
            $product->update_meta_data( '_sale_profit', sanitize_text_field( $_POST['_sale_profit'] ) );
        }
    }
}

// راه‌اندازی کلاس
new Define_Product_Meta();
