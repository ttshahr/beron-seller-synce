<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Sale_Profit_Calculator {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('wp_ajax_calculate_sale_profit', [$this, 'calculate_profit_ajax']);
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
                $('#progress-text').text('');
                $('#calc-result').html('');

                $.post(ajaxurl, {
                    action: 'calculate_sale_profit',
                    categories: selected
                }, function(response){
                    if(response.success){
                        $('#progress-bar').css('width','100%').text('100%');
                        $('#progress-text').text('محاسبه کامل شد.');
                        $('#calc-result').html('<pre>'+response.data+'</pre>');
                    } else {
                        $('#calc-result').html('<pre>خطا: '+response.data+'</pre>');
                    }
                });
            });
        });
        </script>
        <?php
    }

    public function calculate_profit_ajax() {
        if ( ! current_user_can('manage_woocommerce') ) {
            wp_send_json_error('دسترسی غیرمجاز');
        }

        $categories = isset($_POST['categories']) ? array_map('intval', $_POST['categories']) : [];
        if(empty($categories)){
            wp_send_json_error('هیچ دسته‌ای انتخاب نشده است.');
        }

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
        $success_count = 0;

        foreach($products as $product){
            $regular_price = floatval( get_post_meta($product->ID, '_regular_price', true) );
            $seller_price = floatval( get_post_meta($product->ID, '_seller_list_price', true) );
            $profit = $regular_price - $seller_price;
            update_post_meta($product->ID, '_sale_profit', $profit);
            $success_count++;
        }

        wp_send_json_success("محاسبه سود برای {$success_count} محصول انجام شد.");
    }
}

new Sale_Profit_Calculator();
