<?php
/**
 * کلاس مدیریت پایگاه داده
 */

if (!defined('ABSPATH')) {
    exit;
}

class Woo_Excel_Mng_Database {
    
    /**
     * ایجاد جداول مورد نیاز
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // جدول مسیرهای حمل‌ونقل
        $table_shipping = $wpdb->prefix . 'woo_excel_shipping_routes';
        $sql_shipping = "CREATE TABLE IF NOT EXISTS $table_shipping (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            origin_city VARCHAR(255) NOT NULL,
            destination_city VARCHAR(255) NOT NULL,
            peykan_price DECIMAL(10,2) DEFAULT 0,
            mazda_price DECIMAL(10,2) DEFAULT 0,
            nissan_price DECIMAL(10,2) DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_route (origin_city, destination_city)
        ) $charset_collate;";
        
        // جدول فرمول‌های قیمت‌گذاری
        $table_formulas = $wpdb->prefix . 'woo_excel_pricing_formulas';
        $sql_formulas = "CREATE TABLE IF NOT EXISTS $table_formulas (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            product_id BIGINT UNSIGNED NOT NULL,
            formula TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_product (product_id)
        ) $charset_collate;";
        
        // جدول لاگ عملیات
        $table_logs = $wpdb->prefix . 'woo_excel_import_logs';
        $sql_logs = "CREATE TABLE IF NOT EXISTS $table_logs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            import_type VARCHAR(50) NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            status VARCHAR(50) NOT NULL,
            message TEXT,
            records_count INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_type (import_type),
            KEY idx_created (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_shipping);
        dbDelta($sql_formulas);
        dbDelta($sql_logs);
    }
    
    /**
     * ثبت لاگ عملیات
     */
    public static function log_import($type, $file_name, $status, $message = '', $count = 0) {
        global $wpdb;
        $table = $wpdb->prefix . 'woo_excel_import_logs';
        
        $wpdb->insert(
            $table,
            array(
                'import_type' => $type,
                'file_name' => $file_name,
                'status' => $status,
                'message' => $message,
                'records_count' => $count
            ),
            array('%s', '%s', '%s', '%s', '%d')
        );
        
        return $wpdb->insert_id;
    }
}

