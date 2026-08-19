<?php
/**
 * اسکریپت تبدیل فایل‌های CSV به Excel
 * 
 * استفاده:
 * php convert-to-excel.php
 * 
 * یا در مرورگر:
 * http://yoursite.com/wp-content/plugins/woo_excel_mng/samples/convert-to-excel.php
 */

// بررسی وجود PhpSpreadsheet
$autoload_paths = array(
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../vendor/autoload.php',
);

$phpspreadsheet_loaded = false;
foreach ($autoload_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $phpspreadsheet_loaded = true;
        break;
    }
}

if (!$phpspreadsheet_loaded) {
    die("❌ PhpSpreadsheet یافت نشد. لطفاً ابتدا 'composer install' را اجرا کنید.\n");
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;

// تبدیل فایل محصولات
if (file_exists(__DIR__ . '/products-sample.csv')) {
    try {
        $spreadsheet = IOFactory::load(__DIR__ . '/products-sample.csv');
        $writer = new Xlsx($spreadsheet);
        $writer->save(__DIR__ . '/products-sample.xlsx');
        echo "✅ فایل products-sample.xlsx ایجاد شد.\n";
    } catch (Exception $e) {
        echo "❌ خطا در تبدیل products-sample.csv: " . $e->getMessage() . "\n";
    }
} else {
    echo "⚠️ فایل products-sample.csv یافت نشد.\n";
}

// تبدیل فایل شهرها
if (file_exists(__DIR__ . '/shipping-sample.csv')) {
    try {
        $spreadsheet = IOFactory::load(__DIR__ . '/shipping-sample.csv');
        $writer = new Xlsx($spreadsheet);
        $writer->save(__DIR__ . '/shipping-sample.xlsx');
        echo "✅ فایل shipping-sample.xlsx ایجاد شد.\n";
    } catch (Exception $e) {
        echo "❌ خطا در تبدیل shipping-sample.csv: " . $e->getMessage() . "\n";
    }
} else {
    echo "⚠️ فایل shipping-sample.csv یافت نشد.\n";
}

echo "\n✨ تبدیل فایل‌ها به پایان رسید!\n";
echo "📁 فایل‌های Excel در پوشه samples قرار دارند.\n";

