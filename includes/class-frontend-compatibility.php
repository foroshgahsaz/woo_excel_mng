<?php
/**
 * کلاس سازگاری با ووکامرس و سایر افزونه‌ها
 * این کلاس اطمینان می‌دهد که افزونه با ووکامرس و سایر افزونه‌ها تداخل ندارد
 */

if (!defined('ABSPATH')) {
    exit;
}

class Woo_Excel_Mng_Compatibility {
    
    /**
     * سازنده
     */
    public function __construct() {
        // بررسی نسخه ووکامرس
        add_action('admin_init', array($this, 'check_woocommerce_version'));
        
        // جلوگیری از تداخل با سایر افزونه‌های قیمت‌گذاری
        add_filter('woocommerce_product_get_price', array($this, 'preserve_original_price'), 5, 2);
        
        // اطمینان از عدم تغییر وزن اصلی محصول
        add_filter('woocommerce_product_get_weight', array($this, 'preserve_original_weight'), 5, 2);
    }
    
    /**
     * بررسی نسخه ووکامرس
     */
    public function check_woocommerce_version() {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }
        
        if (version_compare(WC()->version, '5.0', '<')) {
            add_action('admin_notices', array($this, 'woocommerce_version_notice'));
        }
    }
    
    /**
     * پیام عدم وجود ووکامرس
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p>
                <?php _e('افزونه "مدیریت محصولات متغیر ووکامرس" نیاز به ووکامرس دارد. لطفاً ووکامرس را نصب و فعال کنید.', 'woo-excel-mng'); ?>
            </p>
        </div>
        <?php
    }
    
    /**
     * پیام نسخه قدیمی ووکامرس
     */
    public function woocommerce_version_notice() {
        ?>
        <div class="notice notice-warning">
            <p>
                <?php printf(
                    __('افزونه "مدیریت محصولات متغیر ووکامرس" برای ووکامرس 5.0 یا بالاتر طراحی شده است. نسخه فعلی شما: %s', 'woo-excel-mng'),
                    WC()->version
                ); ?>
            </p>
        </div>
        <?php
    }
    
    /**
     * حفظ قیمت اصلی محصول (فقط در admin)
     */
    public function preserve_original_price($price, $product) {
        // فقط در admin و برای محصولاتی که متراژ ندارند
        if (is_admin() && !defined('DOING_AJAX')) {
            return $price;
        }
        
        // اگر محصول در سبد خرید نیست، قیمت اصلی را برگردان
        if (!WC()->cart || !WC()->cart->is_empty()) {
            $cart_item = $this->find_cart_item_by_product($product);
            if (!$cart_item || !isset($cart_item['woo_excel_calculated_price'])) {
                return $price;
            }
        }
        
        return $price;
    }
    
    /**
     * حفظ وزن اصلی محصول
     */
    public function preserve_original_weight($weight, $product) {
        // وزن اصلی را همیشه برگردان (وزن محاسبه شده در cart item data ذخیره می‌شود)
        return $weight;
    }
    
    /**
     * یافتن آیتم سبد خرید بر اساس محصول
     */
    private function find_cart_item_by_product($product) {
        if (!WC()->cart || WC()->cart->is_empty()) {
            return false;
        }
        
        foreach (WC()->cart->get_cart() as $cart_item) {
            if ($cart_item['data']->get_id() === $product->get_id()) {
                return $cart_item;
            }
        }
        
        return false;
    }
}

