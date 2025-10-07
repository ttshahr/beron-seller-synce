<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // جلوگیری از دسترسی مستقیم
}

/**
 * کلاس مدیریت ستون‌های سفارشی در لیست محصولات ووکامرس
 */
class Define_Product_Admin_Columns {

    public function __construct() {
        // افزودن ستون‌ها به جدول محصولات
        add_filter( 'manage_edit-product_columns', [ $this, 'add_custom_columns' ] );

        // پر کردن مقدار ستون‌ها
        add_action( 'manage_product_posts_custom_column', [ $this, 'render_custom_columns' ], 10, 2 );

        // قابل مرتب‌سازی کردن ستون‌ها (اختیاری)
        add_filter( 'manage_edit-product_sortable_columns', [ $this, 'make_columns_sortable' ] );
    }

    /**
     * افزودن ستون‌های جدید
     */
    public function add_custom_columns( $columns ) {
        // محل درج ستون‌ها (بعد از قیمت)
        $new_columns = [];

        foreach ( $columns as $key => $label ) {
            $new_columns[ $key ] = $label;

            // بعد از ستون قیمت، ستون‌های جدید را اضافه کن
            if ( $key === 'price' ) {
                $new_columns['seller_list_price'] = 'قیمت لیست فروشنده';
                $new_columns['sale_profit'] = 'سود فروش';
            }
        }

        return $new_columns;
    }

    /**
     * پر کردن مقادیر ستون‌ها
     */
    public function render_custom_columns( $column, $post_id ) {
        switch ( $column ) {
            case 'seller_list_price':
                $value = get_post_meta( $post_id, '_seller_list_price', true );
                echo $value ? wc_price( $value ) : '—';
                break;

            case 'sale_profit':
                $value = get_post_meta( $post_id, '_sale_profit', true );
                echo $value ? wc_price( $value ) : '—';
                break;
        }
    }

    /**
     * قابل مرتب‌سازی کردن ستون‌ها (اختیاری)
     */
    public function make_columns_sortable( $columns ) {
        $columns['seller_list_price'] = 'seller_list_price';
        $columns['sale_profit'] = 'sale_profit';
        return $columns;
    }
}

// راه‌اندازی کلاس
new Define_Product_Admin_Columns();
