jQuery(document).ready(function($){
    $('#vendor-sync-form').on('submit', function(e){
        e.preventDefault();
        var data = { action: 'vendor_sync_batch', step: 1 };
        $.post(ajaxurl, data, function(response){
            alert('شروع شد: ' + response);
        });
    });
});


jQuery(document).ready(function($) {

    $('#vendor-sync-form').on('submit', function(e) {
        e.preventDefault();

        var vendor_id = $('#vendor_id').val();
        var product_cat = $('#product_cat').val();
        var sync_type = $('#sync_type').val();

        if (!vendor_id) return alert('فروشنده را انتخاب کنید.');

        $('#vendor-sync-progress').remove();
        var progressBar = $('<div id="vendor-sync-progress" style="width:100%;border:1px solid #ccc;margin-top:10px;"><div style="width:0%;height:25px;background:#0073aa;color:#fff;text-align:center;">0%</div></div>');
        $('#vendor-sync-form').after(progressBar);

        function syncBatch(offset) {
            $.post(vendorSync.ajax_url, {
                action: 'vendor_sync_batch',
                nonce: vendorSync.nonce,
                vendor_id: vendor_id,
                product_cat: product_cat,
                sync_type: sync_type,
                offset: offset
            }, function(response) {
                if (response.success) {
                    var percent = response.data.percent;
                    $('#vendor-sync-progress div').css('width', percent + '%').text(percent + '%');

                    if (!response.data.done) {
                        syncBatch(response.data.next_offset);
                    } else {
                        alert('بروزرسانی محصولات کامل شد.');
                    }
                } else {
                    alert('خطا در همگام‌سازی.');
                }
            });
        }

        syncBatch(0);
    });

});