<?php
/* ###VERSIONSBLOCKINLCUDE### */

include_once "sys/lib.nestedsets.php";
include_once "sys/lib.shop_kategorien.php";
require_once $ab_path.'sys/lib.pub_kategorien.php';

$kat = new TreeCategories("kat", 1);
$id_kat_root = $kat->tree_get_parent();
$kat_cache = new CategoriesCache();
$tableTemplate = "default";

$action = ($_POST["action"] ? $_POST["action"] : $ar_params[1]);
$npage = ((int)$ar_params[2] ? $ar_params[2] : 1);
$perpage = ((int)$ar_params[3] ? $ar_params[3] : 15);
$hinweis = (!empty($ar_params[4]) ? $ar_params[4] : false);
if (array_key_exists("hinweis", $_REQUEST)) {
    $hinweis = $_REQUEST["hinweis"];
}
$tpl_content->addvar($hinweis, 1);

if ( isset($_GET['type']) ) {
	if ( $_GET['type'] == "get_statistics_for_products" ) {

	  die(
		  Tools_UserStatistic::getInstance()->gather_and_organize_all_log_data($_GET)
    );
	}
}

$arSelected = array();
$arSelectedHidden = array();
if (empty($_POST) && !array_key_exists("selection", $_GET)) {
    $_SESSION["USER_ADS_SELECTED"] = array();
} else if (array_key_exists("selected", $_POST)) {
    $arSelected = array();
    if (array_key_exists("USER_ADS_SELECTED", $_SESSION)) {
        $arSelected = $_SESSION["USER_ADS_SELECTED"];
    }
    foreach ($_POST["selected"] as $selectedIndex => $selectedId) {
        $arSelected[] = (int)$selectedId;
    }
    $_SESSION["USER_ADS_SELECTED"] = $arSelected;
} else if (array_key_exists("USER_ADS_SELECTED", $_SESSION)) {
    $arSelected = $_SESSION["USER_ADS_SELECTED"];
}
foreach ($arSelected as $selectIndex => $selectAdId) {
    $arSelectedHidden[$selectAdId] = '<input type="hidden" class="hiddenArticleSelected" name="selected[]" value="'.$selectAdId.'" />';
}

switch ($action) {
	case "all":
		// Siehe weiter unten (ca. Zeile 210, vor der MySQL-Query zum auslesen der Anzeigen)
		break;
    case "clear":
        $_SESSION["USER_ADS_SELECTED"] = array();
        die(forward($_SERVER["HTTP_REFERER"]));
    case "enable":
    case "disable":
    case "delete":
    case "extend":
    case "eventAdd":
    case "eventRem":
    case "eventUpdate":
        if (!empty($arSelected)) {
            // Prevent users from selecting foreign ads
            $arSelected = array_keys($db->fetch_nar("SELECT ID_AD_MASTER FROM `ad_master` WHERE ID_AD_MASTER IN (".implode(", ", $arSelected).") AND FK_USER=".$uid));
        }
        // Generate default success URL
        $successUrl = $tpl_content->tpl_uri_action("my-marktplatz,".$ar_params[1].",".$npage.",".$perpage.",sel_".$action);
        if (array_key_exists("SUCCESS", $_REQUEST)) {
            $successUrl = $_REQUEST["SUCCESS"];
        }
        if (!empty($_SERVER["QUERY_STRING"])) {
            $params = "";
            if (strpos($_SERVER["QUERY_STRING"], "&") !== false) {
                $params = "?".substr($_SERVER["QUERY_STRING"], strpos($_SERVER["QUERY_STRING"], "&")+1);
            }
            $successUrl .= $params;
        }
        // Initialize template
        $tpl_content->LoadText("tpl/".$s_lang."/my-marktplatz-action-".$action.".htm");
        // Add basic parameters to template
        $tpl_content->addvar("action", $action);
        $tpl_content->addvar("count", count($arSelected));
        // Process php-code for this action
        include "my-marktplatz-action-".$action.".php";
        // Add further parameters to template
        $tpl_content->addvar("REFERER", $_SERVER["HTTP_REFERER"]);
        $tpl_content->addvar("SUCCESS", $successUrl);
        $tpl_content->addvar("HIDDEN_INPUT_SELECTED", implode("\n", $arSelectedHidden));
        return;
}

