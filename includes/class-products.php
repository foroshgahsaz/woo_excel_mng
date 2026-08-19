<?php

if (!defined('ABSPATH')) {
    exit;
}

class Woo_Excel_Mng_Products
{
    const META_VARIATION_MATRIX = '_wem_variation_matrix';
    const ATTR_COLOR            = 'pa_color';
    const ATTR_THICKNESS        = 'pa_thickness';

    /**
     * دریافت یا ایجاد term با کش (سریع‌تر از فراخوانی مکرر term_exists)
     *
     * @param string $name
     * @param string $taxonomy
     * @param array  $cache
     * @return WP_Term|null
     */
    private static function get_or_create_term($name, $taxonomy, array &$cache)
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $cache_key = $taxonomy . '::' . $name;
        if (isset($cache[$cache_key])) {
            return $cache[$cache_key];
        }

        $term = term_exists($name, $taxonomy);
        if (!$term) {
            $term = wp_insert_term($name, $taxonomy);
        }

        if (is_wp_error($term) || !$term) {
            return null;
        }

        $term_id = is_array($term) ? (int) $term['term_id'] : (int) $term->term_id;
        $term_obj = get_term($term_id, $taxonomy);

        if (!$term_obj || is_wp_error($term_obj)) {
            return null;
        }

