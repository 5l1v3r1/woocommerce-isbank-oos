<?php
/*
Plugin Name: WooCommerce İşbank 3D Pay Hosting
Plugin URI: http://www.tema.ninja
Description: İşbank için 3D pay Hosting modeli ile ödeme almanızı sağlar
Version: 1.0
Author: Yasin Altıntaş
Author URI: http://www.tema.ninja
*/
add_action('plugins_loaded', 'woocommerce_isbank3d_init', 0);
function woocommerce_isbank3d_init(){
  if(!class_exists('WC_Payment_Gateway')) return;

  class WC_Isbank3D_Pos extends WC_Payment_Gateway{
    public function __construct(){
      $this->id = 'isbank3d';
      $this->medthod_title = 'İşbank';
      $this->has_fields = false;

      $this->init_form_fields();
      $this->init_settings();

      $this->title = $this->settings['title'];
      $this->description = $this->settings['description'];
      $this->merchant_id = $this->settings['merchant_id'];
      $this->store_key = $this->settings['store_key'];
      $this->liveurl = 'https://sanalpos.isbank.com.tr/fim/est3Dgate';

      $this->msg['message'] = "";
      $this->msg['class'] = "";

       add_action( 'woocommerce_api_callback', 'check_isbank3d_callback' );
      if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
                add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
             } else {
                add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
            }
      add_action('woocommerce_receipt_isbank3d', array(&$this, 'receipt_page'));
   }
    function init_form_fields(){

       $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Aktif / Pasif', 'temaninja'),
                    'type' => 'checkbox',
                    'label' => __('İşbank Modülünü Aktive Edin.', 'temaninja'),
                    'default' => 'no'),
                'title' => array(
                    'title' => __('Görünür Adı:', 'temaninja'),
                    'type'=> 'text',
                    'description' => __('Müşterilerin göreceği adı.', 'temaninja'),
                    'default' => __('Kredi Kartı', 'temaninja')),
                'description' => array(
                    'title' => __('Açıklama:', 'temaninja'),
                    'type' => 'textarea',
                    'description' => __('Müşterilering göreceği açıklama.', 'temaninja'),
                    'default' => __('Kredi kartınızla güvenle ödeme yapın.', 'temaninja')),
                'merchant_id' => array(
                    'title' => __('Müşteri No', 'temaninja'),
                    'type' => 'text',
                    'description' => __('Bankanızın size verdiği müşteri numarası."')),
                'store_key' => array(
                    'title' => __('Storekey', 'temaninja'),
                    'type' => 'text',
                    'description' =>  __('Mağaza 3d güvenlik kodu', 'temaninja'),
                )
            );
    }

       public function admin_options(){
        echo '<h3>'.__('İşbank 3D Pay Sanal Pos', 'temaninja').'</h3>';
        echo '<p>'.__('İşbankası Posunuz ile ödeme alabilirsiniz').'</p>';
        echo '<table class="form-table">';
        // Generate the HTML For the settings form.
        $this->generate_settings_html();
        echo '</table>';

    }

    function payment_fields(){
        if($this->description) echo wpautop(wptexturize($this->description));
    }

    function receipt_page($order){
        echo '<p>'.__('Siparişiniz için teşekkürler', 'temaninja').'</p>';
        echo $this->generate_isbank3d_form($order);
    }

    public function generate_isbank3d_form($order_id){

       global $woocommerce;
    	  $order = new WC_Order( $order_id );
        $clientId = $this->merchant_id;      //Banka tarafindan magazaya verilen isyeri numarasi
        $amount = $order->order_total;             //tutar
        $oid = $order_id;                    //Siparis numarasi
        $okUrl = $woocommerce->cart->get_checkout_url().'?wc-api=isbank3d';      //Islem basariliysa dönülecek isyeri sayfasi  (3D isleminin ve ödeme isleminin sonucu)
        $failUrl = $woocommerce->cart->get_checkout_url().'?wc-api=isbank3d';    //Islem basarisizsa dönülecek isyeri sayfasi  (3D isleminin ve ödeme isleminin sonucu)
        $rnd = microtime();                                     //Tarih ve zaman gibi sürekli degisen bir deger güvenlik amaçli kullaniliyor
        $taksit = "";             //Taksit sayisi
        $islemtipi="Auth";          //Islem tipi
        $storekey = $this->store_key;         //Isyeri anahtari
        $hashstr = $clientId . $oid . $amount . $okUrl . $failUrl . $islemtipi . $taksit . $rnd . $storekey; //güvenlik amaçli hashli deger
        $hash = base64_encode(pack('H*',sha1($hashstr)));

        $isbank3d_args = array(
          'clientid' => $clientId,
          'amount' => $amount,
          'oid' => $oid,
          'okUrl' => $okUrl,
          'failUrl' => $failUrl,
          'islemtipi' => $islemtipi,
          'taksit' => $taksit,
          'rnd' => $rnd,
          'hash' => $hash,
          'storetype' => '3d_oos_pay',
          'refreshtime' => '10',
          'lang' => 'tr',
          'Fismi' => $order->billing_first_name.' '.$order->billing_last_name,
          'faturaFirma' => $order->billing_phone,
          'Fadres' => $order->billing_address_1,
          'Fadres2' => $order->billing_address_2,
          'Fil' => $order->billing_city,
          'fulkekod' => 'tr'
          );

        $isbank3d_args_array = array();
        foreach($isbank3d_args as $key => $value){
          $isbank3d_args_array[] = "<input type='hidden' name='$key' value='$value'/>";
        }
        return '<form action="'.$this->liveurl.'" method="post" id="isbank3d_payment_form">
            ' . implode('', $isbank3d_args_array) . '
            <input type="submit" class="button-alt" id="submit_isbank3d_payment_form" value="'.__('Kredi kartı ile öde', 'temaninja').'" /> <a class="button cancel" href="'.$order->get_cancel_order_url().'">'.__('İptal Et &amp; restore cart', 'temaninja').'</a>
            <script type="text/javascript">
jQuery(function(){
jQuery("body").block(
        {
            message: "<img src=\"'.$woocommerce->plugin_url().'/assets/images/ajax-loader.gif\" alt=\"Redirecting…\" style=\"float:left; margin-right: 10px;\" />'.__('Siparişiniz için teşekkürler lütfen bankaya yönlendirilirken bekleyin.', 'temaninja').'",
                overlayCSS:
        {
            background: "#fff",
                opacity: 0.6
    },
    css: {
        padding:        20,
            textAlign:      "center",
            color:          "#555",
            border:         "3px solid #aaa",
            backgroundColor:"#fff",
            cursor:         "wait",
            lineHeight:"32px"
    }
    });
    jQuery("#submit_isbank3d_payment_form").click();});</script>
            </form>';


    }


    function process_payment($order_id){
        global $woocommerce;
    	$order = new WC_Order( $order_id );
        return array('result' => 'success', 'redirect' => add_query_arg('order',
            $order->id, add_query_arg('key', $order->order_key, get_permalink(get_option('woocommerce_pay_page_id'))))
        );
    }

    function check_isbank3d_callback(){
        global $woocommerce;
        $order_id = $_POST['HostRefNum'];
        $order = new WC_Order($order_id);
        $mdStatus = $_POST["mdStatus"];
      	$ErrMsg = $_POST["ErrMsg"];
        	if($mdStatus == 1 || $mdStatus == 2 || $mdStatus == 3 || $mdStatus == 4)
        	{
              $response = $_POST["Response"];
              if($response == "Approved")
              		{

                    $order->payment_complete();
                    $order->add_order_note("Isbank 3D ile Odendi<br/> Banka Referans No: ".$_POST['AuthCode']);
                    $this->msg['message'] = "Ödemeniz başarıyla tahsil edilmiştir.";
										$order->add_order_note($this->msg['message']);
										$woocommerce->cart->empty_cart();
              		}
              		else
              		{
                    $this->msg['class'] = 'woocommerce_error';
                    $this->msg['message'] = "Malesef ödemeniz bankanız tarafından reddedilmiştir. Bankanızdan aldığımız yanıt:" . $_POST['ErrMsg'];
                    $order->add_order_note('Odeme Reddedildi');

              		}

          } else {
            $this->msg['class'] = 'woocommerce_error';
            $this->msg['message'] = "Malesef ödemeniz bankanız tarafından reddedilmiştir. Bankanızdan aldığımız yanıt:" . $_POST['ErrMsg'] . "<br/ > Biz ödemenizi yine de yapabilmeniz için siparişi beklemede olarak düzenledik. En kısa sürede müşteri hizmetleri sizinle iletişime geçecektir.";
            $order->add_order_note('Odeme Reddedildi');
            $order->update_status('on-hold');
            $woocommerce->cart->empty_cart();

          }

    }

    function showMessage($content){
            return '<div class="box '.$this->msg['class'].'-box">'.$this->msg['message'].'</div>'.$content;
        }
     // get all pages
    function get_pages($title = false, $indent = true) {
        $wp_pages = get_pages('sort_column=menu_order');
        $page_list = array();
        if ($title) $page_list[] = $title;
        foreach ($wp_pages as $page) {
            $prefix = '';
            // show indented child pages?
            if ($indent) {
                $has_parent = $page->post_parent;
                while($has_parent) {
                    $prefix .=  ' - ';
                    $next_page = get_page($has_parent);
                    $has_parent = $next_page->post_parent;
                }
            }
            // add to page list array array
            $page_list[$page->ID] = $prefix . $page->post_title;
        }
        return $page_list;
    }
}

    function woocommerce_add_isbank3d_gateway($methods) {
        $methods[] = 'WC_Isbank3D_Pos';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_isbank3d_gateway' );
}
