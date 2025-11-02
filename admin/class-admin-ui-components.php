<?php
if (!defined('ABSPATH')) exit;

class Vendor_UI_Components {
    
    /**
     * رندر فیلتر برند محصولات - نسخه پیشرفته با چند انتخابی و جستجو
     */
    public static function render_brand_filter($selected_brands = [], $name = 'product_brand', $options = []) {
        $defaults = [
            'multiple' => true, // فعال کردن چند انتخابی
            'searchable' => true, // فعال کردن جستجو
            'show_all_option' => true,
            'all_option_text' => 'همه برندها',
            'placeholder' => 'انتخاب برندها...',
            'class' => '',
            'style' => 'min-width: 300px;',
            'required' => false
        ];
        
        $options = wp_parse_args($options, $defaults);
        
        $brands = get_terms([
            'taxonomy' => 'product_brand',
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC'
        ]);
        
        // اگر multiple فعال است و selected_brands آرایه نیست، تبدیل به آرایه کنیم
        if ($options['multiple'] && !is_array($selected_brands)) {
            $selected_brands = $selected_brands === 'all' ? [] : [$selected_brands];
        }
        
        wp_enqueue_script('jquery');
        wp_enqueue_script('select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js', ['jquery'], '4.0.13');
        wp_enqueue_style('select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css', [], '4.0.13');
        ?>
        
        <select 
            name="<?php echo esc_attr($name); ?><?php echo $options['multiple'] ? '[]' : ''; ?>" 
            id="<?php echo esc_attr($name); ?>" 
            class="vendor-brand-filter <?php echo esc_attr($options['class']); ?>"
            style="<?php echo esc_attr($options['style']); ?>"
            <?php echo $options['multiple'] ? 'multiple' : ''; ?>
            <?php echo $options['required'] ? 'required' : ''; ?>
            data-placeholder="<?php echo esc_attr($options['placeholder']); ?>"
        >
            <?php if (!$options['multiple'] && $options['show_all_option']): ?>
                <option value="all" <?php selected(in_array('all', $selected_brands) || empty($selected_brands)); ?>>
                    <?php echo esc_html($options['all_option_text']); ?>
                </option>
            <?php endif; ?>
            
            <?php if (!empty($brands)): ?>
                <?php foreach ($brands as $brand): ?>
                    <option value="<?php echo $brand->term_id; ?>" 
                        <?php echo $options['multiple'] ? 
                            (in_array($brand->term_id, $selected_brands) ? 'selected' : '') : 
                            selected($selected_brands[0] ?? '', $brand->term_id); ?>>
                        <?php echo esc_html($brand->name); ?> 
                        (<?php echo $brand->count; ?>)
                    </option>
                <?php endforeach; ?>
            <?php else: ?>
                <option value="">هیچ برندی یافت نشد</option>
            <?php endif; ?>
        </select>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#<?php echo esc_js($name); ?>').select2({
                <?php if ($options['searchable']): ?>
                placeholder: '<?php echo esc_js($options['placeholder']); ?>',
                allowClear: true,
                <?php endif; ?>
                <?php if ($options['multiple']): ?>
                closeOnSelect: false,
                <?php endif; ?>
                language: {
                    noResults: function() {
                        return "نتیجه‌ای یافت نشد";
                    },
                    searching: function() {
                        return "در حال جستجو...";
                    }
                }
            });
        });
        </script>
        
        <style>
        .select2-container--default .select2-selection--multiple .select2-selection__choice {
            background-color: #0073aa;
            border-color: #006291;
            color: white;
        }
        .select2-container--default .select2-results__option--highlighted[aria-selected] {
            background-color: #0073aa;
        }
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
        .select2-container--default .select2-selection--multiple .select2-selection__clear {
            cursor: pointer;
            float: left !important;
            font-weight: bold;
            margin: 5px !important;
            padding: 5px;
            background: red;
            border-radius: 25%;
            width: 10px;
            height: 10px;
            color: white;
            position: relative;
            top: 8px;
            text-align: center;
            line-height: 8px;
        }
        .select2-container--default[dir="rtl"] .select2-selection--multiple .select2-selection__choice {
            margin: 7px;
        }
        </style>
        <?php
    }
    
    /**
     * رندر ساده‌تر برای استفاده سریع (تک انتخابی)
     */
    public static function render_simple_brand_filter($selected_brand = 'all', $name = 'product_brand') {
        return self::render_brand_filter([$selected_brand], $name, [
            'multiple' => false,
            'searchable' => false,
            'show_all_option' => true,
            'all_option_text' => 'همه برندها'
        ]);
    }
    
    /**
     * دریافت برندهای انتخاب شده از درخواست
     */
    public static function get_selected_brands_from_request($name = 'product_brand') {
        if (isset($_POST[$name])) {
            if (is_array($_POST[$name])) {
                return array_map('intval', $_POST[$name]);
            } else {
                return $_POST[$name] === 'all' ? [] : [intval($_POST[$name])];
            }
        }
        return [];
    }
}