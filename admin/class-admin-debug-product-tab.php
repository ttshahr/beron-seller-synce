<?php
if (!defined('ABSPATH')) exit;

class Admin_Debug_Product_Tab {
    
    private static $product_data = null;
    private static $search_error = '';
    
    public static function render() {
        // پردازش جستجوی محصول
        if (isset($_POST['search_product']) && wp_verify_nonce($_POST['_wpnonce'], 'debug_product_search')) {
            self::handle_product_search();
        }
        ?>
        
        <div class="product-debug-container">
            <div class="card full-width-card">
                <h2>🔍 بررسی تخصصی تک محصول</h2>
                <p>این ابزار برای دیباگ و بررسی دقیق اطلاعات یک محصول خاص طراحی شده است.</p>
                
                <!-- فرم جستجو -->
                <div class="search-section" style="background: #f8f9fa; border-radius: 8px; padding: 20px; margin: 20px 0;">
                    <h3>🔎 جستجوی محصول</h3>
                    <form method="post" id="product-search-form">
                        <?php wp_nonce_field('debug_product_search'); ?>
                        <table class="form-table">
                            <tr>
                                <th><label for="product_identifier">شناسه محصول یا SKU</label></th>
                                <td>
                                    <input type="text" name="product_identifier" id="product_identifier" 
                                           value="<?php echo isset($_POST['product_identifier']) ? esc_attr($_POST['product_identifier']) : ''; ?>" 
                                           placeholder="مثال: 6993 یا SKU123" style="width: 300px;" required>
                                    <p class="description">می‌توانید از ID محصول یا SKU استفاده کنید</p>
                                </td>
                            </tr>
                        </table>
                        
                        <button type="submit" name="search_product" class="button button-primary">🔍 جستجوی محصول</button>
                        
                        <?php if (self::$search_error): ?>
                            <div class="notice notice-error" style="margin-top: 15px;">
                                <p><?php echo esc_html(self::$search_error); ?></p>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
                
                <!-- نمایش نتایج -->
                <?php if (self::$product_data): ?>
                    <div class="product-results-section">
                        <h3>📊 نتایج بررسی محصول</h3>
                        
                        <!-- دکمه کپی -->
                        <div style="margin-bottom: 20px;">
                            <button type="button" id="copy-product-data" class="button button-secondary">
                                📋 کپی کلیه اطلاعات
                            </button>
                            <span id="copy-status" style="margin-right: 10px; color: #28a745; font-weight: bold;"></span>
                        </div>
                        
                        <?php self::render_product_data(); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <style>
            .product-debug-container {
                display: flex;
                flex-direction: column;
                gap: 20px;
            }

            .data-section {
                background: #fff;
                border: 1px solid #e1e1e1;
                border-radius: 8px;
                padding: 20px;
                margin: 15px 0;
            }

            .data-section h4 {
                margin-top: 0;
                color: #2c5aa0;
                border-bottom: 2px solid #f0f0f1;
                padding-bottom: 10px;
            }

            .meta-table {
                width: 100%;
                border-collapse: collapse;
                font-size: 13px;
            }

            .meta-table th {
                background: #f8f9fa;
                text-align: right;
                padding: 10px;
                border: 1px solid #dee2e6;
                font-weight: 600;
            }

            .meta-table td {
                padding: 10px;
                border: 1px solid #dee2e6;
                direction: ltr;
                text-align: left;
                font-family: 'Courier New', monospace;
            }

            .meta-key {
                background: #e7f3ff;
                font-weight: bold;
                color: #1e40af;
            }

            .meta-value {
                background: #f8f9fa;
                max-width: 400px;
                word-break: break-all;
            }

            .stock-status-instock {
                color: #15803d;
                font-weight: bold;
            }

            .stock-status-outofstock {
                color: #dc2626;
                font-weight: bold;
            }

            .cache-item {
                display: flex;
                justify-content: space-between;
                padding: 8px;
                border-bottom: 1px solid #e1e1e1;
            }

            .cache-item:last-child {
                border-bottom: none;
            }

            .copy-btn {
                background: #28a745;
                color: white;
                border: none;
                padding: 4px 8px;
                border-radius: 3px;
                cursor: pointer;
                font-size: 11px;
            }

            .copy-btn:hover {
                background: #218838;
            }
        </style>

        <script>
        jQuery(document).ready(function($) {
            // کپی کلیه اطلاعات
            $('#copy-product-data').on('click', function() {
                const productData = $('#product-data-json').text();
                
                navigator.clipboard.writeText(productData).then(function() {
                    $('#copy-status').text('✅ اطلاعات کپی شد').fadeIn().delay(2000).fadeOut();
                }).catch(function(err) {
                    $('#copy-status').text('❌ خطا در کپی').fadeIn().delay(2000).fadeOut();
                });
            });

            // کپی مقادیر جداگانه
            $('.copy-btn').on('click', function() {
                const value = $(this).data('value');
                const $status = $(this).siblings('.copy-status');
                
                navigator.clipboard.writeText(value).then(function() {
                    $status.text('✅').fadeIn().delay(1000).fadeOut();
                }).catch(function(err) {
                    $status.text('❌').fadeIn().delay(1000).fadeOut();
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * پردازش جستجوی محصول
     */
    private static function handle_product_search() {
        $identifier = sanitize_text_field($_POST['product_identifier']);
        
        if (empty($identifier)) {
            self::$search_error = 'لطفاً شناسه محصول یا SKU را وارد کنید';
            return;
        }
        
        // جستجو با ID
        if (is_numeric($identifier)) {
            $product_id = intval($identifier);
            $product = wc_get_product($product_id);
            
            if ($product) {
                self::$product_data = self::gather_product_data($product_id, $product);
                return;
            }
        }
        
        // جستجو با SKU
        global $wpdb;
        $product_id = $wpdb->get_var($wpdb->prepare("
            SELECT post_id 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = '_sku' 
            AND meta_value = %s 
            LIMIT 1
        ", $identifier));
        
        if ($product_id) {
            $product = wc_get_product($product_id);
            if ($product) {
                self::$product_data = self::gather_product_data($product_id, $product);
                return;
            }
        }
        
        self::$search_error = 'محصولی با این شناسه یا SKU یافت نشد';
    }
    
    /**
     * جمع‌آوری اطلاعات محصول
     */
    private static function gather_product_data($product_id, $product) {
        global $wpdb;
        
        $data = [
            'basic_info' => [
                'ID' => $product_id,
                'Name' => $product->get_name(),
                'Type' => $product->get_type(),
                'Status' => $product->get_status(),
                'Author' => get_the_author_meta('display_name', $product->get_post_data()->post_author),
                'Date Created' => $product->get_date_created() ? $product->get_date_created()->date('Y-m-d H:i:s') : 'N/A',
                'Date Modified' => $product->get_date_modified() ? $product->get_date_modified()->date('Y-m-d H:i:s') : 'N/A'
            ],
            
            'stock_info' => [
                'Manage Stock' => $product->get_manage_stock() ? 'true' : 'false',
                'Stock Quantity' => $product->get_stock_quantity(),
                'Stock Status' => $product->get_stock_status(),
                'Backorders' => $product->get_backorders(),
                'Low Stock Amount' => $product->get_low_stock_amount(),
                'Sold Individually' => $product->get_sold_individually() ? 'true' : 'false'
            ],
            
            'price_info' => [
                'Regular Price' => $product->get_regular_price(),
                'Sale Price' => $product->get_sale_price(),
                'Price' => $product->get_price(),
                'Vendor Raw Price' => get_post_meta($product_id, '_vendor_raw_price', true),
                'Vendor Final Price' => get_post_meta($product_id, '_vendor_final_price', true),
                'Seller List Price' => get_post_meta($product_id, '_seller_list_price', true)
            ],
            
            'sync_info' => [
                'Vendor Stock Last Sync' => get_post_meta($product_id, '_vendor_stock_last_sync', true),
                'Colleague Price Update Time' => get_post_meta($product_id, '_colleague_price_update_time', true),
                'Out Stock Send SMS' => get_post_meta($product_id, '_out_stock_send_sms', true)
            ],
            
            'all_meta' => get_post_meta($product_id),
            
            'cache_info' => [
                'post_cache' => wp_cache_get($product_id, 'posts'),
                'post_meta_cache' => wp_cache_get($product_id, 'post_meta'),
                'transients' => $wpdb->get_results("
                    SELECT option_name, option_value 
                    FROM {$wpdb->options} 
                    WHERE option_name LIKE '%abep%' 
                    OR option_name LIKE '%w3exabe%'
                    OR option_name LIKE '%{$product_id}%'
                ", ARRAY_A)
            ]
        ];
        
        return $data;
    }
    
    /**
     * نمایش اطلاعات محصول
     */
    private static function render_product_data() {
        $data = self::$product_data;
        ?>
        
        <!-- اطلاعات پایه -->
        <div class="data-section">
            <h4>📝 اطلاعات پایه محصول</h4>
            <table class="meta-table">
                <?php foreach ($data['basic_info'] as $key => $value): ?>
                <tr>
                    <td class="meta-key" width="30%"><?php echo $key; ?></td>
                    <td class="meta-value">
                        <?php echo $value ? esc_html($value) : '<span style="color: #6c757d;">خالی</span>'; ?>
                        <button class="copy-btn" data-value="<?php echo esc_attr($value); ?>">کپی</button>
                        <span class="copy-status" style="margin-right: 5px;"></span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
        
        <!-- اطلاعات موجودی -->
        <div class="data-section">
            <h4>📦 اطلاعات موجودی</h4>
            <table class="meta-table">
                <?php foreach ($data['stock_info'] as $key => $value): ?>
                <tr>
                    <td class="meta-key" width="30%"><?php echo $key; ?></td>
                    <td class="meta-value <?php echo ($key === 'Stock Status') ? 'stock-status-' . $value : ''; ?>">
                        <?php 
                        if ($value === null || $value === '') {
                            echo '<span style="color: #6c757d;">خالی/null</span>';
                        } else {
                            echo esc_html($value);
                        }
                        ?>
                        <button class="copy-btn" data-value="<?php echo esc_attr($value); ?>">کپی</button>
                        <span class="copy-status" style="margin-right: 5px;"></span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
        
        <!-- اطلاعات قیمت -->
        <div class="data-section">
            <h4>💰 اطلاعات قیمت</h4>
            <table class="meta-table">
                <?php foreach ($data['price_info'] as $key => $value): ?>
                <tr>
                    <td class="meta-key" width="30%"><?php echo $key; ?></td>
                    <td class="meta-value">
                        <?php 
                        if ($value === null || $value === '') {
                            echo '<span style="color: #6c757d;">خالی/null</span>';
                        } else {
                            echo esc_html($value);
                        }
                        ?>
                        <button class="copy-btn" data-value="<?php echo esc_attr($value); ?>">کپی</button>
                        <span class="copy-status" style="margin-right: 5px;"></span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
        
        <!-- اطلاعات سینک -->
        <div class="data-section">
            <h4>🔄 اطلاعات همگام‌سازی</h4>
            <table class="meta-table">
                <?php foreach ($data['sync_info'] as $key => $value): ?>
                <tr>
                    <td class="meta-key" width="30%"><?php echo $key; ?></td>
                    <td class="meta-value">
                        <?php echo $value ? esc_html($value) : '<span style="color: #6c757d;">خالی</span>'; ?>
                        <button class="copy-btn" data-value="<?php echo esc_attr($value); ?>">کپی</button>
                        <span class="copy-status" style="margin-right: 5px;"></span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
        
        <!-- اطلاعات کش -->
        <div class="data-section">
            <h4>🧠 اطلاعات کش</h4>
            <div style="margin-bottom: 15px;">
                <strong>Object Cache (posts):</strong> 
                <?php echo $data['cache_info']['post_cache'] ? '✅ دارد' : '❌ ندارد'; ?>
            </div>
            <div style="margin-bottom: 15px;">
                <strong>Object Cache (post_meta):</strong> 
                <?php echo $data['cache_info']['post_meta_cache'] ? '✅ دارد' : '❌ ندارد'; ?>
            </div>
            
            <?php if (!empty($data['cache_info']['transients'])): ?>
                <h5>ترنزینت‌های مرتبط:</h5>
                <div style="max-height: 200px; overflow-y: auto; background: #f8f9fa; padding: 10px; border-radius: 4px;">
                    <?php foreach ($data['cache_info']['transients'] as $transient): ?>
                        <div class="cache-item">
                            <span><strong><?php echo esc_html($transient['option_name']); ?></strong></span>
                            <span><?php echo strlen($transient['option_value']); ?> کاراکتر</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p>❌ هیچ ترنزینت مرتبطی یافت نشد</p>
            <?php endif; ?>
        </div>
        
        <!-- تمام متاها (برای کپی کلی) -->
        <div class="data-section">
            <h4>📋 تمام متاهای محصول (JSON برای کپی)</h4>
            <pre id="product-data-json" style="background: #f8f9fa; padding: 15px; border-radius: 4px; max-height: 300px; overflow-y: auto; direction: ltr; text-align: left; font-size: 12px;"><?php 
                echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            ?></pre>
        </div>
        
        <?php
    }
}