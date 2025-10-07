jQuery(document).ready(function($){
    var step = 1;
    var processing = false;

    function showProgress() {
        $('#progress-container').show();
        $('#vendor-sync-status').text('');
    }

    function updateProgress(percent, text) {
        $('#progress-bar').css('width', percent + '%').text(Math.round(percent) + '%');
        $('#progress-text').text(text || ('در حال پردازش: ' + Math.round(percent) + '%'));
    }

    function ajaxBatch(vendorId, productCat, syncType, step) {
        $.ajax({
            url: vendorSync.ajax_url,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'sync_vendor_products_batch',
                nonce: vendorSync.nonce,
                vendor_id: vendorId,
                product_cat: productCat,
                sync_type: syncType,
                step: step
            },
            success: function(res) {
                if (!res || !res.success) {
                    $('#vendor-sync-status').text('خطا در پاسخ سرور: ' + (res && res.data ? res.data : 'unknown'));
                    $('#sync-button').prop('disabled', false);
                    processing = false;
                    return;
                }

                var data = res.data;
                var total = data.total || 0;
                var done = data.done || false;
                var processedSoFar = (data.next_step - 1) * 50; // approx (step-1)*batch_size
                var percent = 0;
                if (total > 0) {
                    percent = Math.min(100, (processedSoFar / total) * 100);
                }

                updateProgress(percent, 'پردازش شده: ' + processedSoFar + ' از ' + total);

                if (!done) {
                    // ادامه با step بعدی
                    setTimeout(function(){
                        ajaxBatch(vendorId, productCat, syncType, data.next_step);
                    }, 300); // کوتاه تأخیر برای جلوگیری از flood
                } else {
                    // مرحلهٔ اول تمام شد — حالا مرحلهٔ دوم (محاسبهٔ نهایی قیمت‌ها) را اجرا می‌کنیم
                    updateProgress(98, 'مرحله اول تمام شد، در حال محاسبهٔ قیمت نهایی...');
                    // فراخوانی یک endpoint مشابه یا همان endpoint را با یک flag می‌توان استفاده کرد.
                    // برای ساده بودن: ما دوباره از همان endpoint با step=1 و یک پارامتر 'apply_conversion' استفاده خواهیم کرد.
                    applyConversionAll(vendorId, productCat, syncType);
                }
            },
            error: function(xhr, status, err) {
                $('#vendor-sync-status').text('خطا در ارتباط AJAX: ' + status);
                $('#sync-button').prop('disabled', false);
                processing = false;
            }
        });
    }

    function applyConversionAll(vendorId, productCat, syncType) {
        $.ajax({
            url: vendorSync.ajax_url,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'sync_vendor_products_batch',
                nonce: vendorSync.nonce,
                vendor_id: vendorId,
                product_cat: productCat,
                sync_type: syncType,
                // flag to tell server to run conversion pass
                apply_conversion: 1,
                step: 1
            },
            success: function(res) {
                if (!res || !res.success) {
                    $('#vendor-sync-status').text('خطا در مرحلهٔ تبدیل: ' + (res && res.data ? res.data : 'unknown'));
                    $('#sync-button').prop('disabled', false);
                    processing = false;
                    return;
                }
                // روی سرور ما در این حالت باید مرحلهٔ تبدیل را برای همه محصولات اجرا کند و در پاسخ دامنهٔ پیشرفت را برگرداند.
                updateProgress(100, 'تمام شد. بروزرسانی کامل شد.');
                $('#vendor-sync-status').text('عملیات با موفقیت انجام شد.');
                $('#sync-button').prop('disabled', false);
                processing = false;
            },
            error: function() {
                $('#vendor-sync-status').text('خطا در اعمال تبدیل نهایی.');
                $('#sync-button').prop('disabled', false);
                processing = false;
            }
        });
    }

    // کلیک دکمه
    $('#sync-button').on('click', function(e){
        e.preventDefault();
        if (processing) return;
        var vendorId = $('#vendor_id').val();
        var productCat = $('#product_cat').val();
        var syncType = $('#sync_type').val();
        if (!vendorId) {
            alert('لطفاً یک فروشنده انتخاب کنید.');
            return;
        }
        processing = true;
        $(this).prop('disabled', true);
        showProgress();
        step = 1;
        ajaxBatch(vendorId, productCat, syncType, step);
    });

    // غیرفعال کردن دکمه تا فروشنده انتخاب شود
    $('#sync-button').prop('disabled', !$('#vendor_id').val());
    $('#vendor_id').on('change', function(){ $('#sync-button').prop('disabled', !$(this).val()); });
});
