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

// دریافت لیست محصولاتی که متراژ برای آن‌ها غیرفعال است
$disabled_meterage_products = get_option('woo_excel_mng_disable_meterage_products', array());
if (!is_array($disabled_meterage_products)) {
    $disabled_meterage_products = array();
}



$variable_products = wc_get_products(array(
    'limit'  => -1,        // بدون محدودیت - همه محصولات
    'status' => 'publish',
    'type'   => 'variable', // فقط محصولات متغیر
    'return' => 'objects',
));

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

        <!-- بخش جدید: غیرفعال کردن متراژ برای محصولات خاص -->
        <div class="settings-section">
            <h3><?php _e('محصولات بدون متراژ (فقط تعداد در فرمول)', 'woo-excel-mng'); ?></h3>
            <p class="description"><?php _e('محصولاتی که تیک می‌زنید: فیلد متراژ نمایش داده نمی‌شود، در سبد ستون متراژ ندارند، و در فرمول متغیر {meter} برابر ۱ در نظر گرفته می‌شود.', 'woo-excel-mng'); ?></p>

            <div class="disable-meterage-products-list" style="margin-top: 15px; max-height: 400px; overflow-y: auto; border: 1px solid #ccd0d4; padding: 10px; background: #fff;">
                <?php if (!empty($variable_products)): ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th width="50"><input type="checkbox" id="select-all-products"></th>
                                <th><?php _e('شناسه محصول', 'woo-excel-mng'); ?></th>
                                <th><?php _e('نام محصول', 'woo-excel-mng'); ?></th>
                                <th><?php _e('وضعیت فرمول', 'woo-excel-mng'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($variable_products as $product):
                                $product_id = $product->get_id();
                                $product_name = $product->get_name();
                                $has_formula = (bool) Woo_Excel_Mng_Formulas::get_product_formula($product_id);
                                $is_disabled = in_array($product_id, $disabled_meterage_products);
                            ?>
                                <tr>
                                    <td>
                                        <input type="checkbox"
                                            name="disable_meterage_products[]"
                                            value="<?php echo esc_attr($product_id); ?>"
                                            <?php checked($is_disabled, true); ?>
                                            class="product-checkbox">
                                    </td>
                                    <td><?php echo esc_html($product_id); ?></td>
                                    <td>
                                        <strong><?php echo esc_html($product_name); ?></strong>
                                        <?php if (!$has_formula): ?>
                                            <span class="notice-text" style="color: #999; font-size: 11px; display: block;">
                                                <?php _e('(بدون فرمول)', 'woo-excel-mng'); ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($has_formula): ?>
                                            <span class="status-badge status-success" style="background: #d4edda; color: #155724; padding: 3px 8px; border-radius: 3px; font-size: 12px;">
                                                <?php _e('دارای فرمول', 'woo-excel-mng'); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="status-badge status-warning" style="background: #fff3cd; color: #856404; padding: 3px 8px; border-radius: 3px; font-size: 12px;">
                                                <?php _e('بدون فرمول', 'woo-excel-mng'); ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p><?php _e('هیچ محصول متغیری یافت نشد.', 'woo-excel-mng'); ?></p>
                <?php endif; ?>
            </div>
            <p class="description" style="margin-top: 10px;">
                <?php _e('فقط برای محصولات دارای فرمول معنا دارد. بقیه محصولات از قبل فیلد متراژ ندارند.', 'woo-excel-mng'); ?>
            </p>
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
            <li><?php _e('با تیک زدن هر محصول، متراژ در صفحه محصول، سبد و محاسبه قیمت/حمل غیرفعال می‌شود.', 'woo-excel-mng'); ?></li>
        </ul>
    </div>
</div>

<script type="text/javascript">
    jQuery(document).ready(function($) {
        $('#select-all-products').on('change', function() {
            $('.product-checkbox').prop('checked', $(this).prop('checked'));
        });
    });
</script>

<style>
    .disable-meterage-products-list .notice-text {
        font-size: 11px;
        color: #999;
        margin-top: 3px;
    }

    .disable-meterage-products-list table {
        border-collapse: collapse;
    }

    .disable-meterage-products-list th,
    .disable-meterage-products-list td {
        vertical-align: middle;
    }
</style>