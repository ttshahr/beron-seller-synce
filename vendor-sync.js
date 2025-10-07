jQuery(document).ready(function($){
    $('#vendor-sync-form').on('submit', function(e){
        e.preventDefault();
        $('#sync-progress').show();

        var form = $(this);
        var data = form.serialize();

        $.post(ajaxurl, data, function(response){
            $('#sync-bar').css('width','100%');
            $('#sync-status').text('بروزرسانی کامل شد!');
        });
    });
});
