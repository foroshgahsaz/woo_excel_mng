<?php

/**
 * کلاس مدیریت Front-end
 */

if (!defined('ABSPATH')) {
    exit;
}

class Woo_Excel_Mng_Frontend
{

    const CART_ITEM_METERAGE_KEY = 'woo_excel_meterage';
    const METERAGE_MIN_DEFAULT = 0.5;
    const METERAGE_STEP_DEFAULT = 0.5;
    private static $skip_cart_id_filter = false;

    /**
     * سازنده
     */
    public function __construct()
    {
        // تغییر label فیلد quantity به متراژ برای محصولات با فرمول
        add_filter('woocommerce_product_single_add_to_cart_text', array($this, 'change_add_to_cart_text'), 10, 1);
        add_filter('woocommerce_product_add_to_cart_text', array($this, 'change_add_to_cart_text'), 10, 1);

        // تغییر label quantity در صفحه محصول
        add_filter('woocommerce_quantity_input_args', array($this, 'change_quantity_label'), 10, 2);
        add_filter('woocommerce_quantity_input', array($this, 'render_custom_quantity_input'), 10, 3);

        // جلوگیری از ایجاد آیتم تکراری بر اساس متراژ
        add_filter('woocommerce_cart_id', array($this, 'filter_cart_id'), 10, 5);
        add_action('woocommerce_add_to_cart', array($this, 'merge_meterage_on_add_to_cart'), 10, 6);

        // تغییر quantity input در سبد خرید برای محصولات با فرمول (فقط Cart کلاسیک)
        add_filter('woocommerce_quantity_input_args', array($this, 'change_cart_quantity_input'), 10, 2);

        // مدل جدید: quantity همیشه 1، متراژ در cart item meta ذخیره می‌شود
        add_filter('woocommerce_add_cart_item_data', array($this, 'add_meterage_cart_item_data'), 10, 3);

        add_filter('woocommerce_add_to_cart_quantity', array($this, 'force_quantity_one_for_formula'), 10, 2);

        // ستون quantity برای تعداد سفارشی
        add_filter('woocommerce_cart_item_quantity', array($this, 'display_editable_custom_quantity'), 10, 3);
        // ستون متراژ به شکل جداگانه
        add_filter('woocommerce_cart_item_quantity', array($this, 'display_td'), 20, 3);

        // پردازش آپدیت متراژ از فرم Cart کلاسیک
        add_action('woocommerce_update_cart_action_cart_updated', array($this, 'handle_meterage_update_from_post'), 5, 1);

        // اگر صفحه Cart/Checkout با Blocks ساخته شده باشد، به کلاسیک برگردان
        add_filter('the_content', array($this, 'force_classic_cart_for_blocks'), 1);

        // اجازه ورود تعداد اعشاری برای همه محصولات (جلوگیری از intval ووکامرس)
        add_filter('woocommerce_stock_amount', array($this, 'allow_decimal_stock_amount'));

        // فیلتر برای WooCommerce Blocks REST API
        add_filter('woocommerce_rest_cart_item_quantity', array($this, 'rest_cart_item_quantity'), 10, 3);
        add_filter('woocommerce_rest_cart_item_data', array($this, 'rest_cart_item_data'), 10, 2);

        // ارسال flag فرمول به JS در صفحه محصول
        add_filter('woocommerce_available_variation', array($this, 'add_variation_formula_flag'), 10, 3);

        // هدر ستون متراژ در جدول سبد (PHP)
        add_action('woocommerce_before_cart_table', array($this, 'buffer_cart_table_start'), 0);
        add_action('woocommerce_after_cart_table', array($this, 'buffer_cart_table_end'), 999);

        // محاسبه قیمت و وزن بر اساس متراژ
        // استفاده از priority بالا برای اجرا قبل از سایر افزونه‌ها
        add_action('woocommerce_before_calculate_totals', array($this, 'calculate_cart_totals'), 5, 1);

        // نمایش قیمت محاسبه شده در سبد خرید
        // add_filter('woocommerce_cart_item_price', array($this, 'display_calculated_price'), 10, 3);

        // نمایش قیمت کل (subtotal) هر آیتم در سبد خرید
        add_filter('woocommerce_cart_item_subtotal', array($this, 'display_calculated_subtotal'), 10, 3);

        // بلاک حمل رایگان قدیمی حذف شد - حالا در display_shipping_info_box نمایش داده می‌شود

        // حذف فیلدهای پیش‌فرض و نمایش فیلدهای مورد نیاز
        add_filter('woocommerce_checkout_fields', array($this, 'customize_checkout_fields'), 20, 1);
        // billing fields در جای پیش‌فرض خود باقی می‌مانند (بالای صفحه)
        add_action('woocommerce_checkout_process', array($this, 'validate_destination_city'));
        add_action('woocommerce_checkout_update_order_meta', array($this, 'save_destination_city'));

        // محاسبه هزینه حمل‌ونقل
        add_filter('woocommerce_package_rates', array($this, 'calculate_shipping_rates'), 10, 2);

        // اضافه کردن هزینه حمل به فاکتور
        // استفاده از priority پایین برای اجرا بعد از سایر محاسبات
        add_action('woocommerce_cart_calculate_fees', array($this, 'add_shipping_fee_to_cart'), 20, 1);

        // همچنین در hook قبل از نمایش totals
        add_action('woocommerce_before_cart_totals', array($this, 'ensure_shipping_fee_calculated'), 5);

        // AJAX handlers
        add_action('wp_ajax_woo_excel_mng_calculate_price', array($this, 'ajax_calculate_price'));
        add_action('wp_ajax_nopriv_woo_excel_mng_calculate_price', array($this, 'ajax_calculate_price'));
        add_action('wp_ajax_woo_excel_mng_update_cart_item', array($this, 'ajax_update_cart_item'));
        add_action('wp_ajax_nopriv_woo_excel_mng_update_cart_item', array($this, 'ajax_update_cart_item'));
        add_action('wp_ajax_woo_excel_mng_save_destination_city', array($this, 'ajax_save_destination_city'));
        add_action('wp_ajax_nopriv_woo_excel_mng_save_destination_city', array($this, 'ajax_save_destination_city'));

        // تغییر label quantity در صفحه محصول برای محصولات با فرمول
        add_action('woocommerce_before_add_to_cart_quantity', array($this, 'add_quantity_label'), 10);

        // نمایش جمع وزن قبل از جمع کل سبد خرید
        add_action('woocommerce_cart_totals_before_order_total', array($this, 'render_total_weight_row'), 10);

        // اضافه کردن script برای تنظیم step و min در cart
        add_action('wp_footer', array($this, 'add_cart_quantity_script'), 99);

        // بارگذاری اسکریپت‌ها و استایل‌ها
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));

        // غیرفعال کردن کد تخفیف در سبد خرید
        add_filter('woocommerce_coupons_enabled', array($this, 'disable_cart_coupons'), 10, 1);

        // نمایش باکس حمل‌ونقل در سبد خرید و تسویه حساب
        add_action('woocommerce_after_cart_table', array($this, 'display_shipping_info_box'), 10);
        add_action('woocommerce_after_checkout_billing_form', array($this, 'display_shipping_info_box'), 10);
        add_filter('woocommerce_update_order_review_fragments', array($this, 'add_checkout_shipping_fragment'), 10, 1);

        // مخفی کردن حمل‌ونقل در سبد خرید
        add_filter('woocommerce_cart_needs_shipping', array($this, 'disable_cart_shipping_display'), 20, 1);
        add_filter('woocommerce_cart_totals_needs_shipping', array($this, 'disable_cart_shipping_display'), 20, 1);

        add_filter('woocommerce_add_cart_item_data', array($this, 'split_products_in_cart'), 10, 2);


        add_filter('woocommerce_after_add_to_cart_button', array($this, 'add_custom_quantity_field'));

        add_filter('woocommerce_add_cart_item_data', array($this, 'save_custom_quantity_to_cart'), 10, 3);

        add_action('woocommerce_checkout_create_order_line_item', array($this, 'add_custom_quantity_to_order_items'), 10, 4);
        add_filter('woocommerce_get_cart_item_from_session', array($this, 'get_cart_item_from_session'), 10, 2);

        add_action(
            'woocommerce_update_cart_action_cart_updated',
            array($this, 'handle_custom_quantity_update'),
            10,
            1
        );

        add_filter('woocommerce_cart_item_price', array($this, 'display_calculated_price'), 10, 3);
        add_filter('woocommerce_checkout_cart_item_quantity', array($this, 'display_checkout_item_quantity'), 10, 3);
    }

    public function display_calculated_price($price_html, $cart_item, $cart_item_key)
    {
         

        // اگر می‌خوای همیشه «قیمت پایه وارییشن» نشان داده بشه:
        if (isset($cart_item['data']) && is_a($cart_item['data'], 'WC_Product')) {
            // قیمت پایه (regular) وارییشن
            $base_price = $cart_item['data']->get_regular_price();

            // اگر در فرمول {base_price} هم همینو استفاده می‌کنی، این سازگارتره
            if ($base_price !== '') {
                return wc_price($base_price);
            }
        }

        // اگر product نبود یا قیمت نداشت، همون رفتار پیش‌فرض ووکامرس
        return $price_html;
    }

    // public function display_calculated_price($price, $cart_item, $cart_item_key)
    // {




    //     if (
    //         !isset($cart_item['data'])
    //         || !is_a($cart_item['data'], 'WC_Product')
    //     ) {
    //         return $price;
    //     }

    //     $product = $cart_item['data'];

    //     // --- گام 1: پیدا کردن ID محصولی که فرمول روی آن ذخیره شده است ---
    //     $product_with_formula_id = $product->get_id(); // پیش‌فرض: خود محصول

    //     // اگر محصول یک واریانت است، ID والدش را پیدا کن
    //     if ($product->is_type('variation')) {
    //         $product_with_formula_id = $product->get_parent_id();
    //         // var_dump($product_with_formula_id);
    //     }
    //     // -----------------------------------------------------------------

    //     // بررسی فرمول‌دار بودن محصول (با استفاده از ID درست)
    //     if (!method_exists($this, 'is_formula_product') || !$this->is_formula_product(wc_get_product($product_with_formula_id))) {
    //         return $price;
    //     }

    //     // گرفتن فرمول محصول (با استفاده از ID درست)
    //     $formula = Woo_Excel_Mng_Formulas::get_product_formula($product_with_formula_id);



    //     if (!$formula) {
    //         return $price;
    //     }



    //     // var_dump($cart_item['woo_excel_meterage']);
    //     $meterage = $cart_item['woo_excel_meterage'];
    //     $custom_qty = isset($cart_item['custom_quantity']) ? intval($cart_item['custom_quantity']) : 0;


    //     $variables = Woo_Excel_Mng_Formulas::get_variation_variables(
    //         $product->get_id(), // اینجا ID محصول *فعلی* در سبد (که می‌تواند واریانت باشد) را می‌دهیم
    //         $meterage,
    //         $custom_qty
    //     );












    //     // نکته: اگر get_variation_variables فقط با ID والد کار می‌کند،
    //     // و نمی‌تواند متغیرهای واریانت را تشخیص دهد، باید آن تابع را هم تغییر دهی.
    //     // اما معمولاً get_variation_variables باید با ID واریانت (اگر هست) یا ID محصول ساده کار کند
    //     // تا متغیرهای درست را استخراج کند.

    //     // ممکن است لازم باشد قیمت پایه (base_price) را جداگانه بگیریم اگر get_variation_variables آن را برنمی‌گرداند
    //     if (!isset($variables['base_price'])) {
    //         $variables['base_price'] = floatval($product->get_price()); // قیمت از خود واریانت
    //     }
    //     if (!isset($variables['weight'])) {
    //         $variables['weight'] = floatval($product->get_weight()); // وزن از خود واریانت
    //     }
    //     // -----------------------------------------------------------------


    //     $calculated_price = Woo_Excel_Mng_Formulas::calculate_price($formula, $variables);


    //     WC()->cart->cart_contents[$cart_item_key]['woo_excel_calculated_price'] = $calculated_price;


    //     return wc_price($calculated_price);
    // }



    public function handle_custom_quantity_update($cart_updated)
    {
        if (!isset($_POST['custom_quantity'])) {
            return $cart_updated;
        }

        foreach ($_POST['custom_quantity'] as $cart_item_key => $qty) {

            $qty = max(1, absint($qty));

            if (isset(WC()->cart->cart_contents[$cart_item_key])) {

                WC()->cart->cart_contents[$cart_item_key]['custom_quantity'] = $qty;
            }
        }

        WC()->cart->set_session();

        return $cart_updated;
    }


    public function display_editable_custom_quantity($product_quantity, $cart_item_key, $cart_item)
    {
        $product = isset($cart_item['data']) ? $cart_item['data'] : null;
        if (!$product || !$this->is_formula_product($product)) {
            return $product_quantity;
        }

        $custom_quantity = isset($cart_item['custom_quantity']) ? max(1, absint($cart_item['custom_quantity'])) : 1;

        $html  = '<div class="custom-quantity-in-cart woo-excel-cart-qty-cell">';
        $html .= '<input type="number" step="1" min="1" name="custom_quantity[' . esc_attr($cart_item_key) . ']" ';
        $html .= 'id="custom_quantity_' . esc_attr($cart_item_key) . '" value="' . esc_attr($custom_quantity) . '" ';
        $html .= 'class="input-text qty text woo-excel-custom-qty-input" aria-label="' . esc_attr__('تعداد', 'woo-excel-mng') . '" />';
        $html .= '<input type="hidden" name="cart[' . esc_attr($cart_item_key) . '][qty]" value="1" />';
        $html .= '</div>';

        return $html;
    }



    public function display_td($product_quantity, $cart_item_key, $cart_item)
    {
        $product = isset($cart_item['data']) ? $cart_item['data'] : null;
        if (!$product || !$this->is_formula_product($product)) {
            return $product_quantity;
        }

        $meterage = isset($cart_item[self::CART_ITEM_METERAGE_KEY]) ? floatval($cart_item[self::CART_ITEM_METERAGE_KEY]) : $this->get_meterage_min();
        if ($meterage < $this->get_meterage_min()) {
            $meterage = $this->get_meterage_min();
        }
        $meterage_formatted = woo_excel_mng_format_number($meterage, 2, '.', '');

        $new_column_html  = $product_quantity;
        $new_column_html .= '</td>';
        $new_column_html .= '<td class="product-meteraj" data-title="' . esc_attr__('متراژ', 'woo-excel-mng') . '">';
        $new_column_html .= '<div class="woo-excel-meterage-qty woo-excel-cart-meterage-cell">';
        $new_column_html .= '<input type="text" class="input-text qty text woo-excel-meterage-input" ';
        $new_column_html .= 'aria-label="' . esc_attr__('متراژ', 'woo-excel-mng') . '" ';
        $new_column_html .= 'name="' . esc_attr(self::CART_ITEM_METERAGE_KEY) . '[' . esc_attr($cart_item_key) . ']" ';
        $new_column_html .= 'id="woo-excel-meterage-' . esc_attr($cart_item_key) . '" ';
        $new_column_html .= 'value="' . esc_attr($meterage_formatted) . '" data-step="' . esc_attr($this->get_meterage_step()) . '" data-min="' . esc_attr($this->get_meterage_min()) . '" inputmode="decimal" />';
        $new_column_html .= '</div>';
        return $new_column_html;
    }









    public function save_custom_quantity_from_cart($cart_item_key, $values, $quantity)
    {
        // ذخیره متراژ سفارشی
        if (isset($_POST['custom_quantity'][$cart_item_key])) {
            $custom_quantity = sanitize_text_field($_POST['custom_quantity'][$cart_item_key]);
            WC()->cart->cart_contents[$cart_item_key]['custom_quantity'] = $custom_quantity;
        }

        // ذخیره شهر سفارشی (اگر در POST موجود باشد)
        // !!! فرض بر این است که نام فیلد POST برای شهر 'shipping_city' است. اگر متفاوت بود، اینجا اصلاح کنید. !!!
        if (isset($_POST['shipping_city'][$cart_item_key])) {
            $shipping_city = sanitize_text_field($_POST['shipping_city'][$cart_item_key]);
            WC()->cart->cart_contents[$cart_item_key]['shipping_city'] = $shipping_city;
        }

        // بروزرسانی سشن سبد خرید برای اعمال تغییرات
        WC()->cart->set_session();
    }


    public function add_custom_quantity_to_order_items($item, $cart_item_key, $values, $order)
    {
        if (isset($values['custom_quantity'])) {
            $item->add_meta_data('تعداد سفارشی', max(1, absint($values['custom_quantity'])), true);
        }
        if (isset($values[self::CART_ITEM_METERAGE_KEY])) {
            $item->add_meta_data('متراژ', woo_excel_mng_format_number(floatval($values[self::CART_ITEM_METERAGE_KEY]), 2, '.', ''), true);
        }
    }




    public function get_cart_item_from_session($cart_item, $values)
    {
        // اگر مقدار در سشن ذخیره شده بود، آن را به دیتای آیتم سبد خرید برگردان
        if (isset($values['custom_quantity'])) {
            $cart_item['custom_quantity'] = $values['custom_quantity'];
        }
        if (isset($values[self::CART_ITEM_METERAGE_KEY])) {
            $cart_item[self::CART_ITEM_METERAGE_KEY] = floatval($values[self::CART_ITEM_METERAGE_KEY]);
        }
        if (isset($values['woo_excel_decimal_qty'])) {
            $cart_item['woo_excel_decimal_qty'] = floatval($values['woo_excel_decimal_qty']);
        }

        return $cart_item;
    }




    public function add_custom_quantity_field()
    {
        $default_quantity = 1;
		echo '<style>
		span.meterage-label {
    padding: 17px;
}
.custom-quantity-field label {
    padding: 17px;
}
		</style>';
        echo '<div class="custom-quantity-field">';
        echo '<label for="custom_quantity">' . __(' تعداد :', 'textdomain') . '</label>';
        echo '<input type="number" id="custom_quantity" name="custom_quantity" value="' . esc_attr($default_quantity) . '" min="1" step="1" class="input-text qty text" style="text-align: center;
    width: 120px;
    margin-left: 6px;">';
        echo '</div>';
    }




    function save_custom_quantity_to_cart($cart_item_data, $product_id, $variation_id)
    {
        if (isset($_POST['custom_quantity']) && !empty($_POST['custom_quantity'])) {
            $quantity = absint($_POST['custom_quantity']);

            $cart_item_data['custom_quantity'] = $quantity;
        }
        return $cart_item_data;
    }







    public function split_products_in_cart($cart_item_data, $product_id)
    {
        // ایجاد یک شناسه منحصر به فرد برای هر بار کلیک روی دکمه خرید
        $unique_cart_item_key = md5(microtime() . rand());
        $cart_item_data['unique_key'] = $unique_cart_item_key;

        return $cart_item_data;
    }


    /**
     * بارگذاری فایل‌های CSS و JS
     */
    public function enqueue_frontend_assets()
    {
        // فقط در صفحات محصول و سبد خرید
        if (!is_product() && !is_cart() && !is_checkout()) {
            return;
        }

        $has_formula_product = false;
        if (function_exists('is_product') && is_product() && function_exists('wc_get_product')) {
            $product = wc_get_product(get_the_ID());
            if ($product instanceof WC_Product_Variation) {
                $parent_id = $product->get_parent_id();
                $has_formula_product = (bool) Woo_Excel_Mng_Formulas::get_product_formula($parent_id);
            } elseif ($product instanceof WC_Product) {
                $has_formula_product = (bool) Woo_Excel_Mng_Formulas::get_product_formula($product->get_id());
            }
        }

        wp_enqueue_style(
            'woo-excel-mng-frontend',
            WOO_EXCEL_MNG_PLUGIN_URL . 'frontend/assets/css/frontend.css',
            array(),
            WOO_EXCEL_MNG_VERSION
        );

        wp_enqueue_script(
            'woo-excel-mng-frontend',
            WOO_EXCEL_MNG_PLUGIN_URL . 'frontend/assets/js/frontend.js',
            array('jquery'),
            WOO_EXCEL_MNG_VERSION,
            true
        );

        $variation_matrix = array();
        if (is_product() && function_exists('wc_get_product')) {
            $product = wc_get_product(get_the_ID());
            if ($product && ($product->is_type('variable') || $product->is_type('variation'))) {
                $variation_matrix = Woo_Excel_Mng_Products::get_variation_matrix($product->get_id());
            }
        }

        wp_localize_script('woo-excel-mng-frontend', 'wooExcelMngFrontend', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('woo_excel_mng_frontend_nonce'),
            'has_formula_product' => $has_formula_product,
            'variation_matrix' => $variation_matrix,
            'attr_color' => Woo_Excel_Mng_Products::ATTR_COLOR,
            'attr_thickness' => Woo_Excel_Mng_Products::ATTR_THICKNESS,
            'meterage_min' => $this->get_meterage_min(),
            'meterage_step' => $this->get_meterage_step(),
            'decimal_qty_min' => 0.5,
            'decimal_qty_step' => 0.5,
            'is_product' => is_product(),
            'is_cart' => is_cart(),
            'is_checkout' => is_checkout(),
            'strings' => array(
                'enter_meterage' => __('لطفاً متراژ را وارد کنید.', 'woo-excel-mng'),
                'enter_quantity' => __('لطفاً تعداد معتبر وارد کنید (حداقل 0.5).', 'woo-excel-mng'),
                'calculating' => __('در حال محاسبه...', 'woo-excel-mng'),
                'meterage_header' => __('متراژ', 'woo-excel-mng'),
            )
        ));

        if (function_exists('is_cart') && is_cart() && $this->cart_has_formula_items()) {
            wp_add_inline_style('woo-excel-mng-frontend', '
                .woocommerce-cart-form__contents .woo-excel-meterage-qty label,
                .woocommerce-cart-form__contents .custom-quantity-in-cart label { display: none !important; }
                .woocommerce-cart-form__contents .product-name .woo-excel-meterage-display,
                .woocommerce-cart-form__contents .product-name .woo-excel-custom-qty-display { display: none !important; }
            ');
        }
    }

    /**
     * آیا این محصول/وارییشن دارای فرمول است؟
     */
    private function is_formula_product($product)
    {
        if (!$product) {
            return false;
        }

        if ($product->is_type('variation')) {
            $parent_id = $product->get_parent_id();
            return (bool) Woo_Excel_Mng_Formulas::get_product_formula($parent_id);
        }

        if ($product->is_type('variable')) {
            return (bool) Woo_Excel_Mng_Formulas::get_product_formula($product->get_id());
        }

        // برای سایر نوع‌ها، اگر فرمول تعریف شده باشد true است
        return (bool) Woo_Excel_Mng_Formulas::get_product_formula($product->get_id());
    }

    /**
     * نرمال‌سازی ورودی اعشاری (پشتیبانی از ارقام فارسی/عربی)
     */
    private function normalize_decimal_input($value)
    {
        if ($value === null) {
            return null;
        }

        $value = (string) $value;
        if ($value === '') {
            return '';
        }

        $value = str_replace(
            array('۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'),
            array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'),
            $value
        );

        // حذف جداکننده هزارگان و فاصله‌ها
        $value = str_replace(array('٬', ' '), '', $value);
        // نرمال‌سازی جداکننده اعشار
        $value = str_replace(array('٫', ','), '.', $value);

        return $value;
    }

    /**
     * حداقل متراژ مجاز
     */
    private function get_meterage_min()
    {
        $min = apply_filters('woo_excel_mng_meterage_min', self::METERAGE_MIN_DEFAULT);
        return max(0, floatval($min));
    }

    /**
     * گام افزایش متراژ
     */
    private function get_meterage_step()
    {
        $step = apply_filters('woo_excel_mng_meterage_step', self::METERAGE_STEP_DEFAULT);
        return max(0, floatval($step));
    }

    /**
     * نرمال‌سازی متراژ
     */
    private function normalize_meterage_value($value)
    {
        $meterage = floatval($value);
        return round($meterage, 2);
    }

    /**
     * اجازه ورود تعداد اعشاری (جلوگیری از تبدیل به integer توسط ووکامرس)
     */
    public function allow_decimal_stock_amount($val)
    {
        return floatval($val);
    }







    /**
     * محاسبه خلاصه هزینه حمل برای نمایش
     */
    private function get_shipping_summary($destination_city)
    {
        if (!$destination_city || !WC()->cart || WC()->cart->is_empty()) {
            return null;
        }

        // ابتدا داده‌های ذخیره شده در session را بررسی کن
        $shipping_data = WC()->session ? WC()->session->get('woo_excel_shipping_data', array()) : array();

        if (!empty($shipping_data)) {
            // از داده‌های session استفاده کن که قبلاً در add_shipping_fee_to_cart محاسبه شده
            return array(
                'vehicle' => $shipping_data['vehicle_name'],
                'shipping_cost' => $shipping_data['is_free'] ? 0 : $shipping_data['base_cost'],
                'is_free' => $shipping_data['is_free'],
                'target_amount' => isset($shipping_data['target_amount']) ? $shipping_data['target_amount'] : 0,
                'remaining' => isset($shipping_data['remaining']) ? $shipping_data['remaining'] : 0,
                'is_premium' => $shipping_data['is_premium'],
            );
        }

        // fallback به محاسبه مستقیم اگر session خالی بود
        $origin_city = get_option('woo_excel_mng_origin_city', 'تهران');
        $premium_threshold = floatval(get_option('woo_excel_mng_premium_threshold', 65000000));
        $shipping_percentage = floatval(get_option('woo_excel_mng_shipping_percentage', 2)) / 100;

        $total_weight = 0;
        $cart_total = 0;

        foreach (WC()->cart->get_cart() as $cart_item) {

            if (isset($cart_item['woo_excel_calculated_weight'])) {
                $total_weight += floatval($cart_item['woo_excel_calculated_weight']);
            } else {
                $product = $cart_item['data'];
                $meterage = isset($cart_item[self::CART_ITEM_METERAGE_KEY])
                    ? floatval($cart_item[self::CART_ITEM_METERAGE_KEY])
                    : (isset($cart_item['quantity']) ? floatval($cart_item['quantity']) : 1);
                $product_weight = floatval($product->get_weight());
                if ($product_weight > 0) {
                    $total_weight += $product_weight * $meterage;
                }
            }

            if (isset($cart_item['woo_excel_calculated_price'])) {
                $cart_total += floatval($cart_item['woo_excel_calculated_price']);
            } else {
                $item_price = floatval($cart_item['data']->get_price());
                $quantity = isset($cart_item['quantity']) ? floatval($cart_item['quantity']) : 1;
                $cart_total += $item_price * $quantity;
            }
        }

        if ($total_weight <= 0) {
            return null;
        }

        $max_meterage = $this->get_cart_max_meterage(WC()->cart->get_cart());
        $shipping_result = Woo_Excel_Mng_Shipping::calculate_shipping_cost(
            $origin_city,
            $destination_city,
            $total_weight,
            $max_meterage
        );

        if (!$shipping_result) {
            return null;
        }

        $base_cost = floatval($shipping_result['cost']);
        $vehicle = $shipping_result['vehicle'];
        $is_premium_mode = ($cart_total >= $premium_threshold);
        $shipping_cost = $base_cost;
        $is_free_shipping = false;
        $target_amount = 0;
        $remaining = 0;

        if ($is_premium_mode) {
            $shipping_percentage_amount = $cart_total * $shipping_percentage;
            if ($base_cost <= $shipping_percentage_amount) {
                $is_free_shipping = true;
                $shipping_cost = 0;
            } else {
                $target_amount = $base_cost / $shipping_percentage;
                $remaining = $target_amount - $cart_total;
            }
        }

        $vehicle_names = array(
            'peykan' => 'وانت',
            'mazda' => 'مزدا',
            'nissan' => 'نیسان'
        );
        $vehicle_name = isset($vehicle_names[$vehicle]) ? $vehicle_names[$vehicle] : ucfirst($vehicle);

        return array(
            'vehicle' => $vehicle_name,
            'shipping_cost' => $shipping_cost,
            'is_free' => $is_free_shipping,
            'target_amount' => $target_amount,
            'remaining' => $remaining,
            'is_premium' => $is_premium_mode,
        );
    }


    /**
     * دریافت گزینه‌های شهر مقصد
     */
    private function get_destination_city_options()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'woo_excel_shipping_routes';
        $cities = $wpdb->get_col("SELECT DISTINCT destination_city FROM $table_name WHERE is_active = 1 ORDER BY destination_city");

        $options = array('' => __('-- انتخاب شهر --', 'woo-excel-mng'));
        if (!empty($cities)) {
            foreach ($cities as $city) {
                $options[$city] = $city;
            }
        }

        return $options;
    }

    /**
     * بیشترین متراژ در سبد خرید
     */
    private function get_cart_max_meterage($cart_items)
    {
        $max_meterage = 0;

        foreach ($cart_items as $cart_item) {
            $meterage = 0;
            if (isset($cart_item[self::CART_ITEM_METERAGE_KEY])) {
                $meterage = floatval($cart_item[self::CART_ITEM_METERAGE_KEY]);
            } elseif (isset($cart_item['quantity'])) {
                $meterage = floatval($cart_item['quantity']);
            }

            if ($meterage > $max_meterage) {
                $max_meterage = $meterage;
            }
        }

        return $max_meterage;
    }

    /**
     * محاسبه کلید سبد خرید بدون در نظر گرفتن متراژ
     */
    public function filter_cart_id($cart_id, $product_id, $variation_id, $variation, $cart_item_data)
    {
        if (self::$skip_cart_id_filter) {
            return $cart_id;
        }

        if (!isset($cart_item_data[self::CART_ITEM_METERAGE_KEY]) && !isset($cart_item_data['woo_excel_decimal_qty'])) {
            return $cart_id;
        }

        $data = $cart_item_data;
        unset($data[self::CART_ITEM_METERAGE_KEY]);
        unset($data['woo_excel_unique']);
        unset($data['woo_excel_decimal_qty']);

        if (function_exists('WC') && WC()->cart) {
            try {
                self::$skip_cart_id_filter = true;
                $new_id = WC()->cart->generate_cart_id($product_id, $variation_id, $variation, $data);
                return $new_id;
            } finally {
                self::$skip_cart_id_filter = false;
            }
        }

        return $cart_id;
    }

    /**
     * ادغام متراژ/تعداد اعشاری هنگام افزودن به سبد خرید
     * هم برای محصولات فرمول‌دار (meterage) و هم غیر فرمول‌دار (decimal qty)
     */
    public function merge_meterage_on_add_to_cart($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data)
    {
        $actual_product_id = $variation_id ? $variation_id : $product_id;
        $product = wc_get_product($actual_product_id);
        if (!$product) {
            return;
        }

        $cart = WC()->cart;
        $cart_item = $cart ? $cart->get_cart_item($cart_item_key) : null;
        if (!$cart_item) {
            return;
        }

        $is_formula = $this->is_formula_product($product);

        if ($is_formula) {
            // === محصولات فرمول‌دار: متراژ ===
            $incoming_meterage = 0;
            if (isset($_REQUEST[self::CART_ITEM_METERAGE_KEY])) {
                $incoming_meterage = $this->normalize_meterage_value($this->normalize_decimal_input($_REQUEST[self::CART_ITEM_METERAGE_KEY]));
            } elseif (isset($_REQUEST['meterage'])) {
                $incoming_meterage = $this->normalize_meterage_value($this->normalize_decimal_input($_REQUEST['meterage']));
            }

            if ($incoming_meterage < $this->get_meterage_min()) {
                return;
            }

            // existing_meterage فقط اگر قبلاً آیتم وجود داشته باشد
            // (add_meterage_cart_item_data دیگر ذخیره نمی‌کند، پس existing=0 برای آیتم جدید)
            $existing_meterage = isset($cart_item[self::CART_ITEM_METERAGE_KEY])
                ? floatval($cart_item[self::CART_ITEM_METERAGE_KEY])
                : 0;

            $new_meterage = $existing_meterage > 0
                ? $existing_meterage + $incoming_meterage
                : $incoming_meterage;

            $cart->cart_contents[$cart_item_key][self::CART_ITEM_METERAGE_KEY] = $new_meterage;
            $cart->cart_contents[$cart_item_key]['quantity'] = 1;
        } else {
            // === محصولات غیر فرمول‌دار: تعداد اعشاری ===
            $incoming_qty = 0;
            if (isset($_REQUEST['woo_excel_decimal_qty'])) {
                $incoming_qty = floatval($this->normalize_decimal_input($_REQUEST['woo_excel_decimal_qty']));
            } elseif (isset($_REQUEST['quantity'])) {
                $incoming_qty = floatval($this->normalize_decimal_input($_REQUEST['quantity']));
            }

            if ($incoming_qty < 0.5) {
                return;
            }

            // existing فقط اگر قبلاً آیتم وجود داشته باشد (ادغام)
            $existing_decimal = isset($cart_item['woo_excel_decimal_qty'])
                ? floatval($cart_item['woo_excel_decimal_qty'])
                : 0;

            $new_qty = $existing_decimal > 0
                ? $existing_decimal + $incoming_qty
                : $incoming_qty;

            $cart->cart_contents[$cart_item_key]['quantity'] = $new_qty;
            $cart->cart_contents[$cart_item_key]['woo_excel_decimal_qty'] = $new_qty;
        }
    }

    /**
     * افزودن flag فرمول به داده‌های variation برای JS
     */
    public function add_variation_formula_flag($variation_data, $product, $variation)
    {
        try {
            $variation_data['woo_excel_has_formula'] = $this->is_formula_product($variation);
        } catch (\Throwable $e) {
            $variation_data['woo_excel_has_formula'] = false;
        }

        return $variation_data;
    }

    /**
     * ذخیره متراژ در cart item data هنگام add to cart
     * توجه: متراژ و تعداد اعشاری در merge_meterage_on_add_to_cart ذخیره می‌شوند
     * تا از دوبرابر شدن مقدار در افزودن اولیه جلوگیری شود.
     */
    public function add_meterage_cart_item_data($cart_item_data, $product_id, $variation_id)
    {
        // متراژ/تعداد اعشاری اکنون در merge_meterage_on_add_to_cart تنظیم می‌شوند
        return $cart_item_data;
    }

    /**
     * بازیابی متراژ از session
     */
    public function restore_meterage_cart_item_data($cart_item, $values, $cart_item_key)
    {
        if (isset($values[self::CART_ITEM_METERAGE_KEY])) {
            $cart_item[self::CART_ITEM_METERAGE_KEY] = floatval($values[self::CART_ITEM_METERAGE_KEY]);
        }
        if (isset($values['woo_excel_decimal_qty'])) {
            $cart_item['woo_excel_decimal_qty'] = floatval($values['woo_excel_decimal_qty']);
        }

        return $cart_item;
    }

    /**
     * برای محصولات دارای فرمول quantity را همیشه 1 می‌کنیم
     * برای غیر فرمول‌دار: مقدار اعشاری را حفظ می‌کنیم
     */
    public function force_quantity_one_for_formula($quantity, $product_id)
    {
        $product = wc_get_product($product_id);
        if (!$product) {
            return $quantity;
        }

        // فقط برای فرمول‌دار: qty=1
        if ($this->is_formula_product($product)) {
            return 1;
        }

        // برای غیر فرمول‌دار: اجازه اعشاری
        // بررسی کن آیا مقدار اعشاری ارسال شده
        if (isset($_REQUEST['woo_excel_decimal_qty'])) {
            $decimal_qty = floatval($this->normalize_decimal_input($_REQUEST['woo_excel_decimal_qty']));
            if ($decimal_qty >= 0.5) {
                return $decimal_qty;
            }
        }

        // اگر quantity رشته‌ای با اعشار است
        if (isset($_REQUEST['quantity'])) {
            $qty_raw = $this->normalize_decimal_input($_REQUEST['quantity']);
            $qty = floatval($qty_raw);
            if ($qty >= 0.5) {
                return $qty;
            }
        }

        return $quantity;
    }

    /**
     * رندر ورودی متراژ در cart کلاسیک (به جای quantity)
     * و همزمان qty واقعی را به صورت hidden روی 1 نگه می‌دارد.
     */
    public function render_meterage_input_in_cart($product_quantity, $cart_item_key, $cart_item)
    {
        return $product_quantity;
    }

    /**
     * نمایش تعداد واقعی (custom_quantity) در Checkout
     */
    public function display_checkout_item_quantity($quantity_html, $cart_item, $cart_item_key)
    {
        $product = isset($cart_item['data']) ? $cart_item['data'] : null;
        if (!$product || !$this->is_formula_product($product)) {
            return $quantity_html;
        }

        $custom_quantity = isset($cart_item['custom_quantity']) ? max(1, absint($cart_item['custom_quantity'])) : 1;
        return sprintf(' <strong class="product-quantity">&times;&nbsp;%s</strong>', esc_html($custom_quantity));
    }

    /**
     * پردازش متراژ و تعداد اعشاری از فرم Cart کلاسیک
     */
    public function handle_meterage_update_from_post($cart_updated)
    {
        if (!function_exists('is_cart') || !is_cart()) {
            return $cart_updated;
        }

        $cart = WC()->cart;
        if (!$cart) {
            return $cart_updated;
        }

        // پردازش متراژ برای محصولات فرمول‌دار
        if (isset($_POST[self::CART_ITEM_METERAGE_KEY]) && is_array($_POST[self::CART_ITEM_METERAGE_KEY])) {
            foreach ($_POST[self::CART_ITEM_METERAGE_KEY] as $cart_item_key => $meterage_raw) {
                $cart_item_key = sanitize_text_field($cart_item_key);
                $meterage_raw = $this->normalize_decimal_input($meterage_raw);
                $meterage = $this->normalize_meterage_value($meterage_raw);

                if ($meterage < $this->get_meterage_min()) {
                    continue;
                }

                $cart_item = $cart->get_cart_item($cart_item_key);
                if (!$cart_item) {
                    continue;
                }

                $product = isset($cart_item['data']) ? $cart_item['data'] : null;
                if (!$product || !$this->is_formula_product($product)) {
                    continue;
                }

                $cart->cart_contents[$cart_item_key][self::CART_ITEM_METERAGE_KEY] = $meterage;
                $cart->cart_contents[$cart_item_key]['quantity'] = 1;
            }
        }

        // پردازش تعداد اعشاری برای محصولات غیر فرمول‌دار
        if (isset($_POST['cart']) && is_array($_POST['cart'])) {
            foreach ($_POST['cart'] as $cart_item_key => $cart_data) {
                if (!isset($cart_data['qty'])) {
                    continue;
                }

                $cart_item_key = sanitize_text_field($cart_item_key);
                $cart_item = $cart->get_cart_item($cart_item_key);
                if (!$cart_item) {
                    continue;
                }

                $product = isset($cart_item['data']) ? $cart_item['data'] : null;
                // فقط برای محصولات غیر فرمول‌دار
                if ($product && $this->is_formula_product($product)) {
                    continue;
                }

                $qty_raw = $this->normalize_decimal_input($cart_data['qty']);
                $qty = floatval($qty_raw);

                if ($qty < 0.5) {
                    continue;
                }

                $cart->cart_contents[$cart_item_key]['quantity'] = $qty;
                $cart->cart_contents[$cart_item_key]['woo_excel_decimal_qty'] = $qty;
            }
        }

        return $cart_updated;
    }

    /**
     * اگر Cart/Checkout با Blocks ساخته شده باشد، به کلاسیک تبدیل کن.
     */
    public function force_classic_cart_for_blocks($content)
    {
        if (function_exists('is_cart') && is_cart()) {
            if (function_exists('has_block') && has_block('woocommerce/cart', $content)) {
                return do_shortcode('[woocommerce_cart]');
            }
        }

        if (function_exists('is_checkout') && is_checkout()) {
            if (function_exists('has_block') && has_block('woocommerce/checkout', $content)) {
                return do_shortcode('[woocommerce_checkout]');
            }
        }

        return $content;
    }

    /**
     * تغییر متن دکمه افزودن به سبد
     */
    public function change_add_to_cart_text($text)
    {
        global $product;
        if ($product && $product->is_type('variable')) {
            $formula = Woo_Excel_Mng_Formulas::get_product_formula($product->get_id());
            if ($formula) {
                return __('افزودن به سبد خرید', 'woo-excel-mng');
            }
        }
        return $text;
    }

    /**
     * تغییر تنظیمات فیلد quantity برای پشتیبانی از اعشاری
     * برای همه محصولات step=0.5 و برای فرمول‌دار تنظیمات خاص
     */
    public function change_quantity_label($args, $product)
    {
        // برای همه محصولات: اجازه ورود اعشاری با step=0.5
        $args['min_value'] = 0.5;
        $args['step'] = 0.5;
        $args['inputmode'] = 'decimal';

        $target_product_id = 0;
        if ($product instanceof WC_Product_Variation) {
            $target_product_id = $product->get_parent_id();
        } elseif ($product instanceof WC_Product) {
            $target_product_id = $product->get_id();
        } elseif (function_exists('is_product') && is_product()) {
            $target_product_id = get_the_ID();
        }

        $has_formula = false;
        if ($target_product_id) {
            $has_formula = (bool) Woo_Excel_Mng_Formulas::get_product_formula($target_product_id);
        }

        if ($has_formula) {
            $args['input_name'] = 'quantity'; 					
            $args['min_value'] = $this->get_meterage_min();
            $args['step'] = $this->get_meterage_step();
            if (!isset($args['classes'])) {
                $args['classes'] = array();
            }
            $args['classes'][] = 'woo-excel-meterage-quantity';
        }
        return $args;
    }

    /**
     * رندر ورودی سفارشی quantity برای همه محصولات (اعشاری)
     * برای فرمول‌دار: متراژ + hidden qty=1
     * برای غیر فرمول‌دار: quantity اعشاری با step=0.5
     */
    public function render_custom_quantity_input($html, $product = null, $args = array())
    {
        if (!function_exists('is_product') || !is_product()) {
            return $html;
        }

        if (!is_array($args)) {
            $args = array();
        }

        $product_id = 0;
        if ($product instanceof WC_Product_Variation) {
            $product_id = $product->get_parent_id();
        } elseif ($product instanceof WC_Product) {
            $product_id = $product->get_id();
        } else {
            $product_id = get_the_ID();
        }

        $has_formula = $product_id ? (bool) Woo_Excel_Mng_Formulas::get_product_formula($product_id) : false;

        if ($has_formula) {
            // محصولات فرمول‌دار: ورودی متراژ + hidden qty=1
            $input_id = isset($args['input_id']) ? $args['input_id'] : 'woo_excel_meterage';
            $input_value = isset($args['input_value']) ? $args['input_value'] : 1;
            $input_value = $input_value ? $input_value : 1;
            $min_value = $this->get_meterage_min();
            $step_value = $this->get_meterage_step();

            $label = esc_html__('متراژ (متر)', 'woo-excel-mng');
            $html  = '<div class="quantity">';
            $html .= '<label class="" for="' . esc_attr($input_id) . '">' . $label . '</label>';
            $html .= '<input type="text" id="' . esc_attr($input_id) . '" class="input-text qty text woo-excel-meterage-quantity" ';
            $html .= 'name="woo_excel_meterage" value="' . esc_attr($input_value) . '" ';
            $html .= 'inputmode="decimal" autocomplete="off" data-min="' . esc_attr($min_value) . '" data-step="' . esc_attr($step_value) . '" />';
            $html .= '<input type="hidden" name="quantity" value="1" />';
            $html .= '</div>';
        } else {
            // محصولات غیر فرمول‌دار: quantity اعشاری
            $input_id = isset($args['input_id']) ? $args['input_id'] : uniqid('quantity_');
            $input_value = isset($args['input_value']) ? $args['input_value'] : 1;
            $input_value = $input_value ? $input_value : 1;

            $html  = '<div class="quantity">سسسسسسسسسس';
            $html .= '<label class="" for="' . esc_attr($input_id) . '">' . esc_html__('تعداد', 'woo-excel-mng') . '</label>';
            $html .= '<input type="text" id="' . esc_attr($input_id) . '" class="input-text qty text woo-excel-decimal-qty" ';
            $html .= 'name="quantity" value="' . esc_attr($input_value) . '" ';
            $html .= 'inputmode="decimal" autocomplete="off" step="0.5" min="0.5" />';
            $html .= '</div>';
        }

        return $html;
    }

    /**
     * تغییر quantity input در سبد خرید برای همه محصولات (اعشاری)
     */
    public function change_cart_quantity_input($args, $product)
    {
        if (!is_cart()) {
            return $args;
        }

        // برای همه محصولات در سبد خرید: step=0.5
        $args['min_value'] = 0.5;
        $args['step'] = 0.5;

        // برای محصولات فرمول‌دار: تنظیمات خاص متراژ
        if ($product && $product->is_type('variation')) {
            $parent_id = $product->get_parent_id();
            if (Woo_Excel_Mng_Formulas::get_product_formula($parent_id)) {
                $args['min_value'] = $this->get_meterage_min();
                $args['step'] = $this->get_meterage_step();
            }
        }

        return $args;
    }

    /**
     * آیا سبد خرید شامل محصول فرمول‌دار است؟
     */
    private function cart_has_formula_items()
    {
        if (!function_exists('WC') || !WC()->cart || WC()->cart->is_empty()) {
            return false;
        }

        foreach (WC()->cart->get_cart() as $cart_item) {
            $product = isset($cart_item['data']) ? $cart_item['data'] : null;
            if ($product && $this->is_formula_product($product)) {
                return true;
            }
        }

        return false;
    }

    /**
     * شروع بافر HTML جدول سبد برای درج ستون متراژ در thead
     */
    public function buffer_cart_table_start()
    {
        if (!function_exists('is_cart') || !is_cart() || !$this->cart_has_formula_items()) {
            return;
        }

        ob_start();
    }

    /**
     * پایان بافر و درج <th>متراژ</th> + اصلاح colspan
     */
    public function buffer_cart_table_end()
    {
        if (!ob_get_level()) {
            return;
        }

        $html = ob_get_clean();
        if ($html === false || $html === '') {
            echo $html;
            return;
        }

        if (strpos($html, 'product-meteraj') === false) {
            $meterage_th = '<th scope="col" class="product-meteraj">' . esc_html__('متراژ', 'woo-excel-mng') . '</th>';
            $html = preg_replace(
                '/(<th[^>]*\bproduct-quantity\b[^>]*>.*?<\/th>)/is',
                '$1' . $meterage_th,
                $html,
                1
            );
        }

        if (preg_match('/<thead\b[^>]*>.*?<\/thead>/is', $html, $thead_match)) {
            preg_match_all('/<th\b/i', $thead_match[0], $th_matches);
            $col_count = isset($th_matches[0]) ? count($th_matches[0]) : 0;

            if ($col_count > 0) {
                if (preg_match('/<td\b[^>]*\bclass="[^"]*actions[^"]*"[^>]*\bcolspan="(\d+)"/i', $html)) {
                    $html = preg_replace(
                        '/(<td\b[^>]*\bclass="[^"]*actions[^"]*"[^>]*\bcolspan=")(\d+)(")/i',
                        '${1}' . $col_count . '${3}',
                        $html,
                        1
                    );
                } else {
                    $html = preg_replace(
                        '/(<td\b[^>]*\bclass="[^"]*actions[^"]*")/i',
                        '$1 colspan="' . $col_count . '"',
                        $html,
                        1
                    );
                }
            }
        }

        echo $html;
    }

    /**
     * نمایش جمع وزن زیر فاکتور
     */
    public function render_total_weight_row()
    {
        if (!WC()->cart || WC()->cart->is_empty()) {
            return;
        }

        $total_weight = 0;
        foreach (WC()->cart->get_cart() as $cart_item) {
            if (isset($cart_item['woo_excel_calculated_weight'])) {
                $total_weight += floatval($cart_item['woo_excel_calculated_weight']);
				             

                continue;
            }

            $product = isset($cart_item['data']) ? $cart_item['data'] : null;
            if (!$product) {
                continue;
            }

            $meterage = isset($cart_item[self::CART_ITEM_METERAGE_KEY])
                ? floatval($cart_item[self::CART_ITEM_METERAGE_KEY])
                : (isset($cart_item['quantity']) ? floatval($cart_item['quantity']) : 1);

            $product_weight = floatval($product->get_weight());
            if ($product_weight > 0) {
                $total_weight += $product_weight * $meterage;
            }
        }

        echo '<tr class="woo-excel-total-weight">';
        echo '<th>' . esc_html__('جمع وزن', 'woo-excel-mng') . '</th>';
        echo '<td data-title="' . esc_attr__('جمع وزن', 'woo-excel-mng') . '">' . wc_format_weight($total_weight) . '</td>';
        echo '</tr>';
    }

    /**
     * نمایش بلاک اطلاعات حمل‌ونقل
     * سبد خرید: پیام کوتاه / تسویه حساب: فرم کامل (داخل باکس billing)
     */
    public function display_shipping_info_box()
    {
        if (!WC()->cart || WC()->cart->is_empty()) {
            return;
        }

        if (function_exists('is_cart') && is_cart()) {
            // سبد خرید: پیام کوتاه
            echo '<div class="woo-excel-shipping-info-box woo-excel-shipping-minimal">';
            echo '<h3>' . esc_html__('اطلاعات حمل‌ونقل', 'woo-excel-mng') . '</h3>';
            echo '<div class="woo-excel-shipping-note">';
            echo '<span class="dashicons dashicons-info"></span>';
            echo '<p>' . esc_html__('هزینه حمل در مرحله بعد محاسبه می‌شود.', 'woo-excel-mng') . '</p>';
            echo '</div></div>';
            return;
        }

        // تسویه حساب: فرم کامل (داخل باکس billing)
        $this->render_checkout_shipping_inline();
    }

    /**
     * رندر اطلاعات حمل‌ونقل به صورت inline در باکس billing (تسویه حساب)
     */
    private function render_checkout_shipping_inline()
    {
        if (!WC()->cart || WC()->cart->is_empty()) {
            return;
        }

        $destination_city = WC()->session ? WC()->session->get('woo_excel_destination_city', '') : '';
        $city_options = $this->get_destination_city_options();
        if (count($city_options) <= 1) {
            return;
        }

        $shipping_summary = $destination_city ? $this->get_shipping_summary($destination_city) : null;

        // دریافت داده‌های ذخیره شده در session برای اطلاعات بیشتر
        $shipping_data = WC()->session ? WC()->session->get('woo_excel_shipping_data', array()) : array();
?>
        <div id="woo-excel-checkout-shipping-inline" class="woo-excel-shipping-inline">
            <h4><?php _e('اطلاعات حمل‌ونقل', 'woo-excel-mng'); ?></h4>

            <!-- فیلد شهر مقصد با کلاس col-12 برای تمام عرض -->
            <p class="form-row form-row-wide woo-excel-city-select col-12" id="woo_excel_destination_city_field">
                <label for="woo_excel_destination_city"><?php _e('شهر مقصد', 'woo-excel-mng'); ?> <abbr class="required" title="<?php _e('الزامی', 'woo-excel-mng'); ?>">*</abbr></label>
                <select name="woo_excel_destination_city" id="woo_excel_destination_city" class="select">
                    <?php foreach ($city_options as $value => $label): ?>
                        <option value="<?php echo esc_attr($value); ?>" <?php selected($destination_city, $value); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </p>

            <?php if ($destination_city && $shipping_summary): ?>
                <div class="woo-excel-shipping-result">
                    <?php if ($shipping_summary['is_free']): ?>
                        <div class="woo-excel-free-badge">
                            <span class="dashicons dashicons-yes-alt"></span>
                            <strong><?php _e('حمل رایگان!', 'woo-excel-mng'); ?></strong>
                        </div>
                    <?php else: ?>
                        <div class="woo-excel-shipping-cost-info">
                            <span class="label"><?php _e('نوع وسیله:', 'woo-excel-mng'); ?></span>
                            <span class="value"><?php echo esc_html($shipping_summary['vehicle']); ?></span>
                        </div>
                        <div class="woo-excel-shipping-cost-info">
                            <span class="label"><?php _e('هزینه حمل:', 'woo-excel-mng'); ?></span>
                            <span class="value cost"><?php echo woo_excel_mng_format_price($shipping_summary['shipping_cost']); ?></span>
                        </div>

                        <?php
                        // نمایش پیام پیشنهاد در حالت Premium
                        if (!empty($shipping_data) && $shipping_data['is_premium'] && !$shipping_data['is_free']):

                            $message_type = isset($shipping_data['message_type']) ? $shipping_data['message_type'] : 'normal';
                            $target = isset($shipping_data['target_amount']) ? $shipping_data['target_amount'] : 0;
                            $remaining = isset($shipping_data['remaining']) ? $shipping_data['remaining'] : 0;

                            // اگر باقیمانده منفی است (خطای محاسباتی)، صفر کن
                            if ($remaining < 0) {
                                $remaining = 0;
                            }

                            // تعیین پیام مناسب بر اساس مقدار باقیمانده
                            if ($message_type == 'free') {
                                // این حالت نباید پیش بیاید چون is_free قبلاً true شده
                            } elseif ($remaining < 1000) {
                                // کمتر از 1000 تومان - خیلی نزدیک
                                $formatted_remaining = number_format($remaining) . ' ' . __('ریال', 'woo-excel-mng');
                                $message = sprintf(
                                    __('با خرید %s دیگر به حمل رایگان می‌رسید!', 'woo-excel-mng'),
                                    '<strong>' . $formatted_remaining . '</strong>'
                                );
                            } elseif ($remaining < 100000) {
                                // کمتر از 100,000 تومان
                                $formatted_target = woo_excel_mng_format_price($target);
                                $formatted_remaining = woo_excel_mng_format_price($remaining);
                                $message = sprintf(
                                    __('با خرید %s دیگر به حمل رایگان می‌رسید!', 'woo-excel-mng'),
                                    '<strong>' . $formatted_remaining . '</strong>'
                                );
                            } else {
                                // بیشتر از 100,000 تومان
                                $formatted_target = woo_excel_mng_format_price($target);
                                $formatted_remaining = woo_excel_mng_format_price($remaining);
                                $message = sprintf(
                                    __('اگر خرید خود را به %s برسانید، حمل رایگان می‌شود!', 'woo-excel-mng'),
                                    '<strong>' . $formatted_target . '</strong>'
                                );
                            }
                        ?>
                            <div class="woo-excel-premium-suggestion">
                                <p class="premium-notice"><strong><?php _e('💡 پیشنهاد:', 'woo-excel-mng'); ?></strong></p>
                                <p><?php echo $message; ?></p>

                                <?php if (!empty($shipping_data['vehicle_by_weight']) && !empty($shipping_data['vehicle_by_meterage']) && $shipping_data['vehicle_by_meterage'] != $shipping_data['vehicle_by_weight']): ?>
                                    <p class="vehicle-note">
                                        <small><?php _e('(توجه: وسیله نقلیه بر اساس متراژ انتخاب شده است)', 'woo-excel-mng'); ?></small>
                                    </p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php elseif (!$destination_city): ?>
                <p class="woo-excel-select-notice"><?php _e('لطفاً شهر مقصد را انتخاب کنید.', 'woo-excel-mng'); ?></p>
            <?php endif; ?>
        </div>
    <?php
    }




    /**
     * رندر HTML باکس حمل‌ونقل تسویه حساب (قابل استفاده در fragments)
     */
    private function render_checkout_shipping_html()
    {
        if (!WC()->cart || WC()->cart->is_empty()) {
            return;
        }

        $origin_city = get_option('woo_excel_mng_origin_city', 'تهران');
        $premium_threshold = floatval(get_option('woo_excel_mng_premium_threshold', 65000000));
        $shipping_percentage = floatval(get_option('woo_excel_mng_shipping_percentage', 2)) / 100;
        $destination_city = WC()->session ? WC()->session->get('woo_excel_destination_city', '') : '';

        $city_options = $this->get_destination_city_options();
        if (count($city_options) <= 1) {
            return;
        }

        // محاسبه وزن و قیمت
        $total_weight = 0;
        $cart_total = 0;

        foreach (WC()->cart->get_cart() as $cart_item) {
            $product = $cart_item['data'];
            $meterage = isset($cart_item[self::CART_ITEM_METERAGE_KEY])
                ? floatval($cart_item[self::CART_ITEM_METERAGE_KEY])
                : (isset($cart_item['quantity']) ? floatval($cart_item['quantity']) : 1);

            if (isset($cart_item['woo_excel_calculated_weight'])) {
                $total_weight += floatval($cart_item['woo_excel_calculated_weight']);
            } else {
                $total_weight += floatval($product->get_weight()) * $meterage;
            }

            if (isset($cart_item['woo_excel_calculated_price'])) {
                $cart_total += floatval($cart_item['woo_excel_calculated_price']);
            } else {
                $quantity = isset($cart_item['quantity']) ? floatval($cart_item['quantity']) : 1;
                $cart_total += floatval($product->get_price()) * $quantity;
            }
        }

        $max_meterage = $this->get_cart_max_meterage(WC()->cart->get_cart());

        // محاسبه هزینه حمل
        $shipping_cost = 0;
        $vehicle = '';
        $vehicle_name = '';
        $is_free_shipping = false;
        $is_premium_mode = ($cart_total >= $premium_threshold);
        $target_amount = 0;
        $shipping_percentage_amount = 0;
        $base_shipping_cost = 0;
        $vehicle_upgrade_notice = '';

        if ($destination_city && $total_weight > 0) {
            $shipping_result = Woo_Excel_Mng_Shipping::calculate_shipping_cost(
                $origin_city,
                $destination_city,
                $total_weight,
                $max_meterage
            );

            if ($shipping_result) {
                $base_shipping_cost = floatval($shipping_result['cost']);
                $vehicle = $shipping_result['vehicle'];

                $vehicle_names = array('peykan' => 'وانت', 'mazda' => 'مزدا', 'nissan' => 'نیسان');
                $vehicle_name = isset($vehicle_names[$vehicle]) ? $vehicle_names[$vehicle] : ucfirst($vehicle);

                if (!empty($shipping_result['upgraded_by_meterage'])) {
                    $from_vehicle = isset($vehicle_names[$shipping_result['vehicle_by_weight']]) ? $vehicle_names[$shipping_result['vehicle_by_weight']] : ucfirst($shipping_result['vehicle_by_weight']);
                    $to_vehicle = isset($vehicle_names[$shipping_result['vehicle_by_meterage']]) ? $vehicle_names[$shipping_result['vehicle_by_meterage']] : ucfirst($shipping_result['vehicle_by_meterage']);
                    $vehicle_upgrade_notice = sprintf(
                        __('به دلیل بیشترین متراژ آیتم‌ها (%s متر)، نوع خودرو از %s به %s تغییر کرد.', 'woo-excel-mng'),
                        woo_excel_mng_format_number($max_meterage, 2, '.', ''),
                        $from_vehicle,
                        $to_vehicle
                    );
                }

                if ($is_premium_mode) {
                    $shipping_percentage_amount = $cart_total * $shipping_percentage;
                    if ($base_shipping_cost <= $shipping_percentage_amount) {
                        $is_free_shipping = true;
                        $shipping_cost = 0;
                    } else {
                        $target_amount = $base_shipping_cost / $shipping_percentage;
                        $shipping_cost = $base_shipping_cost;
                    }
                } else {
                    $shipping_cost = $base_shipping_cost;
                }
            }
        }

    ?>
        <div id="woo-excel-checkout-shipping-box" class="woo-excel-shipping-info-box woo-excel-shipping-payment-box woo-excel-checkout-section">
            <h3><?php _e('اطلاعات حمل‌ونقل', 'woo-excel-mng'); ?></h3>

            <div class="woo-excel-destination-selector">
                <?php
                woocommerce_form_field('woo_excel_destination_city', array(
                    'type' => 'select',
                    'class' => array('woo-excel-city-select'),
                    'label' => __('شهر مقصد', 'woo-excel-mng'),
                    'required' => true,
                    'options' => $city_options,
                ), $destination_city);
                ?>
            </div>

            <?php if ($destination_city): ?>
                <div class="woo-excel-shipping-details">
                    <?php if ($vehicle_upgrade_notice): ?>
                        <div class="woo-excel-vehicle-change-alert">
                            <strong><?php _e('تغییر نوع خودرو', 'woo-excel-mng'); ?></strong>
                            <p><?php echo esc_html($vehicle_upgrade_notice); ?></p>
                        </div>
                    <?php endif; ?>
                    <?php if ($is_free_shipping): ?>
                        <div class="woo-excel-free-shipping-badge">
                            <span class="dashicons dashicons-yes-alt"></span>
                            <strong><?php _e('حمل رایگان!', 'woo-excel-mng'); ?></strong>
                            <?php if ($is_premium_mode): ?>
                                <p><?php printf(__('هزینه حمل (%s) کمتر از %s%% مبلغ فاکتور (%s) است.', 'woo-excel-mng'), woo_excel_mng_format_price($base_shipping_cost), number_format($shipping_percentage * 100, 1), woo_excel_mng_format_price($shipping_percentage_amount)); ?></p>
                            <?php else: ?>
                                <p><?php _e('حمل شما رایگان است.', 'woo-excel-mng'); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <?php if ($vehicle && $base_shipping_cost > 0): ?>
                            <div class="woo-excel-shipping-info">
                                <p><strong><?php _e('نوع وسیله:', 'woo-excel-mng'); ?></strong> <span class="vehicle-name"><?php echo esc_html($vehicle_name); ?></span></p>
                                <p><strong><?php _e('هزینه حمل:', 'woo-excel-mng'); ?></strong> <span class="shipping-cost"><?php echo woo_excel_mng_format_price($shipping_cost); ?></span></p>

                                <?php if ($is_premium_mode && $target_amount > 0): ?>
                                    <div class="woo-excel-premium-suggestion">
                                        <p class="premium-notice"><strong><?php _e('💡 پیشنهاد:', 'woo-excel-mng'); ?></strong></p>
                                        <p><?php printf(__('اگر خرید خود را به %s برسانید، حمل رایگان می‌شود!', 'woo-excel-mng'), '<strong>' . woo_excel_mng_format_price($target_amount) . '</strong>'); ?></p>
                                        <p class="premium-remaining"><?php printf(__('%s دیگر تا حمل رایگان', 'woo-excel-mng'), '<strong>' . woo_excel_mng_format_price($target_amount - $cart_total) . '</strong>'); ?></p>
                                    </div>
                                <?php elseif (!$is_premium_mode): ?>
                                    <?php
                                    $free_shipping_threshold = floatval(get_option('woo_excel_mng_free_shipping_threshold', 20000000));
                                    if ($free_shipping_threshold > 0):
                                        $remaining = $free_shipping_threshold - $cart_total;
                                        $percentage = min(100, max(0, ($cart_total / $free_shipping_threshold) * 100));
                                    ?>
                                        <div class="woo-excel-free-shipping-progress">
                                            <p><?php printf(__('%s دیگر تا حمل رایگان', 'woo-excel-mng'), woo_excel_mng_format_price($remaining)); ?></p>
                                            <div class="woo-excel-progress-bar">
                                                <div class="woo-excel-progress-fill" style="width: <?php echo esc_attr($percentage); ?>%"></div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <p class="woo-excel-no-route"><?php _e('مسیر حمل‌ونقل برای این شهر یافت نشد.', 'woo-excel-mng'); ?></p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="woo-excel-select-city-notice">
                    <p><?php _e('لطفاً شهر مقصد را انتخاب کنید تا هزینه حمل محاسبه شود.', 'woo-excel-mng'); ?></p>
                </div>
            <?php endif; ?>
        </div>
    <?php
    }

    /**
     * حفظ مقدار اعشاری quantity در cart
     */
    public function preserve_decimal_quantity($quantity, $cart_item_key, $cart_item)
    {
        $product = $cart_item['data'];
        if ($product && $product->is_type('variation')) {
            $parent_id = $product->get_parent_id();
            $formula = Woo_Excel_Mng_Formulas::get_product_formula($parent_id);
            if ($formula) {
                // اطمینان از اینکه quantity به صورت float ذخیره می‌شود
                return floatval($quantity);
            }
        }
        return $quantity;
    }

    /**
     * بعد از به‌روزرسانی quantity در cart
     */
    public function after_cart_item_quantity_update($cart_item_key, $quantity, $old_quantity, $cart)
    {
        $cart_item = $cart->get_cart_item($cart_item_key);
        if (!$cart_item) {
            return;
        }

        $product = $cart_item['data'];
        if ($product && $product->is_type('variation')) {
            $parent_id = $product->get_parent_id();
            $formula = Woo_Excel_Mng_Formulas::get_product_formula($parent_id);
            if ($formula) {
                // اطمینان از اینکه quantity به صورت float ذخیره می‌شود
                $cart->cart_contents[$cart_item_key]['quantity'] = floatval($quantity);
            }
        }
    }

    /**
     * حفظ مقدار اعشاری quantity در update cart
     */
    public function preserve_decimal_in_cart_update($cart_updated)
    {
        if (!isset($_POST['cart']) || !is_array($_POST['cart'])) {
            return $cart_updated;
        }

        $cart = WC()->cart;

        foreach ($_POST['cart'] as $cart_item_key => $cart_item_data) {
            if (!isset($cart_item_data['qty'])) {
                continue;
            }

            $cart_item = $cart->get_cart_item($cart_item_key);
            if (!$cart_item) {
                continue;
            }

            $product = $cart_item['data'];
            if ($product && $product->is_type('variation')) {
                $parent_id = $product->get_parent_id();
                $formula = Woo_Excel_Mng_Formulas::get_product_formula($parent_id);
                if ($formula) {
                    // تبدیل quantity به float قبل از ذخیره
                    $quantity = floatval($cart_item_data['qty']);
                    // ذخیره مستقیم در cart_contents
                    $cart->cart_contents[$cart_item_key]['quantity'] = $quantity;
                }
            }
        }

        return $cart_updated;
    }

    /**
     * حفظ مقدار اعشاری quantity در WooCommerce Blocks REST API
     */
    public function rest_cart_item_quantity($quantity, $cart_item, $cart_item_key)
    {
        $product = $cart_item['data'];
        if ($product && $product->is_type('variation')) {
            $parent_id = $product->get_parent_id();
            $formula = Woo_Excel_Mng_Formulas::get_product_formula($parent_id);
            if ($formula) {
                // در Blocks quantity باید integer باشد
                return 1;
            }
        }
        return $quantity;
    }

    /**
     * حفظ داده‌های اعشاری در WooCommerce Blocks REST API
     */
    public function rest_cart_item_data($cart_item_data, $cart_item)
    {
        $product = $cart_item['data'];
        if ($product && $product->is_type('variation')) {
            $parent_id = $product->get_parent_id();
            $formula = Woo_Excel_Mng_Formulas::get_product_formula($parent_id);
            if ($formula && isset($cart_item['quantity'])) {
                // در Blocks quantity باید integer باشد
                $cart_item_data['quantity'] = 1;
                // متراژ را هم به خروجی اضافه کن (برای استفاده‌های احتمالی در آینده)
                if (isset($cart_item[self::CART_ITEM_METERAGE_KEY])) {
                    $cart_item_data[self::CART_ITEM_METERAGE_KEY] = floatval($cart_item[self::CART_ITEM_METERAGE_KEY]);
                }
            }
        }
        return $cart_item_data;
    }

    /**
     * اضافه کردن script برای تنظیم ورودی‌های اعشاری در cart (پشتیبانی)
     */
    public function add_cart_quantity_script()
    {
        if (!function_exists('is_cart') || !is_cart() || !$this->cart_has_formula_items()) {
            return;
        }

        if (!wp_script_is('woo-excel-mng-frontend', 'enqueued')) {
            return;
        }

        $label = esc_js(__('متراژ', 'woo-excel-mng'));
        $inline_js = "
        function wemFixCartTableHeader() {
            var \$table = jQuery('.woocommerce-cart-form__contents');
            if (!\$table.length || !\$table.find('tbody td.product-meteraj').length) {
                return;
            }
            var \$headRow = \$table.find('thead tr').first();
            if (!\$headRow.find('th.product-meteraj').length) {
                \$headRow.find('th.product-quantity').after('<th scope=\"col\" class=\"product-meteraj\">{$label}</th>');
            }
            var colCount = \$headRow.find('th').length;
            \$table.find('td.actions').attr('colspan', colCount);
        }
        jQuery(wemFixCartTableHeader);
        jQuery(document.body).on('updated_wc_div', function() { setTimeout(wemFixCartTableHeader, 50); });
        ";

        wp_add_inline_script('woo-excel-mng-frontend', $inline_js);
    }

    /**
     * نمایش قیمت محاسبه شده در سبد خرید (قیمت واحد)
     */
    // public function display_calculated_price($price, $cart_item, $cart_item_key)
    // {
    //     if (isset($cart_item['woo_excel_calculated_price'])) {
    //         $calculated_price = floatval($cart_item['woo_excel_calculated_price']);
    //         $meterage = isset($cart_item[self::CART_ITEM_METERAGE_KEY]) ? floatval($cart_item[self::CART_ITEM_METERAGE_KEY]) : 0;

    //         if ($meterage > 0) {
    //             $unit_price = $calculated_price * $meterage;
    //             return sprintf(
    //                 '%s <small class="woo-excel-unit-price">/ %s</small>',
    //                 woo_excel_mng_format_price($unit_price),
    //                 esc_html__('متر', 'woo-excel-mng')
    //             );
    //         }

    //         return woo_excel_mng_format_price($calculated_price);
    //     }

    //     return $price;
    // }

    /**
     * نمایش قیمت کل (subtotal) هر آیتم در سبد خرید
     */
    public function display_calculated_subtotal($subtotal, $cart_item, $cart_item_key)
    {
        if (isset($cart_item['woo_excel_calculated_price'])) {
            $calculated_price = floatval($cart_item['woo_excel_calculated_price']);
            // قیمت محاسبه شده برای متراژ وارد شده است، quantity همیشه 1
            return woo_excel_mng_format_price($calculated_price);
        }

        return $subtotal;
    }



    /**
     * حذف فیلدهای پیش‌فرض و نمایش فیلدهای مورد نیاز در تسویه حساب
     */
    public function customize_checkout_fields($fields)
    {
        $fields['billing'] = array(
            'billing_first_name' => array(
                'label' => __('نام', 'woo-excel-mng'),
                'required' => true,
                'class' => array('form-row-wide', 'col-12'),
                'priority' => 10,
            ),
            'billing_last_name' => array(
                'label' => __('نام خانوادگی', 'woo-excel-mng'),
                'required' => true,
                'class' => array('form-row-wide', 'col-12'),
                'priority' => 20,
            ),
            'billing_phone' => array(
                'label' => __('شماره همراه', 'woo-excel-mng'),
                'required' => true,
                'type' => 'tel',
                'class' => array('form-row-wide', 'col-12'),
                'priority' => 30,
            ),
            'billing_address_1' => array(
                'label' => __('آدرس', 'woo-excel-mng'),
                'required' => true,
                'type' => 'textarea',
                'class' => array('form-row-wide', 'col-12'),
                'priority' => 40,
            ),
        );

        $fields['shipping'] = array();
        $fields['order'] = array();

        return $fields;
    }



    /**
     * Fragment برای بروزرسانی AJAX باکس حمل‌ونقل تسویه حساب
     */
    public function add_checkout_shipping_fragment($fragments)
    {
        ob_start();
        $this->render_checkout_shipping_inline();
        $html = ob_get_clean();
        if (!empty($html)) {
            $fragments['#woo-excel-checkout-shipping-inline'] = $html;
        }
        return $fragments;
    }


    /**
     * محاسبه قیمت و وزن بر اساس متراژ
     * استفاده از flag برای جلوگیری از حلقه بی‌نهایت
     */
    public function calculate_cart_totals($cart)
    {
        // جلوگیری از اجرا در admin (به جز AJAX)
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }

        // جلوگیری از حلقه بی‌نهایت
        if (WC()->session->get('woo_excel_calculating_totals')) {
            return;
        }

        // تنظیم flag
        WC()->session->set('woo_excel_calculating_totals', true);

        try {
            foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
                $product = $cart_item['data'];

                // فقط برای محصولات متغیر
                if (!$product->is_type('variation')) {
                    continue;
                }

                $variation_id = $product->get_id();
                $parent_id = $product->get_parent_id();

                // دریافت فرمول
                $formula = Woo_Excel_Mng_Formulas::get_product_formula($parent_id);
                if (!$formula) {
                    continue;
                }

                // متراژ از cart item meta (quantity همیشه 1)
                $meterage = isset($cart_item[self::CART_ITEM_METERAGE_KEY]) ? floatval($cart_item[self::CART_ITEM_METERAGE_KEY]) : $this->get_meterage_min();
                if ($meterage < $this->get_meterage_min()) {
                    $meterage = $this->get_meterage_min();
                }

                // اجباری: qty باید integer باشد
                $cart->cart_contents[$cart_item_key]['quantity'] = 1;
                $cart->cart_contents[$cart_item_key][self::CART_ITEM_METERAGE_KEY] = $meterage;

                $custom_qty = isset($cart_item['custom_quantity']) ? max(1, absint($cart_item['custom_quantity'])) : 1;

                // دریافت متغیرها (متراژ + تعداد سفارشی)
                $variables = Woo_Excel_Mng_Formulas::get_variation_variables($variation_id, $meterage, $custom_qty);

                if (!$variables) {
                    continue;
                }

                // محاسبه قیمت
                $calculated_price = Woo_Excel_Mng_Formulas::calculate_price($formula, $variables);

                if ($calculated_price !== null && $calculated_price > 0) {
                    // قیمت محاسبه شده برای همین متراژ است
                    $product->set_price($calculated_price);

                    // ذخیره قیمت محاسبه شده در cart item data
                    $cart->cart_contents[$cart_item_key]['woo_excel_calculated_price'] = $calculated_price;
                }
    $quantity = isset($cart_item['custom_quantity']) ? max(1, absint($cart_item['custom_quantity'])) : 1;

                // محاسبه وزن بر اساس متراژ (بدون تغییر وزن اصلی محصول)
                $base_weight = floatval($product->get_weight());
                if ($base_weight > 0) {
                    $total_weight = $base_weight * $meterage * $quantity;

                    // ذخیره وزن محاسبه شده در cart item data (نه در محصول)
                    $cart->cart_contents[$cart_item_key]['woo_excel_calculated_weight'] = $total_weight;
                }
            }
        } finally {
            // حذف flag
            WC()->session->__unset('woo_excel_calculating_totals');
        }
    }
    
    // بلاک حمل رایگان قدیمی حذف شد - حالا در display_shipping_info_box نمایش داده می‌شود

    /**
     * اطمینان از محاسبه هزینه حمل
     */
    public function ensure_shipping_fee_calculated()
    {
        if (!WC()->cart || WC()->cart->is_empty()) {
            return;
        }

        // اگر شهر انتخاب شده و هنوز fee اضافه نشده، اضافه کن
        $destination_city = WC()->session->get('woo_excel_destination_city');
        if ($destination_city) {
            $has_shipping_fee = false;
            $existing_fees = WC()->cart->get_fees();
            foreach ($existing_fees as $fee) {
                if (is_object($fee) && isset($fee->name) && strpos($fee->name, __('هزینه حمل', 'woo-excel-mng')) !== false) {
                    $has_shipping_fee = true;
                    break;
                }
            }

            if (!$has_shipping_fee) {
                WC()->cart->calculate_totals();
            }
        }
    }




    /**
     * اضافه کردن هزینه حمل به فاکتور
     */
    public function add_shipping_fee_to_cart($cart = null)
    {
        // اگر cart به عنوان پارامتر نیامده، از WC()->cart استفاده کن
        if (!$cart) {
            $cart = WC()->cart;
        }

        // جلوگیری از اجرا در admin (به جز AJAX)
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }

        // نمایش هزینه حمل فقط در مرحله تسویه حساب
        if (function_exists('is_cart') && is_cart()) {
            return;
        }

        // بررسی وجود سبد خرید
        if (!$cart || !is_object($cart) || $cart->is_empty()) {
            return;
        }

        // حذف fee قبلی (اگر وجود داشته باشد) برای جلوگیری از تکرار
        $existing_fees = $cart->get_fees();
        $fees_to_remove = array();
        foreach ($existing_fees as $fee_key => $fee) {
            if (is_object($fee) && isset($fee->name) && strpos($fee->name, __('هزینه حمل', 'woo-excel-mng')) !== false) {
                $fees_to_remove[] = $fee_key;
            } elseif (is_array($fee) && isset($fee['name']) && strpos($fee['name'], __('هزینه حمل', 'woo-excel-mng')) !== false) {
                $fees_to_remove[] = $fee_key;
            }
        }

        // حذف feeهای قدیمی
        if (!empty($fees_to_remove) && method_exists($cart, 'fees_api')) {
            foreach ($fees_to_remove as $fee_key) {
                $cart->fees_api()->remove_fee($fee_key);
            }
        }

        // دریافت شهر مبدا و مقصد
        $origin_city = get_option('woo_excel_mng_origin_city', 'تهران');
        $destination_city = WC()->session->get('woo_excel_destination_city');

        // اگر شهر مقصد تعیین نشده، هزینه اضافه نمی‌کنیم
        if (!$destination_city) {
            return;
        }

        // دریافت تنظیمات Premium
        $premium_threshold = floatval(get_option('woo_excel_mng_premium_threshold', 65000000));
        $shipping_percentage = floatval(get_option('woo_excel_mng_shipping_percentage', 2)) / 100;

        // محاسبه وزن کل سبد خرید
        $total_weight = 0;
        $cart_total = 0;

        foreach (WC()->cart->get_cart() as $cart_item) {
            if (isset($cart_item['woo_excel_calculated_weight'])) {
                $total_weight += floatval($cart_item['woo_excel_calculated_weight']);
            } else {
                $product = $cart_item['data'];
                $meterage = isset($cart_item[self::CART_ITEM_METERAGE_KEY])
                    ? floatval($cart_item[self::CART_ITEM_METERAGE_KEY])
                    : (isset($cart_item['quantity']) ? floatval($cart_item['quantity']) : 1);
                $product_weight = floatval($product->get_weight());
                if ($product_weight > 0) {
                    $total_weight += $product_weight * $meterage;
                }
            }

            // محاسبه قیمت
            if (isset($cart_item['woo_excel_calculated_price'])) {
                $cart_total += floatval($cart_item['woo_excel_calculated_price']);
            } else {
                $item_price = floatval($cart_item['data']->get_price());
                $quantity = isset($cart_item['quantity']) ? floatval($cart_item['quantity']) : 1;
                $cart_total += $item_price * $quantity;
            }
        }

        if ($total_weight <= 0) {
            return;
        }

        // محاسبه بیشترین متراژ
        $max_meterage = $this->get_cart_max_meterage(WC()->cart->get_cart());

        // محاسبه هزینه حمل از جدول
        $shipping_result = Woo_Excel_Mng_Shipping::calculate_shipping_cost(
            $origin_city,
            $destination_city,
            $total_weight,
            $max_meterage
        );

        if (!$shipping_result) {
            return;
        }

        $base_shipping_cost = floatval($shipping_result['cost']);
        $vehicle = $shipping_result['vehicle'];
        $vehicle_by_weight = $shipping_result['vehicle_by_weight'];
        $vehicle_by_meterage = $shipping_result['vehicle_by_meterage'];

        $vehicle_names = array(
            'peykan' => 'وانت',
            'mazda' => 'مزدا',
            'nissan' => 'نیسان'
        );
        $vehicle_name = isset($vehicle_names[$vehicle])
            ? $vehicle_names[$vehicle]
            : ucfirst($vehicle);

        // **منطق اصلاح شده Premium با رفع خطای گرد کردن**
        $is_premium_mode = ($cart_total >= $premium_threshold);
        $shipping_cost = $base_shipping_cost;
        $is_free_shipping = false;
        $target_amount = 0;
        $remaining = 0;
        $message_type = 'normal'; // normal, close, exact

        if ($is_premium_mode) {
            // محاسبه 2% از فاکتور فعلی با دقت بالا
            $shipping_percentage_amount = $cart_total * $shipping_percentage;

            // رفع خطای گرد کردن: اختلاف کمتر از 1000 ریال را معادل صفر در نظر بگیر
            $difference = abs($base_shipping_cost - $shipping_percentage_amount);
            $tolerance = 1000; // یک هزار تومان تلورانس

            if ($base_shipping_cost <= $shipping_percentage_amount || $difference <= $tolerance) {
                $is_free_shipping = true;
                $shipping_cost = 0;
                $message_type = 'free';
            } else {
                // محاسبه هدف با دقت بالا
                $target_amount = $base_shipping_cost / $shipping_percentage;

                // گرد کردن به عدد صحیح (ریال)
                $target_amount = round($target_amount);
                $cart_total_rounded = round($cart_total);
                $remaining = $target_amount - $cart_total_rounded;

                // اگر باقیمانده خیلی کم است (خطای گرد کردن)
                if ($remaining < 1000) {
                    // بررسی کن که آیا واقعاً به هدف رسیده یا نه
                    $new_percentage_amount = ($cart_total_rounded + $remaining) * $shipping_percentage;
                    if (abs($new_percentage_amount - $base_shipping_cost) <= $tolerance) {
                        $is_free_shipping = true;
                        $shipping_cost = 0;
                        $message_type = 'free';
                    } else {
                        $message_type = 'close';
                    }
                } else {
                    $message_type = 'normal';
                }
            }
        }
        $shipping_percentage_amount = 0;
        if ($is_premium_mode) {
            $shipping_percentage_amount = $cart_total * $shipping_percentage;
        }
        // ذخیره مقادیر در session برای استفاده در نمایش
        if (WC()->session) {
            WC()->session->set('woo_excel_shipping_data', array(
                'vehicle' => $vehicle,
                'vehicle_name' => $vehicle_name,
                'base_cost' => $base_shipping_cost,
                'is_free' => $is_free_shipping,
                'is_premium' => $is_premium_mode,
                'target_amount' => $target_amount,
                'remaining' => $remaining,
                'cart_total' => $cart_total,
                'cart_total_rounded' => isset($cart_total_rounded) ? $cart_total_rounded : round($cart_total),
                'vehicle_by_weight' => $vehicle_by_weight,
                'vehicle_by_meterage' => $vehicle_by_meterage,
                'message_type' => $message_type,
                'shipping_percentage_amount' => $shipping_percentage_amount,
                'difference' => isset($difference) ? $difference : 0,
            ));
        }

        // اضافه کردن هزینه به فاکتور (فقط اگر بیشتر از 0 باشد)
        if ($shipping_cost > 0) {
            // استفاده از API WooCommerce برای اضافه کردن fee
            $fee_name = sprintf(__('هزینه حمل (%s)', 'woo-excel-mng'), $vehicle_name);

            if (method_exists($cart, 'add_fee')) {
                // روش قدیمی (سازگار با WooCommerce قدیمی)
                $cart->add_fee($fee_name, $shipping_cost, false);
            } elseif (method_exists($cart, 'fees_api')) {
                // استفاده از Fees API جدید (WooCommerce 3.2+)
                $cart->fees_api()->add_fee(array(
                    'name' => $fee_name,
                    'amount' => $shipping_cost,
                    'taxable' => false
                ));
            } else {
                // روش جایگزین: استفاده مستقیم از WC()->cart
                if (WC()->cart && method_exists(WC()->cart, 'add_fee')) {
                    WC()->cart->add_fee($fee_name, $shipping_cost, false);
                }
            }
        }
    }


    /**
     * محاسبه هزینه حمل‌ونقل
     * فقط اگر شهر مقصد تعیین شده باشد، نرخ‌های سفارشی را اعمال می‌کند
     */
    public function calculate_shipping_rates($rates, $package)
    {
        // دریافت شهر مبدا و مقصد
        $origin_city = get_option('woo_excel_mng_origin_city', 'تهران');
        $destination_city = WC()->session->get('woo_excel_destination_city');

        // اگر شهر مقصد تعیین نشده، نرخ‌های پیش‌فرض را برگردان (بدون تغییر)
        if (!$destination_city) {
            return $rates;
        }

        // محاسبه وزن کل سبد خرید (از cart item data)
        $total_weight = 0;
        foreach (WC()->cart->get_cart() as $cart_item) {
            // اول وزن محاسبه شده را بررسی کن
            // توجه: woo_excel_calculated_weight قبلاً شامل quantity (meterage) شده است
            if (isset($cart_item['woo_excel_calculated_weight'])) {
                $total_weight += floatval($cart_item['woo_excel_calculated_weight']);
            } else {
                // در غیر این صورت از وزن محصول استفاده کن
                $product = $cart_item['data'];
                $product_weight = floatval($product->get_weight());
                $meterage = isset($cart_item[self::CART_ITEM_METERAGE_KEY]) ? floatval($cart_item[self::CART_ITEM_METERAGE_KEY]) : (isset($cart_item['quantity']) ? floatval($cart_item['quantity']) : 1);
                if ($product_weight > 0) {
                    $total_weight += $product_weight * $meterage;
                }
            }
        }

        // بررسی آستانه حمل رایگان
        $cart_total = WC()->cart->get_subtotal();
        if (Woo_Excel_Mng_Shipping::check_free_shipping($cart_total)) {
            // اگر حمل رایگان است، نرخ رایگان اضافه کن
            $method_id = 'woo_excel_free_shipping';
            $rate = new WC_Shipping_Rate(
                $method_id,
                __('حمل رایگان', 'woo-excel-mng'),
                0,
                array(),
                $method_id
            );

            // اضافه کردن به نرخ‌های موجود (نه جایگزینی)
            $rates[$method_id] = $rate;
            return $rates;
        }

        // محاسبه هزینه حمل‌ونقل
        $max_meterage = $this->get_cart_max_meterage(WC()->cart->get_cart());
        $shipping_cost = Woo_Excel_Mng_Shipping::calculate_shipping_cost($origin_city, $destination_city, $total_weight, $max_meterage);

        if (!$shipping_cost || $shipping_cost['cost'] <= 0) {
            return $rates;
        }

        // نام وسیله نقلیه به فارسی
        $vehicle_names = array(
            'peykan' => 'وانت',
            'mazda' => 'مزدا',
            'nissan' => 'نیسان'
        );
        $vehicle_name = isset($vehicle_names[$shipping_cost['vehicle']])
            ? $vehicle_names[$shipping_cost['vehicle']]
            : ucfirst($shipping_cost['vehicle']);

        // ایجاد نرخ حمل‌ونقل سفارشی
        $method_id = 'woo_excel_custom_shipping';
        $rate = new WC_Shipping_Rate(
            $method_id,
            sprintf(__('حمل‌ونقل (%s)', 'woo-excel-mng'), $vehicle_name),
            $shipping_cost['cost'],
            array(),
            $method_id
        );

        // اضافه کردن به نرخ‌های موجود
        $rates[$method_id] = $rate;

        return $rates;
    }

    /**
     * اضافه کردن label برای quantity در صفحه محصول
     */
    public function add_quantity_label()
    {
        global $product;
        if ($product && $product->is_type('variable')) {
            $formula = Woo_Excel_Mng_Formulas::get_product_formula($product->get_id());
            if ($formula) {
                echo '<style>
                    .woocommerce-variation-add-to-cart .quantity label {
                        display: block;
                        margin-bottom: 5px;
                        font-weight: 600;
                    }					 
					
				  
					.custom-quantity-field {
							display: flex;
							justify-content: end;
							align-items: baseline;
							margin-top: 10px;
						}
                    .woocommerce-variation-add-to-cart .quantity label:before {
                        content: "متراژ (متر) : ";
						color: black;
						
                    }
                </style>';
            }
        }
    }

    /**
     * AJAX: محاسبه قیمت
     */
    public function ajax_calculate_price()
    {
        check_ajax_referer('woo_excel_mng_frontend_nonce', 'nonce');

        $variation_id = isset($_POST['variation_id']) ? intval($_POST['variation_id']) : 0;
        $meterage_raw = isset($_POST['meterage']) ? $this->normalize_decimal_input($_POST['meterage']) : '';
        $meterage = $this->normalize_meterage_value($meterage_raw);

        if ($variation_id <= 0 || $meterage < $this->get_meterage_min()) {
            wp_send_json_error(__('داده‌های نامعتبر.', 'woo-excel-mng'));
        }

        // دریافت محصول والد
        $variation = wc_get_product($variation_id);
        if (!$variation || !$variation->is_type('variation')) {
            wp_send_json_error(__('Variation یافت نشد.', 'woo-excel-mng'));
        }

        $parent_id = $variation->get_parent_id();

        // دریافت فرمول
        $formula = Woo_Excel_Mng_Formulas::get_product_formula($parent_id);
        if (!$formula) {
            wp_send_json_error(__('فرمول برای این محصول تعریف نشده است.', 'woo-excel-mng'));
        }
        $custom_qty = isset($_POST['custom_quantity']) ? absint($_POST['custom_quantity']) : 0;

        // دریافت متغیرها
        $variables = Woo_Excel_Mng_Formulas::get_variation_variables($variation_id, $meterage, $custom_qty);
        if (!$variables) {
            wp_send_json_error(__('خطا در دریافت اطلاعات Variation.', 'woo-excel-mng'));
        }

        // Debug: برای بررسی
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Woo Excel Mng AJAX - Formula: ' . $formula);
            error_log('Woo Excel Mng AJAX - Variables: ' . print_r($variables, true));
        }

        // محاسبه قیمت
        $calculated_price = Woo_Excel_Mng_Formulas::calculate_price($formula, $variables);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Woo Excel Mng AJAX - Calculated Price: ' . $calculated_price);
        }

        if ($calculated_price === null || $calculated_price === false) {
            wp_send_json_error(__('خطا در محاسبه قیمت. لطفاً فرمول و متغیرها را بررسی کنید.', 'woo-excel-mng'));
        }
    $quantity = isset($cart_item['custom_quantity']) ? max(1, absint($cart_item['custom_quantity'])) : 1;

        // محاسبه وزن
        $base_weight = floatval($variation->get_weight());
        $total_weight = $base_weight * $meterage * $quantity  ;

        wp_send_json_success(array(
            'price' => $calculated_price,
            'formatted_price' => woo_excel_mng_format_price($calculated_price),
            'weight' => $total_weight,
            'formatted_weight' => wc_format_weight($total_weight)
        ));
    }

    /**
     * AJAX: به‌روزرسانی آیتم سبد خرید
     */
    public function ajax_update_cart_item()
    {
        check_ajax_referer('woo_excel_mng_frontend_nonce', 'nonce');

        $cart_item_key  = isset($_POST['cart_item_key']) ? wc_clean($_POST['cart_item_key']) : '';
        $meterage_raw   = isset($_POST['meterage']) ? $this->normalize_decimal_input($_POST['meterage']) : 0;
        $meterage       = $this->normalize_meterage_value($meterage_raw);
        $custom_qty     = isset($_POST['custom_quantity']) ? absint($_POST['custom_quantity']) : 0;

        if (!$cart_item_key || !isset(WC()->cart->cart_contents[$cart_item_key])) {
            wp_send_json_error(__('آیتم سبد یافت نشد.', 'woo-excel-mng'));
        }

        $cart_item = WC()->cart->cart_contents[$cart_item_key];
        $product   = $cart_item['data'];

        WC()->cart->cart_contents[$cart_item_key][self::CART_ITEM_METERAGE_KEY] = max($this->get_meterage_min(), $meterage);
        WC()->cart->cart_contents[$cart_item_key]['custom_quantity'] = $custom_qty;
        WC()->cart->cart_contents[$cart_item_key]['quantity'] = 1;

        if ($product && $product->is_type('variation')) {
            $variation_id = $product->get_id();
            $parent_id    = $product->get_parent_id();

            $formula = Woo_Excel_Mng_Formulas::get_product_formula($parent_id);
            if ($formula) {
                $variables = Woo_Excel_Mng_Formulas::get_variation_variables($variation_id, WC()->cart->cart_contents[$cart_item_key][self::CART_ITEM_METERAGE_KEY], $custom_qty);
                if ($variables) {
                    $calculated_price = Woo_Excel_Mng_Formulas::calculate_price($formula, $variables);
                    if ($calculated_price !== null && $calculated_price > 0) {
                        WC()->cart->cart_contents[$cart_item_key]['woo_excel_calculated_price'] = $calculated_price;
                        $product->set_price($calculated_price);
                    }
                }
            }
        }

        WC()->cart->set_session();
        WC()->cart->calculate_totals();

        $updated_cart_item = WC()->cart->cart_contents[$cart_item_key];
        $subtotal_html = WC()->cart->get_product_subtotal($updated_cart_item['data'], 1, $updated_cart_item);

        wp_send_json_success(array(
            'subtotal_html' => $subtotal_html,
        ));
    }


    /**
     * AJAX: ذخیره شهر مقصد
     */
    public function ajax_save_destination_city()
    {
        check_ajax_referer('woo_excel_mng_frontend_nonce', 'nonce');

        $city = isset($_POST['city']) ? sanitize_text_field($_POST['city']) : '';

        if (empty($city)) {
            wp_send_json_error(__('شهر نامعتبر است.', 'woo-excel-mng'));
        }

        // ذخیره شهر در session
        WC()->session->set('woo_excel_destination_city', $city);

        // محاسبه مجدد totals برای اعمال هزینه حمل
        if (WC()->cart && !WC()->cart->is_empty()) {
            WC()->cart->calculate_totals();
        }

        wp_send_json_success(array(
            'message' => __('شهر مقصد ذخیره شد.', 'woo-excel-mng'),
            'city' => $city
        ));
    }

    /**
     * اضافه کردن فیلد انتخاب شهر مقصد در صفحه تسویه حساب
     */
    public function add_destination_city_field($checkout)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'woo_excel_shipping_routes';

        // دریافت لیست شهرهای منحصر به فرد
        $cities = $wpdb->get_col("SELECT DISTINCT destination_city FROM $table_name WHERE is_active = 1 ORDER BY destination_city");

        if (empty($cities)) {
            return;
        }

        $options = array('' => __('-- انتخاب شهر --', 'woo-excel-mng'));
        foreach ($cities as $city) {
            $options[$city] = $city;
        }

        woocommerce_form_field('woo_excel_destination_city', array(
            'type' => 'select',
            'class' => array('form-row-wide', 'address-field'),
            'label' => __('شهر مقصد', 'woo-excel-mng'),
            'required' => true,
            'options' => $options,
            'default' => WC()->session->get('woo_excel_destination_city')
        ), WC()->session->get('woo_excel_destination_city'));

        // اسکریپت برای به‌روزرسانی نرخ‌های حمل‌ونقل
    ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                $('#woo_excel_destination_city').on('change', function() {
                    var city = $(this).val();
                    if (city) {
                        $('body').trigger('update_checkout');
                    }
                });
            });
        </script>
