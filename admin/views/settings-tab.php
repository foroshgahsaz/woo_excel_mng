<?php
/**
 * تب تنظیمات
 */

if (!defined('ABSPATH')) {
    exit;
}

$origin_city = get_option('woo_excel_mng_origin_city', 'تهران');
$premium_threshold = get_option('woo_excel_mng_premium_threshold', 65000000);
$shipping_percentage = get_option('woo_excel_mng_shipping_percentage', 2);
$peykan_max_length = get_option('woo_excel_mng_peykan_max_length', 4);
$mazda_max_length = get_option('woo_excel_mng_mazda_max_length', 5);
$nissan_max_length = get_option('woo_excel_mng_nissan_max_length', 6);
$peykan_max_length_display = woo_excel_mng_format_number($peykan_max_length, 2, '.', '');
$mazda_max_length_display = woo_excel_mng_format_number($mazda_max_length, 2, '.', '');
$nissan_max_length_display = woo_excel_mng_format_number($nissan_max_length, 2, '.', '');
$premium_threshold_display = woo_excel_mng_format_number($premium_threshold, 2, '.', '');
$shipping_percentage_display = woo_excel_mng_format_number($shipping_percentage, 2, '.', '');
?>

<div class="woo-excel-mng-settings">
    <div class="section-header">
        <h2><?php _e('تنظیمات افزونه', 'woo-excel-mng'); ?></h2>
        <p class="description"><?php _e('تنظیمات عمومی افزونه', 'woo-excel-mng'); ?></p>
    </div>
    
    <form method="post" action="" class="settings-form">
        <?php wp_nonce_field('woo_excel_mng_save_general_settings', 'woo_excel_mng_nonce'); ?>
        <input type="hidden" name="action" value="save_general_settings">
        
        <div class="settings-section">
            <h3><?php _e('تنظیمات شهر مبدا', 'woo-excel-mng'); ?></h3>
            
            <div class="form-group">
                <label for="woo_excel_mng_origin_city">
                    <?php _e('شهر مبدا', 'woo-excel-mng'); ?>
                </label>
                <input type="text" 
                       name="woo_excel_mng_origin_city" 
                       id="woo_excel_mng_origin_city" 
                       value="<?php echo esc_attr($origin_city); ?>" 
                       class="regular-text"
                       required>
                <p class="description">
                    <?php _e('شهر مبدا برای محاسبه هزینه حمل‌ونقل. این شهر باید در فایل Excel شهرها تعریف شده باشد.', 'woo-excel-mng'); ?>
                </p>
            </div>
        </div>
        
        <div class="settings-section">
            <h3><?php _e('تنظیمات Premium', 'woo-excel-mng'); ?></h3>
            
            <div class="form-group">
                <label for="woo_excel_mng_premium_threshold">
                    <?php _e('آستانه خرید Premium (تومان)', 'woo-excel-mng'); ?>
                </label>
                <input type="number" 
                       name="woo_excel_mng_premium_threshold" 
                       id="woo_excel_mng_premium_threshold" 
                       value="<?php echo esc_attr($premium_threshold_display); ?>" 
                       class="regular-text"
                       min="0"
                       step="1000000"
                       required>
                <p class="description">
                    <?php _e('خریدهای بالای این مبلغ از منطق Premium استفاده می‌کنند. پیش‌فرض: 65,000,000 تومان', 'woo-excel-mng'); ?>
                </p>
            </div>
            
            <div class="form-group">
                <label for="woo_excel_mng_shipping_percentage">
                    <?php _e('درصد فاکتور برای حمل رایگان', 'woo-excel-mng'); ?>
                </label>
                <input type="number" 
                       name="woo_excel_mng_shipping_percentage" 
                       id="woo_excel_mng_shipping_percentage" 
                       value="<?php echo esc_attr($shipping_percentage_display); ?>" 
                       class="regular-text"
                       min="0.1"
                       max="10"
                       step="0.1"
                       required>
                <p class="description">
                    <?php _e('درصدی از مبلغ فاکتور که برای حمل رایگان در نظر گرفته می‌شود. پیش‌فرض: 2%', 'woo-excel-mng'); ?>
                </p>
            </div>
        </div>

        <div class="settings-section">
            <h3><?php _e('حداکثر متراژ سفارش (متر)', 'woo-excel-mng'); ?></h3>

            <div class="form-group">
                <label for="woo_excel_mng_peykan_max_length">
                    <?php _e('پیکان (حداکثر متراژ)', 'woo-excel-mng'); ?>
                </label>
                <input type="number"
                       name="woo_excel_mng_peykan_max_length"
                       id="woo_excel_mng_peykan_max_length"
                       value="<?php echo esc_attr($peykan_max_length_display); ?>"
                       class="regular-text"
                       min="0"
                       step="0.1"
                       required>
                <p class="description"><?php _e('پیش‌فرض: 4 متر', 'woo-excel-mng'); ?></p>
            </div>

            <div class="form-group">
                <label for="woo_excel_mng_mazda_max_length">
                    <?php _e('مزدا (حداکثر متراژ)', 'woo-excel-mng'); ?>
                </label>
                <input type="number"
                       name="woo_excel_mng_mazda_max_length"
                       id="woo_excel_mng_mazda_max_length"
                       value="<?php echo esc_attr($mazda_max_length_display); ?>"
                       class="regular-text"
                       min="0"
                       step="0.1"
                       required>
                <p class="description"><?php _e('پیش‌فرض: 5 متر', 'woo-excel-mng'); ?></p>
            </div>

            <div class="form-group">
                <label for="woo_excel_mng_nissan_max_length">
                    <?php _e('نیسان (حداکثر متراژ)', 'woo-excel-mng'); ?>
                </label>
                <input type="number"
                       name="woo_excel_mng_nissan_max_length"
                       id="woo_excel_mng_nissan_max_length"
                       value="<?php echo esc_attr($nissan_max_length_display); ?>"
                       class="regular-text"
                       min="0"
                       step="0.1"
                       required>
                <p class="description"><?php _e('پیش‌فرض: 6 متر', 'woo-excel-mng'); ?></p>
            </div>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="button button-primary">
                <span class="dashicons dashicons-saved"></span>
                <?php _e('ذخیره تنظیمات', 'woo-excel-mng'); ?>
            </button>
        </div>
    </form>
    
    <div class="info-box">
        <h4><?php _e('نکات مهم:', 'woo-excel-mng'); ?></h4>
        <ul>
            <li><?php _e('شهر مبدا باید دقیقاً همان نامی باشد که در فایل Excel شهرها استفاده شده است.', 'woo-excel-mng'); ?></li>
            <li><?php _e('شهر مقصد توسط کاربر در صفحه تسویه حساب انتخاب می‌شود.', 'woo-excel-mng'); ?></li>
            <li><?php _e('اگر شهر مبدا در فایل Excel تعریف نشده باشد، هزینه حمل‌ونقل محاسبه نمی‌شود.', 'woo-excel-mng'); ?></li>
        </ul>
    </div>
</div>

