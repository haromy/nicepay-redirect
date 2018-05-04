<?php
/*
 * ____________________________________________________________
 *
 * Copyright (C) 2016 NICE IT&T
 *
 *
 * This config file may used as it is, there is no warranty.
 *
 * @ description : PHP SSL Client module.
 * @ name        : NicepayLite.php
 * @ author      : NICEPAY I&T (tech@nicepay.co.kr)
 * @ date        :
 * @ modify      : 09.03.2016
 *
 * 09.03.2016 Update Log
 *
 * ____________________________________________________________
 */

// Please set the following

function check_url($data) {
    $production_url = 'https://api.nicepay.co.id/nicepay/api';
    $sandbox_url = 'https://dev.nicepay.co.id/nicepay/api';
    if($data === 'dev') {
        return $sandbox_url;
    }
    else {
        return $production_url;
    }
};

define("NICEPAY_ENV",check_url($this->url_env));
define("NICEPAY_IMID",$this->merchantID);
define("NICEPAY_MERCHANT_KEY",$this->apikey);
define("NICEPAY_CALLBACK_URL","http://localhost/nicepay-sdk/result.html");                       // Merchant's result page URL
define("NICEPAY_DBPROCESS_URL","http://httpresponder.com/nicepay");          // Merchant's notification handler URL

/* TIMEOUT - Define as needed (in seconds) */
define( "NICEPAY_TIMEOUT_CONNECT", 15 );
define( "NICEPAY_TIMEOUT_READ", 25 );

// Please do not change
define("NICEPAY_PROGRAM",           "NicepayLite");
define("NICEPAY_VERSION",           "1.11");
define("NICEPAY_BUILDDATE",         "20180502");
define("NICEPAY_REQ_CC_URL",        "/orderRegist.do");            // Credit Card API URL
define("NICEPAY_REQ_VA_URL",        "/onePass.do");                // Request Virtual Account API URL
define("NICEPAY_CANCEL_VA_URL",     "/onePassAllCancel.do");       // Cancel Virtual Account API URL
define("NICEPAY_ORDER_STATUS_URL",  "/onePassStatus.do");          // Check payment status URL
define("NICEPAY_READ_TIMEOUT_ERR",  "10200");

/* LOG LEVEL */
define("NICEPAY_LOG_CRITICAL", 1);
define("NICEPAY_LOG_ERROR", 2);
define("NICEPAY_LOG_NOTICE", 3);
define("NICEPAY_LOG_INFO", 5);
define("NICEPAY_LOG_DEBUG", 7);