<?php
    }

    /**
     * اعتبارسنجی فیلد شهر مقصد
     */
    public function validate_destination_city()
    {
        if (empty($_POST['woo_excel_destination_city'])) {
            wc_add_notice(__('لطفاً شهر مقصد را انتخاب کنید.', 'woo-excel-mng'), 'error');
        } else {
            // ذخیره در session
            WC()->session->set('woo_excel_destination_city', sanitize_text_field($_POST['woo_excel_destination_city']));
        }
    }

    /**
     * ذخیره شهر مقصد در سفارش
     */
    public function save_destination_city($order_id)
    {
        if (!empty($_POST['woo_excel_destination_city'])) {
            $city = sanitize_text_field($_POST['woo_excel_destination_city']);
            update_post_meta($order_id, '_woo_excel_destination_city', $city);
        }
    }

    /**
     * فیلدهای billing در جای پیش‌فرض خود باقی می‌مانند
     */
    public function reposition_checkout_billing_fields()
    {
        // billing fields stay at default position (top of checkout)
        return;
    }

    /**
     * غیرفعال کردن کد تخفیف در سبد خرید
     */
    public function disable_cart_coupons($enabled)
    {
        if (function_exists('is_cart') && is_cart()) {
            return false;
        }

        return $enabled;
    }

    /**
     * مخفی کردن حمل‌ونقل در سبد خرید
     */
    public function disable_cart_shipping_display($needs_shipping)
    {
        if (function_exists('is_cart') && is_cart()) {
            return false;
        }

        return $needs_shipping;
    }
}
