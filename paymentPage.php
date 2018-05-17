<?php
global $woocommerce;
$resultData = $this->generate_nicepay_form($order);
$woocommerce->cart->empty_cart();
//$urllink = $resultData->data->requestURL."?tXid=".$resultData->tXid;

if ($resultData == null || $resultData == "") {
    $urllink ="";
}
else {
    if ($this->changeMet == "yes") {
        $urllink = $resultData->data->requestURL."?tXid=".$resultData->tXid;
    }
    else {
        $urllink = $resultData->data->requestURL."?tXid=".$resultData->tXid."&optDisplayCB=1";
    }
}

?>
<a id="pay-button" title="Do Payment!" class="button alt" href="<?php echo $urllink; ?>">Pay Now</a>

<?php
