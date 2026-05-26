<?php

/**
 * کلاس پردازش فایل‌های Excel
 */

if (!defined('ABSPATH')) {
    exit;
}

class Woo_Excel_Mng_Excel_Parser
{

    /**
     * بررسی وجود PhpSpreadsheet و پیش‌نیازها
     */
    private static function check_phpspreadsheet()
    {
        // بررسی وجود ZipArchive (پیش‌نیاز PhpSpreadsheet)
        if (!class_exists('ZipArchive') && !extension_loaded('zip')) {
            return array(
                'success' => false,
                'message' => __('افزونه ZipArchive در PHP فعال نیست. لطفاً آن را در php.ini فعال کنید.', 'woo-excel-mng')
            );
        }

        // بررسی وجود PhpSpreadsheet
        if (class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
            return true;
        }

        // تلاش برای بارگذاری از مسیرهای مختلف
        $paths = array(
            WOO_EXCEL_MNG_PLUGIN_DIR . 'vendor/autoload.php',
            ABSPATH . 'vendor/autoload.php',
        );

        foreach ($paths as $path) {
            if (file_exists($path)) {
                require_once $path;
                if (class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * خواندن فایل Excel محصولات
     */
    public static function parse_products_file($file_path)
    {
        $check_result = self::check_phpspreadsheet();

        // اگر نتیجه array باشد (خطا)، آن را برگردان
        if (is_array($check_result) && isset($check_result['success']) && !$check_result['success']) {
            return $check_result;
        }

        // اگر false باشد (PhpSpreadsheet یافت نشد)
        if ($check_result === false) {
            return array(
                'success' => false,
                'message' => __('کتابخانه PhpSpreadsheet یافت نشد. لطفاً آن را نصب کنید.', 'woo-excel-mng')
            );
        }

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file_path);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            if (empty($rows) || count($rows) < 2) {
                return array(
                    'success' => false,
                    'message' => __('فایل Excel خالی است یا فقط هدر دارد.', 'woo-excel-mng')
                );
            }

            // خواندن هدر
            $headers = array_map('trim', $rows[0]);
            $headers = array_map('strtolower', $headers);

            // بررسی وجود ستون‌های ضروری
            $required_columns = array('طول', 'رنگ', 'ضخامت', 'وزن (کیلوگرم)', 'قیمت پایه');
            // $optional_columns = array('شناسه محصول'); // ستون اختیاری برای slug
            $column_indexes = array();

            foreach ($required_columns as $col) {
                $index = array_search(strtolower($col), $headers);
                if ($index === false) {
                    return array(
                        'success' => false,
                        'message' => sprintf(__('ستون "%s" در فایل یافت نشد.', 'woo-excel-mng'), $col)
                    );
                }
                $column_indexes[$col] = $index;
            }


            // خواندن داده‌ها
            $products_data = array();
            for ($i = 1; $i < count($rows); $i++) {
                $row = $rows[$i];

                // رد کردن ردیف‌های خالی
                if (empty(array_filter($row))) {
                    continue;
                }
                $length = isset($row[$column_indexes['طول']]) ? trim(strval($row[$column_indexes['طول']])) : '';
                $color = isset($row[$column_indexes['رنگ']]) ? trim(strval($row[$column_indexes['رنگ']])) : '';
                $thickness = isset($row[$column_indexes['ضخامت']]) ? trim(strval($row[$column_indexes['ضخامت']])) : '';

                // خواندن وزن (حذف جداکننده هزارگان)
                $weight_raw = isset($row[$column_indexes['وزن (کیلوگرم)']]) ? strval($row[$column_indexes['وزن (کیلوگرم)']]) : '0';
                $weight_raw = str_replace(array(',', '٬', ' '), '', $weight_raw);
                $weight = floatval($weight_raw);

                // خواندن قیمت (حذف جداکننده هزارگان)
                $price_raw = isset($row[$column_indexes['قیمت پایه']]) ? strval($row[$column_indexes['قیمت پایه']]) : '0';
                $price_raw = str_replace(array(',', '٬', ' '), '', $price_raw);
                $base_price = floatval($price_raw);

                // ذخیره دقیق همان مقادیر Excel
                $products_data[] = array(
                    // 'product' => $product_name,
                    // 'product_slug' => $product_slug, // شناسه محصول (slug) - اختیاری
                    'طول' => $length,
                    'رنگ' => $color,
                    'ضخامت' => $thickness,
                    'وزن (کیلوگرم)' => $weight,
                    'قیمت پایه' => $base_price,
                );
            }

            return array(
                'success' => true,
                'data' => $products_data,
                'count' => count($products_data)
            );
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => sprintf(__('خطا در خواندن فایل: %s', 'woo-excel-mng'), $e->getMessage())
            );
        }
    }

    /**
     * خواندن فایل Excel شهرها
     */
    public static function parse_shipping_file($file_path)
    {
        $check_result = self::check_phpspreadsheet();

        // اگر نتیجه array باشد (خطا)، آن را برگردان
        if (is_array($check_result) && isset($check_result['success']) && !$check_result['success']) {
            return $check_result;
        }

        // اگر false باشد (PhpSpreadsheet یافت نشد)
        if ($check_result === false) {
            return array(
                'success' => false,
                'message' => __('کتابخانه PhpSpreadsheet یافت نشد. لطفاً آن را نصب کنید.', 'woo-excel-mng')
            );
        }

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file_path);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            if (empty($rows) || count($rows) < 2) {
                return array(
                    'success' => false,
                    'message' => __('فایل Excel خالی است یا فقط هدر دارد.', 'woo-excel-mng')
                );
            }

            // خواندن هدر
            $headers = array_map('trim', $rows[0]);
            $headers = array_map('strtolower', $headers);

            // بررسی وجود ستون‌های ضروری
            $required_columns = array('شهر مبدا', 'شهر مقصد', 'پیکان (تومان)', 'مزدا (تومان)', 'نیسان (تومان)');
            $column_indexes = array();

            foreach ($required_columns as $col) {
                $index = array_search(strtolower($col), $headers);
                if ($index === false) {
                    return array(
                        'success' => false,
                        'message' => sprintf(__('ستون "%s" در فایل یافت نشد.', 'woo-excel-mng'), $col)
                    );
                }
                $column_indexes[$col] = $index;
            }

            // خواندن داده‌ها
            $shipping_data = array();
            for ($i = 1; $i < count($rows); $i++) {
                $row = $rows[$i];

                // رد کردن ردیف‌های خالی
                if (empty(array_filter($row))) {
                    continue;
                }

                $origin = isset($row[$column_indexes['شهر مبدا']]) ? trim($row[$column_indexes['شهر مبدا']]) : '';
                $destination = isset($row[$column_indexes['شهر مقصد']]) ? trim($row[$column_indexes['شهر مقصد']]) : '';

                if (empty($origin) || empty($destination)) {
                    continue;
                }

                $shipping_data[] = array(
                    'origin_city' => $origin,
                    'destination_city' => $destination,
                    'peykan_price' => isset($row[$column_indexes['پیکان (تومان)']]) ? floatval($row[$column_indexes['پیکان (تومان)']]) : 0,
                    'mazda_price' => isset($row[$column_indexes['مزدا (تومان)']]) ? floatval($row[$column_indexes['مزدا (تومان)']]) : 0,
                    'nissan_price' => isset($row[$column_indexes['نیسان (تومان)']]) ? floatval($row[$column_indexes['نیسان (تومان)']]) : 0,
                );
            }

            return array(
                'success' => true,
                'data' => $shipping_data,
                'count' => count($shipping_data)
            );
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => sprintf(__('خطا در خواندن فایل: %s', 'woo-excel-mng'), $e->getMessage())
            );
        }
    }
}
