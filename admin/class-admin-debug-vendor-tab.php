<?php
if (!defined('ABSPATH')) exit;

class Admin_Debug_Vendor_Tab {
    
    public static function render() {
        $vendors = get_users(['role__in' => ['hamkar', 'seller']]);
        $selected_vendor = isset($_GET['vendor_id']) ? intval($_GET['vendor_id']) : 0;
        ?>
        
        <!-- تب دیباگ فروشندگان -->
        <div class="card full-width-card">
            <h2>انتخاب فروشنده برای بررسی</h2>
            <form method="get">
                <input type="hidden" name="page" value="vendor-sync-debug">
                <input type="hidden" name="tab" value="vendor_debug">
                <select name="vendor_id" required style="min-width: 300px;">
                    <option value="">-- انتخاب فروشنده --</option>
                    <?php foreach ($vendors as $vendor): ?>
                        <option value="<?php echo $vendor->ID; ?>" <?php selected($selected_vendor, $vendor->ID); ?>>
                            <?php echo esc_html($vendor->display_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php submit_button('بررسی', 'primary'); ?>
            </form>
        </div>
        
        <?php if ($selected_vendor): ?>
            <?php self::render_vendor_report($selected_vendor); ?>
        <?php endif; ?>
        
        <?php
    }
    
    /**
     * رندر گزارش کامل فروشنده
     */
    private static function render_vendor_report($vendor_id) {
        $vendor_meta = Vendor_Meta_Handler::get_vendor_meta($vendor_id);
        $vendor_name = Vendor_Meta_Handler::get_vendor_display_name($vendor_id);
        
        // تست اتصال اولیه
        $connection_test = Vendor_API_Optimizer::test_connection($vendor_meta, $vendor_id);
        
        if (!$connection_test['success']) {
            echo '<div class="notice notice-error"><p>❌ خطا در اتصال به فروشنده: ' . esc_html($connection_test['error']) . '</p></div>';
            return;
        }
        
        echo '<div class="vendor-report-container">';
        
        // هدر گزارش
        echo '<div class="card full-width-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">';
        echo '<h2 style="color: white;">📊 گزارش کامل فروشنده: ' . esc_html($vendor_name) . '</h2>';
        echo '<p style="color: #e3f2fd;">در حال دریافت اطلاعات... لطفاً منتظر بمانید</p>';
        echo '</div>';
        
        // Flush output buffer to show loading message
        echo str_pad('', 1024); // Add padding to force buffer flush
        if (function_exists('ob_flush')) {
            ob_flush();
        }
        flush();
        
        // دریافت داده‌ها
        $report_data = self::generate_vendor_report($vendor_id, $vendor_meta);
        
        // استفاده از JavaScript برای به‌روزرسانی محتوا
        ?>
        <script>
        jQuery(document).ready(function($) {
            // حذف پیام در حال بارگذاری و نمایش نتایج
            $('.vendor-report-container').html(`
                <div class="card full-width-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                    <h2 style="color: white;">📊 گزارش کامل فروشنده: <?php echo esc_js($vendor_name); ?></h2>
                    <p style="color: #e3f2fd;">آخرین بروزرسانی: <?php echo current_time('Y-m-d H:i:s'); ?></p>
                </div>
                <?php echo self::render_basic_stats_html($report_data['basic_stats']); ?>
                <?php echo self::render_missing_products_html($report_data['missing_in_local'], 'missing_in_local'); ?>
                <?php echo self::render_missing_products_html($report_data['missing_in_vendor'], 'missing_in_vendor'); ?>
                <?php echo self::render_mismatch_products_html($report_data['price_mismatch'], 'price'); ?>
                <?php echo self::render_mismatch_products_html($report_data['stock_mismatch'], 'stock'); ?>
                <?php echo self::render_sync_info_html($report_data['sync_info']); ?>
            `);
            
            // فعال کردن اسکریپت‌های کپی بعد از لود محتوا
            setTimeout(function() {
                $('.copy-all-btn').on('click', function() {
                    const content = $(this).data('content');
                    const $status = $(this).siblings('.copy-status');
                    
                    navigator.clipboard.writeText(content).then(function() {
                        $status.text('✅ کپی شد').fadeIn().delay(2000).fadeOut();
                    }).catch(function(err) {
                        $status.text('❌ خطا در کپی').fadeIn().delay(2000).fadeOut();
                    });
                });
                
                $('.copy-single-btn').on('click', function() {
                    const content = $(this).data('content');
                    const $status = $(this).siblings('.copy-status') || $(this).parent().find('.copy-status');
                    
                    navigator.clipboard.writeText(content).then(function() {
                        if ($status.length) {
                            $status.text('✅').fadeIn().delay(1000).fadeOut();
                        } else {
                            alert('✅ شناسه کپی شد: ' + content);
                        }
                    }).catch(function(err) {
                        if ($status.length) {
                            $status.text('❌').fadeIn().delay(1000).fadeOut();
                        } else {
                            alert('❌ خطا در کپی');
                        }
                    });
                });
            }, 100);
        });
        </script>
        <?php
        
        echo '</div>';
    }
    
    /**
     * تولید گزارش کامل
     */
    private static function generate_vendor_report($vendor_id, $vendor_meta) {
        // 1. دریافت محصولات محلی
        $local_products = self::get_local_products($vendor_id);
        
        // 2. دریافت محصولات فروشنده با Bulk API
        $vendor_products = self::get_vendor_products_bulk($vendor_meta, $vendor_id);
        
        // 3. تولید گزارش
        $report = [
            'basic_stats' => self::get_basic_stats($local_products, $vendor_products, $vendor_id, $vendor_meta),
            'missing_in_local' => self::get_missing_products($vendor_products, $local_products, 'vendor_to_local'),
            'missing_in_vendor' => self::get_missing_products($local_products, $vendor_products, 'local_to_vendor'),
            'price_mismatch' => self::get_price_mismatch_products($local_products, $vendor_products, $vendor_meta),
            'stock_mismatch' => self::get_stock_mismatch_products($local_products, $vendor_products, $vendor_meta),
            'sync_info' => self::get_sync_info($vendor_id)
        ];
        
        return $report;
    }
    
    /**
     * دریافت محصولات محلی
     */
    /**
     * دریافت محصولات محلی - نسخه اصلاح شده برای دریافت قیمت فروشنده
     */
    private static function get_local_products($vendor_id) {
    global $wpdb;
    
    $products = $wpdb->get_results($wpdb->prepare("
        SELECT 
            p.ID,
            p.post_title,
            pm_sku.meta_value as sku,
            pm_price.meta_value as price,
            pm_regular_price.meta_value as regular_price,
            pm_stock.meta_value as stock,
            pm_stock_status.meta_value as stock_status,
            pm_vendor_price.meta_value as vendor_price, -- این همان _seller_list_price است
            pm_last_sync.meta_value as last_sync
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} pm_sku ON p.ID = pm_sku.post_id AND pm_sku.meta_key = '_sku'
        LEFT JOIN {$wpdb->postmeta} pm_price ON p.ID = pm_price.post_id AND pm_price.meta_key = '_price'
        LEFT JOIN {$wpdb->postmeta} pm_regular_price ON p.ID = pm_regular_price.post_id AND pm_regular_price.meta_key = '_regular_price'
        LEFT JOIN {$wpdb->postmeta} pm_stock ON p.ID = pm_stock.post_id AND pm_stock.meta_key = '_stock'
        LEFT JOIN {$wpdb->postmeta} pm_stock_status ON p.ID = pm_stock_status.post_id AND pm_stock_status.meta_key = '_stock_status'
        LEFT JOIN {$wpdb->postmeta} pm_vendor_price ON p.ID = pm_vendor_price.post_id AND pm_vendor_price.meta_key = '_seller_list_price' -- کلید صحیح
        LEFT JOIN {$wpdb->postmeta} pm_last_sync ON p.ID = pm_last_sync.post_id AND pm_last_sync.meta_key = '_vendor_stock_last_sync'
        WHERE p.post_type = 'product'
        AND p.post_status = 'publish'
        AND p.post_author = %d
        AND pm_sku.meta_value IS NOT NULL
        AND pm_sku.meta_value != ''
    ", $vendor_id), ARRAY_A);
    
    $formatted_products = [];
    foreach ($products as $product) {
        if (!empty($product['sku'])) {
            $clean_sku = self::normalize_sku(trim($product['sku']));
            $formatted_products[$clean_sku] = $product;
        }
    }
    
    Vendor_Logger::log_info("Found " . count($formatted_products) . " local products for vendor {$vendor_id}", $vendor_id);
    
    return $formatted_products;
}
    
    /**
     * دریافت محصولات فروشنده با Bulk API - نسخه بهبود یافته
     */
    private static function get_vendor_products_bulk($meta, $vendor_id) {
        Vendor_Logger::log_info("Starting bulk product fetch from vendor", $vendor_id);
        
        $all_products = [];
        $page = 1;
        $max_pages = 50; // افزایش محدودیت برای دریافت محصولات بیشتر
        $total_products = 0;
        $total_pages = 0;
        
        // ابتدا اطلاعات اولیه را دریافت کن
        $connection_info = Vendor_API_Optimizer::test_connection($meta, $vendor_id);
        if ($connection_info['success'] && isset($connection_info['total_products'])) {
            $total_products = $connection_info['total_products'];
            $total_pages = ceil($total_products / 100);
            Vendor_Logger::log_info("Vendor has {$total_products} products ({$total_pages} pages)", $vendor_id);
        }
        
        do {
            $products = Vendor_API_Optimizer::get_products_batch($meta, $page, 100, $vendor_id);
            
            if (is_array($products) && !empty($products)) {
                foreach ($products as $product) {
                    if (!empty($product['sku'])) {
                        $clean_sku = self::normalize_sku(trim($product['sku']));
                        
                        // اگر محصول تکراری است، از جدیدترین استفاده کن
                        if (!isset($all_products[$clean_sku])) {
                            $all_products[$clean_sku] = $product;
                        }
                    }
                }
                
                $current_total = count($all_products);
                Vendor_Logger::log_info("Page {$page} - " . count($products) . " products (Total unique: {$current_total})", $vendor_id);
                $page++;
                
                // اگر تعداد محصولات کمتر از 100 بود، یعنی به آخر رسیده‌ایم
                if (count($products) < 100) {
                    Vendor_Logger::log_info("Reached last page (less than 100 products)", $vendor_id);
                    break;
                }
                
                // تاخیر کوتاه برای جلوگیری از Rate Limit
                usleep(300000); // 0.3 ثانیه
                
            } else {
                Vendor_Logger::log_warning("No products returned from page {$page}", $vendor_id);
                break;
            }
            
        } while ($page <= $max_pages);
        
        $final_count = count($all_products);
        Vendor_Logger::log_info("Bulk fetch completed. Total unique products: {$final_count}", $vendor_id);
        
        return $all_products;
    }
    
    /**
     * نرمال سازی SKU - حذف کاراکترهای اضافی
     */
    private static function normalize_sku($sku) {
        // حذف فاصله‌ها و کاراکترهای خاص از ابتدا و انتها
        $sku = trim($sku);
        // حذف کاراکترهای غیر استاندارد (به جز حروف، اعداد، خط تیره و زیرخط)
        $sku = preg_replace('/[^\w\-]/', '', $sku);
        return strtolower($sku);
    }
    
    /**
     * آمار پایه - نسخه بهبود یافته
     */
    private static function get_basic_stats($local_products, $vendor_products, $vendor_id, $vendor_meta) {
        $local_count = count($local_products);
        $vendor_count = is_array($vendor_products) ? count($vendor_products) : 0;
        
        // محصولات منطبق (هم SKU در هر دو)
        $matched_count = 0;
        $matched_skus = [];
        
        if (is_array($vendor_products)) {
            foreach ($vendor_products as $vendor_sku => $vendor_product) {
                $normalized_vendor_sku = self::normalize_sku($vendor_sku);
                foreach ($local_products as $local_sku => $local_product) {
                    if (self::normalize_sku($local_sku) === $normalized_vendor_sku) {
                        $matched_count++;
                        $matched_skus[] = $vendor_sku;
                        break;
                    }
                }
            }
        }
        
        // اطلاعات ارز و تنظیمات فروشنده
        $vendor_currency = $vendor_meta['vendor_currency'] ?? 'toman';
        $cooperation_price_key = $vendor_meta['vendor_cooperation_price_meta_key'] ?? '';
        
        // لاگ SKUهای منطبق برای دیباگ
        if (!empty($matched_skus)) {
            Vendor_Logger::log_info("Matched SKUs sample: " . implode(', ', array_slice($matched_skus, 0, 5)), $vendor_id);
        }
        
        return [
            'local_products_count' => $local_count,
            'vendor_products_count' => $vendor_count,
            'matched_products' => $matched_count,
            'last_sync_time' => self::get_last_sync_time($vendor_id),
            'vendor_currency' => $vendor_currency,
            'cooperation_price_key' => $cooperation_price_key
        ];
    }
    
    /**
     * محصولات گمشده - با نرمال سازی SKU
     */
    private static function get_missing_products($source_products, $target_products, $direction) {
        $missing = [];
        
        if (!is_array($source_products)) {
            return ['count' => 0, 'items' => []];
        }
        
        foreach ($source_products as $sku => $product) {
            if (empty($sku)) continue;
            
            // نرمال سازی SKU برای مقایسه
            $normalized_sku = self::normalize_sku($sku);
            $found = false;
            
            foreach ($target_products as $target_sku => $target_product) {
                if (self::normalize_sku($target_sku) === $normalized_sku) {
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                if ($direction === 'vendor_to_local') {
                    // محصولات فروشنده که در سایت من نیستند
                    $missing[] = $sku;
                } else {
                    // محصولات سایت من که در فروشنده نیستند
                    $missing[] = [
                        'sku' => $sku,
                        'id' => $product['ID'],
                        'title' => $product['post_title']
                    ];
                }
            }
        }
        
        return [
            'count' => count($missing),
            'items' => $missing
        ];
    }
    
    /**
     * محصولات با قیمت متفاوت - نسخه اصلاح شده با تبدیل ارز صحیح
     */
    /**
     * محصولات با قیمت متفاوت - نسخه اصلاح شده برای مقایسه با قیمت فروشنده
     */
    /**
     * محصولات با قیمت متفاوت - نسخه نهایی برای مقایسه قیمت همکاری
     */
    private static function get_price_mismatch_products($local_products, $vendor_products, $vendor_meta) {
    $mismatch = [];
    
    if (!is_array($vendor_products)) return ['count' => 0, 'items' => []];
    
    // دریافت تنظیمات فروشنده از متای کاربر
    $cooperation_price_meta_key = $vendor_meta['vendor_cooperation_price_meta_key'] ?? '';
    $vendor_currency = $vendor_meta['vendor_currency'] ?? 'toman';
    
    // اگر کلید متای قیمت همکاری مشخص نیست، گزارش نده
    if (empty($cooperation_price_meta_key)) {
        return ['count' => 0, 'items' => []];
    }
    
    foreach ($vendor_products as $vendor_sku => $vendor_product) {
        // پیدا کردن محصول محلی منطبق با نرمال سازی SKU
        $local_product = null;
        $normalized_vendor_sku = self::normalize_sku($vendor_sku);
        
        foreach ($local_products as $local_sku => $local_product_data) {
            if (self::normalize_sku($local_sku) === $normalized_vendor_sku) {
                $local_product = $local_product_data;
                break;
            }
        }
        
        if (!$local_product) continue;
        
        // دریافت قیمت همکاری از فروشنده
        $vendor_cooperation_price = self::get_cooperation_price_from_vendor($vendor_product, $cooperation_price_meta_key);
        
        // تبدیل به تومان اگر واحد پول ریال است
        if ($vendor_currency === 'rial' && $vendor_cooperation_price > 0) {
            $vendor_cooperation_price = $vendor_cooperation_price / 10; // تبدیل ریال به تومان
        }
        
        // قیمت فروشنده در سایت ما (_seller_list_price)
        $local_seller_price = floatval($local_product['vendor_price'] ?? 0);
        
        // فقط اگر هر دو قیمت همکاری موجود باشند مقایسه کن
        if ($vendor_cooperation_price > 0 && $local_seller_price > 0) {
            // اختلاف بیشتر از 1000 تومان یا 5% (هرکدام بزرگتر است)
            $absolute_difference = abs($vendor_cooperation_price - $local_seller_price);
            $percentage_difference = ($absolute_difference / $local_seller_price) * 100;
            
            $threshold = max(1000, $local_seller_price * 0.05); // 1000 تومان یا 5%
            
            if ($absolute_difference > $threshold) {
                $mismatch[] = [
                    'id' => $local_product['ID'],
                    'sku' => $vendor_sku,
                    'title' => $local_product['post_title'],
                    'vendor_cooperation_price' => $vendor_cooperation_price,
                    'local_seller_price' => $local_seller_price,
                    'difference' => $vendor_cooperation_price - $local_seller_price,
                    'percentage_diff' => round($percentage_difference, 2),
                    'vendor_currency' => $vendor_currency
                ];
            }
        }
    }
    
    return [
        'count' => count($mismatch),
        'items' => $mismatch
    ];
}

    /**
     * دریافت قیمت همکاری از محصول فروشنده بر اساس کلید متا
     */
    private static function get_cooperation_price_from_vendor($vendor_product, $cooperation_meta_key) {
    $price = 0;
    
    // جستجو در meta_data برای پیدا کردن قیمت همکاری
    if (isset($vendor_product['meta_data']) && is_array($vendor_product['meta_data'])) {
        foreach ($vendor_product['meta_data'] as $meta_item) {
            if (isset($meta_item['key']) && $meta_item['key'] === $cooperation_meta_key) {
                $price = floatval($meta_item['value'] ?? 0);
                break;
            }
        }
    }
    
    return $price;
}

    /**
     * دریافت قیمت اصلی از فروشنده بدون تبدیل ارز
     */
    private static function get_vendor_product_price_original($vendor_product, $cooperation_price_meta_key) {
        $price = 0;
        
        // 1. اولویت با قیمت همکاری از متا فیلد مشخص شده
        if (!empty($cooperation_price_meta_key)) {
            $price = self::get_price_from_meta_data($vendor_product, $cooperation_price_meta_key);
        }
        
        // 2. اگر قیمت همکاری پیدا نشد، از قیمت معمولی استفاده کن
        if ($price <= 0 && isset($vendor_product['price'])) {
            $price = floatval($vendor_product['price']);
        }
        
        // 3. اگر هنوز قیمتی نداریم، از regular_price استفاده کن
        if ($price <= 0 && isset($vendor_product['regular_price'])) {
            $price = floatval($vendor_product['regular_price']);
        }
        
        return $price;
    }
    
    /**
     * دریافت قیمت محصول از فروشنده با پشتیبانی از متا فیلدهای مختلف
     */
    private static function get_vendor_product_price($vendor_product, $cooperation_price_meta_key, $vendor_currency) {
        $price = 0;
        
        // 1. اولویت با قیمت همکاری از متا فیلد مشخص شده
        if (!empty($cooperation_price_meta_key)) {
            $price = self::get_price_from_meta_data($vendor_product, $cooperation_price_meta_key);
        }
        
        // 2. اگر قیمت همکاری پیدا نشد، از قیمت معمولی استفاده کن
        if ($price <= 0 && isset($vendor_product['price'])) {
            $price = floatval($vendor_product['price']);
        }
        
        // 3. اگر هنوز قیمتی نداریم، از regular_price استفاده کن
        if ($price <= 0 && isset($vendor_product['regular_price'])) {
            $price = floatval($vendor_product['regular_price']);
        }
        
        // 4. تبدیل ارز اگر لازم است
        if ($price > 0 && $vendor_currency === 'rial') {
            $price = $price / 10; // تبدیل ریال به تومان
        }
        
        return $price;
    }
    
    /**
     * دریافت قیمت از متادیتای محصول
     */
    private static function get_price_from_meta_data($vendor_product, $meta_key) {
        if (isset($vendor_product['meta_data']) && is_array($vendor_product['meta_data'])) {
            foreach ($vendor_product['meta_data'] as $meta_item) {
                if (isset($meta_item['key']) && $meta_item['key'] === $meta_key) {
                    return floatval($meta_item['value'] ?? 0);
                }
            }
        }
        return 0;
    }
    
    /**
     * محصولات با موجودی متفاوت - با نرمال سازی SKU و وضعیت
     */
    private static function get_stock_mismatch_products($local_products, $vendor_products, $vendor_meta) {
        $mismatch = [];
        
        if (!is_array($vendor_products)) return ['count' => 0, 'items' => []];
        
        foreach ($vendor_products as $vendor_sku => $vendor_product) {
            // پیدا کردن محصول محلی منطبق با نرمال سازی SKU
            $local_product = null;
            $normalized_vendor_sku = self::normalize_sku($vendor_sku);
            
            foreach ($local_products as $local_sku => $local_product_data) {
                if (self::normalize_sku($local_sku) === $normalized_vendor_sku) {
                    $local_product = $local_product_data;
                    break;
                }
            }
            
            if (!$local_product) continue;
            
            $vendor_stock = self::get_vendor_stock_status($vendor_product, $vendor_meta);
            $local_stock = $local_product['stock_status'] ?? 'outofstock';
            
            // نرمال سازی وضعیت موجودی
            $vendor_stock_normalized = self::normalize_stock_status($vendor_stock);
            $local_stock_normalized = self::normalize_stock_status($local_stock);
            
            if ($vendor_stock_normalized !== $local_stock_normalized) {
                $mismatch[] = [
                    'id' => $local_product['ID'],
                    'sku' => $vendor_sku,
                    'title' => $local_product['post_title'],
                    'vendor_stock' => $vendor_stock,
                    'local_stock' => $local_stock,
                    'vendor_stock_normalized' => $vendor_stock_normalized,
                    'local_stock_normalized' => $local_stock_normalized
                ];
            }
        }
        
        return [
            'count' => count($mismatch),
            'items' => $mismatch
        ];
    }
    
    /**
     * دریافت وضعیت موجودی از فروشنده
     */
    private static function get_vendor_stock_status($vendor_product, $vendor_meta) {
        $stock_type = $vendor_meta['vendor_stock_type'] ?? 'status';
        
        if ($stock_type === 'managed') {
            // مدیریت عددی موجودی
            $stock_quantity = intval($vendor_product['stock_quantity'] ?? 0);
            return $stock_quantity > 0 ? 'instock' : 'outofstock';
        } else {
            // مدیریت وضعیتی موجودی
            return $vendor_product['stock_status'] ?? 'outofstock';
        }
    }
    
    /**
     * نرمال سازی وضعیت موجودی
     */
    private static function normalize_stock_status($status) {
        $status = strtolower(trim($status));
        
        if (in_array($status, ['instock', 'in stock', '1', 'true', 'yes', 'موجود', 'available'])) {
            return 'instock';
        }
        
        if (in_array($status, ['outofstock', 'out of stock', '0', 'false', 'no', 'ناموجود', 'unavailable'])) {
            return 'outofstock';
        }
        
        return $status;
    }
    
    /**
     * اطلاعات سینک
     */
    private static function get_sync_info($vendor_id) {
        global $wpdb;
        
        $last_sync = $wpdb->get_var($wpdb->prepare("
            SELECT meta_value 
            FROM {$wpdb->usermeta} 
            WHERE user_id = %d 
            AND meta_key = 'vendor_last_sync_time'
            ORDER BY umeta_id DESC 
            LIMIT 1
        ", $vendor_id));
        
        $sync_count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = '_vendor_stock_last_sync'
            AND post_id IN (
                SELECT ID FROM {$wpdb->posts} 
                WHERE post_author = %d 
                AND post_type = 'product'
            )
        ", $vendor_id));
        
        return [
            'last_sync_time' => $last_sync ?: 'هرگز',
            'synced_products_count' => $sync_count ?: 0
        ];
    }
    
    /**
     * دریافت آخرین زمان سینک
     */
    private static function get_last_sync_time($vendor_id) {
        global $wpdb;
        
        return $wpdb->get_var($wpdb->prepare("
            SELECT meta_value 
            FROM {$wpdb->usermeta} 
            WHERE user_id = %d 
            AND meta_key = 'vendor_last_sync_time'
            ORDER BY umeta_id DESC 
            LIMIT 1
        ", $vendor_id)) ?: 'هرگز';
    }
    
    /**
     * HTML helper methods برای JavaScript
     */
    private static function render_basic_stats_html($stats) {
        ob_start();
        ?>
        <div class="card full-width-card">
            <h3>📈 آمار پایه</h3>
            <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0;">
                <div class="stat-card" style="background: #e7f3ff; padding: 20px; border-radius: 8px; text-align: center;">
                    <div style="font-size: 24px; font-weight: bold; color: #1e40af;"><?php echo $stats['local_products_count']; ?></div>
                    <div style="color: #6b7280;">تعداد محصولات در سایت من</div>
                </div>
                <div class="stat-card" style="background: #f0fdf4; padding: 20px; border-radius: 8px; text-align: center;">
                    <div style="font-size: 24px; font-weight: bold; color: #15803d;"><?php echo $stats['vendor_products_count']; ?></div>
                    <div style="color: #6b7280;">تعداد محصولات در سایت فروشنده</div>
                </div>
                <div class="stat-card" style="background: #fef3c7; padding: 20px; border-radius: 8px; text-align: center;">
                    <div style="font-size: 24px; font-weight: bold; color: #d97706;"><?php echo $stats['matched_products']; ?></div>
                    <div style="color: #6b7280;">محصولات منطبق (SKU یکسان)</div>
                </div>
                <div class="stat-card" style="background: #f3e8ff; padding: 20px; border-radius: 8px; text-align: center;">
                    <div style="font-size: 18px; font-weight: bold; color: #7e22ce;"><?php echo $stats['last_sync_time']; ?></div>
                    <div style="color: #6b7280;">آخرین زمان سینک</div>
                </div>
            </div>
            
            <!-- اطلاعات اضافی فروشنده -->
            <div style="margin-top: 20px; padding: 15px; background: #f8fafc; border-radius: 8px;">
                <h4>⚙️ تنظیمات فروشنده</h4>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 10px;">
                    <div>
                        <strong>واحد پول:</strong> 
                        <span style="background: #e7f3ff; padding: 2px 8px; border-radius: 4px;">
                            <?php echo $stats['vendor_currency'] === 'rial' ? 'ریال' : 'تومان'; ?>
                        </span>
                    </div>
                    <div>
                        <strong>کلید قیمت همکاری:</strong> 
                        <code style="background: #fef3c7; padding: 2px 6px; border-radius: 4px;">
                            <?php echo $stats['cooperation_price_key'] ?: 'استاندارد'; ?>
                        </code>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    private static function render_missing_products_html($data, $type) {
        ob_start();
        $title = $type === 'missing_in_local' ? 
            '📦 محصولات موجود در فروشنده که در سایت من نیستند' : 
            '❌ محصولات موجود در سایت من که در فروشنده نیستند';
        
        $button_text = $type === 'missing_in_local' ? 'کپی SKUها' : 'کپی شناسه‌ها';
        $copy_data = $type === 'missing_in_local' ? 
            implode(', ', $data['items']) : 
            implode(', ', array_column($data['items'], 'id'));
        ?>
        <div class="card full-width-card">
            <h3><?php echo $title; ?> (<?php echo $data['count']; ?> مورد)</h3>
            
            <?php if ($data['count'] > 0): ?>
                <div style="margin-bottom: 15px;">
                    <button type="button" class="button button-secondary copy-all-btn" data-content="<?php echo esc_attr($copy_data); ?>">
                        📋 <?php echo $button_text; ?>
                    </button>
                    <span class="copy-status" style="margin-right: 10px; color: #28a745; font-weight: bold;"></span>
                </div>
                
                <div style="max-height: 300px; overflow-y: auto;">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <?php if ($type === 'missing_in_vendor'): ?>
                                    <th>شناسه</th>
                                    <th>SKU</th>
                                    <th>عنوان</th>
                                <?php else: ?>
                                    <th>SKU</th>
                                <?php endif; ?>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($data['items'] as $item): ?>
                                <tr>
                                    <?php if ($type === 'missing_in_vendor'): ?>
                                        <td><?php echo $item['id']; ?></td>
                                        <td><?php echo esc_html($item['sku']); ?></td>
                                        <td><?php echo esc_html($item['title']); ?></td>
                                        <td>
                                            <button type="button" class="button copy-single-btn" data-content="<?php echo $item['id']; ?>" style="font-size: 12px; padding: 4px 8px;">
                                                کپی شناسه
                                            </button>
                                        </td>
                                    <?php else: ?>
                                        <td><?php echo esc_html($item); ?></td>
                                        <td>
                                            <button type="button" class="button copy-single-btn" data-content="<?php echo esc_attr($item); ?>" style="font-size: 12px; padding: 4px 8px;">
                                                کپی SKU
                                            </button>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p style="color: #28a745; padding: 15px; background: #f0fdf4; border-radius: 4px;">✅ همه محصولات منطبق هستند</p>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    private static function render_mismatch_products_html($data, $type) {
    ob_start();
    $title = $type === 'price' ? 
        '💰 محصولات با قیمت همکاری مغایر' : 
        '📊 محصولات با وضعیت موجودی مغایر';
    ?>
    <div class="card full-width-card">
        <h3><?php echo $title; ?> (<?php echo $data['count']; ?> مورد)</h3>
        
        <?php if ($data['count'] > 0): ?>
            <div style="margin-bottom: 15px;">
                <button type="button" class="button button-secondary copy-all-btn" 
                        data-content="<?php echo esc_attr(implode(', ', array_column($data['items'], 'id'))); ?>">
                    📋 کپی کلیه شناسه‌ها
                </button>
                <span class="copy-status" style="margin-right: 10px; color: #28a745; font-weight: bold;"></span>
            </div>
            
            <div style="max-height: 300px; overflow-y: auto;">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>شناسه</th>
                            <th>SKU</th>
                            <th>عنوان</th>
                            <?php if ($type === 'price'): ?>
                                <th>قیمت همکاری فروشنده</th>
                                <th>قیمت همکاری در سایت ما</th>
                                <th>اختلاف</th>
                            <?php else: ?>
                                <th>موجودی فروشنده</th>
                                <th>موجودی محلی</th>
                            <?php endif; ?>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data['items'] as $item): ?>
                            <tr>
                                <td><?php echo $item['id']; ?></td>
                                <td><?php echo esc_html($item['sku']); ?></td>
                                <td><?php echo esc_html($item['title']); ?></td>
                                <?php if ($type === 'price'): ?>
                                    <td><?php echo number_format($item['vendor_cooperation_price']); ?> تومان</td>
                                    <td><?php echo number_format($item['local_seller_price']); ?> تومان</td>
                                    <td style="color: <?php echo $item['difference'] > 0 ? '#dc2626' : '#15803d'; ?>">
                                        <?php echo $item['difference'] > 0 ? '+' : ''; ?><?php echo number_format($item['difference']); ?> تومان
                                        <br><small>(<?php echo $item['percentage_diff']; ?>%)</small>
                                    </td>
                                <?php else: ?>
                                    <td>
                                        <span class="stock-badge <?php echo $item['vendor_stock_normalized'] === 'instock' ? 'instock' : 'outofstock'; ?>">
                                            <?php echo $item['vendor_stock']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="stock-badge <?php echo $item['local_stock_normalized'] === 'instock' ? 'instock' : 'outofstock'; ?>">
                                            <?php echo $item['local_stock']; ?>
                                        </span>
                                    </td>
                                <?php endif; ?>
                                <td>
                                    <button type="button" class="button copy-single-btn" data-content="<?php echo $item['id']; ?>" style="font-size: 12px; padding: 4px 8px;">
                                        کپی شناسه
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p style="color: #28a745; padding: 15px; background: #f0fdf4; border-radius: 4px;">✅ هیچ مغایرتی یافت نشد</p>
        <?php endif; ?>
    </div>
    
    <style>
        .stock-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        .stock-badge.instock {
            background: #dcfce7;
            color: #15803d;
        }
        .stock-badge.outofstock {
            background: #fee2e2;
            color: #dc2626;
        }
    </style>
    <?php
    return ob_get_clean();
}
    
    private static function render_sync_info_html($sync_info) {
        ob_start();
        ?>
        <div class="card full-width-card">
            <h3>🔄 اطلاعات همگام‌سازی</h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div>
                    <h4>آخرین سینک کلی</h4>
                    <p style="font-size: 18px; font-weight: bold; color: #7e22ce;"><?php echo $sync_info['last_sync_time']; ?></p>
                </div>
                <div>
                    <h4>تعداد محصولات سینک شده</h4>
                    <p style="font-size: 18px; font-weight: bold; color: #15803d;"><?php echo $sync_info['synced_products_count']; ?> محصول</p>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}