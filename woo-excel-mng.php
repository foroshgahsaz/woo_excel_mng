<?php

/**
 * Plugin Name: مدیریت محصولات متغیر ووکامرس
 * Plugin URI: https://example.com
 * Description: مدیریت انبوه محصولات متغیر ووکامرس از طریق Excel، مدیریت حمل‌ونقل پیشرفته و فرمول‌های قیمت‌گذاری پویا
 * Version: 2.1.9
 * Author: محمد سیفی
 * Author URI: https://example.com
 * Text Domain: woo-excel-mng
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Requires WooCommerce: 5.0
 * Tested up to: 8.9
 * WC requires at least: 5.0
 * WC tested up to: 8.9
 */

// جلوگیری از دسترسی مستقیم
if (!defined('ABSPATH')) {
    exit;
}

// بررسی وجود ووکامرس
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', function () {
        echo '<div class="notice notice-error"><p>';
        echo __('افزونه ووکامرس باید فعال باشد.', 'woo-excel-mng');
        echo '</p></div>';
    });
    return;
}

// تعریف ثابت‌های افزونه
define('WOO_EXCEL_MNG_VERSION', '1.0.34');
define('WOO_EXCEL_MNG_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WOO_EXCEL_MNG_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WOO_EXCEL_MNG_PLUGIN_BASENAME', plugin_basename(__FILE__));

