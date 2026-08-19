<?php
/**
 * کلاس مدیریت حمل‌ونقل
 */

if (!defined('ABSPATH')) {
    exit;
}

class Woo_Excel_Mng_Shipping {
    
    /**
     * واردسازی مسیرهای حمل‌ونقل از داده‌های Excel
     */
    public static function import_routes($routes_data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'woo_excel_shipping_routes';
        
        $inserted = 0;
        $updated = 0;
        $errors = array();
        
        foreach ($routes_data as $route) {
            try {
                // بررسی وجود مسیر
                $existing = $wpdb->get_row($wpdb->prepare(
                    "SELECT id FROM $table_name WHERE origin_city = %s AND destination_city = %s",
                    $route['origin_city'],
                    $route['destination_city']
                ));
                
                if ($existing) {
                    // به‌روزرسانی
                    $result = $wpdb->update(
                        $table_name,
                        array(
                            'peykan_price' => $route['peykan_price'],
                            'mazda_price' => $route['mazda_price'],
                            'nissan_price' => $route['nissan_price'],
                        ),
                        array('id' => $existing->id),
                        array('%f', '%f', '%f'),
                        array('%d')
                    );
                    
                    if ($result !== false) {
                        $updated++;
                    } else {
                        $errors[] = sprintf(
                            __('خطا در به‌روزرسانی مسیر %s → %s', 'woo-excel-mng'),
                            $route['origin_city'],
                            $route['destination_city']
                        );
                    }
                } else {
                    // درج جدید
                    $result = $wpdb->insert(
                        $table_name,
                        array(
                            'origin_city' => $route['origin_city'],
                            'destination_city' => $route['destination_city'],
                            'peykan_price' => $route['peykan_price'],
                            'mazda_price' => $route['mazda_price'],
                            'nissan_price' => $route['nissan_price'],
                            'is_active' => 1
                        ),
                        array('%s', '%s', '%f', '%f', '%f', '%d')
                    );
                    
                    if ($result) {
                        $inserted++;
                    } else {
                        $errors[] = sprintf(
                            __('خطا در درج مسیر %s → %s', 'woo-excel-mng'),
                            $route['origin_city'],
                            $route['destination_city']
                        );
                    }
                }
                
            } catch (Exception $e) {
                $errors[] = sprintf(
                    __('خطا در پردازش مسیر %s → %s: %s', 'woo-excel-mng'),
                    $route['origin_city'],
                    $route['destination_city'],
                    $e->getMessage()
                );
            }
        }
        
        return array(
            'success' => empty($errors),
            'inserted' => $inserted,
            'updated' => $updated,
            'errors' => $errors
        );
    }
    
    /**
     * ذخیره مسیر (از طریق AJAX)
     */
    public static function save_route($route_id, $data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'woo_excel_shipping_routes';
        
        $result = $wpdb->update(
            $table_name,
            array(
                'peykan_price' => floatval($data['peykan_price']),
                'mazda_price' => floatval($data['mazda_price']),
                'nissan_price' => floatval($data['nissan_price']),
                'is_active' => intval($data['is_active'])
            ),
            array('id' => intval($route_id)),
            array('%f', '%f', '%f', '%d'),
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * محاسبه هزینه حمل‌ونقل
     */
    public static function calculate_shipping_cost($origin_city, $destination_city, $total_weight, $total_meterage = 0) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'woo_excel_shipping_routes';
        
        // یافتن مسیر
        $route = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name 
             WHERE origin_city = %s 
             AND destination_city = %s 
             AND is_active = 1",
            $origin_city,
            $destination_city
        ));
        
        if (!$route) {
            return null;
        }
        
        // تعیین وسیله نقلیه بر اساس وزن و متراژ
        $vehicle_by_weight = self::select_vehicle($total_weight);
        $vehicle_by_meterage = self::select_vehicle_by_meterage($total_meterage);
        $vehicle = self::select_vehicle_by_weight_and_meterage($total_weight, $total_meterage);
        
        $cost = 0;
        switch ($vehicle) {
            case 'peykan':
                $cost = floatval($route->peykan_price);
                break;
            case 'mazda':
                $cost = floatval($route->mazda_price);
                break;
            case 'nissan':
                $cost = floatval($route->nissan_price);
                break;
        }
        
        return array(
            'vehicle' => $vehicle,
            'cost' => $cost,
            'vehicle_by_weight' => $vehicle_by_weight,
            'vehicle_by_meterage' => $vehicle_by_meterage,
            'upgraded_by_meterage' => self::is_meterage_upgrade($vehicle_by_weight, $vehicle_by_meterage),
        );
    }
    
    /**
     * انتخاب وسیله نقلیه بر اساس وزن
     */
    public static function select_vehicle($weight) {
        if ($weight <= 200) {
            return 'peykan';
        } elseif ($weight <= 500) {
            return 'mazda';
        } else {
            return 'nissan';
        }
    }

    /**
     * دریافت محدودیت متراژ برای هر وسیله
     */
    public static function get_meterage_limits() {
        return array(
            'peykan' => floatval(get_option('woo_excel_mng_peykan_max_length', 4)),
            'mazda' => floatval(get_option('woo_excel_mng_mazda_max_length', 5)),
            'nissan' => floatval(get_option('woo_excel_mng_nissan_max_length', 6)),
        );
    }

    /**
     * انتخاب وسیله نقلیه بر اساس متراژ
     */
    public static function select_vehicle_by_meterage($meterage) {
        $meterage = floatval($meterage);
        if ($meterage <= 0) {
            return null;
        }

        $limits = self::get_meterage_limits();
        $peykan_max = max(0, floatval($limits['peykan']));
        $mazda_max = max(0, floatval($limits['mazda']));
        $nissan_max = max(0, floatval($limits['nissan']));

        if ($peykan_max > 0 && $meterage <= $peykan_max) {
            return 'peykan';
        }

        if ($mazda_max > 0 && $meterage <= $mazda_max) {
            return 'mazda';
        }

        if ($nissan_max > 0 && $meterage <= $nissan_max) {
            return 'nissan';
        }

        return 'nissan';
    }

    /**
     * انتخاب وسیله نقلیه بر اساس وزن و متراژ (اولویت با بزرگ‌تر)
     */
    public static function select_vehicle_by_weight_and_meterage($weight, $meterage) {
        $by_weight = self::select_vehicle($weight);
        $by_meterage = self::select_vehicle_by_meterage($meterage);

        if (!$by_meterage) {
            return $by_weight;
        }

        $priority = array(
            'peykan' => 1,
            'mazda' => 2,
            'nissan' => 3,
        );

        return ($priority[$by_meterage] >= $priority[$by_weight]) ? $by_meterage : $by_weight;
    }

    /**
     * آیا انتخاب بر اساس متراژ ارتقا داده شده است؟
     */
    public static function is_meterage_upgrade($vehicle_by_weight, $vehicle_by_meterage) {
        if (!$vehicle_by_meterage) {
            return false;
        }

        $priority = array(
            'peykan' => 1,
            'mazda' => 2,
            'nissan' => 3,
        );

        return $priority[$vehicle_by_meterage] > $priority[$vehicle_by_weight];
    }
    
    /**
     * بررسی آستانه حمل رایگان
     */
    public static function check_free_shipping($cart_total) {
        $threshold = get_option('woo_excel_mng_free_shipping_threshold', 20000000);
        return $cart_total >= $threshold;
    }
}
