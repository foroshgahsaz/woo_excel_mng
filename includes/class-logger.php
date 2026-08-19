<?php

/**
 * سیستم لاگ افزونه
 */

if (!defined('ABSPATH')) {
    exit;
}

class Woo_Excel_Mng_Logger
{
    const LEVEL_DEBUG   = 'DEBUG';
    const LEVEL_INFO    = 'INFO';
    const LEVEL_WARNING = 'WARNING';
    const LEVEL_ERROR   = 'ERROR';

    const MAX_FILE_SIZE   = 5242880; // 5MB
    const MAX_LOG_DAYS    = 14;
    const DEFAULT_CHANNEL = 'general';

    /**
     * ثبت پیام در فایل لاگ
     */
    public static function log($level, $message, array $context = array(), $channel = self::DEFAULT_CHANNEL)
    {
        $level   = strtoupper((string) $level);
        $message = is_string($message) ? $message : wp_json_encode($message, JSON_UNESCAPED_UNICODE);

        if ($message === false || $message === null || $message === '') {
            return false;
        }

        $dir = self::get_log_dir();
        if ($dir === '') {
            return false;
        }

        $file_path = self::get_log_file_path();
        if ($file_path === '') {
            return false;
        }

        self::rotate_if_needed($file_path);

        $user_id = get_current_user_id();
        $request = self::get_request_context();

        $entry = sprintf(
            '[%s] [%s] [%s] %s',
            current_time('Y-m-d H:i:s'),
            $level,
            sanitize_key($channel),
            $message
        );

        if ($user_id > 0) {
            $entry .= ' | user_id=' . $user_id;
        }

        if ($request !== '') {
            $entry .= ' | request=' . $request;
        }

        if (!empty($context)) {
            $context_json = wp_json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($context_json !== false) {
                $entry .= ' | context=' . $context_json;
            }
        }

        $entry .= PHP_EOL;

        return (bool) file_put_contents($file_path, $entry, FILE_APPEND | LOCK_EX);
    }

    public static function debug($message, array $context = array(), $channel = self::DEFAULT_CHANNEL)
    {
        return self::log(self::LEVEL_DEBUG, $message, $context, $channel);
    }

    public static function info($message, array $context = array(), $channel = self::DEFAULT_CHANNEL)
    {
        return self::log(self::LEVEL_INFO, $message, $context, $channel);
    }

    public static function warning($message, array $context = array(), $channel = self::DEFAULT_CHANNEL)
    {
        return self::log(self::LEVEL_WARNING, $message, $context, $channel);
    }

    public static function error($message, array $context = array(), $channel = self::DEFAULT_CHANNEL)
    {
        return self::log(self::LEVEL_ERROR, $message, $context, $channel);
    }

    /**
     * ثبت Throwable
     */
    public static function exception(Throwable $exception, array $context = array(), $channel = self::DEFAULT_CHANNEL)
    {
        $context['exception_class'] = get_class($exception);
        $context['file']            = $exception->getFile();
        $context['line']            = $exception->getLine();
        $context['trace']           = $exception->getTraceAsString();

        return self::error($exception->getMessage(), $context, $channel);
    }

    /**
     * ثبت خطای PHP هنگام shutdown
     */
    public static function register_shutdown_handler()
    {
        register_shutdown_function(array(__CLASS__, 'handle_shutdown'));
    }

