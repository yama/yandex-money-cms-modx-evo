<?php
require_once(MODX_MANAGER_PATH . '../../../manager/includes/config.inc.php');
define('MODX_API_MODE', true);

require_once 'yandexmoney.class.php';

require_once(MODX_MANAGER_PATH . 'includes/protect.inc.php');
require_once(MODX_MANAGER_PATH.'includes/document.parser.class.inc.php');

if(!isset($modx)) $modx = new DocumentParser;
$modx->db->connect();
$modx->getSettings();
$modx->config['site_url'] = isset($_REQUEST['site_url']) ? $_REQUEST['site_url'] : '';
$modx->config['site_url'] = htmlspecialchars($modx->config['site_url'], ENT_QUOTES, $modx->config['modx_charset']);

$rs = $modx->db->select('*', $modx->getFullTableName('yandexmoney'), '', 'id ASC', ''); 
$row = $modx->db->getRow($rs);
$config = unserialize($row['config']);

$ym = new Yandexmoney($config);

$order_id = $ym->ProcessResult();


if ($order_id){
	$modx->db->update("status = 6", $modx->getFullTableName('manager_shopkeeper'), 'id = ' . (int)$order_id);
}

