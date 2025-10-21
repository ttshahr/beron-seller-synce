<?php
if (!defined('ABSPATH')) exit;

class Admin_Debug_Pages {
    
    public static function render_debug_page() {
        $vendors = get_users(['role__in' => ['hamkar', 'seller']]);
        $selected_vendor = isset($_GET['vendor_id']) ? intval($_GET['vendor_id']) : 0;
        ?>
        
        <div class="wrap">
            <h1>دیباگ همگام‌سازی فروشندگان</h1>
            
            <div class="card">
                <h2>انتخاب فروشنده برای بررسی</h2>
                <form method="get">
                    <input type="hidden" name="page" value="vendor-sync-debug">
                    <select name="vendor_id" required style="min-width: 300px;">
                        <option value="">-- انتخاب فروشنده --</option>
                        <?php foreach ($vendors as $vendor): ?>
                            <option value="<?php echo $vendor->ID; ?>" <?php selected($selected_vendor, $vendor->ID); ?>>
                                <?php echo esc_html($vendor->display_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php submit_button('بررسی', 'primary'); ?>
                </form>
            </div>
            
            <?php if ($selected_vendor): ?>
                <?php Vendor_Debug_Helper::render_debug_page($selected_vendor); ?>
            <?php endif; ?>
        </div>
        <?php
    }
}