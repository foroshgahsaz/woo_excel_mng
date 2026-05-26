<?php
/**
 * تب داشبورد
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// دریافت آمار
$total_products = wp_count_posts('product')->publish;
$total_variations = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'product_variation' AND post_status = 'publish'");
$total_cities = $wpdb->get_var("SELECT COUNT(DISTINCT origin_city) + COUNT(DISTINCT destination_city) FROM {$wpdb->prefix}woo_excel_shipping_routes");
$total_formulas = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}woo_excel_pricing_formulas");

// آخرین لاگ‌ها
$recent_logs = $wpdb->get_results(
    "SELECT * FROM {$wpdb->prefix}woo_excel_import_logs ORDER BY created_at DESC LIMIT 5",
    ARRAY_A
);
?>

<div class="woo-excel-mng-dashboard">
    <div class="dashboard-header">
        <h2><?php _e('داشبورد', 'woo-excel-mng'); ?></h2>
        <p class="description"><?php _e('نمای کلی از وضعیت افزونه و آخرین فعالیت‌ها', 'woo-excel-mng'); ?></p>
    </div>
    
    <!-- کارت‌های آمار -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">
                <span class="dashicons dashicons-products"></span>
            </div>
            <div class="stat-content">
                <h3><?php echo number_format_i18n($total_products); ?></h3>
                <p><?php _e('محصولات کل', 'woo-excel-mng'); ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <span class="dashicons dashicons-admin-settings"></span>
            </div>
            <div class="stat-content">
                <h3><?php echo number_format_i18n($total_variations); ?></h3>
                <p><?php _e('ویژگی‌های محصول', 'woo-excel-mng'); ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <span class="dashicons dashicons-location"></span>
            </div>
            <div class="stat-content">
                <h3><?php echo number_format_i18n($total_cities); ?></h3>
                <p><?php _e('شهرهای فعال', 'woo-excel-mng'); ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <span class="dashicons dashicons-calculator"></span>
            </div>
            <div class="stat-content">
                <h3><?php echo number_format_i18n($total_formulas); ?></h3>
                <p><?php _e('فرمول‌های تعریف شده', 'woo-excel-mng'); ?></p>
            </div>
        </div>
    </div>
    
    <!-- آخرین لاگ‌ها -->
    <div class="dashboard-section">
        <h3><?php _e('آخرین عملیات واردسازی', 'woo-excel-mng'); ?></h3>
        <div class="logs-table-wrapper">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('نوع', 'woo-excel-mng'); ?></th>
                        <th><?php _e('نام فایل', 'woo-excel-mng'); ?></th>
                        <th><?php _e('وضعیت', 'woo-excel-mng'); ?></th>
                        <th><?php _e('تعداد رکورد', 'woo-excel-mng'); ?></th>
                        <th><?php _e('تاریخ', 'woo-excel-mng'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($recent_logs)): ?>
                        <?php foreach ($recent_logs as $log): ?>
                            <tr>
                                <td>
                                    <span class="log-type log-type-<?php echo esc_attr($log['import_type']); ?>">
                                        <?php echo $log['import_type'] === 'products' ? __('محصولات', 'woo-excel-mng') : __('حمل‌ونقل', 'woo-excel-mng'); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html($log['file_name']); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo esc_attr($log['status']); ?>">
                                        <?php echo $log['status'] === 'success' ? __('موفق', 'woo-excel-mng') : __('خطا', 'woo-excel-mng'); ?>
                                    </span>
                                </td>
                                <td><?php echo number_format_i18n($log['records_count']); ?></td>
                                <td><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($log['created_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="no-items"><?php _e('هیچ لاگی یافت نشد.', 'woo-excel-mng'); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

