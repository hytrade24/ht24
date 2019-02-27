<?php
/* ###VERSIONSBLOCKINLCUDE### */


require_once $ab_path.'sys/lib.ad_variants.php';
require_once $ab_path.'sys/lib.ad_order.php';
require_once $ab_path.'sys/lib.payment.adapter.php';
require_once $ab_path.'sys/payment/PaymentFactory.php';

function killbb(&$row) {
	$row['AD_BESCHREIBUNG'] = substr(strip_tags(html_entity_decode($row['AD_BESCHREIBUNG'])), 0, 250);
	$row['AD_BESCHREIBUNG'] = preg_replace("#(\[)([^\s\]]*)(\])#si", "", $row['AD_BESCHREIBUNG']);
}


function addVariants(&$row) {
	global $db, $langval;

	killbb($row);

	$ar_variant = (isset($row["SER_VARIANT"]) ? unserialize($row["SER_VARIANT"]) : array());
	$ar_variant_list = array();
	foreach ($ar_variant as $index => $ar_current) {
		$value = $db->fetch_atom("SELECT sl.V1 FROM `liste_values` t
				LEFT JOIN `string_liste_values` sl
					ON sl.S_TABLE='liste_values' AND sl.FK=t.ID_LISTE_VALUES
					AND sl.BF_LANG=if(t.BF_LANG_LISTE_VALUES & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_LISTE_VALUES+0.5)/log(2)))
				WHERE t.ID_LISTE_VALUES=".$ar_current["ID_LISTE_VALUES"]);
		if ($value !== FALSE) {
			$ar_variant_list[] = $value;
		} else {
			$ar_variant_list[] = $ar_current["VALUE"];
		}
	}
	$row["VARIANT"] = (empty($ar_variant_list) ? "" : implode(", ", $ar_variant_list));
}

$adOrderManagement = AdOrderManagement::getInstance($db);
$paymentAdapterManagement = PaymentAdapterManagement::getInstance($db);

$orderId = (int) $_REQUEST['ID_AD_ORDER'];


$order = $adOrderManagement->fetchById($orderId);


foreach($order['items'] as $key => $orderItem) {
	if($orderItem['MENGE_LEFT'] < 0) {
		$tpl_content->addvar("ORDER_MENGE_LEFT_TOO_LESS", 1);
	}
	if($orderItem['MENGE_LEFT'] <= 0) {
		$tpl_content->addvar("ORDER_MENGE_LEFT_SOLD_OUT", 1);
	}
}


$tpl_content->addvars($order, "ORDER_");
$tpl_content->addlist('orderItems', $order['items'], "tpl/".$s_lang."/transaction_details.row_item.htm", 'addVariants');
$tpl_content->addlist('liste_rating', $order['items'], "tpl/".$s_lang."/transaction_details.row_item_rating.htm");

if($order['FK_PAYMENT_ADAPTER'] != 0) {
	$paymentAdapter = $paymentAdapterManagement->fetchById($order['FK_PAYMENT_ADAPTER']);
	$tpl_content->addvars($paymentAdapter, "PAYMENT_ADAPTER_");
}


// Mails

$ar_mails = $db->fetch_table("
	SELECT
		c.*
	FROM `chat` c
	WHERE
		c.FK_AD_ORDER = '" . (int)$orderId . "'");

$ar_mailsTrans = $db->fetch_table("
	SELECT
		c.*
	FROM `chat` c
	LEFT JOIN ad_sold ads ON c.FK_TRANS = ads.ID_AD_SOLD
	WHERE
		ads.FK_AD_ORDER = '" . (int)$orderId . "'");

if (!empty($ar_mails)) {
	$tpl_content->addlist("liste_mails", array_merge($ar_mails, $ar_mailsTrans), "tpl/" . $s_lang . "/transaction_details.row.mail.htm");
}

	// Print
if($_REQUEST['do'] == 'print') {
	$tpl_content->LoadText("tpl/".$s_lang."/transaction_details.print.htm");
	$tpl_content->addlist('orderItems', $order['items'], "tpl/".$s_lang."/transaction_details.print.row_item.htm", 'addVariants');
}

/*

if (empty($_REQUEST['embed'])) {
	$tpl_content->addvar("dialog", 1);
}

$id_transaction = (int)$_REQUEST["id"];
if ($id_transaction <= 0) {
	die("<h1>Transaktion nicht gefunden!</h1>");
}



$ar = $db->fetch1("
	SELECT
		ad_sold.*,
		ad_sold.FK_USER AS BUYER,
		ad_sold.FK_AD,
		ad_sold.PRODUKTNAME,
		ad_sold.NOTIZ,
		ad_sold.VERSANDKOSTEN,
		ad_master.STAMP_START,
		rating_get.RATING AS RATING_OWN,
		rating_get.`COMMENT` AS COMMENT_OWN,
		rating_send.RATING AS RATING_SEND,
		rating_send.`COMMENT` AS COMMENT_SEND,
        (ad_sold.STATUS & 2) AS ABGESCHLOSSEN
	FROM
		ad_sold
	LEFT JOIN
		ad_master ON ad_sold.FK_AD=ad_master.ID_AD_MASTER
	LEFT JOIN
		ad_sold_rating rating_get ON rating_get.FK_AD_SOLD=ad_sold.ID_AD_SOLD
		AND rating_get.FK_USER=ad_sold.FK_USER
	LEFT JOIN
		ad_sold_rating rating_send ON rating_send.FK_AD_SOLD=ad_sold.ID_AD_SOLD
		AND rating_send.FK_USER_FROM=ad_sold.FK_USER
	WHERE
		ad_sold.ID_Ad_SOLD=".$id_transaction);

if(!empty($ar))
{
	$ar['SUBJECT'] = urlencode("Transaktions Id ".$ar['ID_AD_SOLD'].": ".$ar['PRODUKTNAME']);
	$tpl_content->addvars($ar);


	$ar_user_vk = $db->fetch1("SELECT * FROM `user` WHERE ID_USER=".$ar["FK_USER_VK"]);
	$ar_user_ek = $db->fetch1("SELECT * FROM `user` WHERE ID_USER=".$ar["FK_USER"]);
	$tpl_content->addvars($ar_user_vk, "VK_");
	$tpl_content->addvars($ar_user_ek, "EK_");
}



$ar_mails = $db->fetch_table("
	SELECT
		c.*
	FROM `chat` c
	WHERE
		FK_TRANS = '".$id_transaction."'");
$tpl_content->addlist("liste_mails", $ar_mails, "tpl/de/transaction_details.row.mail.htm");


*/
?>