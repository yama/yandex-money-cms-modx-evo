<?php
if(!function_exists('YandexMoneyForm')){
	function YandexMoneyForm(&$fields){
	  global $modx, $vMsg;
	  
	  if ($_POST){
			$dbname = $modx->db->config['dbase']; //имя базы данных
			$dbprefix = $modx->db->config['table_prefix']; //префикс таблиц
			$mod_table = $dbprefix."yandexmoney"; //таблица модуля
			
			$data_query = $modx->db->select("*", $mod_table, "", "id ASC", ""); 
			$row = mysql_fetch_assoc($data_query);
			$config = unserialize($row['config']);
			
			$ym = new Yandexmoney($config);
			$ym->pay_method = $_POST['payment'];

			
			if ($ym->checkPayMethod()) {
				
				$orderId = (int)$fields['orderID'];
				
				$order_query = $modx->db->select("*", $modx->getFullTableName('manager_shopkeeper'), 'id = ' . $orderId, "", "");
				$order = mysql_fetch_array($order_query);

				$data = @unserialize($order['short_txt']);
				$price = floatval(str_replace(',', '.', $order['price']));

				$ym->orderId = $orderId;
				$ym->orderTotal = $price;
				$ym->comment = $data['message'];
				$userLoggedIn = $modx->userLoggedIn();
				$ym->userId = $userLoggedIn!==false ? $userLoggedIn['id'] : 0;
				
				echo $ym->createFormHtml();
				exit;
			}else{
			
			}
	  }
	  
	  return true;
	}
}


if(!function_exists('YandexMoneyValidate')){
	function YandexMoneyValidate(&$fields){
	  global $modx;
	  if (!empty($fields)){
		return true;
	  }else{
		return false;
	  }
	}
}



/**
 * YandexMoney for MODX Evo
 *
 * Payment
 *
 * @author YandexMoney
 * @package yandexmoney
 * @version 1.1.0
 */

