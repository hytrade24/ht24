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
	$ar_availability = ($row["SER_AVAILABILITY"] == null ? false : unserialize($row["SER_AVAILABILITY"]));
	$row['AVAILABILITY'] = ($ar_availability !== false);
	$row['AVAILABILITY_DATE_FROM'] = (is_array($ar_availability) ? $ar_availability['DATE_FROM'] : false);
	$row['AVAILABILITY_TIME_FROM'] = (is_array($ar_availability) ? $ar_availability['TIME_FROM'] : false);
	$row['AVAILABILITY_DATE_TO'] = (is_array($ar_availability) ? $ar_availability['DATE_TO'] : false);
}

$adOrderManagement = AdOrderManagement::getInstance($db);
$paymentAdapterManagement = PaymentAdapterManagement::getInstance($db);

$orderId = (int) $_REQUEST['ID_AD_ORDER'];

if(!$adOrderManagement->existSellerOrderForUserId($orderId, $uid)) {
	die("no access to order");
}

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
$tpl_content->addlist('orderItems', $order['items'], "tpl/".$s_lang."/sale_details_seller.row_item.htm", 'addVariants');
$tpl_content->addlist('liste_rating', $order['items'], "tpl/".$s_lang."/sale_details_seller.row_item_rating.htm");

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
	$tpl_content->addlist("liste_mails", array_merge($ar_mails, $ar_mailsTrans), "tpl/" . $s_lang . "/sale_details_seller.row_mail.htm");
}

// shipping
$shippingProvider = $order["SHIPPING_PROVIDER"];
if (!empty($shippingProvider)) {
	$arShippingProvider = Api_LookupManagement::getInstance($db)->readByValue("VERSAND_ANBIETER", $shippingProvider);
	if ($arShippingProvider !== false) {
		$tpl_content->addvar("ORDER_SHIPPING_PROVIDER_LABEL", $arShippingProvider["V1"]);
	}
}

// tracking
$trackingServices = $db->fetch_table("SELECT *, (ID_LOOKUP = '".(int)$order['SHIPPING_TRACKING_SERVICE']."') as SELECTED  FROM lookup WHERE art = 'TRACK_URL' ORDER BY F_ORDER");
$tpl_content->addlist('liste_tracking_service', $trackingServices, 'tpl/'.$s_lang.'/sale_details_seller.row_trackingservice.htm');

	// Print
if($_REQUEST['do'] == 'print') {
	$tpl_content->LoadText("tpl/".$s_lang."/sale_details_seller.print.htm");
	$tpl_content->addlist('orderItems', $order['items'], "tpl/".$s_lang."/sale_details_seller.print.row_item.htm", 'addVariants');
}

// Settings
$tpl_content->addvar("MARKTPLATZ_HIDE_CONTACT_INFO", $nar_systemsettings["MARKTPLATZ"]["HIDE_CONTACT_INFO"]);


