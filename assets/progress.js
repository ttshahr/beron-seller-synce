jQuery(document).ready(function($) {
    let currentBatchId = null;
    let progressInterval = null;
    
    // شروع همگام‌سازی قیمت‌ها
    $('.start-sync-prices').on('click', function(e) {
        e.preventDefault();
        
        const form = $(this).closest('form');
        const vendorId = form.find('#vendor_id').val();
        const catId = form.find('#product_cat').val();
        
        if (!vendorId) {
            alert('لطفا فروشنده را انتخاب کنید');
            return;
        }
        
        startProcess('sync_prices', vendorId, catId);
    });
    
    // شروع محاسبه قیمت‌ها
    $('.start-calculate-prices').on('click', function(e) {
        e.preventDefault();
        
        const form = $(this).closest('form');
        const vendorId = form.find('#calc_vendor_id').val();
        const catId = form.find('#calc_product_cat').val();
        
        if (!vendorId) {
            alert('لطفا فروشنده را انتخاب کنید');
            return;
        }
        
        startProcess('calculate_prices', vendorId, catId);
    });
    
    // شروع بروزرسانی موجودی
    $('.start-update-stocks').on('click', function(e) {
        e.preventDefault();
        
        const form = $(this).closest('form');
        const vendorId = form.find('#stock_vendor_id').val();
        const catId = form.find('#stock_product_cat').val();
        
        if (!vendorId) {
            alert('لطفا فروشنده را انتخاب کنید');
            return;
        }
        
        startProcess('update_stocks', vendorId, catId);
    });
    
    function startProcess(type, vendorId, catId) {
        // غیرفعال کردن دکمه
        $('.start-' + type.replace('_', '-')).prop('disabled', true).text('در حال شروع...');
        
        // نمایش نوار پیشرفت
        $('#vendor-progress-container').show();
        updateProgress(0, 0, 'در حال شروع...');
        
        // ارسال درخواست AJAX
        $.ajax({
            url: vendorSync.ajaxurl,
            type: 'POST',
            data: {
                action: 'start_' + type,
                vendor_id: vendorId,
                product_cat: catId,
                nonce: vendorSync.nonce
            },
            success: function(response) {
                if (response.success) {
                    currentBatchId = response.data.batch_id;
                    startProgressPolling(type);
                } else {
                    alert('خطا در شروع عملیات');
                    resetUI();
                }
            },
            error: function() {
                alert('خطا در ارتباط با سرور');
                resetUI();
            }
        });
    }
    
    function startProgressPolling(type) {
        progressInterval = setInterval(function() {
            checkProgress(type);
        }, 2000); // چک هر 2 ثانیه
    }
    
    function checkProgress(type) {
        if (!currentBatchId) return;
        
        $.ajax({
            url: vendorSync.ajaxurl,
            type: 'POST',
            data: {
                action: 'get_' + type + '_progress',
                batch_id: currentBatchId,
                nonce: vendorSync.nonce
            },
            success: function(response) {
                if (response.success) {
                    const data = response.data;
                    updateProgress(data.processed, data.total, data.status);
                    
                    if (data.status === 'completed' || data.status === 'failed') {
                        clearInterval(progressInterval);
                        processCompleted(data.status, data.processed);
                    }
                }
            },
            error: function() {
                // در صورت خطا ادامه می‌دهیم
            }
        });
    }
    
    function updateProgress(processed, total, status) {
        const percentage = total > 0 ? Math.round((processed / total) * 100) : 0;
        
        // آپدیت نوار
        $('#progress-fill').css('width', percentage + '%');
        
        // آپدیت متن
        $('#progress-text').text(percentage + '% (' + processed + '/' + total + ')');
        
        // آپدیت وضعیت
        let statusText = 'در حال پردازش';
        let statusClass = 'status-processing';
        
        if (status === 'completed') {
            statusText = 'تکمیل شد';
            statusClass = 'status-completed';
        } else if (status === 'failed') {
            statusText = 'خطا';
            statusClass = 'status-failed';
        }
        
        $('#progress-status').text(statusText).attr('class', statusClass);
        
        // اضافه کردن به لاگ
        if (processed > 0) {
            addToLog('پردازش ' + processed + ' از ' + total + ' محصول (' + percentage + '%)');
        }
    }
    
    function addToLog(message) {
        const timestamp = new Date().toLocaleTimeString('fa-IR');
        const logEntry = $('<div class="log-entry"></div>')
            .html('<span class="log-time">[' + timestamp + ']</span> ' + message);
        
        $('#progress-log').prepend(logEntry);
    }
    
    function processCompleted(status, processedCount) {
        if (status === 'completed') {
            $('#progress-title').text('عملیات با موفقیت完成 شد');
            addToLog('✅ عملیات با موفقیت完成 شد. تعداد: ' + processedCount);
            
            // رفرش صفحه بعد از 3 ثانیه
            setTimeout(function() {
                window.location.reload();
            }, 3000);
        } else {
            $('#progress-title').text('عملیات با خطا مواجه شد');
            addToLog('❌ عملیات با خطا مواجه شد');
        }
        
        $('#cancel-process').hide();
    }
    
    function resetUI() {
        $('.start-process').prop('disabled', false).text('شروع عملیات');
    }
    
    // لغو عملیات
    $('#cancel-process').on('click', function() {
        if (confirm('آیا از لغو عملیات مطمئن هستید؟')) {
            clearInterval(progressInterval);
            $('#vendor-progress-container').hide();
            resetUI();
            addToLog('❌ عملیات توسط کاربر لغو شد');
        }
    });
});