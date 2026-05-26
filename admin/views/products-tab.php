<?php

/**
 * تب مدیریت محصولات
 */

if (!defined('ABSPATH')) {
    exit;
}

$import_notice = get_transient('wem_import_notice_' . get_current_user_id());
if ($import_notice) {
    delete_transient('wem_import_notice_' . get_current_user_id());
}
?>

<div class="woo-excel-mng-products">
    <div class="section-header">
        <h2><?php _e('مدیریت محصولات', 'woo-excel-mng'); ?></h2>
        <p class="description"><?php _e('آپلود فایل Excel برای ایجاد یا به‌روزرسانی واریانت‌های محصولات متغیر (پردازش صف‌بندی)', 'woo-excel-mng'); ?></p>
    </div>

    <?php if (!empty($import_notice['message'])) : ?>
        <div class="notice notice-<?php echo esc_attr($import_notice['type']); ?> is-dismissible">
            <p><?php echo esc_html($import_notice['message']); ?></p>
        </div>
    <?php endif; ?>

    <div class="upload-section">
        <div class="upload-box">
            <h3><?php _e('آپلود فایل Excel محصولات', 'woo-excel-mng'); ?></h3>
            <p class="help-text"><?php _e('فرمت فایل باید شامل ستون‌های زیر باشد:', 'woo-excel-mng'); ?></p>
            <ul class="excel-format-list">
                <li><strong><?php _e('طول', 'woo-excel-mng'); ?></strong> - <?php _e('مقدار ویژگی طول', 'woo-excel-mng'); ?></li>
                <li><strong><?php _e('رنگ', 'woo-excel-mng'); ?></strong> - <?php _e('مقدار ویژگی رنگ', 'woo-excel-mng'); ?></li>
                <li><strong><?php _e('ضخامت', 'woo-excel-mng'); ?></strong> - <?php _e('مقدار ویژگی ضخامت', 'woo-excel-mng'); ?></li>
                <li><strong><?php _e('وزن (کیلوگرم)', 'woo-excel-mng'); ?></strong> - <?php _e('وزن محصول برای محاسبه حمل‌ونقل', 'woo-excel-mng'); ?></li>
                <li><strong><?php _e('قیمت پایه', 'woo-excel-mng'); ?></strong> - <?php _e('قیمت پایه محصول', 'woo-excel-mng'); ?></li>
            </ul>

            <div id="wem-upload-area" style="display: flex; justify-content: center; flex-direction: column; align-items: center;">
                <div style="width: 100%; max-width: 600px; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="products_file"><?php _e('انتخاب فایل اکسل:', 'woo-excel-mng'); ?></label></th>
                            <td>
                                <input type="file" name="products_file" id="products_file" accept=".xlsx,.xls" />
                                <p id="products_file_name" class="file-name" style="margin-top:8px;color:#666;"></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="selected_products"><?php _e('انتخاب محصولات:', 'woo-excel-mng'); ?></label></th>
                            <td>
                                <select name="selected_products[]" id="selected_products" class="wem-select2" multiple="multiple" style="width: 100%; min-height: 150px;" required>
                                    <?php
                                    $all_products = wc_get_products(array(
                                        'limit'  => -1,
                                        'status' => 'publish',
                                        'type'   => 'variable',
                                        'return' => 'objects',
                                    ));

                                    foreach ($all_products as $product) {
                                        echo '<option value="' . esc_attr($product->get_id()) . '">' . esc_html($product->get_name()) . ' (ID: ' . $product->get_id() . ')</option>';
                                    }
                                    ?>
                                </select>
                                <p class="description"><?php _e('فقط محصولات متغیر نمایش داده می‌شوند. هر محصول به‌صورت جدا در صف پردازش می‌شود.', 'woo-excel-mng'); ?></p>
                            </td>
                        </tr>
                    </table>

                    <p class="submit" style="text-align: center;">
                        <button type="button" id="wem-start-import" class="button button-primary" disabled style="padding: 10px 30px; font-size: 16px;">
                            <?php _e('شروع پردازش (صف)', 'woo-excel-mng'); ?>
                        </button>
                    </p>
                </div>
            </div>

            <div id="wem-progress-area" style="display:none;">
                <div class="wem-progress-header">
                    <span id="wem-progress-icon" class="wem-progress-icon">⏳</span>
                    <span id="wem-progress-label"><?php _e('در حال آماده‌سازی...', 'woo-excel-mng'); ?></span>
                </div>

                <div id="wem-current-product" class="wem-current-product" style="display:none;">
                    <strong><?php _e('محصول جاری:', 'woo-excel-mng'); ?></strong>
                    <span id="wem-current-product-name">—</span>
                    <span class="wem-variation-hint" id="wem-variation-hint"></span>
                </div>

                <div class="wem-progress-bar-wrap">
                    <div class="wem-progress-bar-bg">
                        <div id="wem-progress-fill" class="wem-progress-bar-fill" style="width:0%;">
                            <span id="wem-progress-percent" class="wem-progress-percent">۰٪</span>
                        </div>
                    </div>
                </div>

                <div class="wem-progress-stats">
                    <div class="wem-stat-group">
                        <div class="wem-stat-box wem-stat-done">
                            <span id="wem-stat-done" class="wem-stat-num">۰</span>
                            <span class="wem-stat-lbl"><?php _e('محصول انجام‌شده', 'woo-excel-mng'); ?></span>
                        </div>
                        <div class="wem-stat-box wem-stat-remaining-box">
                            <span id="wem-stat-remaining" class="wem-stat-num">—</span>
                            <span class="wem-stat-lbl"><?php _e('باقی‌مانده', 'woo-excel-mng'); ?></span>
                        </div>
                        <div class="wem-stat-box wem-stat-total-box">
                            <span id="wem-stat-total" class="wem-stat-num">۰</span>
                            <span class="wem-stat-lbl"><?php _e('کل محصولات', 'woo-excel-mng'); ?></span>
                        </div>
                    </div>
                </div>

                <div id="wem-mini-log" class="wem-mini-log"></div>
            </div>

            <div id="wem-result-area"></div>

            <style>
                .wem-select2 {
                    border: 1px solid #ccc;
                    border-radius: 4px;
                    padding: 5px;
                    background: #f9f9f9;
                }
                .form-table th {
                    width: 200px;
                    text-align: right;
                    padding: 20px 10px;
                }
                .wem-current-product {
                    background: #f0f6fc;
                    border: 1px solid #c3d9ed;
                    border-radius: 6px;
                    padding: 12px 16px;
                    margin-bottom: 14px;
                    font-size: 14px;
                }
                .wem-current-product #wem-current-product-name {
                    font-weight: 700;
                    color: #2271b1;
                }
                .wem-variation-hint {
                    display: block;
                    margin-top: 6px;
                    font-size: 12px;
                    color: #666;
                }
            </style>
        </div>
    </div>

    <div class="info-box">
        <h4><?php _e('نکات مهم:', 'woo-excel-mng'); ?></h4>
        <ul>
            <li><?php _e('برای جلوگیری از خطای 500، هر محصول جداگانه در صف پردازش می‌شود و وضعیت آن نمایش داده می‌شود.', 'woo-excel-mng'); ?></li>
            <li><?php _e('اگر برای یک Variation قیمت تعریف نشده باشد، به صورت "ناموجود" تنظیم می‌شود.', 'woo-excel-mng'); ?></li>
            <li><?php _e('محصولات ایجاد شده در بخش "محصولات → همه محصولات" قابل مدیریت هستند.', 'woo-excel-mng'); ?></li>
            <li><?php _e('وزن برای محاسبه هزینه حمل‌ونقل استفاده می‌شود.', 'woo-excel-mng'); ?></li>
        </ul>
    </div>
</div>
