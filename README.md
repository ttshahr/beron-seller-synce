# افزونه همگام‌سازی برون

- تقسیم‌بندی ماژولار صفحات مدیریت
- ایجاد کلاس `Admin_Common` برای متدهای مشترک
- فایل‌های تخصصی برای هر بخش: قیمت، محاسبه، موجودی، دیباگ
- بهبود نمایش تعداد واقعی محصولات فروشندگان

## قابلیت‌های اصلی

### 🔄 همگام‌سازی قیمت
- دریافت قیمت‌های خام از فروشندگان
- محاسبه قیمت نهایی با درصد سود
- تبدیل خودکار ریال به تومان
- ذخیره در متاهای اختصاصی

### 📦 مدیریت موجودی
- بروزرسانی موجودی از فروشندگان
- پشتیبانی از انواع موجودی (مدیریت شده/ساده)
- همگام‌سازی وضعیت موجودی

### 👥 مدیریت چند فروشنده
- پشتیبانی از چندین فروشنده همزمان
- تنظیمات اختصاصی برای هر فروشنده
- API Keys و تنظیمات اتصال

### 🎯 ویژگی‌های پیشرفته
- پردازش دسته‌ای برای حجم‌های بالا
- سیستم لاگ‌گیری کامل
- ابزار دیباگ و عیب‌یابی
- پیش‌نمایش قبل از اجرا

## ساختار فایل‌ها
beron-seller-synce/
├── 📄 beron-seller-sync.php                            # فایل اصلی افزونه - بارگذاری اولیه و راه‌اندازی
├── 📄 README.md                                        # مستندات راهنمای نصب و استفاده
├── 📄 CHANGELOG.md                                     # تاریخچه تغییرات و نسخه‌ها
├── 📄 uninstall.php                                    # فایل حذف افزونه - پاکسازی داده‌ها هنگام حذف
├── 📁 admin/                                           # مدیریت رابط کاربری
│   ├── 🎛️ class-admin-menus.php                       # تعریف منوها و زیرمنوهای سیستم
│   ├── 🎨 class-admin-pages.php                       # 📦 اصلی - مدیریت و هدایت به صفحات مختلف
│   ├── 📊 class-admin-dashboard.php                   # داشبورد اصلی - آمار و نمای کلی سیستم
│   ├── 🔧 class-admin-common.php                      # 🆕 جدید - متدهای مشترک بین صفحات
│   ├── 💰 class-admin-price-pages.php                 # 🆕 جدید - صفحات مربوط به مدیریت قیمت‌ها
│   ├── 🧮 class-admin-calculate-pages.php             # 🆕 جدید - صفحات محاسبه قیمت نهایی
│   ├── 📦 class-admin-stock-pages.php                 # 🆕 جدید - صفحات مدیریت موجودی
│   ├── 🐛 class-admin-debug-pages.php                 # 🆕 جدید - صفحات دیباگ و عیب‌یابی
│   └── ⚡ class-admin-ajax.php                        # مدیریت درخواست‌های AJAX
├── 📁 handlers/                                       # پردازش درخواست‌ها
│   ├── 📥 class-price-sync-handler.php                # هندلر همگام‌سازی قیمت‌ها
│   ├── 🧮 class-price-calc-handler.php                # هندلر محاسبه قیمت نهایی
│   ├── 📦 class-stock-update-handler.php              # هندلر بروزرسانی موجودی
│   └── 👤 class-product-assign-handler.php            # هندلر اختصاص محصولات
├── 📁 meta/                                            # مدیریت متاها - تعریف و مدیریت فیلدهای سفارشی
│   ├── 🏷️ meta-definitions.php                         # تعریف ثابت‌های متا - کلیدها و ساختار متاهای محصول و کاربر
│   ├── 🔧 class-meta-handler.php                       # هندلر اصلی متاها - دریافت و اعتبارسنجی متاهای فروشنده
│   ├── 📦 class-product-meta.php                       # مدیریت متاهای محصول - فیلدهای قیمت، سود و زمان‌های همگام‌سازی
│   └── 👤 class-user-meta.php                          # مدیریت متاهای کاربر - فیلدهای API و تنظیمات فروشندگان
├── 📁 inc/                                             # هسته اصلی افزونه - منطق کسب و کار و عملیات اصلی
│   ├── 🏷️ admin-columns.php                            # ستون‌های پیشخوان - نمایش اطلاعات سنک در لیست محصولات
│   ├── 💰 class-sale-profit-calculator.php             # محاسبه سود فروش - محاسبه خودکار سود بر اساس قیمت‌ها
│   ├── ⚡ class-vendor-ajax-handler.php                # هندلرهای AJAX - پردازش درخواست‌های غیرهمگام
│   ├── 🔌 class-vendor-api-optimizer.php               # بهینه‌سازی API - مدیریت ارتباط با API فروشندگان
│   ├── 🔧 class-vendor-debug-helper.php                # ابزار دیباگ - کمک به عیب‌یابی و رفع خطاها
│   ├── 📝 class-vendor-logger.php                      # سیستم لاگ‌گیری - ثبت رویدادها و خطاهای سیستم
│   ├── 💰 class-vendor-price-calculator.php            # محاسبه قیمت نهایی - تبدیل قیمت خام به قیمت فروش
│   ├── 👤 class-vendor-product-assigner.php            # اختصاص محصولات - تخصیص محصولات به فروشندگان
│   ├── 💾 class-vendor-raw-price-saver-optimized.php   # ذخیره قیمت (اصلی) - دریافت و ذخیره قیمت‌های خام از فروشنده
│   ├── 📦 class-vendor-stock-updater-optimized.php     # بروزرسانی موجودی (اصلی) - به‌روزرسانی موجودی از فروشنده
│   └── 🆕 update-core.php                              # سیستم آپدیت - مدیریت بروزرسانی‌های افزونه
├── 📁 assets/                                          # فایل‌های استاتیک - رابط کاربری و جلوه‌های بصری
│   ├── 🎨 progress.css                                 # استایل نوار پیشرفت - ظاهر نوارهای پیشرفت عملیات
│   └── ⚡ progress.js                                  # اسکریپت نوار پیشرفت - مدیریت نوارهای پیشرفت در رابط کاربری
└── 📁 logs/                                            # پوشه لاگ‌ها - ذخیره فایل‌های ثبت رویدادهای سیستم


