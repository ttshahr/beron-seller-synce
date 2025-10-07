<?php
if (!defined('ABSPATH')) exit;

/**
 * افزودن فیلدهای اختصاصی برای کاربران hamkar و seller
 */
class Define_Hamkar_And_Seller_Meta {

    public function __construct() {
        add_action('show_user_profile', [$this, 'render_user_fields']);
        add_action('edit_user_profile', [$this, 'render_user_fields']);
        add_action('personal_options_update', [$this, 'save_user_fields']);
        add_action('edit_user_profile_update', [$this, 'save_user_fields']);
    }

    public function render_user_fields($user) {
        // بررسی نقش کاربر
        $roles = (array) $user->roles;
        if (!in_array('hamkar', $roles) && !in_array('seller', $roles)) {
            return;
        }

        $fields = [
            'vendor_website_url'              => 'آدرس سایت فروشنده',
            'vendor_consumer_key'             => 'کلید مصرف‌کننده (Consumer Key)',
            'vendor_consumer_secret'          => 'رمز مصرف‌کننده (Consumer Secret)',
            'vendor_currency'                 => 'واحد پول فروشنده',
            'vendor_stock_type'               => 'نوع موجودی',
            'vendor_cooperation_price_meta_key' => 'کلید متای قیمت همکاری در سایت فروشنده',
            'vendor_price_conversion_percent' => 'درصد تبدیل قیمت همکاری (مثلاً 10 برای 10٪)'
        ];

        $currency = get_user_meta($user->ID, 'vendor_currency', true);
        $stock_type = get_user_meta($user->ID, 'vendor_stock_type', true);
        ?>
        <h3>اطلاعات فروشنده / همکار</h3>
        <table class="form-table">
            <?php foreach ($fields as $key => $label): ?>
                <tr>
                    <th><label for="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th>
                    <td>
                        <?php if ($key === 'vendor_currency'): ?>
                            <select name="<?php echo $key; ?>" id="<?php echo $key; ?>">
                                <option value="toman" <?php selected($currency, 'toman'); ?>>تومان</option>
                                <option value="rial" <?php selected($currency, 'rial'); ?>>ریال</option>
                            </select>

                        <?php elseif ($key === 'vendor_stock_type'): ?>
                            <select name="<?php echo $key; ?>" id="<?php echo $key; ?>">
                                <option value="managed" <?php selected($stock_type, 'managed'); ?>>مدیریت‌شده (عددی)</option>
                                <option value="unmanaged" <?php selected($stock_type, 'unmanaged'); ?>>مدیریت‌نشده (موجود/ناموجود)</option>
                            </select>

                        <?php elseif ($key === 'vendor_price_conversion_percent'): ?>
                            <input type="number" name="<?php echo $key; ?>" id="<?php echo $key; ?>" 
                                   value="<?php echo esc_attr(get_user_meta($user->ID, $key, true)); ?>" 
                                   step="0.01" min="0" style="width:100px;"> %

                        <?php else: ?>
                            <input type="text" name="<?php echo $key; ?>" id="<?php echo $key; ?>" 
                                   value="<?php echo esc_attr(get_user_meta($user->ID, $key, true)); ?>" 
                                   class="regular-text">
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
        <?php
    }

    public function save_user_fields($user_id) {
        if (!current_user_can('edit_user', $user_id)) {
            return false;
        }

        $keys = [
            'vendor_website_url',
            'vendor_consumer_key',
            'vendor_consumer_secret',
            'vendor_currency',
            'vendor_stock_type',
            'vendor_cooperation_price_meta_key',
            'vendor_price_conversion_percent'
        ];

        foreach ($keys as $key) {
            if (isset($_POST[$key])) {
                update_user_meta($user_id, $key, sanitize_text_field($_POST[$key]));
            }
        }
    }
}

new Define_Hamkar_And_Seller_Meta();