// بارگذاری autoloader
if (file_exists(WOO_EXCEL_MNG_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once WOO_EXCEL_MNG_PLUGIN_DIR . 'vendor/autoload.php';
}

// تعریف تابع autoload ساده برای کلاس‌ها
spl_autoload_register(function ($class_name) {
    $prefix = 'Woo_Excel_Mng_';

    // فقط کلاس‌های خودمان را بارگذاری کن
    if (strpos($class_name, $prefix) !== 0) {
        return;
    }

    $class_file = str_replace($prefix, '', $class_name);
    $class_file = str_replace('_', '-', $class_file);
    $class_file = strtolower($class_file);

    $file_path = WOO_EXCEL_MNG_PLUGIN_DIR . 'includes/class-' . $class_file . '.php';

    if (file_exists($file_path)) {
        require_once $file_path;
    }
});

/**
 * کلاس اصلی افزونه
 */
class Woo_Excel_Mng
{

    /**
     * نمونه واحد از کلاس
     */
    private static $instance = null;

    /**
     * دریافت نمونه واحد
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * سازنده
     */
    private function __construct()
    {
        $this->init_hooks();
    }

    /**
     * راه‌اندازی هوک‌ها
     */
    private function init_hooks()
    {
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        add_action('init', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }

    /**
     * بارگذاری فایل ترجمه
     */
    public function load_textdomain()
    {
        load_plugin_textdomain('woo-excel-mng', false, dirname(WOO_EXCEL_MNG_PLUGIN_BASENAME) . '/languages');
    }

    /**
     * راه‌اندازی افزونه
     */
    public function init()
    {
        // بارگذاری کلاس‌های مورد نیاز
        $this->load_dependencies();

        // راه‌اندازی کلاس سازگاری (باید اول باشد)
        if (class_exists('Woo_Excel_Mng_Compatibility')) {
            new Woo_Excel_Mng_Compatibility();
        }

        // راه‌اندازی بخش ادمین
        if (is_admin() && class_exists('Woo_Excel_Mng_Admin')) {
            new Woo_Excel_Mng_Admin();
        }

        // راه‌اندازی بخش Front-end
        if (class_exists('Woo_Excel_Mng_Frontend')) {
            new Woo_Excel_Mng_Frontend();
        }
    }

    /**
     * بارگذاری وابستگی‌ها
     */
    private function load_dependencies()
    {
        // ترتیب بارگذاری مهم است
        $required_classes = array(
            'database',              // اول: برای activate
            'excel-parser',          // دوم: برای پردازش Excel
            'products',              // سوم: برای محصولات
            'shipping',              // چهارم: برای حمل‌ونقل
            'formulas',              // پنجم: برای فرمول‌ها
            'frontend-compatibility', // ششم: سازگاری (قبل از frontend)
            'frontend',              // هفتم: Front-end
            'admin'                  // هشتم: Admin (فقط در admin)
        );

        foreach ($required_classes as $class) {
            $file = WOO_EXCEL_MNG_PLUGIN_DIR . 'includes/class-' . $class . '.php';
            if (file_exists($file)) {
                require_once $file;
            } else {
                // فقط در حالت debug خطا نمایش بده
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log(sprintf('Woo Excel Mng: فایل %s یافت نشد.', $file));
                }
            }
        }
    }

    /**
     * فعال‌سازی افزونه
     */
    public function activate()
    {
        // بارگذاری کلاس Database
        $database_file = WOO_EXCEL_MNG_PLUGIN_DIR . 'includes/class-database.php';
        if (!file_exists($database_file)) {
            wp_die(__('خطا: فایل Database یافت نشد. لطفاً افزونه را دوباره نصب کنید.', 'woo-excel-mng'));
        }

        require_once $database_file;

        // بررسی وجود کلاس
        if (!class_exists('Woo_Excel_Mng_Database')) {
            wp_die(__('خطا در بارگذاری کلاس Database. لطفاً افزونه را دوباره نصب کنید.', 'woo-excel-mng'));
        }

        // ایجاد جداول پایگاه داده
        try {
            Woo_Excel_Mng_Database::create_tables();
        } catch (Exception $e) {
            wp_die(sprintf(__('خطا در ایجاد جداول پایگاه داده: %s', 'woo-excel-mng'), $e->getMessage()));
        }

        // تنظیمات پیش‌فرض
        if (!get_option('woo_excel_mng_free_shipping_threshold')) {
            update_option('woo_excel_mng_free_shipping_threshold', 20000000);
        }

        if (!get_option('woo_excel_mng_origin_city')) {
            update_option('woo_excel_mng_origin_city', 'تهران');
        }

        if (get_option('woo_excel_mng_peykan_max_length') === false) {
            update_option('woo_excel_mng_peykan_max_length', 4);
        }

        if (get_option('woo_excel_mng_mazda_max_length') === false) {
            update_option('woo_excel_mng_mazda_max_length', 5);
        }

        if (get_option('woo_excel_mng_nissan_max_length') === false) {
            update_option('woo_excel_mng_nissan_max_length', 6);
        }

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * غیرفعال‌سازی افزونه
     */
    public function deactivate()
    {
        // پاکسازی در صورت نیاز
        delete_option('woo_excel_mng_test_option');
    }
}

/**
 * فرمت اعداد با حذف اعشار صفر
 */
if (!function_exists('woo_excel_mng_format_number')) {
    function woo_excel_mng_format_number($value, $decimals = 2, $decimal_separator = '.', $thousands_separator = '')
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $number = floatval($value);
        $rounded = round($number, $decimals);
        $formatted = number_format($rounded, $decimals, $decimal_separator, $thousands_separator);

        // حذف صفرهای پایانی اعشار
        if ($decimals > 0) {
            $formatted = rtrim($formatted, '0');
            $formatted = rtrim($formatted, $decimal_separator);
        }

        return $formatted;
    }
}

/**
 * فرمت قیمت با حذف .00
 */
if (!function_exists('woo_excel_mng_format_price')) {
    function woo_excel_mng_format_price($price, $args = array())
    {
        if (!function_exists('wc_price')) {
            return woo_excel_mng_format_number($price, 2, '.', '');
        }

        $decimals = function_exists('wc_get_price_decimals') ? wc_get_price_decimals() : 2;
        $rounded = round((float) $price, $decimals);
        $is_integer = abs($rounded - round($rounded)) < (1 / pow(10, $decimals + 1));

        if ($is_integer) {
            $args['decimals'] = 0;
        }

        return wc_price($rounded, $args);
    }
}

/**
 * راه‌اندازی افزونه
 */
function woo_excel_mng()
{
    // بررسی وجود ووکامرس قبل از راه‌اندازی
    if (!class_exists('WooCommerce')) {
        return;
    }

    try {
        return Woo_Excel_Mng::get_instance();
    } catch (Exception $e) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Woo Excel Mng Error: ' . $e->getMessage());
        }
        return;
    } catch (Error $e) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Woo Excel Mng Fatal Error: ' . $e->getMessage());
        }
        return;
    }
}

// شروع افزونه با تاخیر برای اطمینان از بارگذاری ووکامرس
add_action('plugins_loaded', 'woo_excel_mng', 20);
