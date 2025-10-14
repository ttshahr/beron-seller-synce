<?php
/**
 * Plugin Name: همگام سازی برون
 * Description: همگام سازی محصولات، فروشندگان، و سفارشات برون
 * Version: 3.0.1
 * Author: ویرانت
 * Text Domain: beron-seller-sync
 * Update URI: https://github.com/ttshahr/beron-seller-synce/
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // امنیت: جلوگیری از دسترسی مستقیم
}

// تعریف ثابت‌ها برای مسیرها
define( 'Beron_Seller_Sync_PATH', plugin_dir_path( __FILE__ ) );
define( 'Beron_Seller_Sync_URL', plugin_dir_url( __FILE__ ) );

// اضافه کردن سیستم آپدیت از گیت‌هاب
require_once Beron_Seller_Sync_PATH . 'inc/class-plugin-update-checker.php';
if (class_exists('Beron_Plugin_Update_Checker')) {
    new Beron_Plugin_Update_Checker(
        'https://github.com/ttshahr/beron-seller-synce/',
        __FILE__,
        'beron-seller-sync'
    );
}

// اتولودر ساده یا اینکلود همه فایل‌های inc
foreach ( glob( Beron_Seller_Sync_PATH . 'inc/*.php' ) as $file ) {
    include_once $file;
}