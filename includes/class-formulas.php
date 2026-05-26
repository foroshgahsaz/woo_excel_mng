<?php

/**
 * کلاس مدیریت فرمول‌ها
 */

if (!defined('ABSPATH')) {
    exit;
}

class Woo_Excel_Mng_Formulas
{

    /**
     * ذخیره فرمول
     */
    public static function save_formula($product_id, $formula, $formula_id = null)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'woo_excel_pricing_formulas';

        $data = array(
            'product_id' => intval($product_id),
            'formula' => sanitize_text_field($formula)
        );

        if ($formula_id) {
            // به‌روزرسانی
            $result = $wpdb->update(
                $table_name,
                $data,
                array('id' => intval($formula_id)),
                array('%d', '%s'),
                array('%d')
            );
            return $result !== false;
        } else {
            // درج جدید
            $result = $wpdb->insert(
                $table_name,
                $data,
                array('%d', '%s')
            );
            return $result !== false;
        }
    }

    /**
     * حذف فرمول
     */
    public static function delete_formula($formula_id)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'woo_excel_pricing_formulas';

        $result = $wpdb->delete(
            $table_name,
            array('id' => intval($formula_id)),
            array('%d')
        );

        return $result !== false;
    }

    /**
     * دریافت فرمول محصول
     */
    public static function get_product_formula($product_id)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'woo_excel_pricing_formulas';

        $formula = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE product_id = %d",
            intval($product_id)
        ));

        return $formula ? $formula->formula : null;
    }

    /**
     * محاسبه قیمت بر اساس فرمول
     */
    public static function calculate_price($formula, $variables)
    {
        if (empty($formula)) {
            return null;
        }

        // جایگزینی متغیرها
        $expression = $formula;
        foreach ($variables as $key => $value) {
            // تبدیل به عدد (برای color که string است، 0 می‌شود)
            $numeric_value = is_numeric($value) ? floatval($value) : 0;
            // جایگزینی با مقدار عددی
            $expression = str_replace('{' . $key . '}', $numeric_value, $expression);
        }

        // Debug: برای بررسی
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Woo Excel Mng - Formula: ' . $formula);
            error_log('Woo Excel Mng - Expression after replace: ' . $expression);
        }

        // امنیت: فقط اعداد، عملگرها، نقطه اعشار و پرانتزها مجاز هستند
        // استفاده از delimiter # به جای / برای جلوگیری از مشکل با / در pattern
        $expression = preg_replace('#[^0-9+\-*/().\s]#', '', $expression);

        // Debug: برای بررسی
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Woo Excel Mng - Expression after sanitize: ' . $expression);
        }

        // بررسی ساختار صحیح (تعداد پرانتزها باید برابر باشد)
        $open_count = substr_count($expression, '(');
        $close_count = substr_count($expression, ')');
        if ($open_count !== $close_count) {
            return null;
        }

        // بررسی اینکه عبارت فقط حاوی کاراکترهای مجاز باشد
        // استفاده از delimiter # به جای / برای جلوگیری از مشکل با / در pattern
        if (preg_match('#[^0-9+\-*/().\s]#', $expression)) {
            return null;
        }

        // محاسبه با استفاده از راه‌حل امن
        try {
            $result = self::safe_calculation($expression);

            if ($result === false || $result === null || !is_numeric($result)) {
                // تلاش با روش جایگزین
                $result = self::alternative_calculation($expression);
            }

            return $result !== false ? floatval($result) : null;
        } catch (Exception $e) {
            error_log('Woo Excel MNG Formula Error: ' . $e->getMessage());
            return null;
        } catch (Error $e) {
            error_log('Woo Excel MNG Formula Error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * محاسبه امن ریاضی
     */
    private static function safe_calculation($expression)
    {
        // حذف فاصله‌ها
        $expression = trim($expression);

        // بررسی خالی نبودن
        if (empty($expression)) {
            return false;
        }

        // استفاده از shunting-yard algorithm برای محاسبات امن
        if (function_exists('eval') && ini_get('eval') != 'Off') {
            // محدود کردن به ریاضیات ساده (قبل از wrap)
            // استفاده از delimiter # به جای / برای جلوگیری از مشکل با / در pattern
            $allowed_chars = preg_match('#^[0-9+\-*/().\s]+$#', $expression);
            if (!$allowed_chars) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Woo Excel Mng - Expression contains invalid characters: ' . $expression);
                }
                return false;
            }

            // استفاده از create_function در PHP 7.x
            if (version_compare(PHP_VERSION, '7.2.0', '<')) {
                // برای PHP نسخه‌های قدیمی‌تر
                try {
                    $func = create_function('', 'return ' . $expression . ';');
                    $result = $func();
                    return is_numeric($result) ? floatval($result) : false;
                } catch (Exception $e) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('Woo Excel Mng - Calculation error: ' . $e->getMessage());
                    }
                    return false;
                }
            } else {
                // برای PHP 7.2 و بالاتر
                // استفاده از eval با محدودیت‌های امنیتی
                try {
                    // بررسی نهایی امنیت
                    // استفاده از delimiter # به جای / برای جلوگیری از مشکل با / در pattern
                    $sanitized = preg_replace('#[^0-9+\-*/().\s]#', '', $expression);
                    if ($sanitized !== $expression) {
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            error_log('Woo Excel Mng - Sanitization changed expression: ' . $expression . ' -> ' . $sanitized);
                        }
                        return false;
                    }

                    // محاسبه
                    $result = eval('return ' . $sanitized . ';');
                    return is_numeric($result) ? floatval($result) : false;
                } catch (ParseError $e) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('Woo Excel Mng - Parse error: ' . $e->getMessage());
                    }
                    return false;
                } catch (Throwable $e) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('Woo Excel Mng - Calculation error: ' . $e->getMessage());
                    }
                    return false;
                }
            }
        }

        // اگر eval غیرفعال است، از محاسبه دستی استفاده کن
        return self::alternative_calculation($expression);
    }

    /**
     * محاسبه جایگزین (بدون eval)
     */
    private static function alternative_calculation($expression)
    {
        // یک parser ساده برای عملیات پایه ریاضی
        $expression = preg_replace('#\s+#', '', $expression);

        // اولویت عملیات: پرانتز -> ضرب/تقسیم -> جمع/تفریق
        while (preg_match('#\(([^()]+)\)#', $expression, $matches)) {
            $result = self::calculate_basic($matches[1]);
            $expression = str_replace($matches[0], $result, $expression);
        }

        // محاسبه نهایی
        return self::calculate_basic($expression);
    }

    /**
     * محاسبه عبارت‌های ساده (بدون پرانتز)
     */
    private static function calculate_basic($expression)
    {
        // محاسبه ضرب و تقسیم
        // استفاده از delimiter # به جای / برای جلوگیری از مشکل با / در pattern
        while (preg_match('#(\d+(?:\.\d+)?)([*\/])(\d+(?:\.\d+)?)#', $expression, $matches)) {
            $num1 = floatval($matches[1]);
            $num2 = floatval($matches[3]);
            $operator = $matches[2];

            switch ($operator) {
                case '*':
                    $result = $num1 * $num2;
                    break;
                case '/':
                    if ($num2 == 0) return 0;
                    $result = $num1 / $num2;
                    break;
                default:
                    $result = 0;
            }

            $expression = str_replace($matches[0], $result, $expression);
        }

        // محاسبه جمع و تفریق
        // استفاده از delimiter # به جای / برای جلوگیری از مشکل با / در pattern
        while (preg_match('#(\d+(?:\.\d+)?)([+\-])(\d+(?:\.\d+)?)#', $expression, $matches)) {
            $num1 = floatval($matches[1]);
            $num2 = floatval($matches[3]);
            $operator = $matches[2];

            switch ($operator) {
                case '+':
                    $result = $num1 + $num2;
                    break;
                case '-':
                    $result = $num1 - $num2;
                    break;
                default:
                    $result = 0;
            }

            $expression = str_replace($matches[0], $result, $expression);
        }

        return floatval($expression);
    }

    /**
     * دریافت متغیرهای Variation
     */



    public static function get_variation_variables($variation_id, $meterage = 0, $custom_quantity = 0)

    {
        $variation = wc_get_product($variation_id);
        if (!$variation) return null;

        $attributes = $variation->get_attributes();



        $variables = array(
            'length'     => intval($custom_quantity), // ← اینجا تغییر کرد
            'thickness'  => 0,
            'color'      => '',
            'meter'      => floatval($meterage),
            'base_price' => floatval($variation->get_regular_price()),
            'weight'     => floatval($variation->get_weight())
        );



        // مقدار رنگ
        if (isset($attributes['pa_color'])) {
            $variables['color'] = urldecode($attributes['pa_color']);
        }

        // مقدار ضخامت
        if (isset($attributes['pa_thickness'])) {

            $value = $attributes['pa_thickness'];

            // تبدیل "0-48" ← 0.48
            if (strpos($value, '-') !== false) {
                $value = str_replace('-', '.', $value);
            }

            $variables['thickness'] = floatval($value);
        }

        return $variables;
    }


    /**
     * اعتبارسنجی فرمول
     */
    public static function validate_formula($formula)
    {
        if (empty($formula)) {
            return false;
        }

        // بررسی وجود متغیرهای مجاز
        $allowed_variables = array('length', 'thickness', 'meter', 'base_price', 'weight');
        preg_match_all('/\{([^}]+)\}/', $formula, $matches);

        if (!empty($matches[1])) {
            foreach ($matches[1] as $var) {
                if (!in_array($var, $allowed_variables)) {
                    return false;
                }
            }
        }

        // بررسی کاراکترهای مجاز
        $clean_formula = preg_replace('/\{[^}]+\}/', '1', $formula); // جایگزینی متغیرها با عدد 1
        if (preg_match('/[^0-9+\-*/().\s]/', $clean_formula)) {
            return false;
        }

        // بررسی پرانتزها
        $open_count = substr_count($formula, '(');
        $close_count = substr_count($formula, ')');
        if ($open_count !== $close_count) {
            return false;
        }

        return true;
    }

    /**
     * دریافت تمام فرمول‌ها
     */
    public static function get_all_formulas($limit = 100)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'woo_excel_pricing_formulas';

        $formulas = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT f.*, p.post_title as product_name 
                 FROM $table_name f 
                 LEFT JOIN {$wpdb->posts} p ON f.product_id = p.ID 
                 ORDER BY f.created_at DESC 
                 LIMIT %d",
                $limit
            ),
            ARRAY_A
        );

        return $formulas ?: array();
    }

    /**
     * دریافت فرمول با ID
     */
    public static function get_formula_by_id($formula_id)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'woo_excel_pricing_formulas';

        $formula = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            intval($formula_id)
        ));

        return $formula ?: null;
    }

    /**
     * تست فرمول با داده‌های نمونه
     */
    public static function test_formula($formula, $sample_data = array())
    {
        if (empty($sample_data)) {
            $sample_data = array(
                'length' => 10,
                'thickness' => 2.5,
                'meter' => 5,
                'base_price' => 10000,
                'weight' => 15
            );
        }

        $result = self::calculate_price($formula, $sample_data);

        return array(
            'success' => $result !== null,
            'result' => $result,
            'sample_data' => $sample_data,
            'formula_display' => self::format_formula_display($formula, $sample_data)
        );
    }

    /**
     * فرمت‌بندی نمایش فرمول
     */
    private static function format_formula_display($formula, $variables)
    {
        $display = $formula;

        foreach ($variables as $key => $value) {
            $display = str_replace('{' . $key . '}', '<strong>' . $value . '</strong>', $display);
        }

        return $display;
    }
}
