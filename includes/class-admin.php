<?php

/**
 * کلاس مدیریت پیشخوان
 */

if (!defined('ABSPATH')) {
    exit;
}

class Woo_Excel_Mng_Admin
{

    /**
     * سازنده
     */
    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('admin_init', array($this, 'handle_form_submissions'));
        add_action('admin_init', array($this, 'wem_handle_form_submission'));


        // AJAX handlers
        add_action('wp_ajax_woo_excel_mng_save_route', array($this, 'ajax_save_route'));
        add_action('wp_ajax_woo_excel_mng_save_formula', array($this, 'ajax_save_formula'));
        add_action('wp_ajax_woo_excel_mng_delete_formula', array($this, 'ajax_delete_formula'));

        // AJAX handlers برای import با نوار پیشرفت
        add_action('wp_ajax_woo_excel_mng_start_import', array($this, 'ajax_start_import'));
        add_action('wp_ajax_woo_excel_mng_process_batch', array($this, 'ajax_process_batch'));
    }





    // function wem_handle_form_submission()
    // {
    //     // ۱. بررسی اینکه آیا فرم ارسال شده است یا خیر
    //     if (!isset($_POST['wem_submit'])) {
    //         return;
    //     }

    //     // ۲. بررسی امنیت (Nonce)
    //     if (!isset($_POST['wem_nonce']) || !wp_verify_nonce($_POST['wem_nonce'], 'wem_import_action')) {
    //         wp_die('خطای امنیتی رخ داده است.');
    //     }

    //     // ۳. بررسی وجود فایل
    //     if (!isset($_FILES['products_file']) || $_FILES['products_file']['error'] !== UPLOAD_ERR_OK) {
    //         return;
    //     }

    //     // ۴. مسیردهی به فایلی که گفتی و فراخوانی کلاس
    //     $parser_file = WP_PLUGIN_DIR . '/woo_excel_mng/includes/class-excel-parser.php';

    //     if (file_exists($parser_file)) {
    //         require_once $parser_file;

    //         // ۵. ارسال فایل موقت به متد مربوطه در کلاس
    //         $tmp_file_path = $_FILES['products_file']['tmp_name'];
    //         $result = Woo_Excel_Mng_Excel_Parser::parse_products_file($tmp_file_path);
    //         Woo_Excel_Mng_Products::import_products($result);
    //     } else {
    //         wp_die('فایل کلاس اکسل پیدا نشد در مسیر: ' . $parser_file);
    //     }
    // }




    function wem_handle_form_submission()
    {
        // پردازش همزمان غیرفعال — import از طریق AJAX صف‌بندی انجام می‌شود.
        if (!isset($_POST['wem_submit'])) {
            return;
        }

        if (!isset($_POST['wem_nonce']) || !wp_verify_nonce($_POST['wem_nonce'], 'wem_import_action')) {
            wp_die('خطای امنیتی رخ داده است.');
        }

        set_transient(
            'wem_import_notice_' . get_current_user_id(),
            array(
                'type'    => 'info',
                'message' => __('لطفاً از دکمه «شروع پردازش» استفاده کنید تا import به صورت صف انجام شود.', 'woo-excel-mng'),
            ),
            60
        );

        wp_safe_redirect(admin_url('admin.php?page=woo-excel-mng-products&tab=products'));
        exit;
    }



    /**
     * افزودن منوی پیشخوان
     */
    public function add_admin_menu()
    {
        add_menu_page(
            __('مدیریت فروشگاه', 'woo-excel-mng'),
            __('مدیریت فروشگاه', 'woo-excel-mng'),
            'manage_woocommerce',
            'woo-excel-mng',
            array($this, 'render_main_page'),
            'dashicons-store',
            56
        );

        // زیرمنوها
        add_submenu_page(
            'woo-excel-mng',
            __('داشبورد', 'woo-excel-mng'),
            __('داشبورد', 'woo-excel-mng'),
            'manage_woocommerce',
            'woo-excel-mng',
            array($this, 'render_main_page')
        );

        add_submenu_page(
            'woo-excel-mng',
            __('محصولات', 'woo-excel-mng'),
            __('محصولات', 'woo-excel-mng'),
            'manage_woocommerce',
            'woo-excel-mng-products',
            array($this, 'render_products_page')
        );

        add_submenu_page(
            'woo-excel-mng',
            __('حمل‌ونقل', 'woo-excel-mng'),
            __('حمل‌ونقل', 'woo-excel-mng'),
            'manage_woocommerce',
            'woo-excel-mng-shipping',
            array($this, 'render_shipping_page')
        );

        add_submenu_page(
            'woo-excel-mng',
            __('فرمول‌ها', 'woo-excel-mng'),
            __('فرمول‌ها', 'woo-excel-mng'),
            'manage_woocommerce',
            'woo-excel-mng-formulas',
            array($this, 'render_formulas_page')
        );

        add_submenu_page(
            'woo-excel-mng',
            __('تنظیمات', 'woo-excel-mng'),
            __('تنظیمات', 'woo-excel-mng'),
            'manage_woocommerce',
            'woo-excel-mng-settings',
            array($this, 'render_settings_page')
        );
    }

    /**
     * بارگذاری فایل‌های CSS و JS
     */
    public function enqueue_admin_assets($hook)
    {
        // فقط در صفحات افزونه
        if (strpos($hook, 'woo-excel-mng') === false) {
            return;
        }

        wp_enqueue_style(
            'woo-excel-mng-admin',
            WOO_EXCEL_MNG_PLUGIN_URL . 'admin/assets/css/admin.css',
            array(),
            WOO_EXCEL_MNG_VERSION
        );

        wp_enqueue_script(
            'woo-excel-mng-admin',
            WOO_EXCEL_MNG_PLUGIN_URL . 'admin/assets/js/admin.js',
            array('jquery'),
            WOO_EXCEL_MNG_VERSION,
            true
        );

        wp_localize_script('woo-excel-mng-admin', 'wooExcelMng', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('woo_excel_mng_nonce'),
            'strings' => array(
                'confirm_delete'     => __('آیا مطمئن هستید؟', 'woo-excel-mng'),
                'processing'         => __('در حال پردازش...', 'woo-excel-mng'),
                'uploading'          => __('در حال آپلود فایل...', 'woo-excel-mng'),
                'parsing'            => __('در حال خواندن فایل Excel...', 'woo-excel-mng'),
                'importing'          => __('در حال وارد کردن محصولات...', 'woo-excel-mng'),
                'done'               => __('عملیات با موفقیت انجام شد!', 'woo-excel-mng'),
                'error'              => __('خطا در پردازش', 'woo-excel-mng'),
                'products_remaining' => __('باقی‌مانده', 'woo-excel-mng'),
                'of'                 => __('از', 'woo-excel-mng'),
                'batch_size'         => 1,
                'current_product'    => __('در حال وارد کردن واریانت‌های:', 'woo-excel-mng'),
                'product_done'       => __('محصول تکمیل شد:', 'woo-excel-mng'),
                'variations_count'   => __('تعداد واریانت در اکسل:', 'woo-excel-mng'),
            )
        ));
    }

    /**
     * مدیریت ارسال فرم‌ها
     */
    public function handle_form_submissions()
    {
        // بررسی آپلود فایل محصولات
        if (isset($_POST['action']) && $_POST['action'] === 'upload_products') {
            if (!isset($_POST['woo_excel_mng_nonce']) || !wp_verify_nonce($_POST['woo_excel_mng_nonce'], 'woo_excel_mng_upload_products')) {
                wp_die(__('خطای امنیتی', 'woo-excel-mng'));
            }

            if (!current_user_can('manage_woocommerce')) {
                wp_die(__('شما مجوز لازم را ندارید.', 'woo-excel-mng'));
            }

            $this->handle_products_upload();
        }

        // بررسی آپلود فایل حمل‌ونقل
        if (isset($_POST['action']) && $_POST['action'] === 'upload_shipping') {
            if (!isset($_POST['woo_excel_mng_nonce']) || !wp_verify_nonce($_POST['woo_excel_mng_nonce'], 'woo_excel_mng_upload_shipping')) {
                wp_die(__('خطای امنیتی', 'woo-excel-mng'));
            }

            if (!current_user_can('manage_woocommerce')) {
                wp_die(__('شما مجوز لازم را ندارید.', 'woo-excel-mng'));
            }

            $this->handle_shipping_upload();
        }

        // بررسی ذخیره تنظیمات حمل‌ونقل
        if (isset($_POST['action']) && $_POST['action'] === 'save_shipping_settings') {
            if (!isset($_POST['woo_excel_mng_nonce']) || !wp_verify_nonce($_POST['woo_excel_mng_nonce'], 'woo_excel_mng_save_settings')) {
                wp_die(__('خطای امنیتی', 'woo-excel-mng'));
            }

            if (!current_user_can('manage_woocommerce')) {
                wp_die(__('شما مجوز لازم را ندارید.', 'woo-excel-mng'));
            }

            $this->handle_save_shipping_settings();
        }

        // بررسی ذخیره تنظیمات عمومی
        if (isset($_POST['action']) && $_POST['action'] === 'save_general_settings') {
            if (!isset($_POST['woo_excel_mng_nonce']) || !wp_verify_nonce($_POST['woo_excel_mng_nonce'], 'woo_excel_mng_save_general_settings')) {
                wp_die(__('خطای امنیتی', 'woo-excel-mng'));
            }

            if (!current_user_can('manage_woocommerce')) {
                wp_die(__('شما مجوز لازم را ندارید.', 'woo-excel-mng'));
            }

            $this->handle_save_general_settings();
        }

        // بررسی ذخیره فرمول
        if (isset($_POST['action']) && $_POST['action'] === 'save_formula') {
            if (!isset($_POST['woo_excel_mng_nonce']) || !wp_verify_nonce($_POST['woo_excel_mng_nonce'], 'woo_excel_mng_save_formula')) {
                wp_die(__('خطای امنیتی', 'woo-excel-mng'));
            }

            if (!current_user_can('manage_woocommerce')) {
                wp_die(__('شما مجوز لازم را ندارید.', 'woo-excel-mng'));
            }

            $this->handle_save_formula();
        }
    }

    /**
     * مدیریت آپلود فایل محصولات
     */
    private function handle_products_upload()
    {
        if (!isset($_FILES['products_file']) || $_FILES['products_file']['error'] !== UPLOAD_ERR_OK) {
            add_action('admin_notices', function () {
                echo '<div class="notice notice-error"><p>' . __('خطا در آپلود فایل.', 'woo-excel-mng') . '</p></div>';
            });
            return;
        }

        $file = $_FILES['products_file'];

        // بررسی نوع فایل
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($file_ext, array('xlsx', 'xls'))) {
            add_action('admin_notices', function () {
                echo '<div class="notice notice-error"><p>' . __('فقط فایل‌های Excel مجاز هستند.', 'woo-excel-mng') . '</p></div>';
            });
            return;
        }

        // آپلود فایل موقت
        $upload_dir = wp_upload_dir();
        $temp_file = $upload_dir['path'] . '/' . uniqid('woo_excel_') . '.' . $file_ext;

        if (!move_uploaded_file($file['tmp_name'], $temp_file)) {
            add_action('admin_notices', function () {
                echo '<div class="notice notice-error"><p>' . __('خطا در ذخیره فایل موقت.', 'woo-excel-mng') . '</p></div>';
            });
            return;
        }

        // پردازش فایل
        $parser_result = Woo_Excel_Mng_Excel_Parser::parse_products_file($temp_file);


        if (!$parser_result['success']) {
            unlink($temp_file);
            add_action('admin_notices', function () use ($parser_result) {
                echo '<div class="notice notice-error"><p>' . esc_html($parser_result['message']) . '</p></div>';
            });
            return;
        }

        // واردسازی محصولات
        $import_result = Woo_Excel_Mng_Products::import_products($parser_result['data']);

        // ثبت لاگ
        $log_message = sprintf(
            __('%d محصول ایجاد شد، %d به‌روزرسانی شد.', 'woo-excel-mng'),
            $import_result,
            $parser_result['data'],
            $import_result['updated']
        );

        if (!empty($import_result['errors'])) {
            $log_message .= ' ' . __('خطاها:', 'woo-excel-mng') . ' ' . implode(', ', $import_result['errors']);
        }

        Woo_Excel_Mng_Database::log_import(
            'products',
            $file['name'],
            $import_result['success'] ? 'success' : 'error',
            $log_message,
            $import_result['created'] + $import_result['updated']
        );

        // حذف فایل موقت
        unlink($temp_file);

        // نمایش پیام موفقیت
        if ($import_result['success']) {
            add_action('admin_notices', function () use ($import_result) {
                echo '<div class="notice notice-success"><p>';
                echo sprintf(
                    __('عملیات با موفقیت انجام شد. %d محصول ایجاد شد و %d محصول به‌روزرسانی شد.', 'woo-excel-mng'),
                    $import_result['created'],
                    $import_result['updated']
                );
                echo '</p></div>';
            });
        } else {
            add_action('admin_notices', function () use ($import_result) {
                echo '<div class="notice notice-error"><p>';
                echo __('برخی خطاها رخ داد:', 'woo-excel-mng') . '<br>';
                echo implode('<br>', array_map('esc_html', $import_result['errors']));
                echo '</p></div>';
            });
        }
    }

    /**
     * مدیریت آپلود فایل حمل‌ونقل
     */
    private function handle_shipping_upload()
    {
        if (!isset($_FILES['shipping_file']) || $_FILES['shipping_file']['error'] !== UPLOAD_ERR_OK) {
            add_action('admin_notices', function () {
                echo '<div class="notice notice-error"><p>' . __('خطا در آپلود فایل.', 'woo-excel-mng') . '</p></div>';
            });
            return;
        }

        $file = $_FILES['shipping_file'];

        // بررسی نوع فایل
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($file_ext, array('xlsx', 'xls'))) {
            add_action('admin_notices', function () {
                echo '<div class="notice notice-error"><p>' . __('فقط فایل‌های Excel مجاز هستند.', 'woo-excel-mng') . '</p></div>';
            });
            return;
        }

        // آپلود فایل موقت
        $upload_dir = wp_upload_dir();
        $temp_file = $upload_dir['path'] . '/' . uniqid('woo_excel_') . '.' . $file_ext;

        if (!move_uploaded_file($file['tmp_name'], $temp_file)) {
            add_action('admin_notices', function () {
                echo '<div class="notice notice-error"><p>' . __('خطا در ذخیره فایل موقت.', 'woo-excel-mng') . '</p></div>';
            });
            return;
        }

        // پردازش فایل
        $parser_result = Woo_Excel_Mng_Excel_Parser::parse_shipping_file($temp_file);

        if (!$parser_result['success']) {
            unlink($temp_file);
            add_action('admin_notices', function () use ($parser_result) {
                echo '<div class="notice notice-error"><p>' . esc_html($parser_result['message']) . '</p></div>';
            });
            return;
        }

        // واردسازی مسیرها
        $import_result = Woo_Excel_Mng_Shipping::import_routes($parser_result['data']);

        // ثبت لاگ
        $log_message = sprintf(
            __('%d مسیر جدید اضافه شد، %d مسیر به‌روزرسانی شد.', 'woo-excel-mng'),
            $import_result['inserted'],
            $import_result['updated']
        );

        if (!empty($import_result['errors'])) {
            $log_message .= ' ' . __('خطاها:', 'woo-excel-mng') . ' ' . implode(', ', $import_result['errors']);
        }

        Woo_Excel_Mng_Database::log_import(
            'shipping',
            $file['name'],
            $import_result['success'] ? 'success' : 'error',
            $log_message,
            $import_result['inserted'] + $import_result['updated']
        );

        // حذف فایل موقت
        unlink($temp_file);

        // نمایش پیام موفقیت
        if ($import_result['success']) {
            add_action('admin_notices', function () use ($import_result) {
                echo '<div class="notice notice-success"><p>';
                echo sprintf(
                    __('عملیات با موفقیت انجام شد. %d مسیر جدید اضافه شد و %d مسیر به‌روزرسانی شد.', 'woo-excel-mng'),
                    $import_result['inserted'],
                    $import_result['updated']
                );
                echo '</p></div>';
            });
        } else {
            add_action('admin_notices', function () use ($import_result) {
                echo '<div class="notice notice-error"><p>';
                echo __('برخی خطاها رخ داد:', 'woo-excel-mng') . '<br>';
                echo implode('<br>', array_map('esc_html', $import_result['errors']));
                echo '</p></div>';
            });
        }

        // ریدایرکت برای به‌روزرسانی صفحه
        wp_redirect(admin_url('admin.php?page=woo-excel-mng-shipping&tab=shipping&imported=1'));
        exit;
    }

    /**
     * مدیریت ذخیره تنظیمات حمل‌ونقل
     */
    private function handle_save_shipping_settings()
    {
        if (isset($_POST['free_shipping_threshold'])) {
            $threshold = floatval($_POST['free_shipping_threshold']);
            update_option('woo_excel_mng_free_shipping_threshold', $threshold);

            add_action('admin_notices', function () {
                echo '<div class="notice notice-success"><p>' . __('تنظیمات با موفقیت ذخیره شد.', 'woo-excel-mng') . '</p></div>';
            });
        }
    }

    /**
     * مدیریت ذخیره تنظیمات عمومی
     */
    private function handle_save_general_settings()
    {
        if (isset($_POST['woo_excel_mng_origin_city'])) {
            $origin_city = sanitize_text_field($_POST['woo_excel_mng_origin_city']);
            update_option('woo_excel_mng_origin_city', $origin_city);
        }

        if (isset($_POST['woo_excel_mng_premium_threshold'])) {
            $premium_threshold = floatval($_POST['woo_excel_mng_premium_threshold']);
            update_option('woo_excel_mng_premium_threshold', $premium_threshold);
        }

        if (isset($_POST['woo_excel_mng_shipping_percentage'])) {
            $shipping_percentage = floatval($_POST['woo_excel_mng_shipping_percentage']);
            update_option('woo_excel_mng_shipping_percentage', $shipping_percentage);
        }

        if (isset($_POST['woo_excel_mng_peykan_max_length'])) {
            $peykan_max_length = floatval(str_replace(',', '.', (string) $_POST['woo_excel_mng_peykan_max_length']));
            update_option('woo_excel_mng_peykan_max_length', $peykan_max_length);
        }

        if (isset($_POST['woo_excel_mng_mazda_max_length'])) {
            $mazda_max_length = floatval(str_replace(',', '.', (string) $_POST['woo_excel_mng_mazda_max_length']));
            update_option('woo_excel_mng_mazda_max_length', $mazda_max_length);
        }

        if (isset($_POST['woo_excel_mng_nissan_max_length'])) {
            $nissan_max_length = floatval(str_replace(',', '.', (string) $_POST['woo_excel_mng_nissan_max_length']));
            update_option('woo_excel_mng_nissan_max_length', $nissan_max_length);
        }

        add_action('admin_notices', function () {
            echo '<div class="notice notice-success"><p>' . __('تنظیمات با موفقیت ذخیره شد.', 'woo-excel-mng') . '</p></div>';
        });

        wp_redirect(admin_url('admin.php?page=woo-excel-mng-settings&tab=settings&saved=1'));
        exit;
    }

    /**
     * مدیریت ذخیره فرمول
     */
    private function handle_save_formula()
    {
        if (!isset($_POST['formula_product_id']) || !isset($_POST['formula_text'])) {
            add_action('admin_notices', function () {
                echo '<div class="notice notice-error"><p>' . __('لطفاً تمام فیلدها را پر کنید.', 'woo-excel-mng') . '</p></div>';
            });
            return;
        }

        $product_id = intval($_POST['formula_product_id']);
        $formula = sanitize_text_field($_POST['formula_text']);
        $formula_id = isset($_POST['formula_id']) && !empty($_POST['formula_id']) ? intval($_POST['formula_id']) : null;

        $result = Woo_Excel_Mng_Formulas::save_formula($product_id, $formula, $formula_id);

        if ($result) {
            add_action('admin_notices', function () {
                echo '<div class="notice notice-success"><p>' . __('فرمول با موفقیت ذخیره شد.', 'woo-excel-mng') . '</p></div>';
            });
        } else {
            add_action('admin_notices', function () {
                echo '<div class="notice notice-error"><p>' . __('خطا در ذخیره فرمول.', 'woo-excel-mng') . '</p></div>';
            });
        }

        // ریدایرکت
        wp_redirect(admin_url('admin.php?page=woo-excel-mng-formulas&tab=formulas&saved=1'));
        exit;
    }

    /**
     * AJAX: ذخیره مسیر
     */
    public function ajax_save_route()
    {
        check_ajax_referer('woo_excel_mng_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('شما مجوز لازم را ندارید.', 'woo-excel-mng'));
        }

        $route_id = isset($_POST['route_id']) ? intval($_POST['route_id']) : 0;
        $data = array(
            'peykan_price' => isset($_POST['peykan_price']) ? floatval($_POST['peykan_price']) : 0,
            'mazda_price' => isset($_POST['mazda_price']) ? floatval($_POST['mazda_price']) : 0,
            'nissan_price' => isset($_POST['nissan_price']) ? floatval($_POST['nissan_price']) : 0,
            'is_active' => isset($_POST['is_active']) ? intval($_POST['is_active']) : 0
        );

        $result = Woo_Excel_Mng_Shipping::save_route($route_id, $data);

        if ($result) {
            wp_send_json_success(__('مسیر با موفقیت ذخیره شد.', 'woo-excel-mng'));
        } else {
            wp_send_json_error(__('خطا در ذخیره مسیر.', 'woo-excel-mng'));
        }
    }

    /**
     * AJAX: ذخیره فرمول
     */
    public function ajax_save_formula()
    {
        check_ajax_referer('woo_excel_mng_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('شما مجوز لازم را ندارید.', 'woo-excel-mng'));
        }

        $product_id = isset($_POST['formula_product_id']) ? intval($_POST['formula_product_id']) : 0;
        $formula = isset($_POST['formula_text']) ? sanitize_text_field($_POST['formula_text']) : '';
        $formula_id = isset($_POST['formula_id']) && !empty($_POST['formula_id']) ? intval($_POST['formula_id']) : null;

        if (empty($product_id) || empty($formula)) {
            wp_send_json_error(__('لطفاً تمام فیلدها را پر کنید.', 'woo-excel-mng'));
        }

        $result = Woo_Excel_Mng_Formulas::save_formula($product_id, $formula, $formula_id);

        if ($result) {
            wp_send_json_success(__('فرمول با موفقیت ذخیره شد.', 'woo-excel-mng'));
        } else {
            wp_send_json_error(__('خطا در ذخیره فرمول.', 'woo-excel-mng'));
        }
    }

    /**
     * AJAX: شروع import - آپلود فایل و آماده‌سازی داده‌ها
     */
    public function ajax_start_import()
    {
        // ۱. بررسی امنیت و اعتبارسنجی درخواست
        check_ajax_referer('woo_excel_mng_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('شما مجوز لازم را ندارید.', 'woo-excel-mng'));
        }

        // ۲. بررسی وجود و صحت فایل آپلود شده
        if (!isset($_FILES['products_file']) || $_FILES['products_file']['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(__('خطا در آپلود فایل.', 'woo-excel-mng'));
        }

        $file = $_FILES['products_file'];
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($file_ext, array('xlsx', 'xls'))) {
            wp_send_json_error(__('فقط فایل‌های Excel مجاز هستند.', 'woo-excel-mng'));
        }

        // ۳. ذخیره موقت فایل برای پردازش
        $upload_dir = wp_upload_dir();
        $temp_file  = $upload_dir['path'] . '/' . uniqid('woo_excel_') . '.' . $file_ext;

        if (!move_uploaded_file($file['tmp_name'], $temp_file)) {
            wp_send_json_error(__('خطا در ذخیره فایل موقت.', 'woo-excel-mng'));
        }

        // ۴. تبدیل محتویات اکسل به آرایه PHP
        $parser_result = Woo_Excel_Mng_Excel_Parser::parse_products_file($temp_file);

        // پاکسازی فایل موقت بلافاصله بعد از خواندن
        if (file_exists($temp_file)) {
            unlink($temp_file);
        }

        if (!$parser_result['success']) {
            wp_send_json_error($parser_result['message']);
        }

        $all_rows = $parser_result['data'];

        if (empty($all_rows)) {
            wp_send_json_error(__('فایل اکسل خالی است یا داده معتبری ندارد.', 'woo-excel-mng'));
        }

        $selected_product_ids = array();
        if (isset($_POST['selected_products']) && is_array($_POST['selected_products'])) {
            $selected_product_ids = array_map('intval', $_POST['selected_products']);
            $selected_product_ids = array_values(array_filter($selected_product_ids));
        }

        if (empty($selected_product_ids)) {
            wp_send_json_error(__('حداقل یک محصول را انتخاب کنید.', 'woo-excel-mng'));
        }

        $product_queue = array();
        foreach ($selected_product_ids as $product_id) {
            $product = wc_get_product($product_id);
            if ($product && $product->is_type('variable')) {
                $product_queue[] = array(
                    'id'   => $product_id,
                    'name' => $product->get_name(),
                );
            }
        }

        if (empty($product_queue)) {
            wp_send_json_error(__('هیچ محصول متغیر معتبری انتخاب نشده است.', 'woo-excel-mng'));
        }

        $batch_id = 'wem_import_' . uniqid();

        set_transient($batch_id, array(
            'excel_data'     => $all_rows,
            'product_queue'  => $product_queue,
            'product_index'  => 0,
            'file_name'      => sanitize_text_field($file['name']),
            'variation_rows' => count($all_rows),
            'created'        => 0,
            'updated'        => 0,
            'errors'         => array(),
        ), 3600);

        wp_send_json_success(array(
            'batch_id'         => $batch_id,
            'total'            => count($product_queue),
            'variation_rows'   => count($all_rows),
            'product_names'    => wp_list_pluck($product_queue, 'name'),
        ));
    }




    /**
     * AJAX: پردازش یک دسته از محصولات
     */

    public function ajax_process_batch()
    {
        check_ajax_referer('woo_excel_mng_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('شما مجوز لازم را ندارید.', 'woo-excel-mng'));
        }

        @set_time_limit(300);
        @ini_set('memory_limit', '512M');

        $batch_id = isset($_POST['batch_id']) ? sanitize_text_field($_POST['batch_id']) : '';

        if (empty($batch_id)) {
            wp_send_json_error(__('شناسه صف نامعتبر است.', 'woo-excel-mng'));
        }

        $state = get_transient($batch_id);
        if (!$state || empty($state['product_queue']) || !isset($state['excel_data'])) {
            wp_send_json_error(__('جلسه import منقضی شده است. لطفاً دوباره فایل را آپلود کنید.', 'woo-excel-mng'));
        }

        $product_queue  = $state['product_queue'];
        $product_index  = isset($state['product_index']) ? intval($state['product_index']) : 0;
        $total_products = count($product_queue);
        $excel_data     = $state['excel_data'];

        if ($product_index >= $total_products) {
            wp_send_json_success(array(
                'processed'        => $total_products,
                'total'            => $total_products,
                'done'             => true,
                'created'          => $state['created'],
                'updated'          => $state['updated'],
                'errors'           => $state['errors'],
                'current_product'  => '',
                'variations_rows'  => isset($state['variation_rows']) ? $state['variation_rows'] : count($excel_data),
                'last_created'     => 0,
            ));
        }

        $current_item = $product_queue[$product_index];
        $product_id   = $current_item['id'];
        $product_name = $current_item['name'];

        $result  = Woo_Excel_Mng_Products::import_single_product($product_id, $excel_data);
        $created = isset($result['created']) ? intval($result['created']) : 0;
        $updated = isset($result['updated']) ? intval($result['updated']) : 0;
        $errors  = isset($result['errors']) && is_array($result['errors']) ? $result['errors'] : array();

        $state['created'] += $created;
        $state['updated'] += $updated;
        $state['errors']   = array_merge($state['errors'], $errors);
        $state['product_index'] = $product_index + 1;

        $done = ($state['product_index'] >= $total_products);

        if ($done) {
            $log_summary = sprintf(
                __('پایان import: %1$d واریانت ایجاد شد، %2$d به‌روزرسانی، %3$d خطا', 'woo-excel-mng'),
                $state['created'],
                $state['updated'],
                count($state['errors'])
            );

            Woo_Excel_Mng_Database::log_import(
                'products',
                $state['file_name'],
                empty($state['errors']) ? 'success' : 'partial',
                $log_summary,
                $state['created'] + $state['updated']
            );

            delete_transient($batch_id);
        } else {
            set_transient($batch_id, $state, 3600);
        }

        wp_send_json_success(array(
            'processed'        => $state['product_index'],
            'total'            => $total_products,
            'done'             => $done,
            'created'          => $state['created'],
            'updated'          => $state['updated'],
            'errors'           => $state['errors'],
            'current_product'  => $product_name,
            'current_product_id' => $product_id,
            'variations_rows'  => isset($state['variation_rows']) ? $state['variation_rows'] : count($excel_data),
            'last_created'     => $created,
            'log'              => sprintf(
                __('محصول «%1$s»: %2$d واریانت ایجاد شد', 'woo-excel-mng'),
                $product_name,
                $created
            ),
        ));
    }
    /**
     * AJAX: حذف فرمول
     */
    public function ajax_delete_formula()
    {
        check_ajax_referer('woo_excel_mng_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('شما مجوز لازم را ندارید.', 'woo-excel-mng'));
        }

        $formula_id = isset($_POST['formula_id']) ? intval($_POST['formula_id']) : 0;

        if (empty($formula_id)) {
            wp_send_json_error(__('شناسه فرمول نامعتبر است.', 'woo-excel-mng'));
        }

        $result = Woo_Excel_Mng_Formulas::delete_formula($formula_id);

        if ($result) {
            wp_send_json_success(__('فرمول با موفقیت حذف شد.', 'woo-excel-mng'));
        } else {
            wp_send_json_error(__('خطا در حذف فرمول.', 'woo-excel-mng'));
        }
    }

    /**
     * رندر صفحه اصلی (با تب‌بندی)
     */
    public function render_main_page()
    {
        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'dashboard';
        $tabs = array(
            'dashboard' => __('داشبورد', 'woo-excel-mng'),
            'products' => __('محصولات', 'woo-excel-mng'),
            'shipping' => __('حمل‌ونقل', 'woo-excel-mng'),
            'formulas' => __('فرمول‌ها', 'woo-excel-mng'),
            'settings' => __('تنظیمات', 'woo-excel-mng'),
        );

        include WOO_EXCEL_MNG_PLUGIN_DIR . 'admin/views/main-page.php';
    }

    /**
     * رندر صفحه تنظیمات
     */
    public function render_settings_page()
    {
        $current_tab = 'settings';
        $tabs = array(
            'dashboard' => __('داشبورد', 'woo-excel-mng'),
            'products' => __('محصولات', 'woo-excel-mng'),
            'shipping' => __('حمل‌ونقل', 'woo-excel-mng'),
            'formulas' => __('فرمول‌ها', 'woo-excel-mng'),
            'settings' => __('تنظیمات', 'woo-excel-mng'),
        );
        include WOO_EXCEL_MNG_PLUGIN_DIR . 'admin/views/main-page.php';
    }

    /**
     * رندر صفحه محصولات
     */
    public function render_products_page()
    {
        $current_tab = 'products';
        $tabs = array(
            'dashboard' => __('داشبورد', 'woo-excel-mng'),
            'products' => __('محصولات', 'woo-excel-mng'),
            'shipping' => __('حمل‌ونقل', 'woo-excel-mng'),
            'formulas' => __('فرمول‌ها', 'woo-excel-mng'),
            'settings' => __('تنظیمات', 'woo-excel-mng'),
        );
        include WOO_EXCEL_MNG_PLUGIN_DIR . 'admin/views/main-page.php';
    }

    /**
     * رندر صفحه حمل‌ونقل
     */
    public function render_shipping_page()
    {
        $current_tab = 'shipping';
        $tabs = array(
            'dashboard' => __('داشبورد', 'woo-excel-mng'),
            'products' => __('محصولات', 'woo-excel-mng'),
            'shipping' => __('حمل‌ونقل', 'woo-excel-mng'),
            'formulas' => __('فرمول‌ها', 'woo-excel-mng'),
            'settings' => __('تنظیمات', 'woo-excel-mng'),
        );
        include WOO_EXCEL_MNG_PLUGIN_DIR . 'admin/views/main-page.php';
    }

    /**
     * رندر صفحه فرمول‌ها
     */
    public function render_formulas_page()
    {
        $current_tab = 'formulas';
        $tabs = array(
            'dashboard' => __('داشبورد', 'woo-excel-mng'),
            'products' => __('محصولات', 'woo-excel-mng'),
            'shipping' => __('حمل‌ونقل', 'woo-excel-mng'),
            'formulas' => __('فرمول‌ها', 'woo-excel-mng'),
            'settings' => __('تنظیمات', 'woo-excel-mng'),
        );
        include WOO_EXCEL_MNG_PLUGIN_DIR . 'admin/views/main-page.php';
    }
}
