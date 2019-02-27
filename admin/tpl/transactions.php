<?php
/* ###VERSIONSBLOCKINLCUDE### */

function addVariants(&$row) {
	global $db, $langval;
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

function killbb(&$row) {
	$row['AD_BESCHREIBUNG'] = substr(strip_tags(html_entity_decode($row['AD_BESCHREIBUNG'])), 0, 250);
	$row['AD_BESCHREIBUNG'] = preg_replace("#(\[)([^\s\]]*)(\])#si", "", $row['AD_BESCHREIBUNG']);
}

function addOrderItems(&$row) {
	global $s_lang, $db, $langval, $nar_systemsettings, $paymentAdapterManagement, $userManagement, $adOrderManagement;

	$ar_items = array();
	$row['ORDER_PROV'] = 0;

	if(isset($row['items'])) {
		$orderItemTemplate = '';

		$orderConfirmationData = $adOrderManagement->getOrderConfirmationArrayByItems($row['items']);
		$row = array_merge($row, $orderConfirmationData);

		foreach($row['items'] as $key => $item) {
			// Varianten
			addVariants($item);
			killbb($item);

			$tpl = new Template("tpl/".$s_lang."/my-marktplatz-verkaeufe.item.row.htm");
			$tpl->addvar("CURRENCY_DEFAULT", $nar_systemsettings['MARKTPLATZ']['CURRENCY']);
			$tpl->addvars($item);
			$tpl->addvars($row, 'ORDER_');

			$orderItemTemplate .= $tpl->process();

			if(!($item['STATUS'] & 1)) {
				$orderIsAbgeschlossen = FALSE;
			}
			$row['ORDER_PROV'] += $item['PROV'];
		}

		$row['tplItems'] = $orderItemTemplate;
	}
	$row['ORDER_ABGESCHLOSSEN'] = $orderIsAbgeschlossen;
}

/*

if (empty($status)) {
	$status = array('open' => 1, 'done' => 1, 'confirmed' => 1, 'canceled' => 1);
}

$tpl_content->addvars($status, "status_");

if ($id_sold > 0) {
	$sold = $db->fetch1("SELECT * FROM `ad_sold` WHERE ID_AD_SOLD=".$id_sold);
	$article_data = $db->fetch1("SELECT *, DATEDIFF(STAMP_END,NOW()) as DAYS_LEFT FROM `".$sold["FK_TABLE"]."` WHERE ID_".strtoupper($sold["FK_TABLE"])."=".$sold["FK_AD"]);
    $article_tpl = array(
		"product_id"				=>	$sold["FK_AD"],
		"product_kat"				=>	$article_data["FK_KAT"],
		"product_table"				=>	$sold["FK_TABLE"],
		"product_manufacturer"		=>	$db->fetch_atom("SELECT NAME FROM manufacturers
															WHERE ID_MAN=".(int)$article_data["FK_MAN"]),
		"product_articlename"		=>	$article_data["PRODUKTNAME"],
		"product_price"				=>	$article_data["PREIS"],
		"product_country"			=>	$db->fetch_atom("SELECT V1 FROM string
														WHERE S_TABLE='country' AND BF_LANG=".$langval." AND
															FK=".(int)$article_data["FK_COUNTRY"]),
		"product_zip"				=>	$article_data["ZIP"],
		"product_city"				=>	$article_data["CITY"],
		"product_price_overall"		=>	$article_data["PREIS"] + $article_data["VERSANDKOSTEN"],
		"product_runtime_left"		=>	$article_data["DAYS_LEFT"],
		"product_shipping"			=>	$article_data["VERSANDKOSTEN"],
		"vk_username"				=>	$db->fetch_atom("SELECT NAME FROM `user` WHERE ID_USER=".$sold["FK_USER_VK"]),
		"vk_user"					=>	$sold["FK_USER_VK"],
		"ek_username"				=>	$db->fetch_atom("SELECT NAME FROM `user` WHERE ID_USER=".$sold["FK_USER"]),
		"ek_user"					=>	$sold["FK_USER"]
	);

	if ($action == "delete") {
		// Kauf löschen
		if (($sold["STATUS"]&3) == 2) {
			// Kauf wurde bereits abgelehnt
			$db->querynow("DELETE FROM `ad_sold` WHERE ID_AD_SOLD=".$id_sold);
			die(forward("index.php?page=articles_sold&ok=1"));
		}
		if (($sold["STATUS"]&3) == 0) {
	        // Kauf noch offen
	        $db->querynow("DELETE FROM `ad_sold` WHERE ID_AD_SOLD=".$id_sold);
	        $db->querynow("UPDATE `".$sold["FK_TABLE"]."`
	        		SET STATUS=(STATUS|1)-(STATUS&4)
	        			WHERE ID_".strtoupper($sold["FK_TABLE"])."=".$sold["FK_AD"]);

	        $db->querynow("UPDATE
	        		`ad_temp`
	        	SET
	        		DONE=0
	        	WHERE
	        		`TABLE`='".mysql_escape_string($sold["FK_TABLE"])."' AND FK_AD=".$sold["FK_AD"]);

			die(forward("index.php?page=articles_sold&ok=1"));
		}
	}
} else {
	if ($_REQUEST["ok"] == 1) {
		$tpl_content->addvar("ok_delete", 1);
	}
}

$ar_where = array();
// Filter status
if (!$status['open']) {
	$ar_where[] = "(s.CONFIRMED<>0 OR (s.STATUS&3)<>3)";
}
if (!$status['confirmed']) {
	$ar_where[] = "s.CONFIRMED<>1";
}
if (!$status['canceled']) {
	$ar_where[] = "s.CONFIRMED<>2";
}
if (!$status['done']) {
	$ar_where[] = "(s.STATUS&3)<>3";
}
// Filter article by name
if (!empty($_REQUEST['TITLE'])) {
	$ar_where[] = "a.PRODUKTNAME LIKE '%".mysql_escape_string($_REQUEST['TITLE'])."%'";
	$tpl_content->addvar("TITLE", $_REQUEST['TITLE']);
}
// Filter article by id
if (!empty($_REQUEST['ID_AD'])) {
	$id_ad = (int)$_REQUEST['ID_AD'];
	$ar_where[] = "s.FK_AD=".$id_ad;
	$tpl_content->addvar("ID_AD", $id_ad);
}
// Filter transaction
if (!empty($_REQUEST['ID_AD_SOLD'])) {
	$id_ad_sold = (int)$_REQUEST['ID_AD_SOLD'];
	$ar_where[] = "s.ID_AD_SOLD=".$id_ad_sold;
	$tpl_content->addvar("ID_AD_SOLD", $id_ad_sold);
}
// Filter user
if (!empty($_REQUEST['FK_AUTOR'])) {
	$id_user = (int)$_REQUEST['FK_AUTOR'];
	$ar_where[] = "(s.FK_USER=".$id_user." OR s.FK_USER_VK=".$id_user.")";
	$tpl_content->addvar("NAME_", $_REQUEST['NAME_']);
	$tpl_content->addvar("FK_AUTOR", $id_user);
}

$order = "ORDER BY s.STAMP_BOUGHT DESC";

switch ($_REQUEST["ORDERBY"]) {
	case 'STAMP_BUY':
		$order = "ORDER BY s.STAMP_BOUGHT ".($_REQUEST["UPDOWN"] ? "DESC" : "ASC");
		break;
	case 'STATUS':
		$order = "ORDER BY s.CONFIRMED ".($_REQUEST["UPDOWN"] ? "DESC" : "ASC");
		break;
	case 'USER_SELL':
		$order = "ORDER BY s.FK_USER_VK ".($_REQUEST["UPDOWN"] ? "DESC" : "ASC");
		break;
	case 'USER_BUY':
		$order = "ORDER BY s.FK_USER ".($_REQUEST["UPDOWN"] ? "DESC" : "ASC");
		break;
}
$tpl_content->addvar("ORDERBY_".$_REQUEST["ORDERBY"], 1);
$tpl_content->addvar("UPDOWN", $_REQUEST["UPDOWN"]);

$where = (empty($ar_where) ? "" : "WHERE ".implode(" AND ", $ar_where));
$query_count = "
  	SELECT
  		count(*)
  	FROM
  		`ad_master` a
	RIGHT JOIN `ad_sold` s
		ON s.FK_AD = a.ID_AD_MASTER
  	".$where;
$query = "
  	SELECT
  		a.*,
  		s.FK_AD as ID_ARTIKEL,
  		if(a.STATUS&1,1,if(a.STATUS&2,2,0)) as SOLD,
  		if(a.STATUS&4,1,0) as STORNO,
  		s.ID_AD_SOLD as ID_SOLD,
		s.FK_AD_VARIANT,
		s.SER_VARIANT,
  		s.CONFIRMED,
  		s.STAMP_BOUGHT,
		s.PRODUKTNAME,
		s.NOTIZ,
		s.VERSANDKOSTEN,
  		s.MENGE,
		s.PREIS as PREIS_GESAMT,
		(s.PREIS / s.MENGE) as PREIS,
  		s.FK_USER, s.FK_USER_VK,
  		s.PROV,
  		(SELECT NAME FROM `user` WHERE ID_USER=s.FK_USER) as USERNAME_EK,
  		(SELECT NAME FROM `user` WHERE ID_USER=s.FK_USER_VK) as USERNAME_VK,
  		(SELECT
  				i.SRC_THUMB
  			FROM `ad_images` i
  			WHERE
  				(i.FK_AD = a.ID_AD_MASTER) AND (i.IS_DEFAULT = 1)
  			LIMIT 1) as SRC_THUMB
  	FROM
  		`ad_sold` s
  	LEFT JOIN
  		`ad_master` a ON s.FK_AD = a.ID_AD_MASTER
  	".$where."
  	".$order."
	LIMIT ".$limit.",".$perpage;

$SILENCE = false;

$all = $db->fetch_atom($query_count);
$ads = $db->fetch_table($query);*/



//


require_once $ab_path.'sys/lib.ad_order.php';
require_once $ab_path.'sys/lib.payment.adapter.php';
require_once $ab_path.'sys/payment/PaymentFactory.php';

$adOrderManagement = AdOrderManagement::getInstance($db);
$paymentAdapterManagement = PaymentAdapterManagement::getInstance($db);


$isSearch = false;
$status = $_REQUEST['status'];
$action = $_REQUEST['action'];
$id_sold = $_REQUEST['id'];

$perpage = 20; // Elemente pro Seite
$limit = ((($_REQUEST['npage'] ? $_REQUEST['npage'] : $_REQUEST['npage']=1)-1)*$perpage);

$param = array(
	'LIMIT' => $perpage,
	'OFFSET' => $limit
);


if (isset($_REQUEST['ID_AD_ORDER']) && $_REQUEST['ID_AD_ORDER'] != "") {
	$param['ID_AD_ORDER'] = $_REQUEST['ID_AD_ORDER'];
	$isSearch = true;
}


if (isset($_REQUEST['FK_AUTOR']) && $_REQUEST['FK_AUTOR'] != "") {
	$param['USER_SELLER'] = (int)$_REQUEST['FK_AUTOR'];
	$isSearch = true;
}

if (isset($_REQUEST['SEARCH_ORDER_CONFIRMATION_STATUS']) && $_REQUEST['SEARCH_ORDER_CONFIRMATION_STATUS'] != "-1") {
	$param['STATUS_CONFIRMATION'] = $_REQUEST['SEARCH_ORDER_CONFIRMATION_STATUS'];
	$isSearch = true;
} else {
	$_REQUEST['SEARCH_ORDER_CONFIRMATION_STATUS'] = -1;
}

if (isset($_REQUEST['SEARCH_ORDER_PAYMENT_STATUS']) && $_REQUEST['SEARCH_ORDER_PAYMENT_STATUS'] != "-1") {
	$param['STATUS_PAYMENT'] = $_REQUEST['SEARCH_ORDER_PAYMENT_STATUS'];
	$isSearch = true;
} else {
	$_REQUEST['SEARCH_ORDER_PAYMENT_STATUS'] = -1;
}
if (isset($_REQUEST['SEARCH_ORDER_SHIPPING_STATUS']) && $_REQUEST['SEARCH_ORDER_SHIPPING_STATUS'] != "-1") {
	$param['STATUS_SHIPPING'] = $_REQUEST['SEARCH_ORDER_SHIPPING_STATUS'];
	$isSearch = true;
} else {
	$_REQUEST['SEARCH_ORDER_SHIPPING_STATUS'] = -1;
}


$param['SORT'] = 'ao.STAMP_CREATE';

switch ($_REQUEST["ORDERBY"]) {
	case 'STAMP_BUY':
		$param['SORT'] = 'ao.STAMP_CREATE';
		break;
	case 'USER_SELL':
		$param['SORT'] = 'ao.FK_USER_VK';
		break;
	case 'USER_BUY':
		$param['SORT'] = 'ao.FK_USER';
		break;
}
if(!isset($_REQUEST["UPDOWN"])) { $_REQUEST["UPDOWN"] = 1; }
$param['SORT_DIR'] = ($_REQUEST["UPDOWN"] ? "DESC" : "ASC");

if ($isSearch) {
	$tpl_content->addvar("SEARCH_RESULT", 1);
}

$tpl_content->addvar("ORDERBY_".$_REQUEST["ORDERBY"], 1);
$tpl_content->addvar("UPDOWN", $_REQUEST["UPDOWN"]);

$orders = $adOrderManagement->fetchAllByParam($param);
$countOrder = $adOrderManagement->countByParam($param);

$queryparams = $_REQUEST;
unset($queryparams['npage']);
$query = http_build_query($queryparams);

$tpl_content->addlist("liste", $orders, "tpl/de/transactions.row.htm", "addOrderItems");
$tpl_content->addvar("pager", htm_browse($countOrder, $_REQUEST['npage'], "index.php?".$query."&npage=", $perpage));
$tpl_content->addvars($_REQUEST);

?>