## نصب و راه‌اندازی

1. آپلود افزونه در پوشه `wp-content/plugins/`
2. فعال‌سازی افزونه
3. تنظیم فروشندگان در بخش کاربران
4. شروع همگام‌سازی از منوی "همگام‌سازی فروشندگان"

## تنظیمات فروشنده

برای هر فروشنده این متاها باید تنظیم شوند:

- `vendor_website_url` - آدرس وبسایت فروشنده
- `vendor_consumer_key` - کلید API
- `vendor_consumer_secret` - رمز API
- `vendor_currency` - ارز (rial/toman)
- `vendor_stock_type` - نوع موجودی
- `vendor_cooperation_price_meta_key` - کلید متای قیمت
- `vendor_price_conversion_percent` - درصد سود


## هوک‌ها و فیلترها


// فیلتر برای تغییر درصد سود
add_filter('vendor_price_conversion_percent', function($percent) {
    return 25; // تغییر درصد به 25%
});

// اکشن بعد از همگام‌سازی
add_action('vendor_sync_completed', function($vendor_id, $processed_count) {
    // انجام عملیات بعد از همگام‌سازی
});


## دستورالعمل استفاده از سیستم لاگ‌گیری 

🎯 معرفی
سیستم لاگ‌گیری پیشرفته برای ردیابی و ثبت تمامی رویدادهای همگام‌سازی با فروشندگان.

📁 ساختار فایل‌های لاگ
sync-errors.log - خطاها و مشکلات

sync-success.log - عملیات موفق

sync-api.log - درخواست‌های API

sync-debug.log - اطلاعات دیباگ (فقط در حالت توسعه)

sync-general.log - تمام لاگ‌ها (جامع)

🚀 روش‌های استفاده
1. ثبت خطا (Error)
// خطای ساده
Vendor_Logger::log_error("خطای اتصال به API", $product_id, $vendor_id);

// خطا با جزئیات بیشتر
Vendor_Logger::log_error("Timeout در دریافت قیمت برای SKU: {$sku}", null, $vendor_id);

2. ثبت موفقیت (Success)

// موفقیت با محصول
Vendor_Logger::log_success($product_id, 'price_updated', $vendor_id, "قیمت به {$price} به‌روز شد");

// موفقیت بدون محصول
Vendor_Logger::log_success(0, 'sync_completed', $vendor_id, "همگام‌سازی ۱۵۰ محصول تکمیل شد");

3. ثبت درخواست API

Vendor_Logger::log_api_request(
    $api_url,
    $sku,
    true, // موفق/ناموفق
    $vendor_id,
    $response_time // اختیاری
);

4. ثبت اطلاعات عمومی (Info)

// شروع عملیات
Vendor_Logger::log_info("🚀 شروع همگام‌سازی قیمت برای فروشنده {$vendor_id}", $vendor_id);

// وضعیت پردازش
Vendor_Logger::log_info("📦 پردازش ۵۰ محصول از ۲۰۰ محصول", $vendor_id);

// اتمام عملیات
Vendor_Logger::log_info("✅ همگام‌سازی با موفقیت تکمیل شد", $vendor_id);

5. ثبت هشدار (Warning)

// هشدار برای محصول
Vendor_Logger::log_warning("قیمت صفر تشخیص داده شد", $product_id, $vendor_id);

// هشدار عمومی
Vendor_Logger::log_warning("محدودیت نرخ درخواست API", null, $vendor_id);

