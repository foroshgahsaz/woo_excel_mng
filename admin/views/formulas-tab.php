<?php
/**
 * تب مدیریت فرمول‌های قیمت‌گذاری
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$table_name = $wpdb->prefix . 'woo_excel_pricing_formulas';

// دریافت تمام فرمول‌ها
$formulas = $wpdb->get_results(
    "SELECT f.*, p.post_title as product_name 
     FROM $table_name f 
     LEFT JOIN {$wpdb->posts} p ON f.product_id = p.ID 
     ORDER BY f.created_at DESC",
    ARRAY_A
);

// دریافت لیست محصولات
$products = get_posts(array(
    'post_type' => 'product',
    'posts_per_page' => -1,
    'post_status' => 'publish'
));
?>

<div class="woo-excel-mng-formulas">
    <div class="section-header">
        <h2><?php _e('مدیریت فرمول‌های قیمت‌گذاری', 'woo-excel-mng'); ?></h2>
        <p class="description"><?php _e('تعریف فرمول‌های پویا برای محاسبه قیمت محصولات بر اساس متراژ', 'woo-excel-mng'); ?></p>
    </div>
    
    <!-- افزودن فرمول جدید -->
    <div class="add-formula-section">
        <h3><?php _e('افزودن فرمول جدید', 'woo-excel-mng'); ?></h3>
        <form method="post" action="" class="formula-form" id="add-formula-form">
            <?php wp_nonce_field('woo_excel_mng_save_formula', 'woo_excel_mng_nonce'); ?>
            <input type="hidden" name="action" value="save_formula">
            <input type="hidden" name="formula_id" id="formula_id" value="">
            
            <div class="form-row">
                <div class="form-group">
                    <label for="formula_product_id">
                        <?php _e('محصول مرتبط', 'woo-excel-mng'); ?> <span class="required">*</span>
                    </label>
                    <select name="formula_product_id" id="formula_product_id" required class="regular-text">
                        <option value=""><?php _e('-- انتخاب محصول --', 'woo-excel-mng'); ?></option>
                        <?php foreach ($products as $product): ?>
                            <option value="<?php echo esc_attr($product->ID); ?>">
                                <?php echo esc_html($product->post_title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="formula_text">
                        <?php _e('فرمول', 'woo-excel-mng'); ?> <span class="required">*</span>
                    </label>
                    <textarea name="formula_text" 
                              id="formula_text" 
                              rows="3" 
                              class="large-text code" 
                              placeholder="({length} * {thickness} * {meter} * 0.8) + {base_price}"
                              required></textarea>
                    <p class="description">
                        <?php _e('متغیرهای قابل استفاده:', 'woo-excel-mng'); ?>
                        <code>{length}</code>, <code>{thickness}</code>, <code>{meter}</code>, 
                        <code>{base_price}</code>, <code>{weight}</code>
                    </p>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="button button-primary">
                    <span class="dashicons dashicons-saved"></span>
                    <?php _e('ذخیره فرمول', 'woo-excel-mng'); ?>
                </button>
                <button type="button" class="button cancel-edit" style="display:none;">
                    <?php _e('انصراف', 'woo-excel-mng'); ?>
                </button>
            </div>
        </form>
    </div>
    
    <!-- لیست فرمول‌ها -->
    <div class="formulas-list-section">
        <h3><?php _e('فرمول‌های تعریف شده', 'woo-excel-mng'); ?></h3>
        <div class="formulas-table-wrapper">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('محصول', 'woo-excel-mng'); ?></th>
                        <th><?php _e('فرمول', 'woo-excel-mng'); ?></th>
                        <th><?php _e('تاریخ ایجاد', 'woo-excel-mng'); ?></th>
                        <th><?php _e('عملیات', 'woo-excel-mng'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($formulas)): ?>
                        <?php foreach ($formulas as $formula): ?>
                            <tr data-formula-id="<?php echo esc_attr($formula['id']); ?>">
                                <td>
                                    <strong><?php echo esc_html($formula['product_name'] ?: __('محصول حذف شده', 'woo-excel-mng')); ?></strong>
                                </td>
                                <td>
                                    <code class="formula-code"><?php echo esc_html($formula['formula']); ?></code>
                                </td>
                                <td>
                                    <?php echo date_i18n(get_option('date_format'), strtotime($formula['created_at'])); ?>
                                </td>
                                <td>
                                    <button type="button" 
                                            class="button button-small edit-formula" 
                                            data-formula-id="<?php echo esc_attr($formula['id']); ?>"
                                            data-product-id="<?php echo esc_attr($formula['product_id']); ?>"
                                            data-formula="<?php echo esc_attr($formula['formula']); ?>">
                                        <?php _e('ویرایش', 'woo-excel-mng'); ?>
                                    </button>
                                    <button type="button" 
                                            class="button button-small button-link-delete delete-formula" 
                                            data-formula-id="<?php echo esc_attr($formula['id']); ?>">
                                        <?php _e('حذف', 'woo-excel-mng'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="no-items">
                                <?php _e('هیچ فرمولی تعریف نشده است.', 'woo-excel-mng'); ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

