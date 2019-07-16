<?php
/*
Plugin Name: درگاه شرکت ایران کیش 
Plugin URI: http://softiran.org
Description: ساخته شده توسط  <a href="http://www.softiran.org/" target="_blank"> گروه برنامه نویسی سافت ایران</a> به سفارش ایران کیش
Original: http://www.kiccc.com/App_Data_Public/downloads/irankish-woocommerce.zip
Version: 1.0.1
Modified by: https://github.com/muhammad-naderi
Author: SOFTIRAN.ORG
Author URI: http://www.softiran.org
Copyright: 2015 softiran.org
*/

add_action('plugins_loaded', 'woocommerce_irankish_init', 0);

function woocommerce_irankish_init() 
{
    if ( !class_exists( 'WC_Payment_Gateway' ) ) return;
	if ( isset( $_GET['msg'] ) && !empty( $_GET['msg'] ) && $_GET['msg']!=''){add_action('the_content', 'showMessageirankish');}
	function showMessageirankish($content)
	{
			return '<div class="box '.htmlentities($_GET['type']).'-box">'.base64_decode($_GET['msg']).'</div>'.$content;
	}
    class WC_irankish extends WC_Payment_Gateway 
	{
		protected $msg = array();
        public function __construct()
		{
            $this->id = 'irankish';
            $this->method_title = __('درگاه irankish', 'irankish');
            $this->has_fields = false;
            $this->init_form_fields();
            $this->init_settings();
            $this->title = $this->settings['title'];
            $this->description = $this->settings['description'];
            $this->merchant_id = $this->settings['merchant_id'];
            $this->sha1Key = $this->settings['sha1Key'];
			$this->vahed = $this->settings['vahed'];
			if ( isset( $this->settings['gates'] ) && !empty( $this->settings['gates'] ))
				$this->gates = $this->settings['gates'];
            $this->redirect_page_id = $this->settings['redirect_page_id'];
            $this->msg['message'] = "";
            $this->msg['class'] = "";
			add_action( 'woocommerce_api_wc_irankish', array( $this, 'check_irankish_response' ) );
            add_action('valid-irankish-request', array($this, 'successful_request'));
			
            if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) 
			{
                add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            } else 
			{
                add_action( 'woocommerce_update_options_payment_gateways', array( $this, 'process_admin_options' ) );
            }
			
            add_action('woocommerce_receipt_irankish', array($this, 'receipt_page'));
        }

        function init_form_fields()
		{

            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('فعال سازی/غیر فعال سازی', 'irankish'),
                    'type' => 'checkbox',
                    'label' => __('فعال سازی درگاه پرداخت irankish', 'irankish'),
                    'default' => 'no'),
                'title' => array(
                    'title' => __('عنوان', 'irankish'),
                    'type'=> 'text',
                    'description' => __('عنوانی که کاربر در هنگام پرداخت مشاهده می کند', 'irankish'),
                    'default' => __('پرداخت اینترنتی irankish', 'irankish')),
                'description' => array(
                    'title' => __('توضیحات', 'irankish'),
                    'type' => 'textarea',
                    'description' => __('توضیحات قابل نمایش به کاربر در هنگام انتخاب درگاه پرداخت', 'irankish'),
                    'default' => __('پرداخت از طریق درگاه irankish با کارت های عضو شتاب', 'irankish')),
                'merchant_id' => array(
                    'title' => __('شناسه کاربری', 'irankish'),
                    'type' => 'text',
                    'description' => __('مرچنت درگاه ایران کیش')),
				'sha1Key' => array(
                    'title' => __('sha1Key', 'irankish'),
                    'type' => 'text',
                    'description' => __('sha1Key')),
				'vahed' => array(
                    'title' => __('واحد پولی'),
                    'type' => 'select',
                    'options' => array(
					'rial' => 'ریال',
					'toman' => 'تومان'
					),
                    'description' => "نیازمند افزونه ریال و تومان هست"),
                'redirect_page_id' => array(
                    'title' => __('صفحه بازگشت'),
                    'type' => 'select',
                    'options' => $this->get_pages('انتخاب برگه'),
                    'description' => "ادرس بازگشت از پرداخت در هنگام پرداخت"
                )
            );


        }

        public function admin_options()
		{
            echo '<h3>'.__('درگاه پرداخت irankish', 'irankish').'</h3>';
            echo '<p>'.__('درگاه پرداخت اینترنتی irankish').'</p>';
            echo '<table class="form-table">';
            $this->generate_settings_html();
            echo '</table>';
        }
		
        function payment_fields()
		{
            if($this->description) echo wpautop(wptexturize($this->description));
        }

        function receipt_page($order)
		{
            echo '<p>'.__('در حال اتصال به درگاه شرکت ایران کیش', 'irankish').'</p>';
            echo $this->generate_irankish_form($order);
        }

        function process_payment($order_id)
		{
            $order = new WC_Order($order_id);
            return array('result' => 'success', 'redirect' => $order->get_checkout_payment_url( true )); 
        }

       function check_irankish_response()
		{
            global $woocommerce;
			$order_id = $woocommerce->session->order_id;
			$order = new WC_Order($order_id);
			if($order_id != '')
			{
				if($order->status !='completed')
				{
					$resultCode = $_POST['resultCode'];
					$referenceId = isset($_POST['referenceId']) ? $_POST['referenceId'] : 0;
					$paymentId = isset($_POST["paymentId"]) ? $_POST['paymentId'] : 0;
					if ($resultCode == '100')
					{
						
						$amount = str_replace(".00", "", $order->order_total);
						if($this->vahed!='rial')
							$amount = $amount * 10;

						
						$client = new SoapClient('https://ikc.shaparak.ir/XVerify/Verify.xml', array('soap_version'   => SOAP_1_1));
						@session_start();
						$params['token'] =  $_SESSION['token'];
						$params['merchantId'] = $this->merchant_id;
						$params['referenceNumber'] = $referenceId;
						$params['sha1Key'] = $this->sha1Key;
						$result = $client->__soapCall("KicccPaymentsVerification", array($params));
						$result = ($result->KicccPaymentsVerificationResult);
					
						if( floatval($result) == floatval($amount) )
						{
							$this->msg['message'] = "پرداخت شما با موفقیت انجام شد<br/> کد ارجاع : $referenceId";
							$this->msg['class'] = 'success';
							$order->payment_complete();
							$order->add_order_note('پرداخت انجام شد<br/> - کد ارجاع : '.$referenceId );
							$order->add_order_note($this->msg['message']);
							$woocommerce->cart->empty_cart();
							wp_redirect( add_query_arg( 'wc_status', 'success', $this->get_return_url( $order ) ) );
							exit;
						}
						else
						{
							$this->msg['class'] = 'error';
							$this->msg['message'] = "پرداخت با موفقيت انجام نشد";
						}
					}
					else
					{
						switch ($resultCode) 
						{
						case 110:
								$res = " انصراف دارنده کارت";
							break;
						case 120:
							$res ="   موجودی کافی نیست";
							break;
						case 130:
						case 131:
						case 160:
							$res ="   اطلاعات کارت اشتباه است";
							break;
						case 132:
						case 133:
							$res ="   کارت مسدود یا منقضی می باشد";
							break;
						case 140:
							$res =" زمان مورد نظر به پایان رسیده است";
							break;
						case 200:
						case 201:
						case 202:
							$res =" مبلغ بیش از سقف مجاز";
							break;
						case 166:
							$res =" بانک صادر کننده مجوز انجام  تراکنش را صادر نکرده";
							break;
						case 150:
						default:
							$res =" خطا بانک  $resultCode";
						break;
						}
						$this->msg['class'] = 'error';
						$this->msg['message'] = $res ;
					}
				}
				else
				{
					$this->msg['class'] = 'error';
					$this->msg['message'] = "قبلا اين سفارش به ثبت رسيده يا سفارشي موجود نيست!";
				}
			}
			$redirect_url = ($this->redirect_page_id=="" || $this->redirect_page_id==0)?get_site_url() . "/":get_permalink($this->redirect_page_id);
			$redirect_url = add_query_arg( array('msg'=> base64_encode($this->msg['message']), 'type'=>$this->msg['class']), $redirect_url );
			wp_redirect( $redirect_url );
			exit;
		}
		
        function showMessage($content)
		{
            return '<div class="box '.$this->msg['class'].'-box">'.$this->msg['message'].'</div>'.$content;
        }

        public function generate_irankish_form($order_id)
		{
            global $woocommerce;
            $order = new WC_Order($order_id);
            $redirect_url = ($this->redirect_page_id=="" || $this->redirect_page_id==0)?get_site_url() . "/":get_permalink($this->redirect_page_id);
			$redirect_url = add_query_arg( 'wc-api', get_class( $this ), $redirect_url );
			unset( $woocommerce->session->order_id );
			$woocommerce->session->order_id = $order_id;
			$amount = str_replace(".00", "", $order->order_total);
			if($this->vahed!='rial')
				$amount = $amount * 10;
			$redirect = ($redirect_url); 
				
			$client = new SoapClient('https://ikc.shaparak.ir/XToken/Tokens.xml', array('soap_version'   => SOAP_1_1));

			$params['amount'] = $amount;
			$params['merchantId'] = $this->merchant_id;
			$params['invoiceNo'] = time();
			$params['paymentId'] = time();
			$params['specialPaymentId'] = time();
			$params['revertURL'] = $redirect_url;
			$params['description'] = "";
			$result = $client->__soapCall("MakeToken", array($params));
			$token = $result->MakeTokenResult->token;
			@session_start();
			$_SESSION['token'] = $token;
			$data['token'] = $token;
			$data['merchantId'] = $this->merchant_id;
			$Notice = __( 'در حال اتصال به بانک .....', 'woocommerce' );
			$this->redirect_post('https://ikc.shaparak.ir/TPayment/Payment/index',$data);
	
        }
	function redirect_post($url, array $data)
{


   echo '<form name="redirectpost" id="redirectpost" method="post" action="'.$url.'">';
       
        if ( !is_null($data) ) {
            foreach ($data as $k => $v) {
                echo '<input type="hidden" name="' . $k . '" value="' . $v . '"> ';
            }
        }
       
   echo' </form><div id="main">
 <script type="text/javascript">
            
                document.getElementById("redirectpost").submit();
           
        </script>
    </body>
    </html>';
   
    exit;
}
        function get_pages($title = false, $indent = true) 
		{
            $wp_pages = get_pages('sort_column=menu_order');
            $page_list = array();
            if ($title) $page_list[] = $title;
            foreach ($wp_pages as $page) 
			{
                $prefix = '';
                if ($indent) {
                    $has_parent = $page->post_parent;
                    while($has_parent) 
					{
                        $prefix .=  ' - ';
                        $next_page = get_page($has_parent);
                        $has_parent = $next_page->post_parent;
                    }
                }
                $page_list[$page->ID] = $prefix . $page->post_title;
            }
            return $page_list;
        }

    }

    function woocommerce_add_irankish_gateway($methods) 
	{
        $methods[] = 'WC_irankish';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_irankish_gateway' );
}

?>