<?php
if (!defined('ABSPATH')) exit;

class Vendor_Debug_Helper {
    
    /**
     * ایجاد صفحه دیباگ برای بررسی مشکل
     */
    public static function render_debug_page($vendor_id) {
        $vendor = get_userdata($vendor_id);
        $report = Vendor_Product_Assigner::get_vendor_products_report($vendor_id);
        $mismatches = Vendor_Product_Assigner::check_sku_mismatch($vendor_id);
        ?>
        
        <div class="wrap">
            <h1>گزارش دیباگ - فروشنده: <?php echo esc_html($vendor->display_name); ?></h1>
            
            <div class="card">
                <h2>📊 آمار کلی</h2>
                <ul>
                    <li>تعداد کل محصولات فروشنده: <strong><?php echo $report['total_vendor_products']; ?></strong></li>
                    <li>محصولات دارای SKU: <strong><?php echo $report['products_with_sku']; ?></strong></li>
                    <li>محصولات تطبیق داده شده: <strong><?php echo count($report['matched_products']); ?></strong></li>
                    <li>عدم تطابق SKU: <strong><?php echo count($mismatches); ?></strong></li>
                </ul>
            </div>
            
            <?php if (!empty($report['matched_products'])): ?>
            <div class="card">
                <h2>✅ محصولات تطبیق داده شده</h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>SKU فروشنده</th>
                            <th>ID محصول محلی</th>
                            <th>عنوان محصول</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($report['matched_products'] as $match): ?>
                        <tr>
                            <td><?php echo esc_html($match['vendor_sku']); ?></td>
                            <td><?php echo $match['local_product_id']; ?></td>
                            <td><?php echo esc_html($match['local_product_title']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($mismatches)): ?>
            <div class="card">
                <h2>❌ عدم تطابق SKU</h2>
                <p>این SKUها در فروشنده وجود دارند اما در سایت شما محصولی با این SKU یافت نشد:</p>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>SKU فروشنده</th>
                            <th>نام محصول در فروشنده</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($mismatches as $mismatch): ?>
                        <tr>
                            <td><code><?php echo esc_html($mismatch['vendor_sku']); ?></code></td>
                            <td><?php echo esc_html($mismatch['vendor_product_name']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            
            <div class="card">
                <h2>🔧 اقدامات</h2>
                <div style="display: flex; gap: 10px;">
                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                        <input type="hidden" name="action" value="assign_vendor_products">
                        <input type="hidden" name="vendor_id" value="<?php echo $vendor_id; ?>">
                        <?php submit_button('تلاش مجدد برای اختصاص', 'primary'); ?>
                    </form>
                    
                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                        <input type="hidden" name="action" value="debug_vendor_products">
                        <input type="hidden" name="vendor_id" value="<?php echo $vendor_id; ?>">
                        <?php submit_button('بررسی مجدد', 'secondary'); ?>
                    </form>
                </div>
            </div>
        </div>
        
        <?php
    }
}