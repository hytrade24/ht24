<?php
/* ###VERSIONSBLOCKINLCUDE### */

if (file_exists($ab_path."update/update.yml")) {
    include "welcomeUpdate.php";
    return;
}

$no_user=1;
function user_add_number(&$row) {
	global $no_user;
	$row["NUMMER"] = $no_user;
	$no_user++;
}

require_once "welcome.serverconfig.php";

if ($_REQUEST["ebizTools"] == 42) {
	$tpl_content->addvar("ebizTools", 1);
}

$SILENCE = false;
/*
### anzeigen im Markt
$ar_ads = $db->fetch_table("
	SELECT
			SQL_CALC_FOUND_ROWS
    		am.*,
    		LEFT(am.PRODUKTNAME, 30) AS KURZ,
    		am.ID_AD_MASTER AS ID_ARTIKEL,
    		`user`.`NAME` AS `USER`
    	FROM
    		ad_master am
    	LEFT JOIN
    		`user` on am.FK_USER=`user`.ID_USER
		WHERE
    		am.STATUS&3 = 1
    	ORDER BY
			am.STAMP_START DESC
    	LIMIT
    		5");
$tpl_content->addlist("new_ads", $ar_ads, "tpl/de/welcome.ads.htm",'user_add_number');
*/

# die letzte 5 events
$data = $db->fetch_table('SELECT  ID_CALENDAR_EVENT, FK_REF_TYPE, STAMP_START, TITLE,  LEFT(`DESCRIPTION`, 256) as besch
FROM calendar_event where  STAMP_START >= now() order by STAMP_START LIMIT 5');
$tpl_content->addlist('EVENTS', $data, 'tpl/de/welcome.events.row.htm');

# die letzte 5 Anbieter
$data = $db->fetch_table('select ID_VENDOR as v_ID_VENDOR,FK_USER as v_FK_USER,NAME as v_NAME,CHANGED as v_CHANGED  from vendor where status = 1 order by CHANGED DESC limit 5');
$tpl_content->addlist('VENDORS', $data, 'tpl/de/welcome.vendor.row.htm');

# die letzte 5 gruppen
$data = $db->fetch_table('select ID_CLUB,NAME,CHANGED from club order by  CHANGED DESC limit 5 ');
$tpl_content->addlist('GROUPS', $data, 'tpl/de/welcome.groups.row.htm');

# die letzten 10 transaktionen
require_once $ab_path.'sys/lib.ad_order.php';
$adOrderManagement = AdOrderManagement::getInstance($db);
$data = $adOrderManagement->fetchAllByParam(array("LIMIT" => 10));
foreach ($data as $dataIndex => $dataRow) {
	$orderConfirmationData = $adOrderManagement->getOrderConfirmationArrayByItems($dataRow['items']);
	$data[$dataIndex] = array_merge($dataRow, $orderConfirmationData);
}
$tpl_content->addlist('transactions', $data, 'tpl/de/welcome.transactions.row.htm');


# die letzten 5 produkte
$query = "
  	SELECT
  		a.*,
  		u.NAME as USERNAME,
  		(SELECT k.V1 FROM `string_kat` k WHERE k.FK=a.FK_KAT AND BF_LANG=128 AND S_TABLE='kat') as KAT_NAME
		FROM `ad_master` a
		LEFT JOIN `user` u ON u.ID_USER = a.FK_USER
		WHERE STATUS=1
		ORDER BY STAMP_START DESC
		LIMIT 5";
$data = $db->fetch_table($query);
$tpl_content->addlist('articles', $data, 'tpl/de/welcome.articles.row.htm');

# die letzten 5 kommentare
require_once $ab_path."sys/lib.comment.php";
$commentManagement = CommentManagement::getInstance($db);
$data = $commentManagement->fetchAllByParams(array(), 0, 5);
foreach ($data as $dataIndex => $dataRow) {
	$cm = CommentManagement::getInstance($db, $dataRow['TABLE']);
	$data[$dataIndex]["COMMENT_SHORT"] = substr(strip_tags(html_entity_decode($dataRow['COMMENT'])), 0, 250);
	if ($dataRow["FK"] !== null) {
		$data[$dataIndex]["TARGET_LINK"] = $cm->getTargetLink($dataRow["FK"]);
	} else {
		$data[$dataIndex]["TARGET_LINK"] = $cm->getTargetLinkStr($dataRow["FK_STR"]);
	}
}
$tpl_content->addlist('comments', $data, 'tpl/de/welcome.comments.row.htm');

#lst 10 user
$data = $db->fetch_table('select ID_USER, EMAIL, VORNAME, NACHNAME, NAME,TIMESTAMPDIFF(YEAR,GEBDAT,CURDATE()) as age ,STAT
							from user WHERE IS_VIRTUAL = 0
							order by ID_USER DESC
							limit 10');
  $tpl_content->addlist('users', $data, 'tpl/de/welcome.uesersrow.htm','user_add_number');
  
  
  
$tpl_content->addvar("C_ADS_MARKET", $db->fetch_atom("SELECT count(*) FROM `ad_master` a WHERE a.CRON_DONE=1 AND (a.STATUS&3)=1"));
$tpl_content->addvar("C_ADS_MARKET_TODAY", $db->fetch_atom("
	SELECT
		COUNT(*)
	FROM
		ad_master
	WHERE
		DATE_FORMAT(STAMP_START, '%Y-%m-%d') = CURDATE()
		AND CRON_DONE=1 and STATUS&3=1"));

### Warenwert im Markt
$warenwert = $db->fetch_atom("
	SELECT
		SUM(PREIS*MENGE)
	FROM
		ad_master
	WHERE
		CRON_DONE=1 and STATUS&3=1");
// Tausendertrennzeichen einfügen
$ar_warenwert = explode(".", sprintf("%.2f", $warenwert));
$warenwert = "";
while (strlen($ar_warenwert[0]) > 3) {
	$warenwert = ".".substr($ar_warenwert[0], -3).$warenwert;
	$ar_warenwert[0] = substr($ar_warenwert[0], 0, -3);
}
$warenwert = $ar_warenwert[0].$warenwert.",".$ar_warenwert[1];

$tpl_content->addvar("WARENWERT", $warenwert);

### Verkäufe
$sale_today = $db->fetch1("
	SELECT
		COUNT(*) AS `COUNT`,
		(
			SELECT
				SUM(PREIS)
			FROM
				ad_sold
			WHERE
				DATE_FORMAT(STAMP_BOUGHT, '%Y-%m-%d') = CURDATE()
		) AS SUM_TODAY
	FROM
		ad_sold
	WHERE
		DATE_FORMAT(STAMP_BOUGHT, '%Y-%m-%d') = CURDATE()");
$tpl_content->addvar("SALES_TODAY", $sale_today['COUNT']);
$tpl_content->addvar("SUM_TODAY", $sale_today['SUM_TODAY']);

$sale_month = $db->fetch1("
	SELECT
		COUNT(*) AS `COUNT`,
		(
			SELECT
				SUM(PREIS)
			FROM
				ad_sold
			WHERE
				MONTH(STAMP_BOUGHT) = MONTH(CURDATE())
		)	AS SUM_MONTH
	FROM
		ad_sold
	WHERE
		MONTH(STAMP_BOUGHT) = MONTH(CURDATE())");
$tpl_content->addvar("SALES_MONTH", $sale_month['COUNT']);
$tpl_content->addvar("SUM_MONTH", $sale_month['SUM_MONTH']);

### umsatz

$umsatz['UMSATZ_HEUTE'] = $db->fetch_atom("
    SELECT
        SUM(it.QUANTITY*it.PRICE*(1+(IFNULL(tax.TAX_VALUE, 0)/100)))
    FROM
      billing_invoice i
    LEFT JOIN billing_invoice_item it ON it.FK_BILLING_INVOICE = i.ID_BILLING_INVOICE
        LEFT JOIN tax tax ON tax.ID_TAX = it.FK_TAX
    WHERE
        DATE_FORMAT(i.STAMP_CREATE, '%Y-%m-%d') = CURDATE() AND i.STATUS != 2
");

$umsatz['UMSATZ_MONTH'] = $db->fetch_atom("
    SELECT
        SUM(it.QUANTITY*it.PRICE*(1+(IFNULL(tax.TAX_VALUE, 0)/100)))
    FROM
      billing_invoice i
    LEFT JOIN billing_invoice_item it ON it.FK_BILLING_INVOICE = i.ID_BILLING_INVOICE
        LEFT JOIN tax tax ON tax.ID_TAX = it.FK_TAX
    WHERE
        MONTH(STAMP_CREATE) = MONTH(CURDATE()) AND i.STATUS != 2
");

$umsatz['UMSATZ_GESAMT'] = $db->fetch_atom("
    SELECT
        SUM(it.QUANTITY*it.PRICE*(1+(IFNULL(tax.TAX_VALUE, 0)/100)))
    FROM
      billing_invoice i
    LEFT JOIN billing_invoice_item it ON it.FK_BILLING_INVOICE = i.ID_BILLING_INVOICE
        LEFT JOIN tax tax ON tax.ID_TAX = it.FK_TAX
    WHERE
        i.STATUS != 2
");

$umsatz['OFFEN'] = $db->fetch_atom("
    SELECT
        SUM(it.QUANTITY*it.PRICE*(1+(IFNULL(tax.TAX_VALUE, 0)/100)))
    FROM
      billing_invoice i
    LEFT JOIN billing_invoice_item it ON it.FK_BILLING_INVOICE = i.ID_BILLING_INVOICE
        LEFT JOIN tax tax ON tax.ID_TAX = it.FK_TAX
    WHERE
        i.STATUS = 0
");


$umsatz['C_OFFEN'] = $db->fetch_atom("
    SELECT
        COUNT(i.ID_BILLING_INVOICE)
    FROM
      billing_invoice i
    WHERE
        i.STATUS = 0
");

$tpl_content->addvars($umsatz);
/*
### hersteller
$ar_hersteller = $db->fetch_table("
	SELECT
		SQL_CALC_FOUND_ROWS
		*
	FROM
		manufacturers
	WHERE
		CONFIRMED = 0
	LIMIT
		5");
$c_hersteller = $db->fetch_atom("SELECT FOUND_ROWS()");
$tpl_content->addvar("neue_hersteller", $c_hersteller);
$tpl_content->addlist("hersteller", $ar_hersteller, "tpl/de/welcome.hersteller.htm");
*/
### transaktionen
/*
$ar_trans = $db->fetch_table("
	SELECT
		ad_sold.*,
		ad_master.PRODUKTNAME,
		seller.NAME AS SELLER,
		seller.ID_USER AS ID_SELLER,
		buyer.NAME AS BUYER,
		buyer.ID_USER AS ID_BUYER
	FROM
		ad_sold
	JOIN
		ad_master ON ad_sold.FK_AD=ad_master.ID_AD_MASTER
	JOIN
		user seller ON ad_sold.FK_USER_VK=seller.ID_USER
	JOIN
		user buyer ON ad_sold.FK_USER = buyer.ID_USER
	ORDER BY
		STAMP_BOUGHT DESC
	LIMIT 5");
$tpl_content->addlist("liste_kaufen", $ar_trans, "tpl/de/welcome.transaktion.htm");
*/
### werbeanfragen
$tpl_content->addvar("advertisements", $db->fetch_atom("
	SELECT
		COUNT(*)
	FROM
		`advertisement_user`
	WHERE
		DONE=1 AND CONFIRMED=0"));
### user
$tpl_content->addvar("freischalten", $db->fetch_atom("
	SELECT
		COUNT(*)
	FROM
		user
	WHERE
		STAT=2 AND CODE IS NULL"));
### user
$tpl_content->addvar("freischalten_ads", $db->fetch_atom("
	SELECT
		COUNT(*)
	FROM
		ad_master
	WHERE
		(STATUS&2=0) AND CONFIRMED=0"));
### user
$tpl_content->addvar("freischalten_events", $db->fetch_atom("
	SELECT
		COUNT(*)
	FROM
		calendar_event
	WHERE
		MODERATED=0"));
### user
$tpl_content->addvar("freischalten_vendors", $db->fetch_atom("
	SELECT
		COUNT(*)
	FROM
		vendor
	WHERE
		STATUS=1 AND MODERATED=0"));
### user
$tpl_content->addvar("upgrade", $db->fetch_atom("
	SELECT
		COUNT(*)
	FROM
		packet_membership_upgrade
	WHERE
		STATUS=0"));
### chats
$tpl_content->addvar("chat", $db->fetch_atom("
	SELECT
		COUNT(*)
	FROM
		chat_message
	WHERE
		APPROVED=0"));
### vendor homepages / domains
$tpl_content->addvar("vendor_homepage", $db->fetch_atom("
	SELECT
		COUNT(*)
	FROM
		vendor_homepage
	WHERE
		ACTIVE=0"));

$tpl_content->addvar("request_for_bank_account_verification", $db->fetch_atom("
	SELECT
		count(1) as count
  	FROM
  		user a
	WHERE
		a.PAYMENT_ADAPTER_DIRECTDB_REQ_FLAG = 1
"));

$tpl_content->addvar("cancel_request_for_invoice_item_or_biilableitem", $db->fetch_atom('
	SELECT count(1) as count
		FROM billing_cancel a
		WHERE a.STATUS = "pending"
'));

$tpl_content->addvar("cancel_request_for_invoice_item_or_biilableitem_shelve", $db->fetch_atom('
	SELECT count(1) as count
		FROM billing_cancel a
		WHERE a.STATUS = "shelve"
'));


require_once $ab_path . 'sys/lib.hdb.php';
$manufacturerDatabaseManagement = ManufacturerDatabaseManagement::getInstance($db);

$tpl_content->addvar("freischaltenMan", $db->fetch_atom("SELECT COUNT(*) FROM manufacturers	WHERE CONFIRMED=0"));

$freischaltenProd = 0;
$freischaltenProdUpdate = 0;
foreach($manufacturerDatabaseManagement->fetchAllProductTypes() as $key => $productType) {
	$freischaltenProd +=  $db->fetch_atom("SELECT COUNT(*) FROM `".$productType['HDB_TABLE']."`	WHERE CONFIRMED=8");
	$freischaltenProdUpdate += $db->fetch_atom("SELECT COUNT(*) FROM `".$productType['HDB_TABLE']."`	WHERE CONFIRMED=1 AND DATA_USER IS NOT NULL AND DATA_USER != ''");
}

$tpl_content->addvar("freischaltenProd", $freischaltenProd);
$tpl_content->addvar("freischaltenProdUpdate", $freischaltenProdUpdate);



$userconuter= $db->fetch1("select count(*) as  usercount_yd from user where STAMP_REG=DATE_ADD(CURDATE(),INTERVAL -1 DAY)");
$tpl_main->addvar('usercount_yd',$userconuter['usercount_yd']);

$userconuter= $db->fetch1("select count(*) as  usercount_td from user where STAMP_REG=CURDATE()");
$tpl_main->addvar('usercount_td',$userconuter['usercount_td']);

$userconuter= $db->fetch1("select count(*) as  usercount from user");
$tpl_main->addvar('usercount',$userconuter['usercount']);

$userconuter= $db->fetch1("select count(*) as useronline from `useronline`");
$tpl_main->addvar('useronline',$userconuter['useronline']);


# newsletter
$userconuter= $db->fetch1("select count(*) as nlcount from `nl_recp`");
$tpl_main->addvar('nlcount',$userconuter['nlcount']);
$nlcount=$userconuter['nlcount'];
$userconuter= $db->fetch1("select count(*) as nlrecp from `nl_recp` where STAMP is NULL");
$tpl_main->addvar('nlrecp',$userconuter['nlrecp']);
$nlrecp=$userconuter['nlrecp'];
$tpl_main->addvar('nlunrecp',$nlcount-$nlrecp);

### anzeigen statistik
/*
$c_file = $ab_path.'cache/admin_welcome_flash.tmp';
$time = @filemtime($c_file);
if(!$time || $time < strtotime('-60 minutes'))
{
	$tpl_tmp = new Template("tpl/de/welcome-flash-ads.htm");
	include "tpl/welcome-flash-ads.php";
	$tmp = $tpl_tmp->process();
	#touch($c_file);
	file_put_contents($c_file, $tmp);
	// Muss www-data gehören!!
	chmod($c_file, 0777);
} else
{
	$tmp = file_get_contents($c_file);
}
$tpl_content->addvar("FLASH_ADS", $tmp);
*/








### NewUser (CACHE)
$c_file = $ab_path.'cache/admin_welcome_stats_new_user.tmp';
$time = @filemtime($c_file);
if(!$time || $time < strtotime('-8 hours'))
{
        include_once( $ab_path.'lib/open-flash-chart.php' );
	    $tpl_tmp = new Template("tpl/de/welcome-flash-ads.htm");
		$tpl_tmp->addvar("FLASHDATA", stats_usercounter());
		$tmp = $tpl_tmp->process();
		file_put_contents($c_file, $tmp);
		// Muss www-data gehören!!
		chmod($c_file, 0777);
} // warenwert
else
{
	$tmp = file_get_contents($c_file);
}
$tpl_content->addvar('NewUser', $tmp);





### verstöße
$n_verstoss = $db->fetch_atom("
	select
		count(*)
	from
		verstoss");
$tpl_content->addvar("verstossliste", $n_verstoss);

### abgebrochene Verkäufe
$n = $db->fetch_atom("
	select
		count(*)
	from
		ad_sold
	where
		STAMP_STORNO IS NOT NULL
		and STAMP_STORNO_OK IS NULL");
$tpl_content->addvar("pending_stornos", $n);


// new release
require_once $ab_path.'sys/lib.version.php';
$latestRelease = EbizTraderVersion::getLatestRelease();

if(EbizTraderVersion::compareCurrentVersionWith($latestRelease['version']) > 0) {
    $tpl_main->addvar('RELEASE_NEW_AVAILABLE', true);
    $tpl_main->addvar('RELEASE_VERSION', $latestRelease['version']);
    $tpl_main->addvar('RELEASE_DATE', $latestRelease['date']);
} else {
    $tpl_main->addvar('RELEASE_NEW_AVAILABLE', false);
}
$tpl_main->addvar('RELEASE_CURRENT_VERSION', EbizTraderVersion::getVersion());


//job lesen
$tpl_content->addvar("newjobs", $db->fetch_atom("select count(*) from job where (STAMPEND > now() OR STAMPEND IS NULL) and ok = 1"));
$tpl_content->addvar("newnews", $db->fetch_atom("select count(*) from news where ok = 1"));


$systeminfo = array ();

//System
if ($nar_systemsettings['SITE']['TEMPLATE_COMMENTS']==1) {
	$systeminfo[]='Verwendete Templates werden als HTML-Kommentar ausgeben!. Dieses kann zu performace Probleme führen <br>
        <a href="index.php?page=options&selectplugin=SITE&typ=TEMPLATE_COMMENTS"><b>hier ändern</b></a>';
}
if ($nar_systemsettings['CACHE']['TEMPLATE_AUTO_REFRESH']==1) {
	$systeminfo[]='Mit Auto Refresh werden Änderungen an Templates automatisch erkannt und entsprechende Cachedateien erneuert. Im Produktiveinsatz sollte hierauf verzichtet werden<br>
        <a href="index.php?page=options&selectplugin=CACHE&typ=TEMPLATE_AUTO_REFRESH"><b>hier ändern</b></a>';
}

// Check for errors
$paramErrors = new Api_Entities_EventParamContainer(array(
	"errors" => array()
));
Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::ADMIN_WELCOME_ERROR, $paramErrors);
$arErrors = $paramErrors->getParam("errors");
if (!empty($systeminfo)) {
	array_unshift($arErrors, "<li>".implode("</li>\n<li>", $systeminfo)."</li>");
}

// Check for errors
$paramTodo = new Api_Entities_EventParamContainer(array(
	"list" => array()
));
Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::ADMIN_WELCOME_TODO, $paramTodo);
$arTodo = $paramTodo->getParam("list");

$tpl_content->addvar("ERROR_LIST", implode("\n", $arErrors));
$tpl_content->addvar("TODO_LIST", implode("\n", $arTodo));

?>