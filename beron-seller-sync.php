<?php
/**
 * Plugin Name: همگام سازی برون
 * Description: همگام سازی محصولات، فروشندگان، و سفارشات برون
 * Version: 1.0.0
 * Author: ویرانت
 * Text Domain: beron-seller-sync
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // امنیت: جلوگیری از دسترسی مستقیم
}

// تعریف ثابت‌ها برای مسیرها
define( 'Beron_Seller_Sync_PATH', plugin_dir_path( __FILE__ ) );
define( 'Beron_Seller_Sync_URL', plugin_dir_url( __FILE__ ) );

// اتولودر ساده یا اینکلود همه فایل‌های inc
foreach ( glob( Beron_Seller_Sync_PATH . 'inc/*.php' ) as $file ) {
    include_once $file;
}
