/**
 * اسکریپت‌های Front-end افزونه
 * پشتیبانی از تعداد اعشاری (step=0.5) برای همه محصولات
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {

        var meterageMin = (typeof wooExcelMngFrontend !== 'undefined' && wooExcelMngFrontend.meterage_min)
            ? parseFloat(wooExcelMngFrontend.meterage_min)
            : 0.5;
        var meterageStep = (typeof wooExcelMngFrontend !== 'undefined' && wooExcelMngFrontend.meterage_step)
            ? parseFloat(wooExcelMngFrontend.meterage_step)
            : 0.5;
        var decimalQtyMin = (typeof wooExcelMngFrontend !== 'undefined' && wooExcelMngFrontend.decimal_qty_min)
            ? parseFloat(wooExcelMngFrontend.decimal_qty_min)
            : 0.5;
        var decimalQtyStep = (typeof wooExcelMngFrontend !== 'undefined' && wooExcelMngFrontend.decimal_qty_step)
            ? parseFloat(wooExcelMngFrontend.decimal_qty_step)
            : 0.5;

        // نرمال‌سازی ورودی اعشاری (پشتیبانی از ارقام فارسی/عربی)
        function normalizeDecimalInput(value) {
            if (value === null || value === undefined) {
                return '';
            }

            var str = String(value).replace(/\s+/g, '');
            var map = {
                '۰': '0', '۱': '1', '۲': '2', '۳': '3', '۴': '4',
                '۵': '5', '۶': '6', '۷': '7', '۸': '8', '۹': '9',
                '٠': '0', '١': '1', '٢': '2', '٣': '3', '٤': '4',
                '٥': '5', '٦': '6', '٧': '7', '٨': '8', '٩': '9',
                '٫': '.', ',': '.', '٬': ''
            };

            return str.replace(/[۰-۹٠-٩٫٬,]/g, function(ch) {
                return Object.prototype.hasOwnProperty.call(map, ch) ? map[ch] : ch;
            });
        }

        // نرمال‌سازی مقدار عددی
        function normalizeNumericValue(value) {
            var num = parseFloat(value);
            if (isNaN(num)) {
                return NaN;
            }
            return parseFloat(num.toFixed(2));
        }

        // فرمت عدد برای نمایش (حذف صفرهای اضافی)
        function formatDisplayValue(num) {
            if (isNaN(num)) return '';
            // اگر عدد صحیح است، بدون اعشار نمایش بده
            if (num === Math.floor(num)) {
                return String(Math.floor(num));
            }
            // در غیر این صورت با حداکثر 1 رقم اعشار
            var formatted = num.toFixed(1);
            // حذف صفرهای پایانی
            return formatted.replace(/\.?0+$/, '');
        }

        // ===== تنظیم ورودی‌های اعشاری در صفحه محصول =====
        function setupProductQuantityInputs() {
            // برای همه محصولات: ورودی اعشاری
            $('.quantity input.qty, .quantity input.woo-excel-decimal-qty, .quantity input.woo-excel-meterage-quantity').not('[type="hidden"]').each(function() {
                var $input = $(this);
                $input.attr({
                    'type': 'text',
                    'inputmode': 'decimal'
                });
                // حذف محدودیت‌های عددی مرورگر
                $input.removeAttr('max');
            });
        }

        // اجرای اولیه
        setupProductQuantityInputs();

        // ===== فیلتر ضخامت بر اساس رنگ انتخاب‌شده (ماتریس اکسل) =====
        function setupVariationMatrixFilter() {
            if (typeof wooExcelMngFrontend === 'undefined' || !wooExcelMngFrontend.variation_matrix) {
                return;
            }

            var matrix = wooExcelMngFrontend.variation_matrix;
            var colorAttr = wooExcelMngFrontend.attr_color || 'pa_color';
            var thicknessAttr = wooExcelMngFrontend.attr_thickness || 'pa_thickness';

            $('form.variations_form').each(function() {
                var $form = $(this);
                var $colorSelect = $form.find('select[name="attribute_' + colorAttr + '"]');
                var $thickSelect = $form.find('select[name="attribute_' + thicknessAttr + '"]');

                if (!$colorSelect.length || !$thickSelect.length) {
                    return;
                }

                function filterThicknessOptions() {
                    var colorSlug = $colorSelect.val();

                    $thickSelect.find('option').each(function() {
                        var $opt = $(this);
                        var val = $opt.val();

                        if (!val) {
                            $opt.prop('disabled', false).show();
                            return;
                        }

                        if (!colorSlug) {
                            $opt.prop('disabled', false).show();
                            return;
                        }

                        var allowed = matrix[colorSlug] || [];
                        var isAllowed = allowed.indexOf(val) !== -1;

                        $opt.prop('disabled', !isAllowed);
                        $opt.toggle(isAllowed || !colorSlug);

                        if (!isAllowed && $thickSelect.val() === val) {
                            $thickSelect.val('').trigger('change');
                        }
                    });
                }

                $colorSelect.off('change.wemMatrix').on('change.wemMatrix', filterThicknessOptions);
                $form.off('woocommerce_update_variation_values.wemMatrix')
                    .on('woocommerce_update_variation_values.wemMatrix', filterThicknessOptions);
                $form.off('reset_data.wemMatrix')
                    .on('reset_data.wemMatrix', function() {
                        $thickSelect.find('option').prop('disabled', false).show();
                    });

                filterThicknessOptions();
            });
        }

        setupVariationMatrixFilter();
        $(document.body).on('wc_variation_form', function() {
            setupVariationMatrixFilter();
        });

        // ===== مدیریت فیلد quantity (متراژ) در صفحه محصول =====
        // تغییر label quantity به متراژ فقط برای وارییشن‌هایی که فرمول دارند
        $(document).on('found_variation', function(event, variation) {
            var $quantityInput = $('.quantity input.qty, .quantity input.woo-excel-meterage-quantity, .quantity input.woo-excel-decimal-qty');
            var $quantityLabel = $('.quantity label');
            var $form = $('form.variations_form');

            var hasFormula = !!(variation && variation.woo_excel_has_formula);
            if ($form.length) {
                $form.data('woo_excel_has_formula', hasFormula);
            }

            // تنظیم اعشاری برای همه
            setupProductQuantityInputs();

            if (!hasFormula) {
                if ($quantityLabel.length) {
                    $quantityLabel.text('تعداد:');
                }
                $('.woo-excel-price-preview').hide();
                return;
            }

            // تغییر label به متراژ
            if ($quantityLabel.length) {
                $quantityLabel.text('متراژ (متر):');
            } else {
                $quantityInput.before('<label for="quantity">متراژ (متر):</label>');
            }

            // // اضافه کردن preview قیمت
            // if ($('.woo-excel-price-preview').length === 0) {
            //     $quantityInput.after('<div class="woo-excel-price-preview" style="display:none; margin-top: 10px; padding: 10px; background: #e8f5e9; border: 1px solid #4caf50; border-radius: 4px;"><strong>قیمت نهایی:</strong> <span class="woo-excel-calculated-price"></span></div>');
            // }
        });
        
        // مخفی کردن preview وقتی variation لغو شد
        $(document).on('reset_data', function() {
            var $form = $('form.variations_form');
            if ($form.length) {
                $form.data('woo_excel_has_formula', false);
            }
            $('.woo-excel-price-preview').hide();
            $('.quantity label').text('تعداد:');
        });

        // ===== نرمال‌سازی ورودی هنگام تایپ =====
        $(document).on('input', '.quantity input.qty, .quantity input.woo-excel-decimal-qty, .quantity input.woo-excel-meterage-quantity, table.cart input.woo-excel-meterage-input', function() {
            var $input = $(this);
            var val = $input.val();
            var normalized = normalizeDecimalInput(val);
            if (normalized !== val) {
                $input.val(normalized);
            }
        });
        
        // ===== محاسبه قیمت هنگام تغییر quantity (متراژ) - فقط برای فرمول‌دار =====
        var calculationTimeout;
        $(document).on('input change', '.quantity input.qty, .quantity input.woo-excel-meterage-quantity', function() {
            var $input = $(this);
            var $form = $('form.variations_form');
            if (!$form.length || !$form.data('woo_excel_has_formula')) {
                return;
            }
            
            clearTimeout(calculationTimeout);
            
            var meterageValue = normalizeDecimalInput($input.val());
            var meterage = normalizeNumericValue(meterageValue);
            if (isNaN(meterage) || meterage < meterageMin) {
                $('.woo-excel-price-preview').hide();
                return;
            }
            
            // دریافت Variation ID
            var variationId = $('input[name="variation_id"]').val();
            if (!variationId) {
                return;
            }
            
            // نمایش پیام در حال محاسبه
            var $preview = $('.woo-excel-price-preview');
            $preview.show();
            $('.woo-excel-calculated-price').text(wooExcelMngFrontend.strings.calculating);
            
            calculationTimeout = setTimeout(function() {
                calculatePrice(variationId, meterage);
            }, 500);
        });
        
        // ===== تابع محاسبه قیمت =====
        function calculatePrice(variationId, meterage) {
            $.ajax({
                url: wooExcelMngFrontend.ajax_url,
                type: 'POST',
                data: {
                    action: 'woo_excel_mng_calculate_price',
                    nonce: wooExcelMngFrontend.nonce,
                    variation_id: variationId,
                    meterage: meterage
                },
                success: function(response) {
                    if (response.success) {
                        $('.woo-excel-calculated-price').html(response.data.formatted_price);
                        $('.woo-excel-price-preview').show();
                    } else {
                        $('.woo-excel-price-preview').hide();
                    }
                },
                error: function() {
                    $('.woo-excel-price-preview').hide();
                }
            });
        }
        
        // ===== اعتبارسنجی قبل از افزودن به سبد =====
        $('form.cart').on('submit', function(e) {
            var $quantityInput = $(this).find('.quantity input.qty, .quantity input.woo-excel-meterage-quantity, .quantity input.woo-excel-decimal-qty').first();
            var $form = $('form.variations_form');
            
            if (!$quantityInput.length) {
                return; // اگر ورودی quantity وجود نداره، اجازه بده فرم ارسال بشه
            }

            var rawValue = $quantityInput.val();
            var normalizedValue = normalizeDecimalInput(rawValue);
            if (normalizedValue !== rawValue) {
                $quantityInput.val(normalizedValue);
            }
            var numValue = normalizeNumericValue(normalizedValue);
            
            // بررسی فرمول‌دار بودن
            var isFormula = $form.length && $form.data('woo_excel_has_formula');
            var minVal = isFormula ? meterageMin : decimalQtyMin;
            
            if (isNaN(numValue) || numValue < minVal) {
                e.preventDefault();
                if (isFormula) {
                    alert(wooExcelMngFrontend.strings.enter_meterage || 'لطفاً متراژ را وارد کنید.');
                } else {
                    alert(wooExcelMngFrontend.strings.enter_quantity || 'لطفاً تعداد معتبر وارد کنید.');
                }
                $quantityInput.focus();
                return false;
            }

            if (isFormula) {
                // مدل فرمول: quantity=1, meterage جدا ذخیره می‌شود
                if ($quantityInput.attr('name') === 'woo_excel_meterage') {
                    // ورودی سفارشی: مطمئن شو hidden quantity=1 وجود داره
                    if ($(this).find('input[name="quantity"]').length === 0) {
                        $(this).append('<input type="hidden" name="quantity" value="1">');
                    }
                } else {
                    // ورودی پیش‌فرض: اضافه کردن hidden meterage
                    $(this).find('input[name="woo_excel_meterage"]').remove();
                    $(this).append('<input type="hidden" name="woo_excel_meterage" value="' + numValue + '">');
                    $quantityInput.val('1');
                }
            } else {
                // غیر فرمول‌دار: اضافه کردن hidden woo_excel_decimal_qty برای ارسال مقدار اعشاری
                $(this).find('input[name="woo_excel_decimal_qty"]').remove();
                $(this).append('<input type="hidden" name="woo_excel_decimal_qty" value="' + numValue + '">');
                // quantity هم با مقدار اعشاری
                $quantityInput.val(numValue);
            }
        });
        
        // ===== تنظیم ورودی‌های اعشاری در سبد خرید =====
        function setupCartQuantityInputs() {
            // ورودی‌های متراژ (فرمول‌دار)
            $('table.cart input.woo-excel-meterage-input').each(function() {
                var $input = $(this);
                $input.attr({
                    'type': 'text',
                    'inputmode': 'decimal'
                });
            });

            // ورودی‌های quantity معمولی در سبد خرید
            $('table.cart .quantity input.qty').not('[type="hidden"]').each(function() {
                var $input = $(this);
                $input.attr({
                    'type': 'text',
                    'inputmode': 'decimal',
                    'step': decimalQtyStep,
                    'min': decimalQtyMin
                });
            });
        }
        
        function setupCartTableHeader() {
            var $table = $('.woocommerce-cart-form__contents');
            if (!$table.length || !$table.find('tbody td.product-meteraj').length) {
                return;
            }
            var $headRow = $table.find('thead tr').first();
            var headerLabel = (wooExcelMngFrontend.strings && wooExcelMngFrontend.strings.meterage_header)
                ? wooExcelMngFrontend.strings.meterage_header
                : 'متراژ';
            if (!$headRow.find('th.product-meteraj').length) {
                $headRow.find('th.product-quantity').after(
                    '<th scope="col" class="product-meteraj">' + headerLabel + '</th>'
                );
            }
            var colCount = $headRow.find('th').length;
            $table.find('td.actions').attr('colspan', colCount);
        }

        function setupCartPage() {
            setupCartQuantityInputs();
            setupCartTableHeader();
        }

        setupCartPage();

        $(document).on('updated_wc_div', function() {
            setTimeout(setupCartPage, 100);
        });

        $(document).on('found_variation', function() {
            setTimeout(setupProductQuantityInputs, 0);
        });
        
        // ===== مدیریت تغییر quantity متراژ در سبد خرید (فرمول‌دار) =====
        var updateCartTimeout;
        var refreshCartTotalsTimeout;

        function refreshCartTotals() {
            clearTimeout(refreshCartTotalsTimeout);
            refreshCartTotalsTimeout = setTimeout(function() {
                var $updateBtn = $('button[name="update_cart"]');
                if ($updateBtn.length) {
                    $updateBtn.prop('disabled', false).trigger('click');
                } else {
                    window.location.reload();
                }
            }, 120);
        }
        
        $(document).on('change blur', 'table.cart input.woo-excel-meterage-input', function(e) {
            var $input = $(this);
            var $row = $input.closest('tr.cart_item');
            
            if ($row.find('td.product-meteraj, input.woo-excel-meterage-input').length === 0) {
                return;
            }
            
            var cartItemKey = null;
            var nameAttr = $input.attr('name');
            if (nameAttr) {
                var match = nameAttr.match(/woo_excel_meterage\[([^\]]+)\]/);
                if (match) {
                    cartItemKey = match[1];
                }
            }
            
            if (!cartItemKey) {
                return;
            }
            
            var meterageValue = normalizeDecimalInput($input.val());
            $input.val(meterageValue);
            var meterage = normalizeNumericValue(meterageValue);
            
            if (isNaN(meterage) || meterage < meterageMin) {
                alert('متراژ باید حداقل ' + meterageMin + ' متر باشد.');
                var oldValue = $input.data('old-value');
                $input.val(oldValue || formatDisplayValue(meterageMin));
                return;
            }
            
            // ذخیره مقدار و نمایش فرمت
            $input.data('old-value', formatDisplayValue(meterage));
            $input.val(formatDisplayValue(meterage));
            
            clearTimeout(updateCartTimeout);
            $row.addClass('woo-excel-updating');
            
            

updateCartTimeout = setTimeout(function() {
    var customQty = $row.find('input[name^="custom_quantity"]').val();
    updateCartItem(cartItemKey, meterage, parseInt(customQty) || 0, $row);
}, 500);



        });

        // ===== مدیریت تغییر quantity معمولی در سبد خرید (غیر فرمول‌دار) =====
        $(document).on('change blur', 'table.cart .quantity input.qty', function(e) {
            var $input = $(this);
            if ($input.is('[type="hidden"]')) return;

            var rawValue = normalizeDecimalInput($input.val());
            $input.val(rawValue);
            var numValue = normalizeNumericValue(rawValue);

            if (isNaN(numValue) || numValue < decimalQtyMin) {
                var oldVal = $input.data('old-value') || formatDisplayValue(decimalQtyMin);
                $input.val(oldVal);
                return;
            }

            $input.data('old-value', formatDisplayValue(numValue));
            $input.val(formatDisplayValue(numValue));
            refreshCartTotals();
        });
        
 

        // ===== تابع به‌روزرسانی آیتم سبد خرید (فرمول‌دار) =====
        function updateCartItem(cartItemKey, meterage,customQty, $row) {
            $.ajax({
                url: wooExcelMngFrontend.ajax_url,
                type: 'POST',
                data: {
                    action: 'woo_excel_mng_update_cart_item',
                    nonce: wooExcelMngFrontend.nonce,
                    cart_item_key: cartItemKey,
                    meterage: meterage,
                    custom_quantity: customQty  // ← اضافه شد                            
                },
               


                success: function(response) {
                    if (response.success) {
                        if (response.data && response.data.subtotal_html) {
                            $row.find('.product-subtotal').html(response.data.subtotal_html);
                        }
                        // اجرای آپدیت رسمی ووکامرس تا جمع پایین سبد، وزن و feeها همگام شوند
                        refreshCartTotals();
                    }
                    $row.removeClass('woo-excel-updating');
                },


                error: function() {
                    $row.removeClass('woo-excel-updating');
                    alert('خطا در ارتباط با سرور');
                }
            });
        }
        
        // ===== مدیریت انتخاب شهر مقصد =====
        function saveDestinationCity(city, $select) {
            if (!city) {
                return;
            }

            if ($select && $select.length) {
                $select.prop('disabled', true);
            }

            $.ajax({
                url: wooExcelMngFrontend.ajax_url,
                type: 'POST',
                data: {
                    action: 'woo_excel_mng_save_destination_city',
                    nonce: wooExcelMngFrontend.nonce,
                    city: city
                },
                success: function(response) {
                    if (response && response.success) {
                        if ($('body').hasClass('woocommerce-cart')) {
                            window.location.reload();
                        } else {
                            if ($select && $select.length) {
                                $select.prop('disabled', false);
                            }
                            $('body').trigger('update_checkout');
                        }
                    } else {
                        var errorMsg = (response && response.data) ? response.data : 'خطا در ذخیره شهر';
                        alert('خطا: ' + errorMsg);
                        if ($select && $select.length) {
                            $select.prop('disabled', false);
                        }
                    }
                },
                error: function() {
                    alert('خطا در ارتباط با سرور. لطفاً دوباره تلاش کنید.');
                    if ($select && $select.length) {
                        $select.prop('disabled', false);
                    }
                }
            });
        }

        $(document).on('change', '#woo_excel_destination_city, .woo-excel-city-select select', function() {
            var $select = $(this);
            var city = $select.val();
            saveDestinationCity(city, $select);
        });
        
    });
    
})(jQuery);
