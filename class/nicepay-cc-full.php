<?php

class WC_Gateway_NICEPay_CC extends WC_Payment_Gateway {
    var $callbackUrl;
    private $adminFee = 0; //jika ada request merchant untuk menambahkan credit card fee pada transaksi - dalam Rupiah
    private $mdrFee = 0; //jika ada request merchant untuk menambahkan fee mdr pada transaksi - dalam %
    
    public function nicepay_item_name($item_name)
    {
        if (strlen($item_name) > 127) {
            $item_name = substr($item_name, 0, 124) . '...';
        }
        
        return html_entity_decode($item_name, ENT_NOQUOTES, 'UTF-8');
    }
    
    public function __construct()
    {
        //plugin id
        $this->id = 'nicepay_cc';
        
        //Payment Gateway title
        $this->method_title = 'NICEPay - Credit Card';
        
        //true only in case of direct payment method, false in our case
        $this->has_fields = false;
        
        //payment gateway logo
        //$this->icon = PLUGIN_PATH.'nicepay.png';
        
        //redirect URL
        $this->redirect_url = str_replace('https:', 'http:', add_query_arg('wc-api', 'WC_Gateway_NICEPay_CC', home_url('/')));
        
        //Load settings
        $this->init_form_fields();
        $this->init_settings();
        
        // Define user set variables
        $this->enabled     = $this->settings['enabled'];
        $this->title       = "NICEPay Credit Card Payment";
        $this->description = $this->settings['description'];
        $this->url_env     = $this->settings['select_environment'];
        $this->apikey      = $this->settings['apikey'];
        $this->merchantID  = $this->settings['merchantID'];
        
        
        // Actions
        $this->includes();
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(&$this,'process_admin_options'));
        add_action('woocommerce_receipt_'. $this->id, array(&$this,'receipt_page'));
        
        //Add Text to email
        add_action('woocommerce_email_after_order_table', array($this,'add_content'));
        
