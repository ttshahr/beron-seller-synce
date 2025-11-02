<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Sale_Profit_Calculator {

    private static $batch_size = 100;

    public function __construct() {
        add_action('wp_ajax_calculate_sale_profit', [$this, 'calculate_profit_ajax']);
        add_action('wp_ajax_get_profit_progress', [$this, 'get_progress_ajax']);
    }

    public function render_page() {
        // فراخوانی استایل‌های Select2
        self::enqueue_select2_styles();
        ?>
        <div class="wrap">
            <h1>محاسبه سود فروش محصولات</h1>
            <p>⚠️ <strong>توجه:</strong> برای تعداد زیاد محصولات، محاسبه زمان‌بر است.</p>
            
            <form id="profit-form">
                <table class="form-table">
                    <tr>
                        <th>انتخاب برندها</th>
                        <td>
                            <?php 
                            Vendor_UI_Components::render_brand_filter([], 'product_brand', [
                                'placeholder' => 'برندها را انتخاب کنید...'
                            ]); 
                            ?>
                            <p class="description">می‌توانید چند برند انتخاب کنید. در صورت عدم انتخاب، همه برندها پردازش می‌شوند.</p>
                        </td>
                    </tr>
                </table>
                <button type="button" class="button button-primary" id="start-calc">شروع محاسبه</button>
            </form>

            <div id="progress-container" style="margin-top:20px; display:none;">
                <h3>در حال محاسبه...</h3>
                <div style="width:100%; background:#ddd; border-radius:5px; position:relative;">
                    <div id="progress-bar" style="width:0%; height:25px; background:#4caf50; text-align:center; color:#fff; line-height:25px; transition: width 0.3s ease; border-radius:5px;">
                        <span id="progress-percentage">0%</span>
                    </div>
                </div>
                <p id="progress-text">در حال آماده‌سازی...</p>
                <p id="progress-details" style="font-size:12px; color:#666;"></p>
                <p id="progress-time" style="font-size:11px; color:#888;"></p>
            </div>

            <div id="calc-result" style="margin-top:20px;"></div>
            
            <div class="card" style="margin-top: 20px;">
                <h3>📊 لاگ‌های اخیر محاسبه سود</h3>
                <?php $this->render_recent_logs(); ?>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($){
            var progressInterval;
            var currentJobId = '';
            var startTime = null;

            function updateProgressBar(percentage, message, details, current, total) {
                $('#progress-bar').css('width', percentage + '%');
                $('#progress-percentage').text(percentage + '%');
                $('#progress-text').text(message);
                
                if(details) {
                    $('#progress-details').text(details);
                }
                
                if(current && total) {
                    $('#progress-details').text('پردازش ' + current + ' از ' + total + ' محصول');
                }
                
                // محاسبه زمان سپری شده
                if(startTime) {
                    var elapsed = Math.floor((Date.now() - startTime) / 1000);
                    var minutes = Math.floor(elapsed / 60);
                    var seconds = elapsed % 60;
                    $('#progress-time').text('زمان سپری شده: ' + minutes + ' دقیقه و ' + seconds + ' ثانیه');
                }
            }

            function startProgressPolling(jobId) {
                currentJobId = jobId;
                startTime = Date.now();
                
                progressInterval = setInterval(function() {
                    $.post(ajaxurl, {
                        action: 'get_profit_progress',
                        job_id: jobId,
                        _wpnonce: '<?php echo wp_create_nonce("get_progress_nonce"); ?>'
                    }, function(response) {
                        if(response.success) {
                            var progress = response.data;
                            updateProgressBar(
                                progress.percentage, 
                                progress.message, 
                                progress.details,
                                progress.current,
                                progress.total
                            );

                            // اگر کار تمام شد
                            if(progress.status === 'completed' || progress.status === 'error') {
                                clearInterval(progressInterval);
                                $('#progress-bar').css('background', progress.status === 'completed' ? '#4caf50' : '#dc2626');
                                
                                if(progress.status === 'completed') {
                                    $('#calc-result').html('<div class="notice notice-success"><p>' + progress.details + '</p></div>');
                                    setTimeout(function() {
                                        location.reload();
                                    }, 3000);
                                } else {
                                    $('#calc-result').html('<div class="notice notice-error"><p>' + progress.details + '</p></div>');
                                }
                            }
                        } else {
                            console.error('Progress error:', response.data);
                        }
                    }).fail(function(xhr, status, error) {
                        console.error('Progress polling failed:', error);
                    });
                }, 1500); // بررسی هر 1.5 ثانیه
            }

            $('#start-calc').click(function(){
                var selectedBrands = $('select[name="product_brand[]"]').val();
                
                if(!selectedBrands || selectedBrands.length===0){
                    alert('حداقل یک برند انتخاب کنید.');
                    return;
                }

                if(selectedBrands.length > 5 && !confirm('⚠️ شما ' + selectedBrands.length + ' برند انتخاب کرده‌اید. ادامه می‌دهید؟')) {
                    return;
                }

                // پاکسازی interval قبلی
                if(progressInterval) {
                    clearInterval(progressInterval);
                }

                $('#progress-container').show();
                $('#calc-result').html('');
                updateProgressBar(0, 'در حال شروع محاسبه...', '');

                // ایجاد Job ID ساده
                var jobId = 'profit_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
                currentJobId = jobId;

                // شروع پردازش
                processBatch(jobId, selectedBrands, 0);
            });

            function processBatch(jobId, brandIds, offset) {
                $.post(ajaxurl, {
                    action: 'calculate_sale_profit',
                    job_id: jobId,
                    brand_ids: brandIds,
                    offset: offset,
                    batch_size: <?php echo self::$batch_size; ?>,
                    _wpnonce: '<?php echo wp_create_nonce("calculate_profit_nonce"); ?>'
                }, function(response){
                    if(response.success) {
                        var data = response.data;
                        
                        // آپدیت پیشرفت
                        updateProgressBar(
                            data.percentage,
                            data.message,
                            data.details,
                            data.current,
                            data.total
                        );

                        // اگر batch بعدی وجود دارد
                        if(data.has_more) {
                            setTimeout(function() {
                                processBatch(jobId, brandIds, data.next_offset);
                            }, 100);
                        } else {
                            // کار تمام شد
                            clearInterval(progressInterval);
                            $('#progress-bar').css('width', '100%').text('100%');
                            $('#progress-text').text('محاسبه کامل شد');
                            $('#calc-result').html('<div class="notice notice-success"><p>' + data.final_message + '</p></div>');
                            setTimeout(function() {
                                location.reload();
                            }, 3000);
                        }
                    } else {
                        updateProgressBar(100, 'خطا در محاسبه', '');
                        $('#progress-bar').css('background','#dc2626');
                        $('#calc-result').html('<div class="notice notice-error"><p>خطا: ' + response.data + '</p></div>');
                    }
                }).fail(function(xhr, status, error) {
                    console.error('Batch processing failed:', error);
                    updateProgressBar(100, 'خطا در ارتباط با سرور', '');
                    $('#progress-bar').css('background','#dc2626');
                    $('#calc-result').html('<div class="notice notice-error"><p>خطای ارتباط با سرور - لطفا دوباره تلاش کنید</p></div>');
                });
            }

            // پاکسازی هنگام بستن صفحه
            $(window).on('beforeunload', function() {
                if(progressInterval) {
                    clearInterval(progressInterval);
                }
            });
        });
        </script>
        
        <style>
        .profit-log-entry { padding: 8px 12px; margin: 5px 0; border-radius: 4px; font-family: monospace; font-size: 12px; }
        .profit-log-success { background: #f0fdf4; border-left: 4px solid #22c55e; }
        .profit-log-error { background: #fef2f2; border-left: 4px solid #ef4444; }
        .profit-log-info { background: #f0f9ff; border-left: 4px solid #0ea5e9; }
        #progress-bar { transition: width 0.5s ease-in-out; }
        
        /* استایل‌های Select2 */
        .select2-container--default[dir="rtl"] .select2-selection--multiple .select2-selection__choice {
            margin-left: 5px;
            margin-right: auto;
            color: green;
            background: #f2fff2;
            border: 1px solid green;
            padding: 5px 17px;
        }
        
        .select2-container--default[dir="rtl"] .select2-selection--multiple .select2-selection__choice__remove {
            margin-left: 2px;
            margin-right: auto;
            color: red;
        }
        </style>
        <?php
    }

    /**
     * فراخوانی استایل‌های Select2
     */
    private static function enqueue_select2_styles() {
        echo '
        <style>
        .select2-container--default[dir="rtl"] .select2-selection--multiple .select2-selection__choice {
            margin-left: 5px;
            margin-right: auto;
            color: green;
            background: #f2fff2;
            border: 1px solid green;
            padding: 5px 17px;
        }
        
        .select2-container--default[dir="rtl"] .select2-selection--multiple .select2-selection__choice__remove {
            margin-left: 2px;
            margin-right: auto;
            color: red;
        }
        </style>
        ';
    }

    /**
     * هندلر AJAX اصلی - پردازش batch به batch
     */
    public function calculate_profit_ajax() {
        try {
            if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'calculate_profit_nonce')) {
                throw new Exception('خطای امنیتی');
            }

            if (!current_user_can('manage_woocommerce')) {
                throw new Exception('دسترسی غیرمجاز');
            }

            $brand_ids = isset($_POST['brand_ids']) ? array_map('intval', $_POST['brand_ids']) : [];
            $job_id = sanitize_text_field($_POST['job_id']);
            $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
            $batch_size = isset($_POST['batch_size']) ? intval($_POST['batch_size']) : self::$batch_size;

            // ✅ تنظیمات بهینه
            set_time_limit(300);
            ini_set('memory_limit', '512M');
            wp_suspend_cache_addition(true);

            // اگر اولین batch است، محصولات را بگیر
            if ($offset === 0) {
                $all_product_ids = $this->get_product_ids_by_brands($brand_ids);
                $total_products = count($all_product_ids);
                
                // ذخیره در transient برای batchهای بعدی
                set_transient('profit_calc_' . $job_id, $all_product_ids, HOUR_IN_SECONDS);
                set_transient('profit_calc_total_' . $job_id, $total_products, HOUR_IN_SECONDS);
                
                $brands_text = empty($brand_ids) ? 'همه برندها' : implode(', ', $brand_ids);
                Vendor_Logger::log_info("Starting profit calculation for {$total_products} products, brands: {$brands_text}, job: {$job_id}");
            } else {
                // دریافت از transient
                $all_product_ids = get_transient('profit_calc_' . $job_id);
                $total_products = get_transient('profit_calc_total_' . $job_id);
                
                if (!$all_product_ids) {
                    throw new Exception('داده‌های محاسبه از دست رفته‌اند. لطفا دوباره شروع کنید.');
                }
            }

            if ($total_products === 0) {
                throw new Exception('هیچ محصولی در برندهای انتخاب شده یافت نشد.');
            }

            // محاسبه batch فعلی
            $current_batch = array_slice($all_product_ids, $offset, $batch_size);
            $batch_result = $this->process_batch($current_batch);
            
            $processed_so_far = $offset + count($current_batch);
            $percentage = min(99, ($processed_so_far / $total_products) * 100);
            $has_more = $processed_so_far < $total_products;
            $next_offset = $has_more ? $processed_so_far : 0;

            // اگر کار تمام شد
            if (!$has_more) {
                $final_message = "محاسبه سود برای {$batch_result['total_success']} محصول انجام شد.";
                if ($batch_result['total_errors'] > 0) {
                    $final_message .= " ({$batch_result['total_errors']} خطا)";
                }
                
                // پاکسازی transient
                delete_transient('profit_calc_' . $job_id);
                delete_transient('profit_calc_total_' . $job_id);
                
                Vendor_Logger::log_success(
                    0,
                    'profit_calculation_completed',
                    null,
                    "Profit calculation completed: {$batch_result['total_success']}/{$total_products} products"
                );
            }

            wp_send_json_success([
                'percentage' => round($percentage),
                'message' => $has_more ? 'در حال محاسبه سود...' : 'محاسبه کامل شد',
                'details' => $has_more ? 
                    "دسته " . (floor($offset/$batch_size) + 1) . " در حال پردازش" : 
                    "{$batch_result['total_success']} محصول بروزرسانی شدند",
                'current' => $processed_so_far,
                'total' => $total_products,
                'has_more' => $has_more,
                'next_offset' => $next_offset,
                'final_message' => $final_message ?? '',
                'total_success' => $batch_result['total_success'],
                'total_errors' => $batch_result['total_errors']
            ]);

        } catch (Exception $e) {
            // پاکسازی transient در صورت خطا
            if (isset($job_id)) {
                delete_transient('profit_calc_' . $job_id);
                delete_transient('profit_calc_total_' . $job_id);
            }
            
            Vendor_Logger::log_error('Profit calculation failed: ' . $e->getMessage());
            wp_send_json_error($e->getMessage());
        } finally {
            wp_suspend_cache_addition(false);
        }
    }

    /**
     * هندلر برای دریافت پیشرفت (برای compatibility)
     */
    public function get_progress_ajax() {
        wp_send_json_success([
            'status' => 'processing',
            'percentage' => 50,
            'message' => 'در حال محاسبه...',
            'current' => 0,
            'total' => 0,
            'details' => 'سیستم در حال کار است'
        ]);
    }

    /**
     * دریافت محصولات بر اساس برندها
     */
    private function get_product_ids_by_brands($brand_ids) {
        global $wpdb;
        
        $sql = "SELECT DISTINCT p.ID FROM {$wpdb->posts} p 
                WHERE p.post_type = 'product' AND p.post_status = 'publish'";
        
        $params = [];
        
        // فیلتر بر اساس چند برند
        if (!empty($brand_ids)) {
            $placeholders = implode(',', array_fill(0, count($brand_ids), '%d'));
            $sql .= " AND p.ID IN (
                SELECT DISTINCT tr.object_id 
                FROM {$wpdb->term_relationships} tr 
                WHERE tr.term_taxonomy_id IN ({$placeholders})
            )";
            $params = $brand_ids;
        }
        
        $sql .= " ORDER BY p.ID ASC";
        
        if (!empty($params)) {
            return $wpdb->get_col($wpdb->prepare($sql, $params));
        } else {
            return $wpdb->get_col($sql);
        }
    }

    private function process_batch($product_ids) {
        $success_count = 0;
        $error_count = 0;
        $total_success = 0;
        $total_errors = 0;

        foreach ($product_ids as $product_id) {
            try {
                $regular_price = floatval(get_post_meta($product_id, '_regular_price', true));
                $seller_price = floatval(get_post_meta($product_id, '_seller_list_price', true));
                
                if ($regular_price <= 0 && $seller_price <= 0) continue;
                
                $profit = $regular_price - $seller_price;
                $saved = update_post_meta($product_id, '_sale_profit', $profit);
                
                if ($saved !== false) {
                    $success_count++;
                } else {
                    $error_count++;
                }

            } catch (Exception $e) {
                $error_count++;
            }
        }

        // پاکسازی حافظه
        wp_cache_flush();
        if ($success_count % 50 === 0) {
            gc_collect_cycles();
        }

        return [
            'success_count' => $success_count,
            'error_count' => $error_count,
            'total_success' => $success_count,
            'total_errors' => $error_count
        ];
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
}

new Sale_Profit_Calculator();