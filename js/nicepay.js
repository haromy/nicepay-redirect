var payButton = document.getElementById("pay-button");
    jQuery(payButton).on('click', function(ev) {
        jQuery.ajax({
            url: example_ajax_obj.ajaxurl,
            data: {
                'action':'example_ajax_request'
            },
            success:function(data) {
                console.log(data);
            }
        });
    });