if ($action == "deleteSingle") {
    $id_ad = $ar_params[2];
    include_once "sys/lib.ads.php";
    Ad_Marketplace::deleteAdUser($id_ad, $uid);

    die(forward("/my-pages/my-marktplatz,".(!empty($ar_params[3]) ? $ar_params[3] : "").",".(!empty($ar_params[4]) ? $ar_params[4] : "").",,deleted.htm"));
}
if ($action == "activate") {
	$id_ad = $ar_params[2];
	$id_kat = $ar_params[3];
	$kat_table = $db->fetch_atom("SELECT KAT_TABLE FROM `kat` WHERE ID_KAT=".mysql_real_escape_string($id_kat));

	include_once "sys/lib.ads.php";
	AdManagment::Enable($id_ad, $kat_table);

	die(forward("/my-pages/my-marktplatz,disabled,,,activated.htm"));
}
if ($action == "deactivate") {
	$id_ad = $ar_params[2];
	$id_kat = $ar_params[3];
	$kat_table = $db->fetch_atom("SELECT KAT_TABLE FROM `kat` WHERE ID_KAT=".mysql_real_escape_string($id_kat));

	include_once "sys/lib.ads.php";
	AdManagment::Disable($id_ad, $kat_table);

	die(forward("/my-pages/my-marktplatz,active,,,deakt.htm"));
}
if ($action == "toggleComments") {
	$ar_result = array('success' => false);
	$id_ad = (int)$_POST["idAd"];
	$ar_ad = $db->fetch1("SELECT FK_USER, ALLOW_COMMENTS FROM `ad_master` WHERE ID_AD_MASTER=".$id_ad);
	if ($uid == $ar_ad["FK_USER"]) {
		// Get new state (toggle)
		$state = ($ar_ad["ALLOW_COMMENTS"] > 0 ? 0 : 1);
		$db->querynow("UPDATE `ad_master` SET ALLOW_COMMENTS=".$state." WHERE ID_AD_MASTER=".$id_ad);
		$ar_result["success"] = true;
		$ar_result["enabled"] = $state;
	}
	header('Content-type: application/json');
	die(json_encode($ar_result));
}

$limit = ($perpage*$npage)-$perpage;
$where = '';
$str_where = ',';

$hack = explode(";", $ar_params[1]);
for($i=0; $i<count($hack); $i+=2) {
	$_REQUEST[$hack[$i]] = $hack[$i+1];
}

$whereStatus = array();
$where = array();
$joins = array();