    public static function handle_shutdown()
    {
        $error = error_get_last();

        if (!$error || !in_array($error['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR), true)) {
            return;
        }

        if (!self::should_log_current_request()) {
            return;
        }

        self::error(
            $error['message'],
            array(
                'type' => $error['type'],
                'file' => $error['file'],
                'line' => $error['line'],
            ),
            'fatal'
        );
    }

    /**
     * خواندن آخرین ردیف‌های لاگ
     */
    public static function get_recent_entries($limit = 200, $level = '', $file_name = '')
    {
        $limit = max(1, min(1000, (int) $limit));
        $file_path = self::resolve_log_file_path($file_name);

        if ($file_path === '' || !file_exists($file_path)) {
            return array();
        }

        $lines = file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return array();
        }

        if ($level !== '') {
            $needle = '[' . strtoupper($level) . ']';
            $lines = array_values(array_filter($lines, function ($line) use ($needle) {
                return strpos($line, $needle) !== false;
            }));
        }

        return array_slice($lines, -$limit);
    }

    /**
     * لیست فایل‌های لاگ
     */
    public static function get_log_files()
    {
        $dir = self::get_log_dir();
        if ($dir === '' || !is_dir($dir)) {
            return array();
        }

        $files = glob(trailingslashit($dir) . 'wem-*.log');
        if ($files === false) {
            return array();
        }

        rsort($files);

        return array_map('basename', $files);
    }

    /**
     * پاکسازی همه لاگ‌ها
     */
    public static function clear_logs()
    {
        $dir = self::get_log_dir();
        if ($dir === '' || !is_dir($dir)) {
            return 0;
        }

        $deleted = 0;
        $files   = glob(trailingslashit($dir) . 'wem-*.log');

        if ($files === false) {
            return 0;
        }

        foreach ($files as $file) {
            if (@unlink($file)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * مسیر فایل لاگ برای دانلود
     */
    public static function get_download_file_path($file_name = '')
    {
        return self::resolve_log_file_path($file_name);
    }

    /**
     * اطمینان از وجود پوشه لاگ
     */
    public static function ensure_log_dir()
    {
        $dir = self::get_log_dir();
        if ($dir === '') {
            return false;
        }

        if (!wp_mkdir_p($dir)) {
            return false;
        }

        self::protect_log_dir($dir);
        self::cleanup_old_logs();

        return true;
    }

    private static function get_log_dir()
    {
        $upload_dir = wp_upload_dir();
        if (!empty($upload_dir['error'])) {
            return '';
        }

        return trailingslashit($upload_dir['basedir']) . 'woo-excel-logs';
    }

    private static function get_log_file_path()
    {
        $dir = self::get_log_dir();
        if ($dir === '') {
            return '';
        }

        self::ensure_log_dir();

        return trailingslashit($dir) . 'wem-' . current_time('Y-m-d') . '.log';
    }

    private static function resolve_log_file_path($file_name)
    {
        $dir = self::get_log_dir();
        if ($dir === '') {
            return '';
        }

        if ($file_name === '') {
            return self::get_log_file_path();
        }

        $file_name = basename(sanitize_file_name($file_name));
        if (!preg_match('/^wem-\d{4}-\d{2}-\d{2}\.log$/', $file_name)) {
            return '';
        }

        return trailingslashit($dir) . $file_name;
    }

    private static function protect_log_dir($dir)
    {
        $index_file = trailingslashit($dir) . 'index.php';
        if (!file_exists($index_file)) {
            file_put_contents($index_file, "<?php\n// Silence is golden.\n");
        }

        $htaccess_file = trailingslashit($dir) . '.htaccess';
        if (!file_exists($htaccess_file)) {
            file_put_contents($htaccess_file, "Deny from all\n");
        }
    }

    private static function rotate_if_needed($file_path)
    {
        if (!file_exists($file_path) || filesize($file_path) < self::MAX_FILE_SIZE) {
            return;
        }

        $rotated = $file_path . '.' . current_time('His') . '.bak';
        @rename($file_path, $rotated);
    }

    private static function cleanup_old_logs()
    {
        $dir = self::get_log_dir();
        if ($dir === '' || !is_dir($dir)) {
            return;
        }

        $files = glob(trailingslashit($dir) . 'wem-*');
        if ($files === false) {
            return;
        }

        $threshold = time() - (self::MAX_LOG_DAYS * DAY_IN_SECONDS);

        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $threshold) {
                @unlink($file);
            }
        }
    }

    private static function get_request_context()
    {
        if (defined('DOING_AJAX') && DOING_AJAX) {
            $action = isset($_REQUEST['action']) ? sanitize_text_field(wp_unslash($_REQUEST['action'])) : 'ajax';
            return 'ajax:' . $action;
        }

        if (is_admin()) {
            $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : 'admin';
            return 'admin:' . $page;
        }

        if (function_exists('is_cart') && (is_cart() || is_checkout() || is_product())) {
            return 'frontend:' . (is_cart() ? 'cart' : (is_checkout() ? 'checkout' : 'product'));
        }

        return 'frontend';
    }

    private static function should_log_current_request()
    {
        if (defined('DOING_AJAX') && DOING_AJAX) {
            $action = isset($_REQUEST['action']) ? sanitize_text_field(wp_unslash($_REQUEST['action'])) : '';
            return strpos($action, 'woo_excel_mng_') === 0;
        }

        if (is_admin()) {
            $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
            return strpos($page, 'woo-excel-mng') === 0;
        }

        return false;
    }
}
