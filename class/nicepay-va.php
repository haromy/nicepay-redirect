<?php
class wc_gateway_nicepay_va extends WC_Payment_Gateway {
    var $callbackUrl;
    var $tSuccess = array();
    private $adminFee = 0;

    public function nicepay_item_name($item_name ) {
        if ( strlen($item_name ) > 127 ) {
            $item_name = substr($item_name, 0, 124 ) . '...';
        }

        return html_entity_decode($item_name, ENT_NOQUOTES, 'UTF-8' );
    }

    public function __construct() {
        //plugin id
        $this->id = 'nicepay_va';

        //Payment Gateway title
        $this->method_title = 'NICEPay Virtual Account';

        //true only in case of direct payment method, false in our case
        $this->has_fields = false;

        //payment gateway logo
        $this->icon = plugins_url('logobank.png', dirname(__FILE__));

        //redirect URL
        $this->redirect_url     = str_replace( 'https:', 'http:', add_query_arg( 'wc-api', 'wc_gateway_nicepay_va', home_url( '/' ) ) );

        //Load settings
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables
        $this->enabled      = $this->settings['enabled'];
        $this->title        = "NICEPay Virtual Account";
        $this->description  = $this->settings['description'];
        $this->url_env      = $this->settings['select_environment'];
        $this->apikey       = $this->settings['apikey'];
        $this->merchantID   = $this->get_option('merchantID');
        $this->vaType       = $this->get_option('va_type');
        $this->vaLength     = $this->get_option('va_length');
        $this->banklist     = $this->get_option('bankedlist');
        $this->bankset = array(
            "BMRI" => array("label" => "Bank Mandiri"),
            "BBBA" => array("label" => "Bank Permata"),
            "IBBK" => array("label" => "BII Maybank"),
            "BNIN" => array("label" => "BNI"),
            "CENA" => array("label" => "BCA"),
            "BNIA" => array("label" => "CIMB Niaga"),
            "HNBN" => array("label" => "Keb Hana Bank"),
            "BRIN" => array("label" => "BRI"),
            "BDIN" => array("label" => "Danamon")
        );

        // Actions
        $this->includes();
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(&$this, 'process_admin_options'));
        add_action('woocommerce_receipt_nicepay_va', array(&$this, 'receipt_page'));

        add_action( 'woocommerce_processing', array(&$this, 'add_payment_detail_to_order_email'), 1 );
        add_action( 'woocommerce_thankyou', array($this, 'add_description_payment_success'), 1 );