/*
if (empty($_REQUEST['embed'])) {
	$tpl_content->addvar("dialog", 1);
}



$ar = $db->fetch1("
	SELECT
		ad_sold.*,
		manufacturers.`NAME` AS MANUFACTURER,
		ad_sold.FK_USER AS BUYER,
		sc.V1 as VERSAND_LAND,
		(ad_master.MENGE - ad_sold.MENGE) as MENGE_LEFT,
		ad_master.STAMP_START,
		ad_sold.PREIS as PREIS_NOSHIP,
		ad_sold.VERSANDKOSTEN,
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
		AND rating_get.FK_USER=".$uid."
	LEFT JOIN
		ad_sold_rating rating_send ON rating_send.FK_AD_SOLD=ad_sold.ID_AD_SOLD
		AND rating_send.FK_USER_FROM=".$uid."
	LEFT JOIN country c ON c.ID_COUNTRY=ad_sold.VERSAND_FK_COUNTRY
	LEFT JOIN string sc ON sc.S_TABLE='country' AND sc.FK=c.ID_COUNTRY AND
		sc.BF_LANG=if(c.BF_LANG & ".$langval.", ".$langval.", 1 << floor(log(c.BF_LANG+0.5)/log(2)))
	LEFT JOIN manufacturers on manufacturers.ID_MAN = ad_master.FK_MAN
	WHERE
		ad_sold.ID_AD_SOLD=".(int)$_REQUEST['FK_SOLD']."
		AND ad_sold.FK_USER_VK=".$uid);

if(!empty($ar))
{
	$ar_ad = $db->fetch1("SELECT * FROM `ad_master` WHERE ID_AD_MASTER=".$ar["FK_AD"]);
	if ($ar["FK_AD_VARIANT"] > 0) {
		$variants = AdVariantsManagement::getInstance($db);
		$ar_variant = $variants->getAdVariantDetailsById($ar["FK_AD_VARIANT"]);
		$ar_ad = array_merge($ar_ad, $ar_variant);
	}
	$tpl_content->addvars($ar_ad, "AD_");

	$ar['SUBJECT'] = urlencode("Transaktions Id ".$ar['ID_AD_SOLD'].": ".$ar['PRODUKTNAME']);
	$tpl_content->addvars($ar);


	// Varianten
	$liste_variants = array();
	$ar_variant = (isset($ar["SER_VARIANT"]) ? unserialize($ar["SER_VARIANT"]) : array());
	foreach ($ar_variant as $index => $ar_current) {
		$name = $db->fetch_atom("SELECT sf.V1 FROM `field_def` f
		 		LEFT JOIN `string_field_def` sf
		 		ON sf.S_TABLE='field_def' AND sf.FK=f.ID_FIELD_DEF
		 		AND sf.BF_LANG=if(f.BF_LANG_FIELD_DEF & ".$langval.", ".$langval.", 1 << floor(log(f.BF_LANG_FIELD_DEF+0.5)/log(2)))
			WHERE f.ID_FIELD_DEF=".$ar_current["ID_FIELD_DEF"]);
		if ($name === false) {
			$name = $ar_current["FIELD"];
		}
		$value = $db->fetch_atom("SELECT sl.V1 FROM `liste_values` t
			LEFT JOIN `string_liste_values` sl
				ON sl.S_TABLE='liste_values' AND sl.FK=t.ID_LISTE_VALUES
				AND sl.BF_LANG=if(t.BF_LANG_LISTE_VALUES & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_LISTE_VALUES+0.5)/log(2)))
			WHERE t.ID_LISTE_VALUES=".$ar_current["ID_LISTE_VALUES"]);
		if ($value === false) {
			$value = $ar_current["VALUE"];
		}
		$liste_variants[] = array("FIELD" => $name, "VALUE" => $value);
	}
	$tpl_content->addlist("VARIANTS", $liste_variants, "tpl/".$s_lang."/sale_details.variant.row.htm");

	$ar_data = $db->fetch1("
		SELECT
			`NAME` AS `USER`,
			FIRMA,
			VORNAME,
			NACHNAME,
			STRASSE,
			PLZ,
			ORT,
			s.V1 AS LAND,
			TEL,
			FAX,
			MOBIL,
			EMAIL,
            CACHE
		FROM
			`user`
		left join
			string s on s.S_TABLE='country'
			and s.FK=user.FK_COUNTRY
			and s.BF_LANG=if(".$langval." & ".$langval.", ".$langval.", 1 << floor(log(".$langval."+0.5)/log(2)))
		WHERE
			ID_USER=".(int)$ar['BUYER']);
	foreach($ar_data as $key => $value)
	{
		$tpl_content->addvar($key, $value);
	}
}


$ar_mails = $db->fetch_table("
	SELECT
		c.*
	FROM `chat` c
	WHERE
		FK_TRANS = '".(int)$_REQUEST['FK_SOLD']."'");
if (!empty($ar_mails)) {
	$tpl_content->addlist("liste_mails", $ar_mails, "tpl/".$s_lang."/sale_details_seller.row_mail.htm");
}

*/


?>