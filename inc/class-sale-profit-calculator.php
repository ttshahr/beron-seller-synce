<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Sale_Profit_Calculator {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('wp_ajax_calculate_sale_profit', [$this, 'calculate_profit_ajax']);
        
        // ثبت لاگ هنگام راه‌اندازی
        Vendor_Logger::log_info('Sale Profit Calculator initialized');
    }

    public function add_menu() {
        add_menu_page(
            'محاسبه سود فروش',
            'محاسبه سود فروش',
            'manage_woocommerce',
            'sale-profit-calculator',
            [$this, 'render_page'],
            'dashicons-chart-line',
            57
        );
    }

    public function render_page() {
        $categories = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
        
        Vendor_Logger::log_info('Profit calculator page loaded');
        ?>
        <div class="wrap">
            <h1>محاسبه سود فروش محصولات</h1>
            <p>یک یا چند دسته را انتخاب کنید تا سود فروش محصولات محاسبه شود.</p>

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
            </div>

            <div id="calc-result" style="margin-top:20px;"></div>
            
            <!-- لاگ‌های اخیر -->
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

                $('#progress-container').show();
                $('#progress-bar').css('width','0%').text('0%');
                $('#progress-text').text('در حال شروع...');
                $('#calc-result').html('');

                // نمایش دسته‌های انتخاب شده
                var categoryNames = [];
                $('select[name="categories[]"] option:selected').each(function() {
                    categoryNames.push($(this).text());
                });
                
                $('#progress-text').text('در حال محاسبه سود برای دسته‌ها: ' + categoryNames.join(', '));

                $.post(ajaxurl, {
                    action: 'calculate_sale_profit',
                    categories: selected
                }, function(response){
                    if(response.success){
                        $('#progress-bar').css('width','100%').text('100%');
                        $('#progress-text').text('محاسبه کامل شد.');
                        $('#calc-result').html('<div class="notice notice-success"><p>' + response.data + '</p></div>');
                        
                        // رفرش بخش لاگ‌ها
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        $('#progress-bar').css('width','100%').text('100%').css('background','#dc2626');
                        $('#progress-text').text('خطا در محاسبه');
                        $('#calc-result').html('<div class="notice notice-error"><p>خطا: ' + response.data + '</p></div>');
                    }
                }).fail(function() {
                    $('#progress-bar').css('width','100%').text('100%').css('background','#dc2626');
                    $('#progress-text').text('خطا در ارتباط با سرور');
                    $('#calc-result').html('<div class="notice notice-error"><p>خطا در ارتباط با سرور</p></div>');
                });
            });
        });
        </script>
        
        <style>
        .profit-log-entry {
            padding: 8px 12px;
            margin: 5px 0;
            border-radius: 4px;
            font-family: monospace;
            font-size: 12px;
        }
        .profit-log-success {
            background: #f0fdf4;
            border-left: 4px solid #22c55e;
        }
        .profit-log-error {
            background: #fef2f2;
            border-left: 4px solid #ef4444;
        }
        .profit-log-info {
            background: #f0f9ff;
            border-left: 4px solid #0ea5e9;
        }
        </style>
        <?php
    }
    
    /**
     * نمایش لاگ‌های اخیر
     */
    private function render_recent_logs() {
        $recent_logs = Vendor_Logger::get_recent_logs('general', 10);
        $profit_logs = [];
        
        // فیلتر کردن لاگ‌های مربوط به محاسبه سود
        foreach ($recent_logs as $log) {
            if (strpos($log, 'profit_calc') !== false || 
                strpos($log, 'سود') !== false ||
                strpos($log, 'Sale Profit') !== false) {
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
            if (strpos($log, 'SUCCESS') !== false) {
                $log_class = 'profit-log-success';
            } elseif (strpos($log, 'ERROR') !== false) {
                $log_class = 'profit-log-error';
            }
            
            echo '<div class="profit-log-entry ' . $log_class . '">' . esc_html($log) . '</div>';
        }
        echo '</div>';
        
        echo '<p style="margin-top: 10px; font-size: 12px; color: #666;">';
        echo 'آخرین ' . count($profit_logs) . ' رویداد مرتبط با محاسبه سود';
        echo '</p>';
    }

    public function calculate_profit_ajax() {
        if ( ! current_user_can('manage_woocommerce') ) {
            Vendor_Logger::log_error('Unauthorized access attempt to profit calculator');
            wp_send_json_error('دسترسی غیرمجاز');
        }

        $categories = isset($_POST['categories']) ? array_map('intval', $_POST['categories']) : [];
        
        if(empty($categories)){
            Vendor_Logger::log_error('No categories selected for profit calculation');
            wp_send_json_error('هیچ دسته‌ای انتخاب نشده است.');
        }

        // ثبت اطلاعات دسته‌های انتخاب شده
        $category_names = [];
        foreach ($categories as $cat_id) {
            $category = get_term($cat_id, 'product_cat');
            if ($category && !is_wp_error($category)) {
                $category_names[] = $category->name;
            }
        }
        
        Vendor_Logger::log_info(
            'Starting profit calculation for categories: ' . implode(', ', $category_names)
        );

        $args = [
            'post_type' => 'product',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'tax_query' => [
                [
                    'taxonomy' => 'product_cat',
                    'field' => 'term_id',
                    'terms' => $categories,
                ]
            ]
        ];

        $products = get_posts($args);
        $total = count($products);
        
        Vendor_Logger::log_info("Found {$total} products for profit calculation");

        if ($total === 0) {
            Vendor_Logger::log_warning('No products found for selected categories');
            wp_send_json_error('هیچ محصولی در دسته‌های انتخاب شده یافت نشد.');
        }

        $success_count = 0;
        $zero_profit_count = 0;
        $error_count = 0;
        $total_profit = 0;

        foreach($products as $index => $product){
            $regular_price = floatval( get_post_meta($product->ID, '_regular_price', true) );
            $seller_price = floatval( get_post_meta($product->ID, '_seller_list_price', true) );
            
            // محاسبه سود
            $profit = $regular_price - $seller_price;
            
            // ذخیره سود
            $saved = update_post_meta($product->ID, '_sale_profit', $profit);
            
            if ($saved !== false) {
                $success_count++;
                $total_profit += $profit;
                
                if ($profit <= 0) {
                    $zero_profit_count++;
                    Vendor_Logger::log_warning(
                        "Zero or negative profit for product: {$product->post_title}",
                        $product->ID
                    );
                }
                
                // ثبت لاگ برای هر 50 محصول
                if (($index + 1) % 50 === 0) {
                    Vendor_Logger::log_info(
                        "Profit calculation progress: " . ($index + 1) . "/{$total} products processed"
                    );
                }
            } else {
                $error_count++;
                Vendor_Logger::log_error(
                    "Failed to save profit for product: {$product->post_title}",
                    $product->ID
                );
            }
        }

        // گزارش نهایی
        $average_profit = $success_count > 0 ? $total_profit / $success_count : 0;
        
        Vendor_Logger::log_success(
            0,
            'profit_calculation_completed',
            null,
            "Profit calculation completed: {$success_count} successful, {$error_count} errors, " .
            "{$zero_profit_count} zero-profit products, Average profit: " . number_format($average_profit) . " تومان"
        );

        $result_message = "محاسبه سود برای {$success_count} محصول انجام شد.";
        
        if ($error_count > 0) {
            $result_message .= " ({$error_count} خطا)";
        }
        
        if ($zero_profit_count > 0) {
            $result_message .= " - {$zero_profit_count} محصول سود صفر یا منفی دارند";
        }
        
        $result_message .= " - میانگین سود: " . number_format($average_profit) . " تومان";

        wp_send_json_success($result_message);
    }
}

new Sale_Profit_Calculator();