<?php
/**
 * تب مدیریت حمل‌ونقل
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$table_name = $wpdb->prefix . 'woo_excel_shipping_routes';

// دریافت تمام مسیرها
$routes = $wpdb->get_results("SELECT * FROM $table_name ORDER BY origin_city, destination_city", ARRAY_A);
?>

<div class="woo-excel-mng-shipping">
    <div class="section-header">
        <h2><?php _e('مدیریت حمل‌ونقل', 'woo-excel-mng'); ?></h2>
        <p class="description"><?php _e('مدیریت نرخ‌های حمل‌ونقل بر اساس شهر مبدا و مقصد', 'woo-excel-mng'); ?></p>
    </div>
    
    <!-- آپلود فایل Excel -->
    <div class="upload-section">
        <div class="upload-box">
            <h3><?php _e('آپلود فایل Excel شهرها', 'woo-excel-mng'); ?></h3>
            <p class="help-text">
                <?php _e('فرمت فایل باید شامل ستون‌های زیر باشد:', 'woo-excel-mng'); ?>
            </p>
            <ul class="excel-format-list">
                <li><strong><?php _e('شهر مبدا', 'woo-excel-mng'); ?></strong></li>
                <li><strong><?php _e('شهر مقصد', 'woo-excel-mng'); ?></strong></li>
                <li><strong><?php _e('پیکان (تومان)', 'woo-excel-mng'); ?></strong></li>
                <li><strong><?php _e('مزدا (تومان)', 'woo-excel-mng'); ?></strong></li>
                <li><strong><?php _e('نیسان (تومان)', 'woo-excel-mng'); ?></strong></li>
            </ul>
            
            <form method="post" action="" enctype="multipart/form-data" class="upload-form">
                <?php wp_nonce_field('woo_excel_mng_upload_shipping', 'woo_excel_mng_nonce'); ?>
                <input type="hidden" name="action" value="upload_shipping">
                
                <div class="form-group">
                    <label for="shipping_file" class="file-label">
                        <span class="dashicons dashicons-upload"></span>
                        <span class="label-text"><?php _e('انتخاب فایل Excel', 'woo-excel-mng'); ?></span>
                        <input type="file" name="shipping_file" id="shipping_file" accept=".xlsx,.xls" required>
                    </label>
                    <span class="file-name" id="shipping_file_name"></span>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="button button-primary button-large">
                        <span class="dashicons dashicons-upload"></span>
                        <?php _e('آپلود و پردازش', 'woo-excel-mng'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- جدول مسیرها -->
    <div class="routes-section">
        <h3><?php _e('مسیرهای حمل‌ونقل', 'woo-excel-mng'); ?></h3>
        <div class="routes-table-wrapper">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('شهر مبدا', 'woo-excel-mng'); ?></th>
                        <th><?php _e('شهر مقصد', 'woo-excel-mng'); ?></th>
                        <th><?php _e('پیکان (تومان)', 'woo-excel-mng'); ?></th>
                        <th><?php _e('مزدا (تومان)', 'woo-excel-mng'); ?></th>
                        <th><?php _e('نیسان (تومان)', 'woo-excel-mng'); ?></th>
                        <th><?php _e('وضعیت', 'woo-excel-mng'); ?></th>
                        <th><?php _e('عملیات', 'woo-excel-mng'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($routes)): ?>
                        <?php foreach ($routes as $route): ?>
                            <tr data-route-id="<?php echo esc_attr($route['id']); ?>">
                                <td><?php echo esc_html($route['origin_city']); ?></td>
                                <td><?php echo esc_html($route['destination_city']); ?></td>
                                <td>
                                    <input type="number" 
                                           class="route-price" 
                                           data-field="peykan_price" 
                                           value="<?php echo esc_attr(woo_excel_mng_format_number($route['peykan_price'], 2, '.', '')); ?>" 
                                           min="0" 
                                           step="1000">
                                </td>
                                <td>
                                    <input type="number" 
                                           class="route-price" 
                                           data-field="mazda_price" 
                                           value="<?php echo esc_attr(woo_excel_mng_format_number($route['mazda_price'], 2, '.', '')); ?>" 
                                           min="0" 
                                           step="1000">
                                </td>
                                <td>
                                    <input type="number" 
                                           class="route-price" 
                                           data-field="nissan_price" 
                                           value="<?php echo esc_attr(woo_excel_mng_format_number($route['nissan_price'], 2, '.', '')); ?>" 
                                           min="0" 
                                           step="1000">
                                </td>
                                <td>
                                    <label class="toggle-switch">
                                        <input type="checkbox" 
                                               class="route-active" 
                                               <?php checked($route['is_active'], 1); ?>>
                                        <span class="slider"></span>
                                    </label>
                                </td>
                                <td>
                                    <button type="button" class="button button-small save-route" data-route-id="<?php echo esc_attr($route['id']); ?>">
                                        <?php _e('ذخیره', 'woo-excel-mng'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="no-items">
                                <?php _e('هیچ مسیری تعریف نشده است. لطفاً فایل Excel را آپلود کنید.', 'woo-excel-mng'); ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

