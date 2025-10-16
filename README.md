# افزونه همگام‌سازی برون

یک افزونه حرفه‌ای برای همگام‌سازی محصولات، قیمت‌ها و موجودی با فروشندگان مختلف.

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

beron-seller-sync/
├── 📄 beron-seller-sync.php                 # فایل اصلی افزونه
├── 📄 README.md                            # مستندات
├── 📄 CHANGELOG.md                         # تاریخچه تغییرات
├── 📄 uninstall.php                        # فایل حذف افزونه
│
├── 📁 inc/                                 # هسته اصلی افزونه
│   ├── 🎯 class-vendor-product-sync-manager.php    # مدیریت اصلی
│   ├── 🔌 class-vendor-api-handler.php            # ارتباط با API
│   ├── 🔌 class-vendor-api-optimizer.php          # بهینه‌سازی API
│   ├── 💰 class-vendor-price-processor.php        # پردازش قیمت
│   ├── 💰 class-vendor-price-calculator.php       # محاسبه قیمت نهایی
│   ├── 💾 class-vendor-raw-price-saver-optimized.php # ذخیره قیمت (اصلی)
│   ├── 📦 class-vendor-stock-updater-optimized.php  # بروزرسانی موجودی (اصلی)
│   ├── 👤 class-vendor-product-assigner.php       # اختصاص محصولات
│   ├── 🏷️ class-vendor-meta-handler.php          # مدیریت متاها
│   ├── 📝 class-vendor-logger.php                # سیستم لاگ‌گیری
│   ├── 🔧 class-vendor-debug-helper.php          # ابزار دیباگ
│   ├── ⚡ class-vendor-ajax-handler.php          # هندلرهای AJAX
│   ├── 📊 class-admin.php                       # مدیریت پیشخوان
│   ├── 🏷️ admin-columns.php                    # ستون‌های پیشخوان
│   ├── 🏷️ define-hamkar-and-seller-meta.php    # تعریف متاهای فروشنده
│   ├── 🏷️ define-product-meta.php              # تعریف متاهای محصول
│   ├── 💰 class-sale-profit-calculator.php      # محاسبه سود فروش
│   │
│   ├── 🗑️ فایل‌های قدیمی (حذف شوند) ❌
│   ├── ❌ class-vendor-raw-price-saver.php      # نسخه قدیمی
│   ├── ❌ class-vendor-stock-updater.php        # نسخه قدیمی  
│   ├── ❌ class-vendor-sync-batch-processor.php # تکراری
│
├── 📁 assets/                               # فایل‌های استاتیک
│   ├── 🎨 progress.css                      # استایل نوار پیشرفت
│   └── ⚡ progress.js                       # اسکریپت نوار پیشرفت
│
├── 📁 logs/                                 # پوشه لاگ‌ها (جدید)
│   ├── 📄 vendor-sync-errors.log           # خطاها
│   ├── 📄 vendor-sync-success.log          # موفقیت‌ها  
│   └── 📄 vendor-sync-api.log              # لاگ API
│
└── 📁 .git/                                # گیت (کنترل نسخه)



## 🎯 **حالا برای توسعه:**

**چه فیچرهای جدیدی می‌خواهید اضافه کنید؟**

- [ ] **سیستم Scheduled Sync** (همگام‌سازی زمان‌بندی شده)
- [ ] **گزارش‌گیری پیشرفته** (نمودار و آمار)
- [ ] **ایمیل نوتیفیکیشن** 
- [ ] **همگام‌سازی دوطرفه**
- [ ] **سطح دسترسی مختلف**
- [ ] **لاگ real-time**
- [ ] **backup و restore**
- [ ] **API برای توسعه‌دهندگان**
- [ ] **قالب‌های قیمت‌گذاری**
- [ ] **چیز دیگر...**

**کدوم مورد اولویت داره؟** 💪بزار دیباگ



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

```php
// فیلتر برای تغییر درصد سود
add_filter('vendor_price_conversion_percent', function($percent) {
    return 25; // تغییر درصد به 25%
});

// اکشن بعد از همگام‌سازی
add_action('vendor_sync_completed', function($vendor_id, $processed_count) {
    // انجام عملیات بعد از همگام‌سازی
});