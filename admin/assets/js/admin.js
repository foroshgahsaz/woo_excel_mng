/**
 * اسکریپت‌های پیشخوان افزونه
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        // ===== مدیریت انتخاب فایل =====
        $('#products_file, #shipping_file').on('change', function() {
            var fileName = $(this).val().split('\\').pop();
            var fileLabel = $(this).closest('.form-group').find('.file-name');
            if (fileName) {
                fileLabel.text('✓ ' + fileName);
                fileLabel.css('color', '#00a32a');
            } else {
                fileLabel.text('');
            }
        });
        
        function wemCanStartImport() {
            var hasFile = $('#products_file')[0] && $('#products_file')[0].files && $('#products_file')[0].files.length > 0;
            var hasProducts = $('#selected_products').val() && $('#selected_products').val().length > 0;
            $('#wem-start-import').prop('disabled', !(hasFile && hasProducts));
        }

        $('#products_file').on('change', function() {
            var fileName = $(this).val().split('\\').pop();
            $('#products_file_name').text(fileName ? '✓ ' + fileName : '');
            wemCanStartImport();
        });

        $('#selected_products').on('change', wemCanStartImport);
        
        // ===== مدیریت ذخیره مسیرهای حمل‌ونقل =====
        $('.save-route').on('click', function(e) {
            e.preventDefault();
            var $button = $(this);
            var routeId = $button.data('route-id');
            var $row = $button.closest('tr');
            
            var data = {
                action: 'woo_excel_mng_save_route',
                nonce: wooExcelMng.nonce,
                route_id: routeId,
                peykan_price: $row.find('input[data-field="peykan_price"]').val(),
                mazda_price: $row.find('input[data-field="mazda_price"]').val(),
                nissan_price: $row.find('input[data-field="nissan_price"]').val(),
                is_active: $row.find('.route-active').is(':checked') ? 1 : 0
            };
            
            $button.prop('disabled', true).text(wooExcelMng.strings.processing);
            
            $.ajax({
                url: wooExcelMng.ajax_url,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        $button.prop('disabled', false).text('ذخیره شد ✓');
                        setTimeout(function() {
                            $button.text('ذخیره');
                        }, 2000);
                    } else {
                        alert('خطا: ' + (response.data || 'خطای نامشخص'));
                        $button.prop('disabled', false).text('ذخیره');
                    }
                },
                error: function() {
                    alert('خطا در ارتباط با سرور');
                    $button.prop('disabled', false).text('ذخیره');
                }
            });
        });
        
        // ===== مدیریت ویرایش فرمول =====
        $('.edit-formula').on('click', function() {
            var formulaId = $(this).data('formula-id');
            var productId = $(this).data('product-id');
            var formula = $(this).data('formula');
            
            $('#formula_id').val(formulaId);
            $('#formula_product_id').val(productId);
            $('#formula_text').val(formula);
            $('.cancel-edit').show();
            
            // اسکرول به فرم
            $('html, body').animate({
                scrollTop: $('.add-formula-section').offset().top - 100
            }, 500);
        });
        
        $('.cancel-edit').on('click', function() {
            $('#formula_id').val('');
            $('#formula_product_id').val('');
            $('#formula_text').val('');
            $(this).hide();
        });
        
        // ===== مدیریت حذف فرمول =====
        $('.delete-formula').on('click', function(e) {
            e.preventDefault();
            
            if (!confirm(wooExcelMng.strings.confirm_delete)) {
                return;
            }
            
            var $button = $(this);
            var formulaId = $button.data('formula-id');
            var $row = $button.closest('tr');
            
            $.ajax({
                url: wooExcelMng.ajax_url,
                type: 'POST',
                data: {
                    action: 'woo_excel_mng_delete_formula',
                    nonce: wooExcelMng.nonce,
                    formula_id: formulaId
                },
                success: function(response) {
                    if (response.success) {
                        $row.fadeOut(300, function() {
                            $(this).remove();
                            // بررسی وجود ردیف دیگر
                            if ($('.formulas-table-wrapper tbody tr').length === 0) {
                                $('.formulas-table-wrapper tbody').append(
                                    '<tr><td colspan="4" class="no-items">هیچ فرمولی تعریف نشده است.</td></tr>'
                                );
                            }
                        });
                    } else {
                        alert('خطا: ' + (response.data || 'خطای نامشخص'));
                    }
                },
                error: function() {
                    alert('خطا در ارتباط با سرور');
                }
            });
        });
        
        // ===== اعتبارسنجی فرم آپلود shipping =====
        $('.upload-form').on('submit', function(e) {
            var fileInput = $(this).find('input[type="file"]');
            if (!fileInput[0].files.length) {
                e.preventDefault();
                alert('لطفاً یک فایل انتخاب کنید.');
                return false;
            }
            var fileName = fileInput[0].files[0].name;
            var fileExt = fileName.split('.').pop().toLowerCase();
            if (fileExt !== 'xlsx' && fileExt !== 'xls') {
                e.preventDefault();
                alert('فقط فایل‌های Excel (.xlsx, .xls) مجاز هستند.');
                return false;
            }
        });
        
        // ===== Import محصولات با نوار پیشرفت (AJAX) =====
        
        var wemImport = {
            batchId    : null,
            total      : 0,
            processed  : 0,
            variationRows : 0,
            productNames  : [],
            batchSize  : wooExcelMng.strings.batch_size || 1,
            
            toFarsi: function(n) {
                return String(n).replace(/\d/g, function(d) {
                    return '۰۱۲۳۴۵۶۷۸۹'[d];
                });
            },
            
            setLabel: function(icon, text) {
                $('#wem-progress-icon').html(icon);
                $('#wem-progress-label').text(text);
            },
            
            setProgress: function(done, total) {
                var pct = total > 0 ? Math.round((done / total) * 100) : 0;
                $('#wem-progress-fill').css('width', pct + '%');
                $('#wem-progress-percent').text(this.toFarsi(pct) + '٪');
                $('#wem-stat-done').text(this.toFarsi(done));
                $('#wem-stat-total').text(this.toFarsi(total));
                var rem = total - done;
                $('#wem-stat-remaining').text(rem > 0 ? this.toFarsi(rem) : '✓');
            },

            setCurrentProduct: function(name, variationRows) {
                if (!name) {
                    $('#wem-current-product').hide();
                    return;
                }
                $('#wem-current-product').show();
                $('#wem-current-product-name').text(name);
                if (variationRows) {
                    $('#wem-variation-hint').text(
                        (wooExcelMng.strings.variations_count || 'تعداد واریانت در اکسل:') +
                        ' ' + this.toFarsi(variationRows)
                    );
                }
            },
            
            addLog: function(text, type) {
                var cls = type === 'error' ? 'wem-log-error' : (type === 'success' ? 'wem-log-success' : 'wem-log-info');
                var $log = $('#wem-mini-log');
                $log.append('<div class="wem-log-line ' + cls + '">' + text + '</div>');
                $log.scrollTop($log[0].scrollHeight);
            },
            
            start: function(file) {
                var self = this;
                
                // نمایش progress، پنهان کردن فرم
                $('#wem-upload-area').hide();
                $('#wem-progress-area').show();
                $('#wem-result-area').hide().html('');
                $('#wem-mini-log').html('');
                
                self.setLabel('⏳', wooExcelMng.strings.uploading);
                self.setProgress(0, 0);
                
                // آپلود فایل و parse
                var selected = $('#selected_products').val() || [];
                if (!selected.length) {
                    alert('لطفاً حداقل یک محصول انتخاب کنید.');
                    return;
                }

                var formData = new FormData();
                formData.append('action', 'woo_excel_mng_start_import');
                formData.append('nonce', wooExcelMng.nonce);
                formData.append('products_file', file);
                $.each(selected, function(i, id) {
                    formData.append('selected_products[]', id);
                });
                
                self.addLog('📁 فایل در حال آپلود است...', 'info');
                
                $.ajax({
                    url: wooExcelMng.ajax_url,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(res) {
                        if (!res.success) {
                            self.showError(res.data || 'خطا در پردازش فایل.');
                            return;
                        }
                        self.batchId        = res.data.batch_id;
                        self.total          = res.data.total;
                        self.processed      = 0;
                        self.variationRows  = res.data.variation_rows || 0;

                        self.setLabel('⚙️', wooExcelMng.strings.importing);
                        self.setProgress(0, self.total);
                        self.addLog(
                            '✅ فایل خوانده شد. ' + self.toFarsi(self.total) + ' محصول در صف — ' +
                            self.toFarsi(self.variationRows) + ' ردیف واریانت در اکسل.',
                            'success'
                        );
                        self.productNames = res.data.product_names || [];
                        if (self.productNames.length) {
                            self.addLog('📋 صف: ' + self.productNames.join(' ← '), 'info');
                        }

                        self.processBatch();
                    },
                    error: function() {
                        self.showError('خطا در ارتباط با سرور هنگام آپلود.');
                    }
                });
            },
            
            processBatch: function() {
                var self = this;

                if (self.productNames[self.processed]) {
                    self.setCurrentProduct(self.productNames[self.processed], self.variationRows);
                    self.setLabel('⏳', (wooExcelMng.strings.current_product || 'در حال وارد کردن واریانت‌های:') + ' ' + self.productNames[self.processed]);
                }
                
                $.ajax({
                    url: wooExcelMng.ajax_url,
                    type: 'POST',
                    data: {
                        action     : 'woo_excel_mng_process_batch',
                        nonce      : wooExcelMng.nonce,
                        batch_id   : self.batchId,
                        offset     : self.processed,
                        batch_size : self.batchSize
                    },
                    success: function(res) {
                        if (!res.success) {
                            self.showError(res.data || 'خطا در پردازش دسته محصولات.');
                            return;
                        }
                        
                        var d = res.data;
                        self.processed = d.processed;
                        self.setProgress(d.processed, d.total);

                        if (d.current_product) {
                            self.setCurrentProduct(d.current_product, d.variations_rows);
                            self.setLabel('⚙️', (wooExcelMng.strings.current_product || 'در حال وارد کردن واریانت‌های:') + ' ' + d.current_product);
                        }

                        if (d.log) {
                            self.addLog('✓ ' + d.log, d.last_created > 0 ? 'success' : 'info');
                        }

                        if (d.done) {
                            self.setCurrentProduct('');
                            self.showResult(d);
                        } else {
                            setTimeout(function() { self.processBatch(); }, 150);
                        }
                    },
                    error: function() {
                        self.showError('خطا در ارتباط با سرور هنگام پردازش.');
                    }
                });
            },
            
            showResult: function(d) {
                var self = this;
                self.setLabel('✅', wooExcelMng.strings.done);
                self.setProgress(d.total, d.total);
                
                var hasErrors = d.errors && d.errors.length > 0;
                var cls  = hasErrors ? 'wem-result-warning' : 'wem-result-success';
                var icon = hasErrors ? '⚠️' : '✅';
                
                var html  = '<div class="wem-result-box ' + cls + '">';
                html += '<div class="wem-result-title">' + icon + ' ' + wooExcelMng.strings.done + '</div>';
                html += '<div class="wem-result-row"><span>واریانت‌های ایجادشده:</span> <strong>' + self.toFarsi(d.created) + '</strong></div>';
                html += '<div class="wem-result-row"><span>تعداد محصولات پردازش‌شده:</span> <strong>' + self.toFarsi(d.total) + '</strong></div>';
                
                if (hasErrors) {
                    html += '<div class="wem-result-errors"><strong>خطاها (' + self.toFarsi(d.errors.length) + '):</strong><ul>';
                    $.each(d.errors.slice(0, 10), function(i, err) {
                        html += '<li>' + err + '</li>';
                    });
                    if (d.errors.length > 10) {
                        html += '<li>... و ' + self.toFarsi(d.errors.length - 10) + ' خطای دیگر</li>';
                    }
                    html += '</ul></div>';
                }
                
                html += '<button type="button" id="wem-reset-btn" class="button button-secondary" style="margin-top:12px;">آپلود فایل جدید</button>';
                html += '</div>';
                
                $('#wem-result-area').html(html).show();
                
                // دکمه آپلود مجدد
                $('#wem-reset-btn').on('click', function() {
                    self.resetForm();
                });
            },

            resetForm: function() {
                $('#wem-progress-area').hide();
                $('#wem-result-area').hide().html('');
                $('#wem-current-product').hide();
                $('#products_file').val('');
                $('#products_file_name').text('');
                $('#selected_products').val([]);
                wemCanStartImport();
                $('#wem-upload-area').show();
            },
            
            showError: function(msg) {
                var self = this;
                self.setLabel('❌', wooExcelMng.strings.error);
                $('#wem-result-area').html(
                    '<div class="wem-result-box wem-result-error">' +
                    '<div class="wem-result-title">❌ خطا در پردازش</div>' +
                    '<p>' + msg + '</p>' +
                    '<button type="button" id="wem-reset-btn" class="button button-secondary">دوباره امتحان کنید</button>' +
                    '</div>'
                ).show();
                $('#wem-reset-btn').on('click', function() {
                    wemImport.resetForm();
                });
            }
        };
        
        $('#wem-start-import').on('click', function() {
            var file = $('#products_file')[0];
            if (!file || !file.files || !file.files.length) {
                alert('لطفاً یک فایل انتخاب کنید.');
                return;
            }
            if (!$('#selected_products').val() || !$('#selected_products').val().length) {
                alert('لطفاً حداقل یک محصول انتخاب کنید.');
                return;
            }
            var ext = file.files[0].name.split('.').pop().toLowerCase();
            if (ext !== 'xlsx' && ext !== 'xls') {
                alert('فقط فایل‌های Excel (.xlsx, .xls) مجاز هستند.');
                return;
            }
            wemImport.start(file.files[0]);
        });
        
        // ===== نمایش پیام موفقیت/خطا =====
        if ($('.notice-success, .notice-error').length) {
            setTimeout(function() {
                $('.notice-success, .notice-error').fadeOut();
            }, 5000);
        }
        
    });
    
})(jQuery);

