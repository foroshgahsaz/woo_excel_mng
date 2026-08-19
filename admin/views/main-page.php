<?php
/**
 * صفحه اصلی افزونه با تب‌بندی
 */

if (!defined('ABSPATH')) {
    exit;
}

// تابع کمکی برای آیکون تب‌ها (باید قبل از استفاده تعریف شود)
if (!function_exists('woo_excel_mng_get_tab_icon')) {
    function woo_excel_mng_get_tab_icon($tab_key) {
        $icons = array(
            'dashboard' => 'dashicons-dashboard',
            'products' => 'dashicons-products',
            'shipping' => 'dashicons-cart',
            'formulas' => 'dashicons-calculator',
            'settings' => 'dashicons-admin-settings',
        );
        return isset($icons[$tab_key]) ? $icons[$tab_key] : 'dashicons-admin-generic';
    }
}
?>
<div class="wrap woo-excel-mng-wrap">
    <h1 class="woo-excel-mng-title">
        <span class="dashicons dashicons-store"></span>
        <?php _e('مدیریت فروشگاه', 'woo-excel-mng'); ?>
    </h1>
    
    <div class="woo-excel-mng-tabs-wrapper">
        <nav class="woo-excel-mng-tabs">
            <?php foreach ($tabs as $tab_key => $tab_label): ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=woo-excel-mng' . ($tab_key !== 'dashboard' ? '-' . $tab_key : '') . '&tab=' . $tab_key)); ?>" 
                   class="woo-excel-mng-tab <?php echo $current_tab === $tab_key ? 'active' : ''; ?>"
                   data-tab="<?php echo esc_attr($tab_key); ?>">
                    <span class="tab-icon dashicons <?php echo woo_excel_mng_get_tab_icon($tab_key); ?>"></span>
                    <span class="tab-label"><?php echo esc_html($tab_label); ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
        
        <div class="woo-excel-mng-tab-content">
            <?php
            switch ($current_tab) {
                case 'dashboard':
                    include WOO_EXCEL_MNG_PLUGIN_DIR . 'admin/views/dashboard-tab.php';
                    break;
                case 'products':
                    include WOO_EXCEL_MNG_PLUGIN_DIR . 'admin/views/products-tab.php';
                    break;
                case 'shipping':
                    include WOO_EXCEL_MNG_PLUGIN_DIR . 'admin/views/shipping-tab.php';
                    break;
                case 'formulas':
                    include WOO_EXCEL_MNG_PLUGIN_DIR . 'admin/views/formulas-tab.php';
                    break;
                case 'settings':
                    include WOO_EXCEL_MNG_PLUGIN_DIR . 'admin/views/settings-tab.php';
                    break;
            }
            ?>
        </div>
    </div>
</div>

