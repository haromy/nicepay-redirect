<?php

$wp_base_url = home_url( '/' );
$order_detail = $order;
//$data = $this->generate_nicepay_form($order);


function example_ajax_request() {
    echo 'test';
   die();
}
add_action( 'wp_ajax_example_ajax_request', 'example_ajax_request' );
//add_action( 'wp_ajax_nopriv_example_ajax_request', 'example_ajax_request' );

function ajax_send_notification_init(){

    wp_register_script( 'ajax-send-notification-script', plugins_url() . '/nicepay-redirect/js/nicepay.js', array('jquery') ); 
    wp_localize_script( 'ajax-send-notification-script', 'example_ajax_obj', array( 
        'ajaxurl' => admin_url( 'admin-ajax.php' )
    ));
    // To enqueque the JS file.
    wp_enqueue_script( 'ajax-send-notification-script' );

}
add_action('wp_footer', 'ajax_send_notification_init');
?>
<form name="checkout" method="post" class="checkout" action="<?php echo $this->generate_nicepay_form($order); ?>">
  <a id="pay-button" title="Do Payment!" class="button alt">Pay Now</a>
  <input type="submit" class="button alt" name="woocommerce_checkout_place_order" id="place_order" value="Place order" data-value="Place order">
</form>

<?php

