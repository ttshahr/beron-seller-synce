<?php
if (!defined('ABSPATH')) exit;

class Admin_Ajax {
    
    public function __construct() {
        add_action('wp_ajax_get_stock_report', [$this, 'ajax_get_stock_report']);
        add_action('admin_footer', [$this, 'add_admin_footer_scripts']);
    }
    
    public function ajax_get_stock_report() {
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('دسترسی غیرمجاز');
        }
        
        $vendor_id = intval($_POST['vendor_id']);
        $cat_id = sanitize_text_field($_POST['product_cat']);
        
        try {
            $report = Vendor_Stock_Updater_Optimized::get_stock_update_report($vendor_id, $cat_id);
            wp_send_json_success($report);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    public function add_admin_footer_scripts() {
        $current_screen = get_current_screen();
        if (!$current_screen || strpos($current_screen->id, 'vendor-sync') === false) {
            return;
        }
        ?>
        <script>
        jQuery(document).ready(function($) {
            // پر کردن خودکار همه فیلدها (بدون رفرش صفحه)
            $('#stock_vendor_id').on('change', function() {
                var vendorId = $(this).val();
                
                // فقط مقادیر دیگر فیلدها را پر می‌کند
                $('#assign_vendor_id').val(vendorId);
                $('#assign_smart_vendor_id').val(vendorId);
                
                // رفرش صفحه حذف شد - فقط کنسول لاگ می‌گذارد
                console.log('فروشنده انتخاب شد: ' + vendorId + ' - صفحه رفرش نمی‌شود');
            });
            
            // پیش‌نمایش بروزرسانی
            $('#preview-stock-update').on('click', function() {
                var vendorId = $('#stock_vendor_id').val();
                var catId = $('#stock_product_cat').val();
                
                if (!vendorId) {
                    alert('لطفا فروشنده را انتخاب کنید');
                    return;
                }
                
                var $button = $(this);
                $button.prop('disabled', true).text('در حال بررسی...');
                
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'get_stock_report',
                        vendor_id: vendorId,
                        product_cat: catId
                    },
                    success: function(response) {
                        $button.prop('disabled', false).text('🔍 پیش‌نمایش بروزرسانی');
                        
                        if (response.success) {
                            var report = response.data;
                            var html = '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin-bottom: 15px;">';
                            html += '<div style="text-align: center; padding: 10px; background: #fff; border-radius: 5px; border: 1px solid #e1e1e1;">';
                            html += '<div style="font-size: 18px; font-weight: bold; color: #1e40af;">' + report.total_local_products + '</div>';
                            html += '<div>محصولات محلی</div>';
                            html += '</div>';
                            
                            html += '<div style="text-align: center; padding: 10px; background: #fff; border-radius: 5px; border: 1px solid #e1e1e1;">';
                            html += '<div style="font-size: 18px; font-weight: bold; color: #dc2626;">' + report.total_vendor_products + '</div>';
                            html += '<div>محصولات فروشنده</div>';
                            html += '</div>';
                            
                            html += '<div style="text-align: center; padding: 10px; background: #fff; border-radius: 5px; border: 1px solid #e1e1e1;">';
                            html += '<div style="font-size: 18px; font-weight: bold; color: #15803d;">' + report.matched_products + '</div>';
                            html += '<div>قابل بروزرسانی</div>';
                            html += '</div>';
                            html += '</div>';
                            
                            if (report.matched_products > 0) {
                                html += '<p style="color: #15803d; font-weight: bold;">✅ ' + report.matched_products + ' محصول برای بروزرسانی موجودی پیدا شد.</p>';
                            } else {
                                html += '<p style="color: #dc2626; font-weight: bold;">❌ هیچ محصولی برای بروزرسانی پیدا نشد. لطفا ابتدا محصولات را اختصاص دهید.</p>';
                            }
                            
                            $('#stock-report-content').html(html);
                            $('#stock-report-container').show();
                        } else {
                            alert('خطا در دریافت گزارش: ' + response.data);
                        }
                    },
                    error: function() {
                        $button.prop('disabled', false).text('🔍 پیش‌نمایش بروزرسانی');
                        alert('خطا در ارتباط با سرور');
                    }
                });
            });
            
            $('#hide-report').on('click', function() {
                $('#stock-report-container').hide();
            });
        });
        </script>
        
        <style>
        #vendor-progress-container {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
            margin: 20px 0;
        }
        .vendor-progress-bar {
            width: 100%;
            height: 20px;
            background: #f0f0f1;
            border-radius: 10px;
            overflow: hidden;
            margin: 10px 0;
        }
        .vendor-progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #00a32a, #00ba37);
            border-radius: 10px;
            transition: width 0.3s ease;
            width: 0%;
        }
        .vendor-progress-info {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
            color: #3c434a;
        }
        </style>
        <?php
    }
}

new Admin_Ajax();