// Base filter
$filter = (!empty($ar_params[1]) ? $ar_params[1] : $action);
switch ($filter) {
    case 'active':
    default:
        $where[] = $whereStatus[] = "(am.STATUS&3 = 1 OR (am.STATUS&3 = 0 AND am.CONFIRMED=0)) AND (am.DELETED=0)";
        $filter = "active";
        break;
    case 'timeout':
        $where[] = $whereStatus[] = "am.STATUS&3 = 1 AND (am.DELETED=0) AND (DATEDIFF(STAMP_END, NOW())<14)";
        break;
    case 'declined':
        $where[] = $whereStatus[] = "am.CONFIRMED=2 AND (am.DELETED=0)";
        break;
    case 'disabled':
        $where[] = $whereStatus[] = "am.STATUS&1 = 0 AND (am.DELETED=0) AND (STAMP_END IS NOT NULL OR am.CONFIRMED=0)";
        break;
}
$tpl_content->addvar("FILTER_STATUS", $filter);
$tpl_content->addvar("FILTER_STATUS_".strtoupper($filter), 1);
// Search for id
if ($_REQUEST['ID_AD_MASTER'] > 0) {
    $id = (int)$_REQUEST['ID_AD_MASTER'];
    $where[] = "am.ID_AD_MASTER=".mysql_real_escape_string($id);
    $tpl_content->addvar("ID_AD_MASTER", $id);
}
// Search for sale options
if ($_REQUEST['VERKAUFSOPTIONEN'] > 0) {
    $searchVerkaufsoptionen = (int)$_REQUEST['VERKAUFSOPTIONEN'];
    $where[] = "am.VERKAUFSOPTIONEN=".mysql_real_escape_string($searchVerkaufsoptionen);
    $tpl_content->addvar("VERKAUFSOPTIONEN", $searchVerkaufsoptionen);
}
// Search for id
if ($_REQUEST['FK_IMPORT_SOURCE'] > 0) {
    $searchImportSource = (int)$_REQUEST['FK_IMPORT_SOURCE'];
    $where[] = "am.IMPORT_SOURCE=".mysql_real_escape_string($searchImportSource);
    $tpl_content->addvar("FK_IMPORT_SOURCE", $searchImportSource);
}
// Search for name (product)
if (!empty($_REQUEST['PRODUKTNAME'])) {
    $name = trim($_REQUEST['PRODUKTNAME']);
    if(!empty($name)) {
        $where[] = "am.PRODUKTNAME LIKE '%".mysql_real_escape_string($name)."%'";
        $tpl_content->addvar("PRODUKTNAME", $name);
    }
}
// Search for manufacturer
if (!empty($_REQUEST["HERSTELLER"])) {
    $manufacturer = trim($_REQUEST['HERSTELLER']);
    if(!empty($manufacturer)) {
		$joins[] = "LEFT JOIN `manufacturers` m ON m.ID_MAN=am.FK_MAN";
        $where[] = "m.NAME LIKE '%".mysql_real_escape_string($manufacturer)."%'";
        $tpl_content->addvar("HERSTELLER", $manufacturer);
    }
}
// Search for category
if ($_REQUEST["FK_KAT"] > 0) {
    $searchKatId = (int)$_REQUEST['FK_KAT'];
    $row_kat = $kat->element_read($searchKatId);
    if($row_kat) {
        $ids_kats = $db->fetch_nar("
				SELECT ID_KAT
				  FROM `kat`
				WHERE
				  (LFT >= ".$row_kat["LFT"].") AND
				  (RGT <= ".$row_kat["RGT"].") AND
				  (ROOT = ".$row_kat["ROOT"].")
				");
        $ids_kats = "(".implode(",",array_keys($ids_kats)).")";
        $where[] = "am.FK_KAT IN ".$ids_kats;
    }
    $tpl_content->addvar("SEARCH_FK_KAT", $searchKatId);
}
$where = (count($where) ? "\nAND ".implode("\nAND ", $where) : '');
$str_where = "ID_AD_MASTER;".$id.";PRODUKTNAME;".urlencode($name).",";

$arOrderFields = array(
    "STAMP" => "am.CONFIRMED ASC, am.STAMP_START",
    "B_TOP" => "am.B_TOP_LIST"
);
// Sort field and order
$order = (!empty($_REQUEST["SORT"]) ? $_REQUEST["SORT"] : "STAMP:DESC");
list($orderField, $orderDir) = explode(":", $order);
if (array_key_exists($orderField, $arOrderFields)) {
    $order = $arOrderFields[$orderField]." ".(strtoupper($orderDir) == "ASC" ? "ASC" : "DESC");
} else {
    $orderField = "STAMP";
    $orderDir = "DESC";
    $order = "am.CONFIRMED ASC, am.STAMP_START DESC";
}
$tpl_content->addvar("ORDER_BY_".strtoupper($orderField)."_".strtoupper($orderDir), 1);

if ($action == "all") {
	$_SESSION["USER_ADS_SELECTED"] = array_keys($db->fetch_nar($q = "
		SELECT am.ID_AD_MASTER AS ID_ARTIKEL
	    	FROM ad_master am
			".implode("", $joins)."
			WHERE am.FK_USER=".$uid." ".$where."
	    	GROUP BY am.ID_AD_MASTER"));
	$urlTarget = $_SERVER["HTTP_REFERER"];
	if (strpos($urlTarget, "?") !== false) {
		if (strpos($urlTarget, "selection=") === false) {
            $urlTarget .= "&selection=keep";
        }
	} else {
		$urlTarget .= "?selection=keep";
	}
	die(forward($urlTarget));
}

// Categories
$tpl_content->addvar('marketplace_categories', $tpl_content->process_text($kat_cache->cacheKatSelectOptions(1)));

// Import sources
$arImportSources = $db->fetch_table("
    SELECT am.IMPORT_SOURCE, COUNT(*) AS AD_COUNT, s.SOURCE_NAME FROM `ad_master` am
    JOIN `import_source` s ON s.ID_IMPORT_SOURCE=am.IMPORT_SOURCE
    WHERE am.IMPORT_SOURCE IS NOT NULL AND am.FK_USER=".(int)$uid.(!empty($whereStatus) ? " AND ".implode(" AND ", $whereStatus) : "")."
    GROUP BY am.IMPORT_SOURCE");
$tpl_content->addlist('importSources', $arImportSources, 'tpl/'.$s_lang.'/my-marktplatz.importSource.htm');

$ads = $db->fetch_table($q = "
	SELECT
			SQL_CALC_FOUND_ROWS
    		am.*,
    		am.ID_AD_MASTER AS ID_ARTIKEL,
    		LEFT(am.BESCHREIBUNG, 250) AS DSC,
    		DATEDIFF(am.STAMP_END, NOW()) as TIMELEFT,
    		DATEDIFF(NOW(), am.STAMP_END) as TIMEOUT_DAYS,
    		DATEDIFF(am.STAMP_END, am.STAMP_DEACTIVATE) as TIME_LEFT,
    		(SELECT s.V1 FROM string_kat s where s.S_TABLE='kat' and s.FK=am.FK_KAT and s.BF_LANG=if(".$langval." & ".$langval.", ".$langval.", 1 << floor(log(".$langval."+0.5)/log(2)))) as KAT,
    		(SELECT i.SRC_THUMB FROM `ad_images` i WHERE i.IS_DEFAULT=1 AND i.FK_AD=am.ID_AD_MASTER LIMIT 1) as SRC_THUMB,
			(SELECT i.SRC FROM `ad_images` i WHERE i.IS_DEFAULT=1 AND i.FK_AD=am.ID_AD_MASTER LIMIT 1) as SRC_FULL,
        	(SELECT NAME FROM manufacturers WHERE ID_MAN = am.FK_MAN) as MANUFACTURER,
        	(SELECT AMOUNT FROM `comment_stats` WHERE `TABLE`='ad_master' AND FK=am.ID_AD_MASTER) as COMMENTS
    	FROM
    		ad_master am
		".implode("", $joins)."
		WHERE
    		am.FK_USER=".$uid." ".$where."
    	ORDER BY
			".$order."
    	LIMIT
    		".$limit.", ".$perpage);

// Seitenzähler hinzufügen
$all = $db->fetch_atom("
  		SELECT
  			FOUND_ROWS()");


foreach ($ads as $curAdIndex => $curAd) {
    $curAdId = $curAd["ID_ARTIKEL"];
    if (in_array($curAdId, $arSelected)) {
        $ads[$curAdIndex]["IS_SELECTED"] = true;
        unset($arSelectedHidden[$curAdId]);
    } else {
        $ads[$curAdIndex]["IS_SELECTED"] = false;
    }
	$adReminderCount = $db->fetch_atom(
		"SELECT COUNT(*) as a 
			FROM watchlist 
			WHERE FK_REF = '".mysql_escape_string($curAd["ID_AD_MASTER"])."' 
			AND FK_REF_TYPE = 'ad_master'"
	);
    $ads[$curAdIndex]["AD_REMINDER_COUNT"] = $adReminderCount;
    if ($curAd["VERKAUFSOPTIONEN"] == 5) {
        $ads[$curAdIndex]["BID_COUNT"] = $db->fetch_atom("SELECT COUNT(DISTINCT FK_NEGOTIATION) FROM `trade` WHERE FK_AD_REQUEST=".(int)$curAdId);
    }
}
$tpl_content->addvar("HIDDEN_INPUT_SELECTED", implode("\n", $arSelectedHidden));
$tpl_content->addvar("DATE_START",date("Y-m-d"));
$tpl_content->addvar("DATE_END",date("Y-m-d"));

#echo ht(dump($lastresult));
$tplBaseName = ($action == "disabled" ? "my-marktplatz-disabled" : "my-marktplatz");
$tpl_content->addlist("liste", $ads, "tpl/".$s_lang."/".$tplBaseName.".row.".$tableTemplate.".htm");

$additionalParams = "";
$mark = "?";

if ($id) {
    $additionalParams .= $mark."ID_AD_MASTER=".(int)$id;
    $mark = "&";
}
if ($searchImportSource) {
    $additionalParams .= $mark."FK_IMPORT_SOURCE=".(int)$searchImportSource;
    $mark = "&";
}
if ($name) {
    $additionalParams .= $mark."PRODUKTNAME=".$name;
    $mark = "&";
}
if ($manufacturer) {
    $additionalParams .= $mark."HERSTELLER=".$manufacturer;
    $mark = "&";
}
if ($searchKatId) {
    $additionalParams .= $mark."FK_KAT=".$searchKatId;
    $mark = "&";
}
if (!empty($_REQUEST["SORT"])) {
    $additionalParams .= $mark."SORT=".rawurlencode($_REQUEST["SORT"]);
    $mark = "&";
}

$tpl_content->addvar("pager", htm_browse_extended($all, $npage, "my-marktplatz,".$action.",{PAGE}", $perpage, 5, $additionalParams));

/** Suchen und Ersetzen TEST */

if ($_REQUEST["do"] == "Suchen und Ersetzen") {

	$ids = array_keys($db->fetch_nar($q = "SELECT am.ID_AD_MASTER FROM `ad_master` am ".
			"WHERE am.FK_USER=".$uid." AND am.STATUS&3 = 1 ".$where));

	$tpl_content->addvar("SEARCHREPLACE_AD_ID", json_encode($ids));


	$tpl_content->addvar("SHOW_REPLACE", 1);
}

$tpl_content->addvar("ACTION", $action);
$tpl_content->addvar("NPAGE", $npage);
$tpl_content->addvar("PERPAGE", $perpage);
$tpl_content->addvar("ADDITIONAL_PARAMS", $additionalParams);
$tpl_content->addvar("ALLOW_COMMENTS_AD", $nar_systemsettings['MARKTPLATZ']['ALLOW_COMMENTS_AD']);
$tpl_content->addvar("SETTINGS_USE_HERSTELLER_DB", $nar_systemsettings['MARKTPLATZ']['USE_PRODUCT_DB']);
$tpl_content->addvar("SETTINGS_MODERATE_ADS", $nar_systemsettings['MARKTPLATZ']['MODERATE_ADS']);
$tpl_content->addvar("TABLE_TEMPLATE", $tableTemplate);

?>