        $cache[$cache_key] = $term_obj;
        return $term_obj;
    }

    /**
     * ساخت ماتریس رنگ → ضخامت‌های مجاز (برای فیلتر فرانت)
     *
     * @param array $excel_rows
     * @return array
     */
    private static function build_variation_matrix(array $excel_rows)
    {
        $matrix = array();

        foreach ($excel_rows as $row) {
            $color     = isset($row['رنگ']) ? trim($row['رنگ']) : '';
            $thickness = isset($row['ضخامت']) ? trim($row['ضخامت']) : '';

            if ($color === '' || $thickness === '') {
                continue;
            }

            if (!isset($matrix[$color])) {
                $matrix[$color] = array();
            }
            $matrix[$color][$thickness] = true;
        }

        $slug_matrix = array();
        $term_cache  = array();

        foreach ($matrix as $color_name => $thicknesses) {
            $color_term = self::get_or_create_term($color_name, self::ATTR_COLOR, $term_cache);
            if (!$color_term) {
                continue;
            }

            $slug_matrix[$color_term->slug] = array();

            foreach (array_keys($thicknesses) as $thickness_name) {
                $thickness_term = self::get_or_create_term($thickness_name, self::ATTR_THICKNESS, $term_cache);
                if ($thickness_term) {
                    $slug_matrix[$color_term->slug][] = $thickness_term->slug;
                }
            }

            $slug_matrix[$color_term->slug] = array_values(array_unique($slug_matrix[$color_term->slug]));
        }

        return $slug_matrix;
    }

    /**
     * وارد کردن واریانت‌ها برای یک محصول متغیر
     *
     * @param int   $product_id
     * @param array $excel_rows
     * @return array
     */
    public static function import_single_product($product_id, $excel_rows)
    {
        $product_id = intval($product_id);

        if ($product_id <= 0) {
            return array(
                'success' => false,
                'created' => 0,
                'updated' => 0,
                'errors'  => array('شناسه محصول نامعتبر است.'),
            );
        }

        if (!is_array($excel_rows) || empty($excel_rows)) {
            return array(
                'success' => false,
                'created' => 0,
                'updated' => 0,
                'errors'  => array('داده‌های اکسل برای این محصول خالی است.'),
            );
        }

        $product = wc_get_product($product_id);

        if (!$product || !$product->is_type('variable')) {
            return array(
                'success' => false,
                'created' => 0,
                'updated' => 0,
                'errors'  => array(sprintf('محصول #%d متغیر نیست یا یافت نشد.', $product_id)),
            );
        }

        $created_count = 0;
        $errors        = array();
        $term_cache    = array();

        wp_defer_term_counting(true);
        wp_suspend_cache_invalidation(true);

        try {
            $old_variation_ids = $product->get_children();
            if (!empty($old_variation_ids)) {
                foreach ($old_variation_ids as $variation_id) {
                    wp_delete_post((int) $variation_id, true);
                }
            }

            $color_term_ids     = array();
            $thickness_term_ids = array();
            $seen_color         = array();
            $seen_thickness     = array();

            foreach ($excel_rows as $row) {
                $color     = isset($row['رنگ']) ? trim($row['رنگ']) : '';
                $thickness = isset($row['ضخامت']) ? trim($row['ضخامت']) : '';

                if ($color !== '') {
                    $color_term = self::get_or_create_term($color, self::ATTR_COLOR, $term_cache);
                    if ($color_term && !isset($seen_color[$color_term->term_id])) {
                        $color_term_ids[] = (int) $color_term->term_id;
                        $seen_color[$color_term->term_id] = true;
                    }
                }

                if ($thickness !== '') {
                    $thickness_term = self::get_or_create_term($thickness, self::ATTR_THICKNESS, $term_cache);
                    if ($thickness_term && !isset($seen_thickness[$thickness_term->term_id])) {
                        $thickness_term_ids[] = (int) $thickness_term->term_id;
                        $seen_thickness[$thickness_term->term_id] = true;
                    }
                }
            }

            if (!empty($color_term_ids)) {
                wp_set_object_terms($product_id, $color_term_ids, self::ATTR_COLOR, false);
            }

            if (!empty($thickness_term_ids)) {
                wp_set_object_terms($product_id, $thickness_term_ids, self::ATTR_THICKNESS, false);
            }

            $product_attributes = array(
                self::ATTR_COLOR => array(
                    'name'         => self::ATTR_COLOR,
                    'value'        => '',
                    'position'     => 0,
                    'is_visible'   => 1,
                    'is_variation' => 1,
                    'is_taxonomy'  => 1,
                ),
                self::ATTR_THICKNESS => array(
                    'name'         => self::ATTR_THICKNESS,
                    'value'        => '',
                    'position'     => 1,
                    'is_visible'   => 1,
                    'is_variation' => 1,
                    'is_taxonomy'  => 1,
                ),
            );

            update_post_meta($product_id, '_product_attributes', $product_attributes);

            $seen_combos = array();

            foreach ($excel_rows as $row) {
                $color     = isset($row['رنگ']) ? trim($row['رنگ']) : '';
                $thickness = isset($row['ضخامت']) ? trim($row['ضخامت']) : '';
                $price     = isset($row['قیمت پایه']) ? $row['قیمت پایه'] : '';
                $length    = isset($row['طول']) ? trim($row['طول']) : '';
                $weight    = isset($row['وزن (کیلوگرم)']) ? floatval($row['وزن (کیلوگرم)']) : 0;

                if ($color === '' || $thickness === '') {
                    continue;
                }

                $color_term_obj = self::get_or_create_term($color, self::ATTR_COLOR, $term_cache);
                $thickness_term_obj = self::get_or_create_term($thickness, self::ATTR_THICKNESS, $term_cache);

                if (!$color_term_obj || !$thickness_term_obj) {
                    continue;
                }

                $combo_key = $color_term_obj->slug . '::' . $thickness_term_obj->slug;
                if (isset($seen_combos[$combo_key])) {
                    continue;
                }
                $seen_combos[$combo_key] = true;

                $variation = new WC_Product_Variation();
                $variation->set_parent_id($product_id);
                $variation->set_status('publish');
                $variation->set_regular_price($price);
                $variation->set_length($length);
                $variation->set_manage_stock(false);

                if ($weight > 0) {
                    $variation->set_weight($weight);
                }

                $variation->set_attributes(array(
                    self::ATTR_COLOR     => $color_term_obj->slug,
                    self::ATTR_THICKNESS => $thickness_term_obj->slug,
                ));

                $variation_id = $variation->save();

                if ($variation_id) {
                    $created_count++;
                }
            }

            $variation_matrix = self::build_variation_matrix($excel_rows);
            update_post_meta($product_id, self::META_VARIATION_MATRIX, wp_json_encode($variation_matrix, JSON_UNESCAPED_UNICODE));

            WC_Product_Variable::sync($product_id);
            wc_delete_product_transients($product_id);
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }

        wp_suspend_cache_invalidation(false);
        wp_defer_term_counting(false);

        return array(
            'success' => empty($errors),
            'created' => $created_count,
            'updated' => 0,
            'errors'  => $errors,
        );
    }

    /**
     * ماتریس واریانت ذخیره‌شده برای محصول
     *
     * @param int $product_id
     * @return array
     */
    public static function get_variation_matrix($product_id)
    {
        $product = wc_get_product($product_id);
        if (!$product) {
            return array();
        }

        if ($product->is_type('variation')) {
            $product_id = $product->get_parent_id();
            $product = wc_get_product($product_id);
        }

        if (!$product || !$product->is_type('variable')) {
            return array();
        }

        $raw = get_post_meta($product_id, self::META_VARIATION_MATRIX, true);
        if (!empty($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded) && !empty($decoded)) {
                return $decoded;
            }
        }

        return self::build_matrix_from_existing_variations($product);
    }

    /**
     * ساخت ماتریس از واریانت‌های موجود (برای محصولاتی که قبل از این قابلیت import شده‌اند)
     *
     * @param WC_Product_Variable $product
     * @return array
     */
    private static function build_matrix_from_existing_variations($product)
    {
        $matrix = array();

        foreach ($product->get_children() as $variation_id) {
            $variation = wc_get_product($variation_id);
            if (!$variation || !$variation->is_type('variation')) {
                continue;
            }

            $attrs = $variation->get_attributes();
            $color_slug = isset($attrs[self::ATTR_COLOR]) ? $attrs[self::ATTR_COLOR] : '';
            $thick_slug = isset($attrs[self::ATTR_THICKNESS]) ? $attrs[self::ATTR_THICKNESS] : '';

            if ($color_slug === '' || $thick_slug === '') {
                continue;
            }

            if (!isset($matrix[$color_slug])) {
                $matrix[$color_slug] = array();
            }

            if (!in_array($thick_slug, $matrix[$color_slug], true)) {
                $matrix[$color_slug][] = $thick_slug;
            }
        }

        return $matrix;
    }

    public static function import_products($products_data, $selected_product_ids = array())
    {
        if (empty($selected_product_ids)) {
            return array(
                'success' => false,
                'created' => 0,
                'updated' => 0,
                'errors'  => array('هیچ محصولی برای پردازش انتخاب نشده است.'),
            );
        }

        if (empty($products_data['data']) || !is_array($products_data['data'])) {
            return array(
                'success' => false,
                'created' => 0,
                'updated' => 0,
                'errors'  => array('داده‌های اکسل معتبر نیستند.'),
            );
        }

        $excel_data    = $products_data['data'];
        $created_count = 0;
        $updated_count = 0;
        $errors        = array();

        foreach ($selected_product_ids as $product_id) {
            $result = self::import_single_product($product_id, $excel_data);
            $created_count += $result['created'];
            $updated_count += $result['updated'];
            if (!empty($result['errors'])) {
                $errors = array_merge($errors, $result['errors']);
            }
        }

        return array(
            'success' => empty($errors),
            'created' => $created_count,
            'updated' => $updated_count,
            'errors'  => $errors,
        );
    }
}
