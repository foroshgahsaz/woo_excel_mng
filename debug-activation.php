<?php
/**
 * فایل تست برای بررسی خطاهای فعال‌سازی
 * این فایل را در root وردپرس اجرا کنید: php debug-activation.php
 */

// شبیه‌سازی محیط وردپرس
define('ABSPATH', dirname(__FILE__) . '/');
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

// بارگذاری فایل اصلی افزونه
require_once __DIR__ . '/woo-excel-mng.php';

// تست فعال‌سازی
try {
    $plugin = Woo_Excel_Mng::get_instance();
    $plugin->activate();
    echo "✓ فعال‌سازی موفق بود!\n";
} catch (Exception $e) {
    echo "✗ خطا: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
} catch (Error $e) {
    echo "✗ خطای فانی: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

