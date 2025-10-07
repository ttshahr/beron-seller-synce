jQuery(document).ready(function($) {
    var step = 1;
    var totalSteps = 1;
    var vendorId = $('#vendor_id').val();
    var productCat = $('#product_cat').val();

    // بروزرسانی نوار پیشرفت
    function updateProgressBar() {
        var progress = (step / totalSteps) * 100;
        $('#progress-bar').css('width', progress + '%');
        $('#progress-text').text('در حال پردازش: ' + Math.round(progress) + '%');
    }

    // ارسال درخواست AJAX برای پردازش دسته‌ای محصولات
    function processStep() {
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'sync_vendor_products_batch',
                step: step,
                vendor_id: vendorId,
                product_cat: productCat
            },
            success: function(response) {
                if (response.success) {
                    if (response.data.message === 'تمام شد') {
                        $('#progress-text').text('تمام شد');
                    } else {
                        step = response.data.next_step;
                        updateProgressBar();
                        processStep();
                    }
                } else {
                    $('#progress-text').text('خطا در پردازش');
                }
            },
            error: function() {
                $('#progress-text').text('خطا در ارتباط با سرور');
            }
        });
    }

    // شروع پردازش با کلیک روی دکمه
    $('#sync-button').click(function() {
        $('#sync-button').prop('disabled', true);
        $('#progress-container').show();
        processStep();
    });

    // بروزرسانی مقادیر فروشنده و دسته‌بندی محصولات
    $('#vendor_id, #product_cat').change(function() {
        vendorId = $('#vendor_id').val();
        productCat = $('#product_cat').val();
        $('#sync-button').prop('disabled', !vendorId);
    });

    // غیرفعال کردن دکمه شروع تا زمانی که فروشنده انتخاب نشده باشد
    $('#sync-button').prop('disabled', !vendorId);
});
