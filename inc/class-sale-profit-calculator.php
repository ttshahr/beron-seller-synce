<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Sale_Profit_Calculator {

    private static $batch_size = 100; // پردازش 100 تایی
    private static $memory_cleanup_interval = 50;

    public function __construct() {
        add_action('wp_ajax_calculate_sale_profit', [$this, 'calculate_profit_ajax']);
        Vendor_Logger::log_info('Sale Profit Calculator initialized');
    }

    public function render_page() {
        $categories = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
        ?>
        <div class="wrap">
            <h1>محاسبه سود فروش محصولات</h1>
            <p>⚠️ <strong>توجه:</strong> برای تعداد زیاد محصولات، محاسبه زمان‌بر است. بهتر است دسته‌ها را جداگانه انتخاب کنید.</p>
            
            <form id="profit-form">
                <table class="form-table">
                    <tr>
                        <th>انتخاب دسته‌ها</th>
                        <td>
                            <select name="categories[]" multiple style="width:300px; height:150px;">
                                <?php foreach($categories as $cat): ?>
                                    <option value="<?php echo esc_attr($cat->term_id); ?>"><?php echo esc_html($cat->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">برای عملکرد بهتر، حداکثر 3-5 دسته انتخاب کنید</p>
                        </td>
                    </tr>
                </table>
                <button type="button" class="button button-primary" id="start-calc">شروع محاسبه</button>
            </form>

            <div id="progress-container" style="margin-top:20px; display:none;">
                <h3>در حال محاسبه...</h3>
                <div style="width:100%; background:#ddd; border-radius:5px;">
                    <div id="progress-bar" style="width:0%; height:25px; background:#4caf50; text-align:center; color:#fff; line-height:25px;">0%</div>
                </div>
                <p id="progress-text"></p>
                <p id="progress-details" style="font-size:12px; color:#666;"></p>
            </div>

            <div id="calc-result" style="margin-top:20px;"></div>
            
            <div class="card" style="margin-top: 20px;">
                <h3>📊 لاگ‌های اخیر محاسبه سود</h3>
                <?php $this->render_recent_logs(); ?>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($){
            $('#start-calc').click(function(){
                var selected = $('select[name="categories[]"]').val();
                if(!selected || selected.length===0){
                    alert('حداقل یک دسته انتخاب کنید.');
                    return;
                }

                // ⚠️ هشدار برای انتخاب زیاد
                if(selected.length > 5) {
                    if(!confirm('⚠️ شما ' + selected.length + ' دسته انتخاب کرده‌اید. این ممکن است باعث کندی یا خطا شود. آیا ادامه می‌دهید؟')) {
                        return;
                    }
                }

                $('#progress-container').show();
                $('#progress-bar').css('width','0%').text('0%');
                $('#progress-text').text('در حال شروع...');
                $('#progress-details').text('');
                $('#calc-result').html('');

                var categoryNames = [];
                $('select[name="categories[]"] option:selected').each(function() {
                    categoryNames.push($(this).text());
                });
                
                $('#progress-text').text('در حال محاسبه سود برای دسته‌ها: ' + categoryNames.join(', '));

                $.post(ajaxurl, {
                    action: 'calculate_sale_profit',
                    categories: selected,
                    _wpnonce: '<?php echo wp_create_nonce("calculate_profit_nonce"); ?>'
                }, function(response){
                    console.log('AJAX Response:', response);
                    if(response.success){
                        $('#progress-bar').css('width','100%').text('100%');
                        $('#progress-text').text('محاسبه کامل شد.');
                        $('#calc-result').html('<div class="notice notice-success"><p>' + response.data + '</p></div>');
                        
                        setTimeout(function() {
                            location.reload();
                        }, 3000);
                    } else {
                        $('#progress-bar').css('width','100%').text('100%').css('background','#dc2626');
                        $('#progress-text').text('خطا در محاسبه');
                        $('#calc-result').html('<div class="notice notice-error"><p>خطا: ' + response.data + '</p></div>');
                    }
                }).fail(function(xhr, status, error) {
                    console.error('AJAX Error:', xhr.responseText);
                    $('#progress-bar').css('width','100%').text('100%').css('background','#dc2626');
                    $('#progress-text').text('خطا در ارتباط با سرور - احتمالاً مشکل حافظه');
                    $('#calc-result').html('<div class="notice notice-error"><p>خطای حافظه - لطفا دسته‌های کمتری انتخاب کنید</p></div>');
                });
            });
        });
        </script>
        
        <style>
        .profit-log-entry { padding: 8px 12px; margin: 5px 0; border-radius: 4px; font-family: monospace; font-size: 12px; }
        .profit-log-success { background: #f0fdf4; border-left: 4px solid #22c55e; }
        .profit-log-error { background: #fef2f2; border-left: 4px solid #ef4444; }
        .profit-log-info { background: #f0f9ff; border-left: 4px solid #0ea5e9; }
        </style>
        <?php
    }
    
    private function render_recent_logs() {
        $recent_logs = Vendor_Logger::get_recent_logs('general', 10);
        $profit_logs = [];
        
        foreach ($recent_logs as $log) {
            if (strpos($log, 'profit_calc') !== false || strpos($log, 'سود') !== false) {
                $profit_logs[] = $log;
            }
        }
        
        if (empty($profit_logs)) {
            echo '<p>هنوز هیچ محاسبه سودی انجام نشده است.</p>';
            return;
        }
        
        echo '<div style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #f9f9f9;">';
        foreach (array_slice($profit_logs, 0, 10) as $log) {
            $log_class = 'profit-log-info';
            if (strpos($log, 'SUCCESS') !== false) $log_class = 'profit-log-success';
            elseif (strpos($log, 'ERROR') !== false) $log_class = 'profit-log-error';
            echo '<div class="profit-log-entry ' . $log_class . '">' . esc_html($log) . '</div>';
        }
        echo '</div>';
    }

    public function calculate_profit_ajax() {
        try {
            if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'calculate_profit_nonce')) {
                throw new Exception('خطای امنیتی');
            }

            if (!current_user_can('manage_woocommerce')) {
                throw new Exception('دسترسی غیرمجاز');
            }

            $categories = isset($_POST['categories']) ? array_map('intval', $_POST['categories']) : [];
            
            if(empty($categories)){
                throw new Exception('هیچ دسته‌ای انتخاب نشده است.');
            }

            // ⚠️ هشدار برای دسته‌های زیاد
            if (count($categories) > 10) {
                Vendor_Logger::log_warning('Too many categories selected: ' . count($categories));
            }

            Vendor_Logger::log_info('Starting optimized profit calculation for ' . count($categories) . ' categories');

            // ✅ تنظیمات بهینه برای حجم بالا
            set_time_limit(600); // 10 دقیقه
            ini_set('memory_limit', '1024M'); // افزایش رم
            wp_suspend_cache_addition(true);
            wp_defer_term_counting(true);
            wp_defer_comment_counting(true);

            // ✅ دریافت ID محصولات به صورت مستقیم (سبک‌تر)
            $product_ids = $this->get_product_ids_optimized($categories);
            $total = count($product_ids);
            
            Vendor_Logger::log_info("Found {$total} products for profit calculation");

            if ($total === 0) {
                throw new Exception('هیچ محصولی در دسته‌های انتخاب شده یافت نشد.');
            }

            // ✅ پردازش دسته‌ای برای مدیریت حافظه
            $result = $this->process_in_batches($product_ids, $total);

            // ✅ بازگردانی تنظیمات
            wp_defer_term_counting(false);
            wp_defer_comment_counting(false);

            // ✅ گزارش نهایی
            $result_message = "محاسبه سود برای {$result['success_count']} محصول انجام شد.";
            if ($result['error_count'] > 0) {
                $result_message .= " ({$result['error_count']} خطا)";
            }
            if ($result['zero_profit_count'] > 0) {
                $result_message .= " - {$result['zero_profit_count']} محصول سود صفر یا منفی دارند";
            }
            $result_message .= " - میانگین سود: " . number_format($result['average_profit']) . " تومان";

            Vendor_Logger::log_success(
                0,
                'profit_calculation_completed',
                null,
                "Profit calculation completed: {$result['success_count']}/{$total} products"
            );

            wp_send_json_success($result_message);

        } catch (Exception $e) {
            // ✅ بازگردانی تنظیمات در صورت خطا
            wp_defer_term_counting(false);
            wp_defer_comment_counting(false);
            
            Vendor_Logger::log_error('Profit calculation failed: ' . $e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * ✅ دریافت ID محصولات بهینه‌شده
     */
    private function get_product_ids_optimized($category_ids) {
        global $wpdb;
        
        $category_placeholders = implode(',', array_fill(0, count($category_ids), '%d'));
        
        $sql = $wpdb->prepare("
            SELECT DISTINCT p.ID 
            FROM {$wpdb->posts} p 
            INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id 
            WHERE p.post_type = 'product' 
            AND p.post_status = 'publish' 
            AND tr.term_taxonomy_id IN ($category_placeholders)
            ORDER BY p.ID ASC
        ", $category_ids);
        
        return $wpdb->get_col($sql);
    }

    /**
     * ✅ پردازش دسته‌ای برای مدیریت حافظه
     */
    private function process_in_batches($product_ids, $total) {
        $success_count = 0;
        $zero_profit_count = 0;
        $error_count = 0;
        $total_profit = 0;
        $total_batches = ceil($total / self::$batch_size);

        Vendor_Logger::log_info("Processing {$total} products in {$total_batches} batches");

        foreach (array_chunk($product_ids, self::$batch_size) as $batch_index => $batch) {
            $batch_number = $batch_index + 1;
            
            Vendor_Logger::log_info("Processing batch {$batch_number}/{$total_batches}");

            $batch_result = $this->process_batch($batch);
            $success_count += $batch_result['success_count'];
            $zero_profit_count += $batch_result['zero_profit_count'];
            $error_count += $batch_result['error_count'];
            $total_profit += $batch_result['total_profit'];

            // ✅ پاکسازی حافظه بعد از هر batch
            $this->cleanup_memory();

            // ✅ تاخیر کوچک برای کاهش فشار
            if ($batch_number < $total_batches) {
                usleep(100000); // 0.1 ثانیه
            }
        }

        $average_profit = $success_count > 0 ? $total_profit / $success_count : 0;

        return [
            'success_count' => $success_count,
            'zero_profit_count' => $zero_profit_count,
            'error_count' => $error_count,
            'total_profit' => $total_profit,
            'average_profit' => $average_profit
        ];
    }

    /**
     * ✅ پردازش یک batch
     */
    private function process_batch($product_ids) {
        $success_count = 0;
        $zero_profit_count = 0;
        $error_count = 0;
        $total_profit = 0;

        foreach ($product_ids as $product_id) {
            try {
                $regular_price = floatval(get_post_meta($product_id, '_regular_price', true));
                $seller_price = floatval(get_post_meta($product_id, '_seller_list_price', true));
                
                // رد کردن محصولات بدون قیمت
                if ($regular_price <= 0 && $seller_price <= 0) {
                    continue;
                }
                
                $profit = $regular_price - $seller_price;
                
                // ذخیره سود
                $saved = update_post_meta($product_id, '_sale_profit', $profit);
                
                if ($saved !== false) {
                    $success_count++;
                    $total_profit += $profit;
                    
                    if ($profit <= 0) {
                        $zero_profit_count++;
                    }
                } else {
                    $error_count++;
                    Vendor_Logger::log_error("Failed to save profit for product ID: {$product_id}");
                }

            } catch (Exception $e) {
                $error_count++;
                Vendor_Logger::log_error("Batch processing failed for product {$product_id}: " . $e->getMessage());
            }

            // پاکسازی حافظه هر چند محصول
            if ($success_count % self::$memory_cleanup_interval === 0) {
                wp_cache_flush();
            }
        }

        return [
            'success_count' => $success_count,
            'zero_profit_count' => $zero_profit_count,
            'error_count' => $error_count,
            'total_profit' => $total_profit
        ];
    }

    /**
     * ✅ پاکسازی حافظه
     */
    private function cleanup_memory() {
        wp_cache_flush();
        gc_collect_cycles();
        
        if (isset($GLOBALS['wpdb']->queries)) {
            $GLOBALS['wpdb']->queries = [];
        }
    }
}

new Sale_Profit_Calculator();