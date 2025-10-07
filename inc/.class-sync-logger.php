<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * کلاس ثبت لاگ برای افزونه Beron Seller Sync
 */
class Beron_Seller_Sync_Logger {

    private static $log_file;

    public static function init() {
        self::$log_file = plugin_dir_path( __FILE__ ) . '../sync-log.txt';

        // اگر فایل وجود ندارد بساز
        if ( ! file_exists( self::$log_file ) ) {
            file_put_contents( self::$log_file, "==== Beron Seller Sync Log ====\n" );
        }
    }

    /**
     * ثبت لاگ ساده
     */
    public static function log( $message ) {
        $date = date('Y-m-d H:i:s');
        $entry = "[$date] $message" . PHP_EOL;
        file_put_contents( self::$log_file, $entry, FILE_APPEND );
    }

    /**
     * ثبت خلاصه نهایی
     */
    public static function log_summary( $success, $errors, $duration ) {
        $summary = sprintf(
            "Sync completed - Success: %d | Errors: %d | Duration: %.2f sec",
            $success,
            count( $errors ),
            $duration
        );
        self::log( $summary );

        if ( ! empty( $errors ) ) {
            foreach ( $errors as $e ) {
                self::log( "  ERROR: " . $e );
            }
        }
        self::log( "---------------------------------------" );
    }
}

Beron_Seller_Sync_Logger::init();
