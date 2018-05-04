<?php
global $woocommerce;
$woocommerce->cart->empty_cart();
$wp_base_url = home_url( '/' );
$order_detail = $order;
$resultData = $this->generate_nicepay_form($order);
$urllink = $resultData->data->requestURL."?tXid=".$resultData->tXid;
?>
<a id="pay-button" title="Do Payment!" class="button alt" href="<?php echo $urllink; ?>">Pay Now</a>

<script>
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
</script>

<?php

