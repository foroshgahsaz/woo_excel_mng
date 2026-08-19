<?php

/**
 * تب مشاهده لاگ‌ها
 */

if (!defined('ABSPATH')) {
    exit;
}

$selected_file = isset($_GET['log_file']) ? sanitize_file_name(wp_unslash($_GET['log_file'])) : '';
$selected_level = isset($_GET['log_level']) ? sanitize_text_field(wp_unslash($_GET['log_level'])) : '';
$log_files = Woo_Excel_Mng_Logger::get_log_files();
$entries = Woo_Excel_Mng_Logger::get_recent_entries(300, $selected_level, $selected_file);
$current_file = $selected_file !== '' ? $selected_file : ('wem-' . current_time('Y-m-d') . '.log');
?>

<div class="woo-excel-mng-logs">
    <div class="section-header">
        <h2><?php _e('لاگ خطاها و رویدادها', 'woo-excel-mng'); ?></h2>
        <p class="description">
            <?php _e('تمام خطاها و رویدادهای مهم افزونه در این بخش ثبت می‌شوند. فایل‌ها در uploads/woo-excel-logs ذخیره می‌شوند.', 'woo-excel-mng'); ?>
        </p>
    </div>

    <?php if (isset($_GET['logs_cleared'])) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php _e('لاگ‌ها با موفقیت پاک شدند.', 'woo-excel-mng'); ?></p>
        </div>
    <?php endif; ?>

    <div class="wem-logs-toolbar">
        <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" class="wem-logs-filter-form">
            <input type="hidden" name="page" value="woo-excel-mng-logs" />
            <input type="hidden" name="tab" value="logs" />

            <label for="log_file"><?php _e('فایل:', 'woo-excel-mng'); ?></label>
            <select name="log_file" id="log_file">
                <option value=""><?php _e('امروز', 'woo-excel-mng'); ?></option>
                <?php foreach ($log_files as $file) : ?>
                    <option value="<?php echo esc_attr($file); ?>" <?php selected($selected_file, $file); ?>>
                        <?php echo esc_html($file); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="log_level"><?php _e('سطح:', 'woo-excel-mng'); ?></label>
            <select name="log_level" id="log_level">
                <option value=""><?php _e('همه', 'woo-excel-mng'); ?></option>
                <option value="ERROR" <?php selected($selected_level, 'ERROR'); ?>>ERROR</option>
                <option value="WARNING" <?php selected($selected_level, 'WARNING'); ?>>WARNING</option>
                <option value="INFO" <?php selected($selected_level, 'INFO'); ?>>INFO</option>
                <option value="DEBUG" <?php selected($selected_level, 'DEBUG'); ?>>DEBUG</option>
            </select>

            <button type="submit" class="button"><?php _e('اعمال فیلتر', 'woo-excel-mng'); ?></button>
        </form>

        <div class="wem-logs-actions">
            <a
                class="button button-secondary"
                href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=woo-excel-mng-logs&tab=logs&wem_download_log=1&log_file=' . rawurlencode($current_file)), 'wem_download_log')); ?>"
            >
                <?php _e('دانلود لاگ', 'woo-excel-mng'); ?>
            </a>

            <button type="button" class="button button-secondary" id="wem-clear-logs">
                <?php _e('پاک کردن همه لاگ‌ها', 'woo-excel-mng'); ?>
            </button>

            <button type="button" class="button" onclick="window.location.reload();">
                <?php _e('بروزرسانی', 'woo-excel-mng'); ?>
            </button>
        </div>
    </div>

    <div class="wem-logs-meta">
        <span><?php printf(__('نمایش %d ردیف', 'woo-excel-mng'), count($entries)); ?></span>
        <span><?php printf(__('فایل جاری: %s', 'woo-excel-mng'), esc_html($current_file)); ?></span>
    </div>

    <div class="wem-logs-viewer">
        <?php if (empty($entries)) : ?>
            <div class="wem-logs-empty">
                <?php _e('هنوز لاگی ثبت نشده است.', 'woo-excel-mng'); ?>
            </div>
        <?php else : ?>
            <pre><?php
                foreach ($entries as $entry) {
                    echo esc_html($entry) . "\n";
                }
            ?></pre>
        <?php endif; ?>
    </div>
</div>