6. ثبت دیباگ (Debug) - فقط در حالت توسعه

// ابتدا در wp-config.php تعریف کنید:
define('BERON_DEBUG', true);

// سپس استفاده کنید:
Vendor_Logger::log_debug("مقدار متا: {$meta_value}", $product_id, $vendor_id);
Vendor_Logger::log_debug("پاسخ API: " . print_r($response, true), null, $vendor_id);

💡 الگوهای توصیه شده
برای عملیات همگام‌سازی قیمت:

    public function sync_prices($vendor_id, $brand_id) {
        try {
            Vendor_Logger::log_info("🚀 شروع همگام‌سازی قیمت", $vendor_id);
            
            // منطق اصلی
            foreach ($products as $product) {
                Vendor_Logger::log_debug("پردازش محصول: {$product['sku']}", $product['id'], $vendor_id);
                
                if ($price > 0) {
                    Vendor_Logger::log_success($product['id'], 'price_saved', $vendor_id, "قیمت: {$price}");
                } else {
                    Vendor_Logger::log_warning("قیمت نامعتبر", $product['id'], $vendor_id);
                }
            }
            
            Vendor_Logger::log_info("✅ همگام‌سازی تکمیل شد", $vendor_id);
            
        } catch (Exception $e) {
            Vendor_Logger::log_error("خطا در همگام‌سازی: " . $e->getMessage(), null, $vendor_id);
            throw $e;
        }
    }
برای درخواست‌های API:


    public function fetch_vendor_data($sku, $vendor_id) {
        $start_time = microtime(true);
        
        try {
            $response = $this->api_call($sku);
            $response_time = round(microtime(true) - $start_time, 2);
            
            Vendor_Logger::log_api_request($this->api_url, $sku, true, $vendor_id, $response_time);
            return $response;
            
        } catch (Exception $e) {
            $response_time = round(microtime(true) - $start_time, 2);
            Vendor_Logger::log_api_request($this->api_url, $sku, false, $vendor_id, $response_time);
            Vendor_Logger::log_error("API Error: " . $e->getMessage(), null, $vendor_id);
            throw $e;
        }
    }

🛠️ ابزارهای مدیریت لاگ

مشاهده لاگ‌های اخیر:

$recent_logs = Vendor_Logger::get_recent_logs('general', 50); // ۵۰ خط آخر
$error_logs = Vendor_Logger::get_recent_logs('error', 20);   // ۲۰ خط آخر خطاها

دریافت آمار لاگ‌ها:

$stats = Vendor_Logger::get_log_stats();

خروجی:
[
    'error' => ['size' => '15 KB', 'lines' => 150, 'last_modified' => '2024-01-15 10:30:00'],
    'success' => ['size' => '45 KB', 'lines' => 450, 'last_modified' => '2024-01-15 10:35:00'],
    ...
]


پاکسازی خودکار:
// پاکسازی لاگ‌های قدیمی‌تر از ۳۰ روز
Vendor_Logger::cleanup_old_logs(30);

📊 سطوح لاگ و موارد استفاده
سطح	مورد استفاده	مثال
error	خطاهای بحرانی	خطای اتصال به دیتابیس
success	عملیات موفق	قیمت با موفقیت ذخیره شد
warning	هشدارها	قیمت صفر تشخیص داده شد
info	اطلاعات عمومی	شروع عملیات همگام‌سازی
debug	اطلاعات توسعه	مقادیر متا و متغیرها
api	درخواست‌های API	API Call - Success - 0.45s


📋 نکات مهم
همیشه vendor_id را ارسال کنید برای ردیابی بهتر
از emoji استفاده کنید برای خوانایی بهتر لاگ‌ها
لاگ‌های دیباگ را در production غیرفعال کنید
لاگ‌ها را به طور منظم بررسی و پاکسازی کنید
از پیام‌های توصیفی و واضح استفاده کنید
این دستورالعمل به همه توسعه‌دهندگان کمک می‌کند تا به صورت استاندارد و یکپارچه از سیستم لاگ‌گیری استفاده کنند.


% اصول توسعه صفحات 

🎯 اصول توسعه
1. استفاده از Admin_Common برای متدهای مشترک
php
// ✅ درست
<?php Admin_Common::render_common_stats(); ?>

// ❌ اشتباه  
<?php self::render_common_stats(); ?>
2. نام‌گذاری استاندارد
php
// کلاس صفحات: Admin_{Feature}_Pages
// متد رندر: render_{feature}_page()
// متد فرم: render_{feature}_form()
3. سازگاری با ساختار موجود
php
// همیشه از طریق Admin_Pages فراخوانی شود
Admin_Pages::render_{feature}_page();
4. مدیریت وابستگی‌ها
php
// استفاده از متدهای کمکی موجود
Vendor_Product_Assigner::get_vendor_real_products_count($vendor_id);
A