if (!class_exists('Yandexmoney')){
class Yandexmoney {

	public $test_mode;
	public $org_mode;
	public $status;

	public $orderId;
	public $orderTotal;
	public $userId;

	public $successUrl;
	public $failUrl;

	public $reciver;
	public $formcomment;
	public $short_dest;
	public $writable_targets = 'false';
	public $comment_needed = 'true';
	public $label;
	public $quickpay_form = 'shop';
	public $payment_type = '';
	public $targets;
	public $sum;
	public $comment;
	public $need_fio = 'true';
	public $need_email = 'true';
	public $need_phone = 'true';
	public $need_address = 'true';

	public $shopid;
	public $scid;
	public $account;
	public $password;

	public $method_ym;
	public $method_cards;
	public $method_cash;
	public $method_mobile;
	public $method_wm;
	public $method_ab;
	public $method_sb;
	public $method_ma;
	public $method_pb;

	public $pay_method;
    
    function __construct($config = array()) {
		
		$this->org_mode = ($config['mode'] == 2);
		$this->test_mode = ($config['testmode'] == 1);
		$this->shopid = ($config['testmode'] == 1);

		
		if (isset($config) && is_array($config)){
			foreach ($config as $k=>$v){
				$this->$k = $v;
			}
		}
    }
    

	/**
	 * Переводит статус заказа в "Оплата получена" (Shopkeeper)
	 * 
	 */
	function shkOrderPaid($order_id){
		
		if($order_id){
			
			$this->modx->addPackage('shopkeeper', MODX_CORE_PATH."components/shopkeeper/model/");
			
			$order = $this->modx->getObject('SHKorder',$order_id);
			if($order){
				$order->set('status',$this->modx->config['yandexmonay.payStatusOut']);
				$order->save();
				return true;	
			}	
		}
		return false;
		
	}


	public function getFormUrl(){
		if (!$this->org_mode){
			return $this->individualGetFormUrl();
		}else{
			return $this->orgGetFormUrl();
		}
	}

	public function individualGetFormUrl(){
		if ($this->test_mode){
			return 'https://demomoney.yandex.ru/quickpay/confirm.xml';
		}else{
			return 'https://money.yandex.ru/quickpay/confirm.xml';
		}
	}

	public function orgGetFormUrl(){
		if ($this->test_mode){
            return 'https://demomoney.yandex.ru/eshop.xml';
        } else {
            return 'https://money.yandex.ru/eshop.xml';
        }
	}

	public function checkPayMethod(){
		if (in_array($this->pay_method, array('PC','AC','MC','GP','WM','AB','SB','MA','PB'))) return TRUE;
		return FALSE;
	}

	public function getSelectHtml(){
		if ((int)$this->status === 0)	return '';
		if ($this->method_ym == 1) {
			$output .= '<option value="PC"';
			if ($this->pay_method == 'PC') $output.=' selected ';
			$output .= '>Оплата из кошелька в Яндекс.Деньгах</option>';
		}
		if ($this->method_cards == 1) {
			$output .= '<option value="AC"';
			if ($this->pay_method == 'AC') $output.=' selected ';
			$output .= '>Оплата с произвольной банковской карты</option>';
		}
		if ($this->method_cash == 1 && $this->org_mode) {
			$output .= '<option value="GP"';
			if ($this->pay_method == 'GP') $output.=' selected ';
			$output .= '>Оплата наличными через кассы и терминалы</option>';
		}
		if ($this->method_mobile == 1 &&  $this->org_mode) {
			$output .= '<option value="MC"';
			if ($this->pay_method == 'MC') $output.=' selected ';
			$output .= '>Платеж со счета мобильного телефона</option>';
		}
		if ($this->method_ab == 1 &&  $this->org_mode) {
			$output .= '<option value="AB"';
			if ($this->pay_method == 'AB') $output.=' selected ';
			$output .= '>Оплата через Альфа-Клик</option>';
		}
		if ($this->method_sb == 1 &&  $this->org_mode) {
			$output .= '<option value="SB"';
			if ($this->pay_method == 'SB') $output.=' selected ';
			$output .= '>Оплата через Сбербанк: оплата по SMS или Сбербанк Онлайн</option>';
		}		
		if ($this->method_wm == 1 &&  $this->org_mode) {
			$output .= '<option value="WM"';
			if ($this->pay_method == 'WM') $output.=' selected '; 
			$output .= '>Оплата из кошелька в системе WebMoney</option>';
		}
		if ($this->method_ma == 1 &&  $this->org_mode) {
			$output .= '<option value="MA"';
			if ($this->pay_method == 'MA') $output.=' selected '; 
			$output .= '>Оплата через MasterPass</option>';
		}
		if ($this->method_pb == 1 &&  $this->org_mode) {
			$output .= '<option value="PB"';
			if ($this->pay_method == 'PB') $output.=' selected '; 
			$output .= '>Оплата через интернет-банк Промсвязьбанка</option>';
		}
		return $output;
	}

	public function createFormHtml(){
		if ($this->org_mode){
			$html = '
				<form method="POST" action="'.$this->getFormUrl().'"  id="paymentform" name = "paymentform">
				   <input type="hidden" name="paymentType" value="'.$this->pay_method.'" />
				   <input type="hidden" name="shopid" value="'.$this->shopid.'">
				   <input type="hidden" name="scid" value="'.$this->scid.'">
				   <input type="hidden" name="orderNumber" value="'.$this->orderId.'">
				   <input type="hidden" name="sum" value="'.$this->orderTotal.'" data-type="number" >
				   <input type="hidden" name="customerNumber" value="'.$this->userId.'" >	
				   <input type="hidden" name="cms_name" value="modxevo" >	
				</form>';
		}else{
			$html = '<form method="POST" action="'.$this->getFormUrl().'"  id="paymentform" name = "paymentform">
					   <input type="hidden" name="receiver" value="'.$this->account.'">
					   <input type="hidden" name="formcomment" value="Order '.$this->orderId.'">
					   <input type="hidden" name="short-dest" value="Order '.$this->orderId.'">
					   <input type="hidden" name="writable-targets" value="'.$this->writable_targets.'">
					   <input type="hidden" name="comment-needed" value="'.$this->comment_needed.'">
					   <input type="hidden" name="label" value="'.$this->orderId.'">
					   <input type="hidden" name="quickpay-form" value="'.$this->quickpay_form.'">
					   <input type="hidden" name="paymentType" value="'.$this->pay_method.'">
					   <input type="hidden" name="targets" value="Заказ '.$this->orderId.'">
					   <input type="hidden" name="sum" value="'.$this->orderTotal.'" data-type="number" >
					   <input type="hidden" name="comment" value="'.$this->comment.'" >
					   <input type="hidden" name="need-fio" value="'.$this->need_fio.'">
					   <input type="hidden" name="need-email" value="'.$this->need_email.'" >
					   <input type="hidden" name="need-phone" value="'.$this->need_phone.'">
					   <input type="hidden" name="need-address" value="'.$this->need_address.'">
					 
					</form>';
		}
		$html .= '<script type="text/javascript">
						document.getElementById("paymentform").submit();
					</script>';
		echo $html; exit;
		return $html;
	}


	public function checkSign($callbackParams){
		$string = $callbackParams['action'].';'.$callbackParams['orderSumAmount'].';'.$callbackParams['orderSumCurrencyPaycash'].';'.$callbackParams['orderSumBankPaycash'].';'.$callbackParams['shopId'].';'.$callbackParams['invoiceId'].';'.$callbackParams['customerNumber'].';'.$this->password;
		$md5 = strtoupper(md5($string));
		return ($callbackParams['md5']==$md5);
	}

	public function sendAviso($callbackParams, $code){
		header("Content-type: text/xml; charset=utf-8");
		$xml = '<?xml version="1.0" encoding="UTF-8"?>
			<paymentAvisoResponse performedDatetime="'.date("c").'" code="'.$code.'" invoiceId="'.$callbackParams['invoiceId'].'" shopId="'.$this->shopid.'"/>';
		echo $xml;
	}

	public function sendCode($callbackParams, $code){
		header("Content-type: text/xml; charset=utf-8");
		$xml = '<?xml version="1.0" encoding="UTF-8"?>
			<checkOrderResponse performedDatetime="'.date("c").'" code="'.$code.'" invoiceId="'.$callbackParams['invoiceId'].'" shopId="'.$this->shopid.'"/>';
		echo $xml;
	}

	public function checkOrder($callbackParams, $sendCode=FALSE, $aviso=FALSE){ 
		
		if ($this->checkSign($callbackParams)){
			$code = 0;
		}else{
			$code = 1;
		}
		
		if ($sendCode){
			if ($aviso){
				$this->sendAviso($callbackParams, $code);
			}else{
				$this->sendCode($callbackParams, $code);
			}
			exit;
		}else{
			return $code;
		}
	}

	public function individualCheck($callbackParams){
		$string = $callbackParams['notification_type'].'&'.$callbackParams['operation_id'].'&'.$callbackParams['amount'].'&'.$callbackParams['currency'].'&'.$callbackParams['datetime'].'&'.$callbackParams['sender'].'&'.$callbackParams['codepro'].'&'.$this->password.'&'.$callbackParams['label'];
		$check = (sha1($string) == $callbackParams['sha1_hash']);
		if (!$check){
			header('HTTP/1.0 401 Unauthorized');
			return false;
		}
		return true;
	
	}

	/* оплачивает заказ */
	public function ProcessResult()
	{
		$callbackParams = $_POST;
		$order_id = false;
		if ($this->org_mode){
			if ($callbackParams['action'] == 'checkOrder'){
				$code = $this->checkOrder($callbackParams);
				$this->sendCode($callbackParams, $code);
				$order_id = (int)$callbackParams["orderNumber"];
			}
			if ($callbackParams['action'] == 'paymentAviso'){
				$this->checkOrder($callbackParams, TRUE, TRUE);
			}
		}else{
			$check = $this->individualCheck($callbackParams);
			
			if (!$check){
				
			}else{
				$order_id = (int)$callbackParams["label"];
			}
		}
		
		return $order_id;
	}

	public static function adminHeadHtml($theme)
	{
		return '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
				<html xmlns="http://www.w3.org/1999/xhtml"  lang="en" xml:lang="en">
				<head>
				  <link rel="stylesheet" type="text/css" href="media/style/'.$theme.'/style.css" />
				  <script src="/assets/snippets/yandexmoney/js/jquery.min.js"></script>
				</head>
				<body>';
	}
	
	public static function adminInstallHtml()
	{
		$html = '<br/><h1>YandexMoney Payment Module</h1>'
			  . '<form action="" method="POST">'
			  . '<input type="hidden" name="action" value="install" />'
			  . '<input type="submit" value="Install YandexMoney">'
			  . '</form>';
		return $html;
	}

	public static function isInstalled($dbname, $mod_table)
	{
		$c = mysql_num_rows(mysql_query($sql = "show tables from $dbname like '$mod_table'"));
		return ($c > 0);
	}

	public function adminModuleHtml()
	{
		global $modx;
		$rb_base_url = $modx->config['rb_base_url'];
		$site_url = $modx->config['site_url'];

		$html = '<div class="box">
	
    <div class="heading">
      <h1>Яндекс.Деньги</h1>
     
    </div>
    <div class="content">
	
      <form action="" method="post" enctype="multipart/form-data" id="form">
		
        <table class="form">
		<tbody>
		<tr>
            <td>Статус:</td>
            <td>
			<select name="config[status]">
                 <option value="1" '.(($this->status==1 ? 'selected' : '')).'>Включено</option>
				 <option value="0"'.(($this->status==0 ? 'selected' : '')).'>Выключено</option>
            </select>
			 </td>
          </tr>
		 <tr>
            <td><span class="required">*</span> Использовать в тестовом режиме?</td>
            <td>
				<input id="testmode1" type="radio" name="config[testmode]" value="1" '.(($this->testmode==1 ? 'checked' : '')).'>
				<label for="testmode1" style="text-align: left;" >Да</label>
				
				<input id="testmode2" type="radio" name="config[testmode]" value="0" '.(($this->testmode==0 ? 'checked' : '')).'>
				<label for="testmode2" style="text-align: left;">Нет</label>
			</td>
          </tr>
		  
		  <tr>
            <td><span class="required">*</span> Выберите способы оплаты:</td>
            <td>
				<select name="config[mode]" id="yandexmoney_mode" onchange="yandex_validate_mode();">
					<option value="1" '.(($this->mode==1 ? 'selected' : '')).'>На счет физического лица в электронной валюте Яндекс.Денег</option>
					<option value="2" '.(($this->mode==2 ? 'selected' : '')).'>На расчетный счет организации с заключением договора с Яндекс.Деньгами</option>
				</select>
			</td>
          </tr>

		   <tr>
            <td><span class="required">*</span> Укажите необходимые способы оплаты:</td>
            <td>
				<input type="checkbox" name="config[method_ym]" value="1" id="ym_method_1" '.(($this->method_ym==1 ? 'checked' : '')).'><label for="ym_method_1" style="width: 280px; text-align: left;">Кошелек Яндекс.Деньги</label> <br>
				
				<input type="checkbox" name="config[method_cards]" value="1" id="ym_method_2" '.(($this->method_cards==1 ? 'checked' : '')).'><label for="ym_method_2" style="width: 280px; text-align: left;">Банковская карта</label> <br>
				
				<div class="org" style="display:none;">
					<input type="checkbox" name="config[method_cash]" value="1" id="ym_method_3" '.(($this->method_cash==1 ? 'checked' : '')).'><label for="ym_method_3" style="width: 280px; text-align: left;">Наличными через кассы и терминалы</label> <br>
					<input type="checkbox" name="config[method_mobile]" value="1" id="ym_method_4" '.(($this->method_mobile==1 ? 'checked' : '')).'><label for="ym_method_4" style="width: 280px; text-align: left;">Счет мобильного телефона</label> <br>
					<input type="checkbox" name="config[method_wm]" value="1" id="ym_method_5" '.(($this->method_wm==1 ? 'checked' : '')).'><label for="ym_method_5" style="width: 280px;text-align: left;">Кошелек WebMoney</label> <br>
					<input type="checkbox" name="config[method_ab]" value="1" id="ym_method_6" '.(($this->method_ab==1 ? 'checked' : '')).'><label for="ym_method_6" style="width: 280px;text-align: left;">Альфа-Клик</label> <br>
					<input type="checkbox" name="config[method_sb]" value="1" id="ym_method_7" '.(($this->method_sb==1 ? 'checked' : '')).'><label for="ym_method_7" style="width: 280px;text-align: left;">Сбербанк: оплата по SMS или Сбербанк Онлайн</label> <br>
					<input type="checkbox" name="config[method_ma]" value="1" id="ym_method_8" '.(($this->method_ma==1 ? 'checked' : '')).'><label for="ym_method_8" style="width: 280px;text-align: left;">MasterPass</label> <br>
					<input type="checkbox" name="config[method_pb]" value="1" id="ym_method_9" '.(($this->method_pb==1 ? 'checked' : '')).'><label for="ym_method_9" style="width: 280px;text-align: left;">Интернет-банк Промсвязьбанка</label> <br>
				</div>
			 </td>
          </tr>
		
		 <tr class="individ">
			<td></td>
			<td><p>Модуль версии 1.1.0</p>
			<p>Если у вас нет аккаунта в Яндекс-Деньги, то следует зарегистрироваться тут - <a href="https://money.yandex.ru/">https://money.yandex.ru/</a></p><p><b>ВАЖНО!</b> Вам нужно будет указать ссылку для приема HTTP уведомлений здесь - <a href="https://sp-money.yandex.ru/myservices/online.xml" target="_blank">https://sp-money.yandex.ru/myservices/online.xml</a></p></td>
		 </tr>

		  <tr class="org" style="display: none;">
			<td></td>
			<td><p>Модуль версии 1.1.0</p>
			<p>Если у вас нет аккаунта в Яндекс-Деньги, то следует зарегистрироваться тут - <a href="https://money.yandex.ru/joinups/">https://money.yandex.ru/joinups/</a></p></td>
		 </tr>
	
		  <tr class="individ">
			<td></td>
			<td>
				Параметры для заполнения в личном кабинете:<br>
				<table style="border: 1px black solid;">
				  <tbody><tr>
						<td style="border: 1px black solid; padding: 5px;">Название параметра</td>
						<td style="border: 1px black solid; padding: 5px;">Значение</td>
				  </tr>
				  <tr>
						<td style="border: 1px black solid; padding: 5px;">Адрес приема HTTP уведомлений</td>
						<td style="border: 1px black solid; padding: 5px;">'.$site_url.'assets/snippets/yandexmoney/callback.php</td>
				   </tr>
				</tbody></table>
			</td>
		  </tr>

		   <tr class="org" style="display: none;">
			<td></td>
			<td>
				Параметры для заполнения в личном кабинете:<br>
				<table style="border: 1px black solid;">
				  <tbody><tr>
						<td style="border: 1px black solid; padding: 5px;">Название параметра</td>
						<td style="border: 1px black solid; padding: 5px;">Значение</td>
				  </tr>
				  <tr>
						<td style="border: 1px black solid; padding: 5px;">Адрес приема HTTP уведомлений (paymentAvisoURL)</td>
						<td style="border: 1px black solid; padding: 5px;">'.$site_url.'assets/snippets/yandexmoney/callback.php</td>
				   </tr>
				   <tr>
						<td style="border: 1px black solid; padding: 5px;">checkURL</td>
						<td style="border: 1px black solid; padding: 5px;">'.$site_url.'assets/snippets/yandexmoney/callback.php</td>
				   </tr>
				   <tr>
						<td style="border: 1px black solid; padding: 5px;">successURL</td>
						<td style="border: 1px black solid; padding: 5px;">'.$site_url.'assets/snippets/yandexmoney/callback.php?success=1</td>
				   </tr>
				   <tr>
						<td style="border: 1px black solid; padding: 5px;">failURL</td>
						<td style="border: 1px black solid; padding: 5px;">'.$site_url.'assets/snippets/yandexmoney/callback.php?fail=1</td>
				   </tr>
				</tbody></table>
			</td>
		  </tr>

		  <tr class="individ">
            <td><span class="required">*</span> Номер кошелька Яндекс:</td>
            <td><input type="text" name="config[account]" value="'.$this->account.'">
              </td>
          </tr>

		  <tr>
            <td><span class="required">*</span> Секретное слово (shopPassword) для обмена сообщениями:</td>
            <td><input type="text" name="config[password]" value="'.$this->password.'">
              </td>
          </tr>
          <tr class="org" style="display: none;">
            <td><span class="required">*</span> Идентификатор вашего магазина в Яндекс.Деньгах (ShopID):</td>
            <td><input type="text" name="config[shopid]" value="'.$this->shopid.'">
              </td>
          </tr>
          <tr class="org" style="display: none;">
            <td><span class="required">*</span> Идентификатор витрины вашего магазина в Яндекс.Деньгах (scid):</td>
            <td><input type="text" name="config[scid]" value="'.$this->scid.'">
              </td>
          </tr>
        </tbody></table>
		<br/>
		<input type="submit" value="Сохранить" />
		<input type="hidden" name="action" value="save" />
      </form>
    </div>
  </div>
		<script type="text/javascript">
		function yandex_validate_mode(){
			var yandex_mode = $("#yandexmoney_mode").val();
			if (yandex_mode == 1){
				$(".individ").show();
				$(".org").hide();
			}else{
				$(".org").show();
				$(".individ").hide();
			}
		}
		$( document ).ready(function() {
			yandex_validate_mode();
		})
		</script>
  ';
		return $html;
	}

}
}