        //Add Text to email
        add_action( 'woocommerce_email_after_order_table', array($this, 'add_content' ));
        // Payment listener/API hook
        //add_action( 'woocommerce_api_wc_gateway_nicepay_va', array($this, 'notification_handler' ) );
    }

    function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __( 'Enable/Disable', 'woothemes' ),
                'label' => __( 'Enable NICEPay', 'woothemes' ),
                'type' => 'checkbox',
                'description' => '',
                'default' => 'no'
            ),
            'title' => array(
                'title' => __( 'Title', 'woothemes' ),
                'type' => 'text',
                'description' => __( '', 'woothemes' ),
                'default' => __( 'Pembayaran NICEPay Virtual Payment', 'woothemes' )
            ),
            'description' => array(
                'title' => __( 'Description', 'woothemes' ),
                'type' => 'textarea',
                'description' => __( '', 'woothemes' ),
                'default' => 'Sistem pembayaran menggunakan NICEPay Virtual Payment.'
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
                'title' => __( 'Merchant ID', 'woothemes' ),
                'type' => 'text',
                'description' => __( '<small>Isikan dengan Merchant ID dari NICEPay</small>.', 'woothemes' ),
                'default' => ''
            ),
            'apikey' => array(
                'title' => __( 'Merchant Key', 'woothemes' ),
                'type' => 'text',
                'description' => __( '<small>Isikan dengan Merchant Key dari NICEPay</small>.', 'woothemes' ),
                'default' => ''
            ),
            'va_type' => array(
                'title' => __( 'VA Fix Type', 'woothemes' ),
                'type' => 'select',
                'default' => '0',
                'description' => __( 'If you want to use FIX VA number, kindly select this. Dont forget contact NICEPay to activate it.<br><strong>If enable, please disable setting for guest checkout and disable registration on checkout page.</strong>', 'woothemes' ),
                'options'   => array(
                  '0'    => __( 'Disable', 'woothemes' ),
                  '1'   => __( 'by customer ID', 'woothemes' ),
                  '2'   => __( 'by phone number of customer', 'woothemes' ),
                ),
            ),
            'va_length' => array(
                'title' => __( 'FIX VA Lenght', 'woothemes' ),
                'type' => 'text',
                'description' => __( '<small>If fix VA is enabled. please fill this field</small>.', 'woothemes' ),
                'default' => ''
            ),
            'bankedlist' => array(
                'title'         => __( 'Bank used', 'woothemes' ),
                'type'          => 'multiselect',
                'default'       => '',
                'description'   => __( 'select the bank which is active for payment', 'woothemes' ),
                'options' => array(
                  'BMRI'    => 'Bank Mandiri',
                  'IBBK'    => 'BII Maybank',
                  'BBBA'    => 'Bank Permata',
                  'CENA'    => 'Bank BCA',
                  'BNIN'    => 'Bank BNI',
                  'HNBN'    => 'Bank KEB Hana Indonesia',
                  'BRIN'    => 'Bank BRI',
                  'BNIA'    => 'Bank CIMB',
                  'BDIN'    => 'Bank DANAMON'
                )
            ),
        );
    }

    public function admin_options() {
        echo '<table class="form-table">';
        $this->generate_settings_html();
        if($this->adminFee > 0){
            echo "<tr><th>Admin Fee (IDR)</th><td>".$this->adminFee."</td></tr>";
        }
        echo '</table>';
    }

    function payment_fields() {
        $bank = $this->bankset;
        $output = "";
        $output .= wpautop(wptexturize($this->description)).'<br>';
        $output .= "Pilih Bank Account :".'<br>';

        $data = $this->banklist;
        $lenght = count($data);
        $output .= '<div class="banklist"><select name="bankCd" style="width:100%;">';
        for ($i=0;$i<$lenght;$i++) {
            $output .= '<option value="'.$data[$i].'" data-icon="'.$data[$i].'">'.$bank[$data[$i]]["label"].'</option>';   
        }
        $output .= '</select></div>';

        if($this->adminFee > 0){
            $output .= '<br/>'.wpautop(wptexturize('Exclude Admin Fee <b>Rp. '.$this->adminFee.'</b>'));
        }
        echo $output;
        //echo '<br>'.$this->vaType;
        //$varesult = $this->gen_fix_va($this->vaType);
        //echo '<br>'.$varesult;
    }

    function gen_fix_va($data){
        $return = "";
        switch($data) {
            case 0:
                break;
            case 1:
                $return = get_current_user_id();
                break;
            case 2:
                $return = get_user_meta(get_current_user_id(),'billing_phone',true);
                break;
        }
        $lengthdata = strlen($return);
        $result = "";
        if ($lengthdata < 8) {
            $lack = 8 - $lengthdata;
            for ($i=0;$i<$lack;$i++) {
                $result .= "0";
            }
            $result .= $return;
        }
        else {
            $result = substr($return, -8);
        }
        return $result;
    }

    function add_content() {
        if($this->adminFee > 0){
            echo 'Exclude Admin Fee <b>Rp. '.$this->adminFee.'</b><br/>';
        }
    }

    public function validate_fields(){
        WC()->session->set('bankCd', $_POST["bankCd"]);
    }

    function how_to($input) {
        $output ="";
        $tabdown = "";
        $i = 0;
        $howto = array(
            "BMRI" => array(
                "atm" => array("Pilih Menu <span class='a'>Bayar/Beli</span>", "Pilih <span class='a'>Lainnya</span>", "Pilih <span class='a'>Multi Payment</span>", "Input <span class='a'>70014</span> sebagai <span class='a'>Kode Institusi</span>", "input Virtual Account Number,<br><span class='vanumber'></span>", "Pilih <span class='a'>Benar</span>", "Pilih <span class='a'>Ya</span>", "Pilih <span class='a'>Ya</span>", "Ambil bukti bayar anda", "Selesai"),
                "sms" => array(),
                "mbank" => array("Login <span class='a'>Mobile Banking</span>", "Pilih <span class='a'>Bayar</span>", "Pilih <span class='a'>Multi Payment</span>", "Input <span class='a'>Transferpay</span> sebagai <span class='a'>Penyedia Jasa</span>", "Input Nomor Virtual Account,<br><span class='vanumber'></span>", "Pilih <span class='a'>Lanjut</span>", "Input <span class='a'>OTP</span> and <span class='a'>PIN</span>", "Pilih <span class='a'>OK</span>", "Bukti bayar ditampilkan", "Selesai"),
                "ibank" => array("Login <span class='a'>Internet Banking</span>", "Pilih <span class='a'>Bayar</span>", "Pilih <span class='a'>Multi Payment</span>", "Input <span class='a'>Transferpay</span> sebagai Penyedia Jasa", "Input Nomor Virtual Account, <br><span class='vanumber'></span> sebagai <span class='a'>Kode Bayar</span>", "Ceklis <span class='a'>IDR</span>", "Klik <span class='a'>Lanjutkan</span>", "Bukti bayar ditampilkan", "Selesai")
            ),
            "CENA" => array(
                "atm" => array("Pilih Menu <span class='a'>Transaksi Lainnya</a>", "Pilih <span class='a'>Transfer</a>", "Pilih <span class='a'>Ke rekening BCA Virtual Account</a>", "Input Nomor Virtual Account,<br><span class='vanumber'></span>", "Pilih <span class='a'>Benar</a>", "Pilih <span class='a'>Ya</a>", "Ambil bukti bayar anda", "Selesai"),
                "sms" => array(),
                "mbank" => array("Login <span class='a'>Mobile Banking</a>", "Pilih <span class='a'>m-Transfe</a>", "Pilih <span class='a'>BCA Virtual Account</a>", "Input Nomor Virtual Account,<br><span class='vanumber'></span> sebagai No. <span class='a'>Virtual Account</a>", "Klik <span class='a'>Send</a>", "Informasi VA akan ditampilkan", "Klik <span class='a'>O</a>", "Input <span class='a'>PIN</a> Mobile Banking", "Bukti bayar ditampilkan", "Selesai"),
                "ibank" => array("Login <span class='a'>Internet Banking</a>", "Pilih <span class='a'>Transfer Dana</a>", "Pilih <span class='a'>Transfer Ke BCA Virtual Account</a>", "Input Nomor Virtual Account,<br><span class='vanumber'></span> sebagai No. <span class='a'>Virtual Account</a>", "Klik <span class='a'>Lanjutka</a>n", "Input <span class='a'>Respon KeyBCA Apply 1</a>", "Klik <span class='a'>Kirim</a>", "Bukti bayar ditampilkan", "Selesai")
            ),
            "BNI" => array(
                "atm" => array("Pilih <span class='a'>Menu Lain", "Pilih <span class='a'>Menu Transfer", "Pilih <span class='a'>Ke Rekening BNI", "Masukkan Nominal. misal, <span class='a'>10000", "Masukkan Nomor Virtual Account.<span class='vanumber'></span>", "Pilih <span class='a'>Ya", "Ambil Bukti Pembayaran Anda", "Selesai"),
                "sms" => array("Masuk <span class='a'>Aplikasi SMS Banking BNI</span>", "Pilih <span class='a'>Menu Transfer</span>", "Pilih <span class='a'>Trf Rekening BNI</span>", "Masukkan Nomor Virtual Account.<br><span class='vanumber'></span>", "Masukkan Jumlah Tagihan. misal, <span class='a'>10000</span>", "Pilih <span class='a'>Proses</span>", "Pada Pop Up Message, Pilih <span class='a'>Setuj</span>u", "Anda Akan Mendapatkan Pesan Konfirmasi", "Masukkan 2 Angka Dari <span class='a'>PIN Anda</span> Dengan Mengikuti Petunjuk Yang Tertera Pada Pesan", "Bukti Pembayaran Ditampilkan", "Selesai"),
                "mbank" => array("Pilih <span class='a'>Transfer</span>", "Pilih <span class='a'>Antar Rekening BNI</span>", "Pilih <span class='a'>Rekening Tujuan</span>", "Pilih <span class='a'>Input Rekening Baru</span>", "Masukkan Nomor Virtual Account sebagai <span class='a'>Nomor Rekening, <br><span class='vanumber'></span>", "Klik <span class='a'>Lanjut</span>", "Klik <span class='a'>Lanjut Kembali</span>", "Masukkan Nominal Tagihan. misal, <span class='a'>10000</span>", "Klik <span class='a'>Lanjut</span>", "Periksa Detail Konfirmasi. Pastikan Data Sudah Benar", "Jika Sudah Benar, Masukkan <span class='a'>Password Transaksi</span>", "Klik <span class='a'>Lanjut</span>", "Bukti Pembayaran Ditampilkan", "Selesai"),
                "ibank" => array("Masuk <span class='a'>Internet Banking</span>", "Pilih <span class='a'>Transaksi</span>", "Pilih <span class='a'>Info dan Administrasi</span>", "Pilih <span class='a'>Atur Rekening Tujuan</span>", "Pilih <span class='a'>Tambah Rekening Tujuan</span>", "Klik <span class='a'>Ok</span>", "Masukkan Nomor Order Sebagai <span class='a'>Nama Singkat</span> misal, <span class='a'>Invoice-1234</span>", "Masukkan Nomor Virtual Account Sebagai <span class='a'>Nomor Rekening</span> : <span class='vanumber'></span>", "Lengkapi Semua Data Yang Diperlukan", "Klik <span class='a'>Lanjutka</span>n", "Masukkan <span class='a'>Kode Otentikasi Token</span> lalu, <span class='a'>Proses</span>", "Rekening Tujuan Berhasil Ditambahkan", "Pilih <span class='a'>Menu Transfer</span>", "Pilih <span class='a'>Transfer Antar Rek. BNI</span>", "Pilih Rekening Tujuan dengan <span class='a'>Nama Singkat</span> Yang Sudah Anda Tambahkan. misal, <span class='a'>Invoice-1234</span>", "Masukkan Nominal. misal, <span class='a'>10000</span>", "Masukkan <span class='a'>Kode Otentikasi Token</span>", "Bukti Pembayaran Ditampilkan", "Selesai")
            ),
            "IBBK" => array(
                "atm" => array("Pilih Menu <span class='a'>Pembayaran/Top Up Pulsa", "Pilih <span class='a'>Virtual Account", "Input Nomor Virtual Account,<br><span class='vanumber'></span>", "Pilih <span class='a'>Benar", "Pilih <span class='a'>Ya", "Ambil bukti bayar anda", "Selesai"),
                "sms" => array("SMS ke <span class='a'>69811", "Ketik <span class='a'>TRANSFER <Nomor Virtual Account> <Nominal>, <br>Contoh: <span class='a'>TRANSFER <span class='vanumber'></span> 10000</span>", "Kirim SMS", "Anda akan mendapat balasan,<br><span class='a'>Transfer dr rek < nomor rekening anda > ke rek < Nomor Virtual Account > sebesar Rp. 10.000 Ketik < karakter acak >", "Balas SMS tersebut, ketik <span class='a'>< karakter acak >", "Kirim SMS", "Selesai"),
                "mbank" => array(),
                "ibank" => array("Login <span class='a'>Internet Banking</span>", "Pilih <span class='a'>Rekening dan Transaksi</span>", "Pilih <span class='a'>Maybank Virtual Account</span>", "Pilih <span class='a'>Sumber Tabunga</span>n", "Input Nomor Virtual Account,<span class='vanumber'></span>", "Input Nominal, misal. <span class='a'>10000</span>", "Klik <span class='a'>Submit</span>", "Input <span class='a'>SMS Token</span>", "Bukti bayar ditampilkan", "Selesai")
            ),
            "BBBA" => array(
                "atm" => array("Pilih Menu <span class='a'>Transaksi Lainnya</span>", "Pilih <span class='a'>Pembayaran</span>", "Pilih <span class='a'>Pembayaran Lain-lain</span>", "Pilih <span class='a'>Virtual Account</span>", "Input Nomor Virtual Account, <span class='vanumber'></span>", "Select <span class='a'>Benar</span>", "Select <span class='a'>Ya</span>", "Ambil bukti bayar anda", "Selesai"),
                "sms" => array(),
                "mbank" => array("Login <span class='a'>Mobile Banking</span>", "Pilih <span class='a'>Pembayaran Tagihan</span>", "Pilih <span class='a'>Virtual Account</span>", "Input Nomor Virtual Account, <span class='vanumber'></span> sebagai <span class='a'>No. Virtual Account</span>", "Input Nominal misal. <span class='a'>10000</span>", "Klik <span class='a'>Kirim</span>", "Input <span class='a'>Token</span>", "Klik <span class='a'>Kirim</span>", "Bukti bayar akan ditampilkan", "Selesai"),
                "ibank" => array("Login Internet Banking", "Pilih <span class='a'>Pembayaran Tagihan</span>", "Pilih <span class='a'>Virtual Account</span>", "Input Nomor Virtual Account, <span class='vanumber'></span> sebagai <span class='a'>No. Virtual Account</span>", "Input Nominal misal. <span class='a'>10000</span>", "Klik <span class='a'>Kirim</span>", "Input <span class='a'>Token</span>", "Klik <span class='a'>Kirim</span>", "Bukti bayar akan ditampilkan", "Selesai")
            ),
            "BNIA" => array(
                "atm" => array("Pilih Menu <span class='a'>Pembayaran</span>", "Pilih Menu <span class='a'>Lanjut</span>", "Pilih Menu <span class='a'>Virtual Account</span>", "Masukkan Nomor Virtual Account, <span class='vanumber'></span>", "Pilih <span class='a'>Proses</span>", "Data Virtual Account akan ditampilkan", "Pilih <span class='a'>Proses</span>", "Ambil bukti bayar anda", "Selesai"),
                "sms" => array(),
                "mbank" => array("Login <span class='a'>Go Mobile</span>", "Pilih Menu <span class='a'>Transfer</span>", "Pilih Menu <span class='a'>Other Rekening Ponsel/CIMB Niaga</span>", "Pilih Sumber Dana yang akan digunakan", "Pilih <span class='a'>Casa</span>", "Masukkan Nomor Virtual Account, misal. <span class='vanumber'></span>", "Masukkan Nominal misal. <span class='a'>10000</span>", "klik <span class='a'>Lanjut</span>", "Data Virtual Account akan ditampilkan", "Masukkan <span class='a'>PIN Mobile</span>", "Klik <span class='a'>Konfirmasi</span>", "Bukti bayar akan dikirim melalui sms", "Selesai"),
                "ibank" => array("Login <span class='a'>Internet Banking</span>", "Pilih <span class='a'>Bayar Tagihan</span>", "Rekening Sumber - Pilih yang akan Anda digunakan", "Jenis Pembayaran - Pilih <span class='a'>Virtual Account</span>", "Untuk Pembayaran - Pilih <span class='a'>Masukkan Nomor Virtual Account</span>", "Nomor Rekening Virtual, misal. <span class='vanumber'></span>", "Isi <span class='a'>Remark Jika diperlukan</span>", "Klik <span class='a'>Lanjut</span>", "Data Virtual Account akan ditampilkan", "Masukkan<span class='a'> mPIN</span>", "Klik <span class='a'>Kirim</span>", "Bukti bayar akan ditampilkan", "Selesai")
            ),
            "BDIN" => array(
                "atm" => array("Pilih Menu <span class='a'>Pembayaran</span>", "Pilih <span class='a'>Lainnya</span>", "Pilih Menu <span class='a'>Virtual Account</span>", "Input Nomor Virtual Account, <span class='vanumber'></span>", "Pilih <span class='a'>Benar</span>", "Data Virtual Account akan ditampilkan", "Pilih <span class='a'>Ya</span>", "Ambil bukti bayar anda", "Selesai"),
                "sms" => array(),
                "mbank" => array("Login <span class='a'>D-Mobile</span>", "Pilih menu <span class='a'>Pembayara</span>n", "Pilih menu <span class='a'>Virtual Account</span>", "Pilih <span class='a'>Tambah Biller Baru Pembayaran</span>", "Tekan <span class='a'>Lanjut</span>", "Masukkan Nomor Virtual Account, <span class='vanumber'></span>", "Tekan <span class='a'>Ajukan</span>", "Data Virtual Account akan ditampilkan", "Masukkan <span class='a'>mPIN</span>", "Pilih <span class='a'>Konfirmasi</span>", "Bukti bayar akan dikirim melalui sms", "Selesai"),
                "ibank" => array()
            ),
            "HNBN" => array(
                "atm" => array("Pilih Menu <span class='a'>Pembayaran</span>", "Pilih <span class='a'>Lainnya</span>", "Input Nomor Virtual Account, <span class='vanumber'></span>", "Pilih <span class='a'>Benar</span>", "Pilih <span class='a'>Ya</span>", "Ambil bukti bayar anda", "Selesai"),
                "sms" => array(),
                "mbank" => array(),
                "ibank" => array("Login <span class='a'>Internet Banking</span>", "Pilih menu <span class='a'>Transfer</span> kemudian Pilih <span class='a'>Withdrawal Account Information</span>", "Pilih <span class='a'>Account Number anda</span>", "Input Nomor Virtual Account, <span class='vanumber'></span>", "Input Nominal, misal. <span class='a'>10000</span>", "Click <span class='a'>Submit</span>", "Input <span class='a'>SMS Pin</span>", "Bukti bayar akan ditampilkan", "Selesai")
            ),
            "BRIN" => array(
                "atm" => array("Pilih Menu <span class='a'>Transaksi Lain</span>", "Pilih Menu <span class='a'>Pembayaran</span>", "Pilih Menu <span class='a'>Lain-lain</span>", "Pilih Menu <span class='a'>BRIVA</span>", "Masukkan Nomor Virtual Account, <span class='vanumber'></span>", "Pilih <span class='a'>Ya</span>", "Ambil bukti bayar anda", "Selesai"),
                "sms" => array(),
                "mbank" => array("Login <span class='a'>BRI Mobile</span>", "Pilih <span class='a'>Mobile Banking BRI</span>", "Pilih Menu <span class='a'>Pembayaran</span>", "Pilih Menu <span class='a'>BRIVA</span>", "Masukkan Nomor Virtual Account, <span class='vanumber'></span>", "Masukkan Nominal misal. <span class='a'>10000</span>", "Klik <span class='a'>Ok</span>", "Masukkan <span class='a'>PIN Mobile</span>", "Klik <span class='a'>Kirim</span>", "Bukti bayar akan dikirim melalui sms", "Selesai"),
                "ibank" => array("Login <span class='a'>Internet Banking</span>", "Pilih <span class='a'>Pembayaran</span>", "Pilih <span class='a'>BRIVA</span>", "Masukkan Nomor Virtual Account, <span class='vanumber'></span>", "Klik <span class='a'>Kirim</span>", "Masukkan <span class='a'>Password</span>", "Masukkan <span class='a'>mToken</span>", "Klik <span class='a'>Kirim</span>", "Bukti bayar akan ditampilkan", "Selesai")
            )
        );
        $count_atm = count($howto[$input]["atm"]);
        $count_sms = count($howto[$input]["sms"]);
        $count_mbank = count($howto[$input]["mbank"]);
        $count_ibank = count($howto[$input]["ibank"]);
        
        $output .= "<ul class='headnav'>";
        $tabdown .= "<div class='tabdown'>";
        if ($count_atm > 0) {
            $output .= "<li class='navitem' id='atm'>ATM</li>";
            $tabdown .="<div class='atm howto'><ul>";
            for ($i=0;$i<$count_atm;$i++) {
                $tabdown .= "<li>".$howto[$input]["atm"][$i]."</li>";
            }
            $tabdown .="</ul></div>";
        }
        if ($count_sms > 0) {
            $output .= "<li class='navitem' id='sms'>SMS Banking</li>";
            $tabdown .="<div class='sms howto'><ul>";
            for ($i=0;$i<$count_sms;$i++) {
                $tabdown .= "<li>".$howto[$input]["sms"][$i]."<br>";
            }
            $tabdown .="</ul></div>";
        }
        if ($count_mbank > 0) {
            $output .= "<li class='navitem' id='mbank'>M-Banking</li>";
            $tabdown .="<div class='mbank howto'><ul>";
            for ($i=0;$i<$count_mbank;$i++) {
                $tabdown .= "<li>".$howto[$input]["mbank"][$i]."<br>";
            }
            $tabdown .="</ul></div>";
        }
        if ($count_ibank > 0) {
            $output .= "<li class='navitem' id='ibank'>I-Banking</li>";
            $tabdown .="<div class='ibank howto'><ul>";
            for ($i=0;$i<$count_ibank;$i++) {
                $tabdown .= "<li>".$howto[$input]["ibank"][$i]."<br>";
            }
            $tabdown .="</ul></div>";
        }
        $output .= "</ul>";
        $tabdown .= "</div>";
        $result = $output.$tabdown;
        return $result;
    }

    function receipt_page($order) {
        global $woocommerce;
        echo $this->generate_nicepay_form($order);
        $woocommerce->cart->empty_cart();
    }

    function includes(){
        //Validation class payment gateway woocommerce
        
            include_once PLUGIN_PATH."lib/NicepayLib.php";
    }

    function konversi($nilai) {
        return $nilai*(int)1;
    }

    function getProperty($order, $property) {
        $functionName = "get_".$property;
        // woo commerce version 3
        if (method_exists($order, $functionName)){
          return (string)$order->{$functionName}();
        // woo commerce version 2
        } else { // WC v2
          return (string)$order->{$property};
        }
    }

    public function generate_nicepay_form($order_id) {
        global $woocommerce;

        //running debug
        $nicepay_log["redirect"] = "callback";
        $nicepay_log["referenceNo"] = $order_id;
        $nicepay_log["isi"] = $_SERVER["REQUEST_URI"];
        $this->sent_log(json_encode($nicepay_log));

        $order = new WC_Order($order_id);
        $cart = new WC_Cart();

        if ( sizeof($order->get_items() ) > 0 ) {
            foreach ($order->get_items() as $item ) {
                if ( ! $item['qty'] ) {
                continue;
                }

                $product   = $order->get_product_from_item($item );
                $item_name = $item['name'];
                //$item_meta = new WC_Order_Item_Meta($item['item_meta'] );

                //if ($meta = $item_meta->display( true, true ) ) {
                //    $item_name .= ' ( ' . $meta . ' )';
                //}

                $pro        = new WC_Product($item["product_id"]);
                $image      = wp_get_attachment_image_src( get_post_thumbnail_id($pro->get_id()), 'single-post-thumbnail' );
                $img_url    = $image[0];

                $orderInfo[] = array(
                    'img_url' => $img_url,
                    'goods_name' => $this->nicepay_item_name($item_name ),
                    'goods_detail' => $this->nicepay_item_name($item_name ),
                    'goods_amt' => $this->konversi($item["line_subtotal"]+$item["line_tax"])
                );

                if ( count($orderInfo) < 0 ) {
                    return false; // Abort - negative line
                }
            }

            if($order->calculate_shipping() > 0){
                $orderInfo[] = array(
                    'img_url' => plugins_url()."/nicepay_va/icons/delivery.png",
                    'goods_name' => "SHIPPING",
                    'goods_detail' => $order->get_shipping_method(),
                    'goods_amt' => $order->calculate_shipping()
                );
            }

            if(count($woocommerce->cart->applied_coupons) > 0){
                for($i=0; $i<sizeof($woocommerce->cart->applied_coupons); $i++){
                    $couponName = $woocommerce->cart->applied_coupons[$i];

                    $orderInfo[] = array(
                        'img_url' => plugins_url()."/nicepay_va/icons/delivery.png",
                        'goods_name' => "COUPON",
                        'goods_detail' => $couponName,
                        'goods_amt' => "-".$woocommerce->cart->coupon_discount_amounts[$couponName]
                    );
                }
            }

            if($woocommerce->cart->fee_total != 0){
                for($i=0; $i<sizeof($woocommerce->cart->fees); $i++){
                    $fees = $woocommerce->cart->fees;
                    $discounts = $fees[$i];
                    $fileImg = "fees.png";
                    if(preg_match("/^\-/", $discounts->amount)){
                        $fileImg = "sale.png";
                    }

                    $orderInfo[] = array(
                        'img_url' => plugins_url()."/nicepay_cc/icons/".$fileImg,
                        'goods_name' => $discounts->name,
                        'goods_detail' => "",
                        'goods_amt' => $discounts->amount
                    );
                }
            }

            if($this->adminFee && ($this->adminFee != null || $this->adminFee > 0)){
                $orderInfo[] = array(
                    'img_url' => plugins_url()."/nicepay_cc/icons/money.png",
                    'goods_name' => "Fraud Detection Fee",
                    'goods_detail' => 1,
                    'goods_amt' => $this->adminFee
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
        $this->sent_log(json_encode($nicepay_log));

        //Order Total
        //$order_total = $this->get_order_total();

        //Get current user
        $current_user = wp_get_current_user();

        //Get Address customer
        $name           = $this->getProperty($order,'billing_first_name')." ".$this->getProperty($order,'billing_last_name');
        $billingNm      = $this->checkingAddrRule("name", $name);
        $billingPhone   = $this->checkingAddrRule("phone", $this->getProperty($order,'billing_phone'));
        $billingEmail   = $this->getProperty($order,'billing_email');
        $addr           = $this->getProperty($order,'billing_address_1')." ".$this->getProperty($order,'billing_address_2');
        $billingAddr    = $this->checkingAddrRule("addr", $addr);
        $billingCity    = $this->checkingAddrRule("city", $this->getProperty($order,'billing_city'));
        // get full state name
        $state   = WC()->countries->states[ $this->getProperty($order,'billing_country')][$this->checkingAddrRule("state", $this->getProperty($order,'billing_state'))];
        $billingState = $this->checkingAddrRule("state", $state);
        $billingPostCd  = $this->checkingAddrRule("postCd", $this->getProperty($order,'billing_postcode'));
        // get full country name
        $billingCountry = WC()->countries->countries[$this->checkingAddrRule("country", $this->getProperty($order,'billing_country'))];
        
        // Get Shipping Address
        $deliName       = $this->getProperty($order,'shipping_first_name')." ".$this->getProperty($order,'shipping_last_name');
        $deliveryNm     = $this->checkingAddrRule("name", $deliName);
        $deliveryPhone  = $this->checkingAddrRule("phone", $this->getProperty($order,'billing_phone'));
        $deliveryEmail  = $this->getProperty($order,'billing_email');
        $deliAddr       = $this->getProperty($order,'shipping_address_1')." ".$this->getProperty($order,'shipping_address_2');
        $deliveryAddr   = $this->checkingAddrRule("addr", $deliAddr);
        $deliveryCity   = $this->checkingAddrRule("city", $this->getProperty($order,'shipping_city'));
        // get full state name
        $state   = WC()->countries->states[ $this->getProperty($order,'shipping_country')][$this->checkingAddrRule("state", $this->getProperty($order,'shipping_state'))];
        $deliveryState = $this->checkingAddrRule("state", $state);
        $deliveryPostCd = $this->checkingAddrRule("postCd", $this->getProperty($order,'shipping_postcode'));
        // get full country name
        $deliveryCountry= WC()->countries->countries[ $this->checkingAddrRule("country", $this->getProperty($order,'shipping_country')) ];


        // Prepare Parameters
        $nicepay = new NicepayLib();

        $dateNow        = date('Ymd');
        $vaExpiryDate   = date('Ymd', strtotime($dateNow . ' +1 day'));
        $nicepay->setmerchatKey($this->apikey);
        // Populate Mandatory parameters to send
        $nicepay->set('iMid',$this->merchantID);
        $nicepay->set('payMethod', '02');
        $nicepay->set('currency', 'IDR');
        $nicepay->set('cartData', json_encode($cartData));
        $nicepay->set('amt', $order_total); // Total gross amount //
        $nicepay->set('referenceNo', $order_id);
        $nicepay->set('description', 'Payment of invoice No '.$order_id); // Transaction description
        $nicepay->set("bankCd",WC()->session->get('bankCd'));
        $myaccount_page_id = get_option( 'woocommerce_myaccount_page_id' );
        $myaccount_page_url = null;

        if ($myaccount_page_id ) {
            $myaccount_page_url = get_permalink($myaccount_page_id );
        }

        $nicepay->callBackUrl = $myaccount_page_url."view-order/".$order_id;
        $nicepay->dbProcessUrl = WC()->api_request_url( 'wc_gateway_nicepay_cc' );// Transaction description
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
        $nicepay->set('vacctValidDt', $vaExpiryDate); // Set VA expiry date example: +1 day
        $nicepay->set('vacctValidTm', date('His')); // Set VA Expiry Time
        $nicepay->set('dbProcessUrl', WC()->api_request_url( 'wc_gateway_nicepay_cc' ));


        // check fix VA generate number
        switch($this->vaType) {
            case 0:
                break;
            case 1:
                $lackdata = $this->vaLength - strlen(get_current_user_id());
                $firstdata = "";
                for ($i=0;$i<$lackdata;$i++) {
                    $firstdata .= "0";
                }
                $merFixAcctId = $firstdata.get_current_user_id();
                $nicepay->set('merFixAcctId',$merFixAcctId);
                break;
            case 2:
                $reducelength = '-'.$this->vaLength;
                if (strlen($billingPhone) > $this->vaLength) {
                    $merFixAcctId = substr($billingPhone, (int)$reducelength);
                }
                else {
                    $firstdata = "";
                    $lackdata = $this->vaLength - strlen($billingPhone);
                    for ($i=0;$i<$lackdata;$i++) {
                        $firstdata .= "0";
                    }
                    $merFixAcctId = $firstdata.$billingPhone;
                }
                $nicepay->set('merFixAcctId',$merFixAcctId);
                break;
        }


        //running debug
        $nicepay_log["isi"] = $nicepay;
        $this->sent_log(json_encode($nicepay_log));

        // Send Data
        $response = $nicepay->registAPI("virtual_account");

        //running debug
        $nicepay_log["isi"] = $response;
        $this->sent_log(json_encode($nicepay_log));

        // Response from NICEPAY
        if (isset($response->resultCd) && $response->resultCd == "0000") {
            $bank = $this->bankset;
            $bankCd = WC()->session->get('bankCd');

            //Change format date
            $getYear = substr($vaExpiryDate,0,4);
            $getMonth = substr($vaExpiryDate,4,2);
            $getDay = substr($vaExpiryDate,6,2);
            $newDate = $getDay."-".$getMonth."-".$getYear;

            $order->add_order_note( __( 'Menunggu pembayaran melalui NICEPay Virtual Payment Gateway dengan id transaksi '.$response->tXid, 'woocommerce' ) );
            WC()->session->set('tXid',$response->tXid);
            WC()->session->set('virtual_account',$response->bankVacctNo);
            WC()->session->set('description',$response->description);
            WC()->session->set('payment_date',$response->transDt);
            WC()->session->set('payment_time',$response->transTm);
            WC()->session->set('result_code',$response->resultCd);
            WC()->session->set('result_message',$response->resultMsg);
            WC()->session->set('reference_no',$response->referenceNo);
            WC()->session->set('bankName',$bank[$bankCd]["label"]);
            WC()->session->set('expDate',$newDate);
            WC()->session->set('paymentMethod',$response->payMethod);

            $dataTr = array(
                "tXid" => $response->tXid,
                "virtual_account" => $response->bankVacctNo,
                "description" => $response->description,
                "payment_date" => $response->transDt,
                "payment_time" => $response->transTm,
                "result_code" => $response->resultCd,
                "result_message" => $response->resultMsg,
                "reference_no" => $response->referenceNo,
                "bankName" => $bank[$bankCd]["label"],
                "expDate" => $newDate,
                "paymentMethod" => $response->payMethod,
                "bankCd" => $bankCd,
                "amount" => $order_total,
                "user_name" => $billingNm,
                "shop_name" => get_option( 'blogname' ),
                "order_name" => "#".$response->referenceNo,
                "billingEmail" => $billingEmail
            );

            $this->add_payment_detail_to_order_email($dataTr);

            header("Location: ".$this->get_return_url($order ));
            // please save tXid in your database
            // echo "<pre>";
            // echo "tXid              : $response->tXid\n";
            // echo "API Type          : $response->apiType\n";
            // echo "Request Date      : $response->requestDate\n";
            // echo "Response Date     : $response->requestDate\n";
            // echo "</pre>";
        } elseif(isset($response->data->resultCd)) {
            // API data not correct or error happened in bank system, you can redirect back to checkout page or echo error message.
            // In this sample, we echo error message
            // header("Location: "."http://example.com/checkout.php");
            echo "<pre>";
            echo "result code       : ".$response->data->resultCd."\n";
            echo "result message    : ".$response->data->resultMsg."\n";
            // echo "requestUrl        : ".$response->data->requestURL."\n";
            echo "</pre>";
        } else {
            // Timeout, you can redirect back to checkout page or echo error message.
            // In this sample, we echo error message
            // header("Location: "."http://example.com/checkout.php");
            echo "<pre>Connection Timeout. Please Try again.</pre>";
        }
    }

    public function add_description_payment_success($order) {
        $output = "";
        $jscript = "";
        $output .= "<h2 id='h2thanks'>Bank Transfer Virtual Account</h2>";
        $output .= "<table border='0' cellpadding='10'>";
        $output .= '<tr><td><strong>Deskripsi</strong></td><td>'.WC()->session->get('description').'</td></tr>';
        $output .= '<tr><td><strong>Bank</strong></td><td>'.WC()->session->get('bankName').'</td></tr>';
        $output .= '<tr><td><strong>Virtual Account</strong></td><td>'.WC()->session->get('virtual_account').'</td></tr>';
        $output .= '<tr><td><strong>Pembayaran berakhir pada</strong></td><td>'.WC()->session->get('expDate').'</td></tr>';
        $output .= "</table>";
        $output .= '<p>Pembayaran melalui '.WC()->session->get('bankName').' Virtual Account dapat dilakukan dengan mengikuti petunjuk berikut :</p>';
        $output .= $this->how_to(WC()->session->get('bankCd'));
        
        $jscript .= "<script>";
        $jscript .= "var vanumber='".WC()->session->get('virtual_account')."'";
        $jscript .= "</script>";
        echo $output.$jscript;
        
        wp_register_script('va_script', PLUGIN_URL_PATH.'/js/va.js',array('jquery'),'1.0',true);
        wp_enqueue_script('va_script');
    }

    public function add_payment_detail_to_order_email($session){
        $current_user = wp_get_current_user();
        $email_to = ($current_user->ID == 0) ? $session['billingEmail'] : get_user_meta($current_user->ID, "billing_email", true);
        $admin_email = get_option( 'admin_email' );
        $blogname = get_option( 'blogname' );
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        $headers[] = 'From: '.$blogname.' <'.$admin_email.'> ' . "\r\n";
        $subject = "Detail Pembayaran order #".$session['reference_no'];
        $message = $this->petunjuk_payment_va_to_email($session);

        wp_mail($email_to, $subject, $message, $headers );
    }

    function petunjuk_payment_va_to_email($data){
        $content = null;
        $bankCd = $data["bankCd"];

        $opts = array(
            'http'=>array(
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n".
                    "Content-Length: ".strlen(http_build_query($data))."\r\n".
                    "User-Agent:MyAgent/1.0\r\n",
                'method'=>"POST",
                'content' => http_build_query($data)),
            'ssl' => array('verify_peer' => false,)
        );

        $context = stream_context_create($opts);

        switch($bankCd){
            case "BMRI" :
                $content = file_get_contents(plugins_url("email/order_conf_mandiri.php", dirname(__FILE__)),false,$context);
                break;
            case "BBBA" :
                $content = file_get_contents(plugins_url("email/order_conf_permata.php", dirname(__FILE__)),false,$context);
                break;
            case "IBBK" :
                $content = file_get_contents(plugins_url("email/order_conf_bii.php", dirname(__FILE__)),false,$context);
                break;
            case "BNIN" :
                $content = file_get_contents(plugins_url("email/order_conf_bni.php", dirname(__FILE__)),false,$context);
                break;
            case "CENA" :
                $content = file_get_contents(plugins_url("email/order_conf_bca.php", dirname(__FILE__)),false,$context);
                break;
            case "HNBN" :
                $content = file_get_contents(plugins_url("email/order_conf_bersama.php", dirname(__FILE__)),false,$context);
                break;
            case "BRIN" :
                $content = file_get_contents(plugins_url("email/order_conf_bri.php", dirname(__FILE__)),false,$context);
                break;
            case "BDIN" :
                $content = file_get_contents(plugins_url("email/order_conf_danamon.php", dirname(__FILE__)),false,$context);
                break;
            case "BNIA" :
                $content = file_get_contents(plugins_url("email/order_conf_cimb.php", dirname(__FILE__)),false,$context);
                break;
        }

        return $content;
    }

    function process_payment($order_id) {
        global $woocommerce;
        $order = new WC_Order($order_id);
        return array(
            'result' => 'success',
            'redirect' => $order->get_checkout_payment_url( true )
        );
    }

    function notification_handler(){
        $nicepay = new NicepayLib();

        // Listen for parameters passed
        $pushParameters = array(
            'tXid',
            'referenceNo',
            'amt',
            'merchantToken'
        );

        $nicepay->extractNotification($pushParameters);

        $iMid               = $nicepay->iMid;
        $tXid               = $nicepay->getNotification('tXid');
        $referenceNo        = $nicepay->getNotification('referenceNo');
        $amt                = $nicepay->getNotification('amt');
        $pushedToken        = $nicepay->getNotification('merchantToken');

        //running debug
        $nicepay_log["redirect"] = "dbproccess";
        $nicepay_log["referenceNo"] = $referenceNo;
        $nicepay_log["isi"] = $_SERVER["REQUEST_URI"];
        $this->sent_log(json_encode($nicepay_log));

        $nicepay->set('tXid', $tXid);
        $nicepay->set('referenceNo', $referenceNo);
        $nicepay->set('amt', $amt);
        $nicepay->set('iMid',$iMid);

        $merchantToken = $nicepay->merchantTokenC();
        $nicepay->set('merchantToken', $merchantToken);

        //running debug
        $nicepay_log["isi"] = $pushedToken ." == ". $merchantToken;
        $this->sent_log(json_encode($nicepay_log));

        // <RESQUEST to NICEPAY>
        $paymentStatus = $nicepay->checkPaymentStatus($iMid, $tXid, $referenceNo, $amt);

        //running debug
        $nicepay_log["isi"] = $paymentStatus;
        $this->sent_log(json_encode($nicepay_log));

        if($pushedToken == $merchantToken) {
            $order = new WC_Order((int)$referenceNo);

            if (isset($paymentStatus->status) && $paymentStatus->status == '0'){
                $order->add_order_note( __( 'Pembayaran telah dilakukan melalui NICEPay dengan id transaksi '.$referenceNo, 'woocommerce' ) );
                $order->payment_complete();
            }else{
                $order->add_order_note( __( 'Pembayaran gagal! '.$referenceNo, 'woocommerce' ) );
                $order->update_status('Failed');
            }
        }
    }

    public function addrRule(){
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

    public function checkingAddrRule($var, $val){
        $value = null;

        $rule = $this->addrRule();
        $type = $rule[$var]->type;
        $length =(int)$rule[$var]->length;

        $defaultValue = $rule[$var]->defaultValue;
        if($val == null || $val == "" || "null" == $val){
            $val = $defaultValue;
        }

        switch($type){
            case "string" :
                $valLength = strlen($val);
                if($valLength > $length){
                    $val = substr($val, 0, $length);
                }

                $value = (string)$val;
            break;

            case "integer" :
                if(gettype($val) != "string" || gettype($val) != "String"){
                    $val = (string)$val;
                }

                $valLength = strlen($val);
                if($valLength > $length){
                    $val = substr($val, 0, $length);
                }

                $value = (int)$val;
            break;

            default:
                $value = (string)$val;
            break;
        }

        return $value;
    }

    public function sent_log($data){
        //$debugMode = $this->debug;
        //if($debugMode == 'yes'){
        //	$ch = curl_init();
            //set the url, number of POST vars, POST data

            //curl_setopt($ch,CURLOPT_URL, "http://checking-bug.hol.es/proc.php");
            //curl_setopt($ch,CURLOPT_POST, 1);
            //curl_setopt($ch,CURLOPT_POSTFIELDS, "log=".$data."++--++debug==".$debugMode);

            //execute post
            //$result = curl_exec($ch);

            //close connection
            //curl_close($ch);
        //}
    }
}