        // Payment listener/API hook
        add_action('woocommerce_api_wc_gateway_nicepay_cc', array($this,'notification_handler'));
    }
    
    function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'woothemes'),
                'label' => __('Enable NICEPay', 'woothemes'),
                'type' => 'checkbox',
                'description' => '',
                'default' => 'no'
            ),
            'title' => array(
                'title' => __('Title', 'woothemes'),
                'type' => 'text',
                'description' => __('', 'woothemes'),
                'default' => __('Pembayaran NICEPay Credit Card', 'woothemes')
            ),
            'description' => array(
                'title' => __('Description', 'woothemes'),
                'type' => 'textarea',
                'description' => __('', 'woothemes'),
                'default' => 'Sistem pembayaran menggunakan NICEPay Credit Card.'
            ),
            'select_environment' => array(
                'title' => __( 'Environment', 'woothemes' ),
                'type' => 'select',
                'default' => 'dev',
                'description' => __( 'Select the Environment', 'woothemes' ),
                'options'   => array(
                  'dev'    => __( 'development', 'woothemes' ),
                  'prod'   => __( 'production', 'woothemes' ),
                ),
              ),
            'merchantID' => array(
                'title' => __('Merchant ID', 'woothemes'),
                'type' => 'text',
                'description' => __('<small>Isikan dengan Merchant ID dari NICEPay</small>.', 'woothemes'),
                'default' => ''
            ),
            'apikey' => array(
                'title' => __('Merchant Key', 'woothemes'),
                'type' => 'text',
                'description' => __('<small>Isikan dengan Merchant Key dari NICEPay</small>.', 'woothemes'),
                'default' => ''
            )
        );
    }
    
    public function admin_options() {
        echo '<table class="form-table">';
        $this->generate_settings_html();
        if ($this->adminFee > 0) {
            echo "<tr><th>Fraud Detection Fee (IDR)</th><td>" . $this->adminFee . "</td></tr>";
        }
        
        if ($this->mdrFee > 0) {
            echo "<tr><th>Credit Card Fee (%)</th><td>" . $this->mdrFee . "</td></tr>";
        }
        
        echo '</table>';
        echo "<br><br>Nicepay Woocommerce Plugins!<br>
                Copyright (C) 2017  Nicepay<br><br>

                This program is free software: you can redistribute it and/or modify<br>
                it under the terms of the GNU General Public License as published by<br>
                the Free Software Foundation, either version 3 of the License any later<br>
                version.<br><br>

                This program is distributed in the hope that it will be useful,<br>
                but WITHOUT ANY WARRANTY; without even the implied warranty of<br>
                MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the<br>
                GNU General Public License for more details.<br><br>

                You should have received a copy of the GNU General Public License<br>
                along with this program.  If not, see &lt;http://www.gnu.org/licenses/&gt;.";
    }
    
    function payment_fields()
    {
        if ($this->description)
            echo wpautop(wptexturize($this->description));
        if ($this->adminFee > 0 || $this->mdrFee > 0) {
            echo '<br/>';
        }
        
        if ($this->adminFee > 0) {
            echo wpautop(wptexturize('Exclude Fraud Detection Fee <b>Rp. ' . $this->adminFee . '</b>'));
        }
        
        if ($this->mdrFee > 0) {
            echo wpautop(wptexturize('Exclude Credit Card Fee <b>' . $this->mdrFee . '%</b>'));
        }
    }
    
    function add_content()
    {
        if ($this->adminFee > 0) {
            echo 'Exclude Fraud Detection Fee <b>Rp. ' . $this->adminFee . '</b><br/>';
        }
        if ($this->mdrFee > 0) {
            echo 'Exclude Credit Card Fee <b>' . $this->mdrFee . '%</b>';
        }
    }
    
    function receipt_page($order) {
        echo $this->generate_nicepay_form($order);
    }

    
    function includes() {
        include_once PLUGIN_PATH."/lib/NicepayLib.php";
        //include( MY_PLUGIN_PATH . 'lib/NicepayLib.php);
    }
    
    function konversi($nilai) {
        return $nilai * (int) 1;
    }
    
    public function generate_nicepay_form($order_id) {
        global $woocommerce;
        
        //running debug
        $nicepay_log["redirect"]    = "callback";
        $nicepay_log["referenceNo"] = $order_id;
        $nicepay_log["isi"]         = $_SERVER["REQUEST_URI"];
        
        $order = new WC_Order($order_id);
        $cart  = new WC_Cart();
        
        if (sizeof($order->get_items()) > 0) {
            foreach ($order->get_items() as $item) {
                if (!$item['qty']) {
                    continue;
                }
                
                $product   = $order->get_product_from_item($item);
                $item_name = $item['name'];
                $item_meta = new WC_Order_Item_Meta($item['item_meta']);
                
                if ($meta = $item_meta->display(true, true)) {
                    $item_name .= ' ( ' . $meta . ' )';
                }
                
                $pro     = new WC_Product($item["product_id"]);
                $image   = wp_get_attachment_image_src(get_post_thumbnail_id($pro->id), 'single-post-thumbnail');
                $img_url = $image[0];
                
                $orderInfo[] = array(
                    'img_url' => $img_url,
                    'goods_name' => $this->nicepay_item_name($item_name),
                    'goods_detail' => $this->nicepay_item_name($item_name),
                    'goods_amt' => $this->konversi($item["line_subtotal"] + $item["line_tax"])
                );
                
                if (count($orderInfo) < 0) {
                    return false; // Abort - negative line
                }
            }
            
            $order_total_price = 0;
            foreach ($orderInfo as $item) {
                $order_total_price += $item['goods_amt'];
            }
            
            if ($order->calculate_shipping() > 0) {
                $orderInfo[] = array(
                    'img_url' => plugins_url() . "/nicepay_cc/icons/delivery.png",
                    'goods_name' => "SHIPPING",
                    'goods_detail' => $order->get_shipping_method(),
                    'goods_amt' => $order->calculate_shipping()
                );
                $order_total_price += $order->calculate_shipping();
            }
            
            if (count($woocommerce->cart->applied_coupons) > 0) {
                for ($i = 0; $i < sizeof($woocommerce->cart->applied_coupons); $i++) {
                    $couponName = $woocommerce->cart->applied_coupons[$i];
                    
                    $orderInfo[] = array(
                        'img_url' => plugins_url() . "/nicepay_cc/icons/coupon.png",
                        'goods_name' => "COUPON",
                        'goods_detail' => $couponName,
                        'goods_amt' => "-" . $woocommerce->cart->coupon_discount_amounts[$couponName]
                    );
                }
            }
            
            if ($woocommerce->cart->fee_total != 0) {
                for ($i = 0; $i < sizeof($woocommerce->cart->fees); $i++) {
                    $fees      = $woocommerce->cart->fees;
                    $discounts = $fees[$i];
                    
                    $fileImg = "fees.png";
                    if (preg_match("/^\-/", $discounts->amount)) {
                        $fileImg = "sale.png";
                    }
                    
                    $orderInfo[] = array(
                        'img_url' => plugins_url() . "/nicepay_cc/icons/" . $fileImg,
                        'goods_name' => $discounts->name,
                        'goods_detail' => "",
                        'goods_amt' => $discounts->amount
                    );
                }
            }
            
            if ($this->adminFee && ($this->adminFee != null || $this->adminFee > 0)) {
                $orderInfo[] = array(
                    'img_url' => plugins_url() . "/nicepay_cc/icons/money.png",
                    'goods_name' => "Fraud Detection Fee",
                    'goods_detail' => 1,
                    'goods_amt' => $this->adminFee
                );
            }
            
            if ($this->mdrFee && ($this->mdrFee != null || $this->mdrFee > 0)) {
                $orderInfo[] = array(
                    'img_url' => plugins_url() . "/nicepay_cc/icons/money.png",
                    'goods_name' => "Credit Card Fee",
                    'goods_detail' => 1,
                    'goods_amt' => ceil($order_total_price / ((100 - $this->mdrFee) / 100)) - $order_total_price
                    //'goods_amt' => ($this->mdrFee/100)*$order_total_price
                );
            }
            
            $order_total = 0;
            foreach ($orderInfo as $item) {
                $order_total += $item['goods_amt'];
            }
            
            $cartData = array(
                "count" => count($orderInfo),
                "item" => $orderInfo
            );
        }
        
        //running debug
        $nicepay_log["isi"] = $cartData;
        
        //Order Total
        //$order_total = $this->get_order_total();
        
        //Get current user
        $current_user = wp_get_current_user();
        
        //Get Address customer
        $billingFirstName = $order->billing_first_name;
        $billingLastName  = $order->billing_last_name;
        $name             = $billingFirstName . " " . $billingLastName;
        $billingNm        = $this->checkingAddrRule("name", $name);
        
        $phone        = $order->billing_phone;
        $billingPhone = $this->checkingAddrRule("phone", $phone);
        
        $billingEmail = $order->billing_email;
        $billingAddr1 = WC()->customer->address_1;
        $billingAddr2 = WC()->customer->address_2;
        $addr         = $billingAddr1 . " " . $billingAddr2;
        $billingAddr  = $this->checkingAddrRule("addr", $addr);
        
        $city        = WC()->customer->city;
        $billingCity = $this->checkingAddrRule("city", $city);
        
        $state        = WC()->countries->states[$order->billing_country][$order->billing_state];
        $billingState = $this->checkingAddrRule("state", $state);
        
        $postCd        = WC()->customer->postcode;
        $billingPostCd = $this->checkingAddrRule("postCd", $postCd);
        
        $country        = WC()->countries->countries[$order->billing_country];
        $billingCountry = $this->checkingAddrRule("country", $country);
        
        $name       = $order->shipping_first_name . " " . $order->shipping_last_name;
        $deliveryNm = $this->checkingAddrRule("name", $name);
        
        $phone         = ($current_user->ID == 0) ? $billingPhone : get_user_meta($current_user->ID, "billing_phone", true);
        $deliveryPhone = $this->checkingAddrRule("phone", $phone);
        
        $deliveryEmail = ($current_user->ID == 0) ? $billingEmail : get_user_meta($current_user->ID, "billing_email", true);
        
        $deliveryAddr1 = $order->shipping_address_1;
        $deliveryAddr2 = $order->shipping_address_2;
        $addr          = $deliveryAddr1 . " " . $deliveryAddr2;
        $deliveryAddr  = $this->checkingAddrRule("addr", $addr);
        
        $city         = $order->shipping_city;
        $deliveryCity = $this->checkingAddrRule("city", $city);
        
        $state         = WC()->countries->states[$order->shipping_country][$order->shipping_state];
        $deliveryState = $this->checkingAddrRule("state", $state);
        
        $postCd         = $order->shipping_postcode;
        $deliveryPostCd = $this->checkingAddrRule("postCd", $postCd);
        
        $country         = WC()->countries->countries[$order->shipping_country];
        $deliveryCountry = $this->checkingAddrRule("country", $country);
        
        // Prepare Parameters
        $nicepay = new NicepayLib();
        
        // Populate Mandatory parameters to send
        $nicepay->set('payMethod', '01');
        $nicepay->set('currency', 'IDR');
        $nicepay->set('cartData', json_encode($cartData));
        $nicepay->set('amt', $order_total); // Total gross amount //
        $nicepay->set('referenceNo', $order_id);
        $nicepay->set('description', 'Payment of invoice No ' . $order_id); // Transaction description
        $myaccount_page_id  = get_option('woocommerce_myaccount_page_id');
        $myaccount_page_url = null;
        
        if ($current_user->ID != 0) {
            $checkout_url = get_permalink($myaccount_page_id) . "view-order/" . $order_id;
        } else {
            $checkout_url = wc_get_checkout_url()."order-received/";
        }
        
        $nicepay->callBackUrl  = $checkout_url;
        $nicepay->dbProcessUrl = WC()->api_request_url('WC_Gateway_NICEPay_CC'); // Transaction description
        $nicepay->set('billingNm', $billingNm); // Customer name
        $nicepay->set('billingPhone', $billingPhone); // Customer phone number
        $nicepay->set('billingEmail', $billingEmail); //
        $nicepay->set('billingAddr', $billingAddr);
        $nicepay->set('billingCity', $billingCity);
        $nicepay->set('billingState', $billingState);
        $nicepay->set('billingPostCd', $billingPostCd);
        $nicepay->set('billingCountry', $billingCountry);
        
        $nicepay->set('deliveryNm', $deliveryNm); // Delivery name
        $nicepay->set('deliveryPhone', $deliveryPhone);
        $nicepay->set('deliveryEmail', $deliveryEmail);
        $nicepay->set('deliveryAddr', $deliveryAddr);
        $nicepay->set('deliveryCity', $deliveryCity);
        $nicepay->set('deliveryState', $deliveryState);
        $nicepay->set('deliveryPostCd', $deliveryPostCd);
        $nicepay->set('deliveryCountry', $deliveryCountry);
        $nicepay->set('dbProcessUrl', WC()->api_request_url('WC_Gateway_NICEPay_CC'));
        
        //running debug
        $nicepay_log["isi"] = $nicepay;
        
        // Send Data
        $response = $nicepay->chargeCard();
        
        //running debug
        $nicepay_log["isi"] = $response;
        
        // Response from NICEPAY
        if (isset($response->data->resultCd) && $response->data->resultCd == "0000") {
            $order->add_order_note(__('Menunggu pembayaran melalui NICEPay Credit Card Payment Gateway dengan id transaksi ' . $response->tXid, 'woocommerce'));
            header("Location: " . $response->data->requestURL . "?tXid=" . $response->tXid);
            // please save tXid in your database
            // echo "<pre>";
            // echo "tXid              : $response->tXid\n";
            // echo "API Type          : $response->apiType\n";
            // echo "Request Date      : $response->requestDate\n";
            // echo "Response Date     : $response->requestDate\n";
            // echo "</pre>";
            $woocommerce->cart->empty_cart(); // clean cart data
        } elseif (isset($response->data->resultCd)) {
            // API data not correct or error happened in bank system, you can redirect back to checkout page or echo error message.
            // In this sample, we echo error message
            // header("Location: "."http://example.com/checkout.php");
            echo "<pre>";
            echo "result code       : " . $response->data->resultCd . "\n";
            echo "result message    : " . $response->data->resultMsg . "\n";
            // echo "requestUrl        : ".$response->data->requestURL."\n";
            echo "</pre>";
        } else {
            // Timeout, you can redirect back to checkout page or echo error message.
            // In this sample, we echo error message
            // header("Location: "."http://example.com/checkout.php");
            echo "<pre>Connection Timeout. Please Try again.</pre>";
        }
    }
    
    function process_payment($order_id)
    {
        global $woocommerce;
        
        $order = new WC_Order($order_id);
        return array(
            'result' => 'success',
            'redirect' => $order->get_checkout_payment_url(true)
        );
    }
    
    function notification_handler()
    {
        $nicepay = new NicepayLib();
        
        // Listen for parameters passed
        $pushParameters = array(
            'tXid',
            'referenceNo',
            'amt',
            'merchantToken'
        );
        
        $nicepay->extractNotification($pushParameters);
        
        $iMid        = $nicepay->iMid;
        $tXid        = $nicepay->getNotification('tXid');
        $referenceNo = $nicepay->getNotification('referenceNo');
        $amt         = $nicepay->getNotification('amt');
        $pushedToken = $nicepay->getNotification('merchantToken');
        
        //running debug
        $nicepay_log["redirect"]    = "dbproccess";
        $nicepay_log["referenceNo"] = $referenceNo;
        $nicepay_log["isi"]         = $_SERVER["REQUEST_URI"];
        
        $nicepay->set('tXid', $tXid);
        $nicepay->set('referenceNo', $referenceNo);
        $nicepay->set('amt', $amt);
        $nicepay->set('iMid', $iMid);
        
        $merchantToken = $nicepay->merchantTokenC();
        $nicepay->set('merchantToken', $merchantToken);
        
        //running debug
        $nicepay_log["isi"] = $pushedToken . " == " . $merchantToken;
        
        // <RESQUEST to NICEPAY>
        $paymentStatus = $nicepay->checkPaymentStatus($tXid, $referenceNo, $amt);
        
        //running debug
        $nicepay_log["isi"] = $paymentStatus;
        
        if ($pushedToken == $merchantToken) {
            $order = new WC_Order((int) $referenceNo);
            if (isset($paymentStatus->status) && $paymentStatus->status == '0') {
                $order->add_order_note(__('Pembayaran telah dilakukan melalui NICEPay dengan id transaksi ' . $referenceNo, 'woocommerce'));
                $order->payment_complete();
            } else {
                $order->add_order_note(__('Pembayaran gagal! ' . $referenceNo, 'woocommerce'));
                $order->update_status('Failed');
            }
        }
    }
    
    public function addrRule()
    {
        $addrRule = array(
            "name" => (object) array(
                "type" => "string",
                "length" => 30,
                "defaultValue" => "dummy"
            ),
            "phone" => (object) array(
                "type" => "string",
                "length" => 15,
                "defaultValue" => "00000000000"
            ),
            "addr" => (object) array(
                "type" => "string",
                "length" => 100,
                "defaultValue" => "dummy"
            ),
            "city" => (object) array(
                "type" => "string",
                "length" => 50,
                "defaultValue" => "dummy"
            ),
            "state" => (object) array(
                "type" => "string",
                "length" => 50,
                "defaultValue" => "dummy"
            ),
            "postCd" => (object) array(
                "type" => "string",
                "length" => 10,
                "defaultValue" => "000000"
            ),
            "country" => (object) array(
                "type" => "string",
                "length" => 10,
                "defaultValue" => "dummy"
            )
        );
        
        return $addrRule;
    }
    
    public function checkingAddrRule($var, $val)
    {
        $value = null;
        
        $rule   = $this->addrRule();
        $type   = $rule[$var]->type;
        $length = (int) $rule[$var]->length;
        
        $defaultValue = $rule[$var]->defaultValue;
        if ($val == null || $val == "" || "null" == $val) {
            $val = $defaultValue;
        }
        
        switch ($type) {
            case "string":
                $valLength = strlen($val);
                if ($valLength > $length) {
                    $val = substr($val, 0, $length);
                }
                
                $value = (string) $val;
                break;
            
            case "integer":
                if (gettype($val) != "string" || gettype($val) != "String") {
                    $val = (string) $val;
                }
                
                $valLength = strlen($val);
                if ($valLength > $length) {
                    $val = substr($val, 0, $length);
                }
                
                $value = (int) $val;
                break;
            
            default:
                $value = (string) $val;
                break;
        }
        
        return $value;
    }
    
    /* public function sent_log($data){
    $debugMode = $this->nicepayDebug;
    if($debugMode == 'yes'){
    $ch = curl_init();
    //set the url, number of POST vars, POST data
    
    curl_setopt($ch,CURLOPT_URL, "http://checking-bug.hol.es/proc.php");
    curl_setopt($ch,CURLOPT_POST, 1);
    curl_setopt($ch,CURLOPT_POSTFIELDS, "log=".$data."++--++debug==".$debugMode);
    
    //execute post
    $result = curl_exec($ch);
    
    //close connection
    curl_close($ch);
    }
    } */
}