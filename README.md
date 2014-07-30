yandexmoney_modx_evo
====================

1. Скопировать файлы в корень сайта.

2. Модули -> управление модулями -> новый модуль
Название: YandexMoney
Новая категория: YandexMoney

Код модуля: include '../assets/snippets/yandexmoney/yandexmoney.class.php';

$dbname = $modx->db->config['dbase']; //имя базы данных
$dbprefix = $modx->db->config['table_prefix']; //префикс таблиц
$mod_table = $dbprefix."yandexmoney"; //таблица модуля
$theme = $modx->config['manager_theme']; //тема админки
$basePath = $modx->config['base_path']; //путь до сайта на сервере
 
$action = isset($_POST['action']) ? $_POST['action']:'';

switch($action) {
 
//Установка модуля (создание таблицы в БД)
case 'install':
  $sql = "CREATE TABLE $mod_table (id INT(11) NOT NULL AUTO_INCREMENT, config LONGTEXT, PRIMARY KEY (id))";
  $modx->db->query($sql);

  $sql = "INSERT INTO $mod_table (id, config) VALUES (1, '')";
  $modx->db->query($sql);

  header("Location: $_SERVER[REQUEST_URI]");
break;
 
//Удаление таблицы модуля
case "uninstall":
  $sql = "DROP TABLE $mod_table";
  $modx->db->query($sql);
  header("Location: $_SERVER[REQUEST_URI]");
break;
 
//Обновление записи в БД
case 'save':
  $fields = array(
     'config' => serialize($_POST['config'])
  );
  $query = $modx->db->update($fields, $mod_table, 'id = 1'); 
  header("Location: $_SERVER[REQUEST_URI]");
break;
 
 
//Перезагрузка страницы (сброс $_POST)
case 'reload':
  header("Location: $_SERVER[REQUEST_URI]");
break;

//Страница модуля
default:
	echo Yandexmoney::adminHeadHtml($theme);;
	if (!Yandexmoney::isInstalled($dbname, $mod_table)){
		//если таблицы не существует, выводим кнопку "Установить модуль"
		echo Yandexmoney::adminInstallHtml();
	}else{
		$data_query = $modx->db->select("*", $mod_table, "", "id ASC", ""); 
                $row = mysql_fetch_assoc($data_query);
                $config = unserialize($row['config']);
		
		$ym = new Yandexmoney($config);
		echo $ym->adminModuleHtml();
	}
	break;
} 
 
3. Элементы -> управление элементами -> сниппеты -> новый сниппет

Наименование: YandexMoney
Свойства -> категория: YandexMoney

Код сниппета: 

<?php
$dbname = $modx->db->config['dbase']; //имя базы данных
$dbprefix = $modx->db->config['table_prefix']; //префикс таблиц
$mod_table = $dbprefix."yandexmoney"; //таблица модуля
$theme = $modx->config['manager_theme']; //тема админки
$basePath = $modx->config['base_path']; //путь до сайта на сервере
if(!defined('YANDEXMONEY_PATH')) {
 define('YANDEXMONEY_PATH', MODX_BASE_PATH."assets/snippets/yandexmoney/");
}
require_once YANDEXMONEY_PATH.'yandexmoney.class.php';

$data_query = $modx->db->select("*", $mod_table, "", "id ASC", ""); 
$row = mysql_fetch_assoc($data_query);
$config = unserialize($row['config']);
$ym = new Yandexmoney($config);
if ($_POST['payment']){
 $ym->pay_method = $_POST['payment'];
}
if ($action == 'showMethods') {
 return $ym->getSelectHtml();
}
?>

4. В файле assests/snippets/shopkeeper/shopkeeper.inc.php

в начале файла, после <?php добавить код:

if(!function_exists('sendOrderToManager')){
	function sendOrderToManager(&$fields){
	  global $modx, $shkconf;
	 
	  if(!class_exists('Shopkeeper')) require_once MODX_BASE_PATH."assets/snippets/shopkeeper/class.shopkeeper.php";
	  $shopCart = new Shopkeeper($modx, $shkconf);
	  
	  $shopCart->sendOrderToManager($fields);
	  
	  YandexMoneyForm($fields);	

	  return true;
	}
}

5. Элементы - управление элементами - чанки
В чанке shopOrderForm в способы оплаты добавить:

[[YandexMoney? &action=`showMethods`]]

Т.е. будет выглядеть примерно так:

<select name="payment" eform="payment::::#FUNCTION YandexMoneyValidate">
    <option value="При получении">При получении</option>
    <option value="WebMoney">WebMoney</option>
    [[YandexMoney? &action=`showMethods`]]
</select>

6. На странице форма заказа добавить вызов YandexMoney. 
Примерный вид:

[[YandexMoney? &action=`beforeCart`]]
[!Shopkeeper? &cartType=`full` &priceTV=`price` &orderFormPage=`34` &currency=`руб.` &noJQuery=`1`!]
[!eForm? &formid=`shopOrderForm` &tpl=`shopOrderForm` &report=`shopOrderReport` &vericode=`1` &ccsender=`1` &gotoid=`35` &subject=`Новый заказ` &eFormOnBeforeMailSent=`populateOrderData` &eFormOnMailSent=`sendOrderToManager` !]

7. Модули - YandexMoney - установить
8. Модули - YandexMoney
заполнить данные.
