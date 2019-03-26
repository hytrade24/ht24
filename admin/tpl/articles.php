<?php
/* ###VERSIONSBLOCKINLCUDE### */

global $id_kat;

//////////////// IMENSO //////////////////////
//include_once $ab_path.'sys/MicrosoftTranslator.php';
include_once $ab_path."sys/GoogleTranslator.php";

function hide_childs(&$row) {
    global $id_kat, $id_root_kat, $kat;
    $row["kids"] = $kat->element_has_childs($row["ID_KAT"]);
    if ($row["PARENT"] != $id_kat) {
      $row["HIDDEN"] = 1;
    }
    $row["KAT_ROOT"] = $id_root_kat;
}
/*
function hide_childs(&$row) {
	global $db, $id_kat, $id_root_kat, $kats_open;
	$row["level"]--;
	if (($row["PARENT"] != $id_kat) && !in_array($row["PARENT"], $kats_open)) {
		$row["HIDDEN"] = 1;
	}
	if ($id_kat == $row["ID_KAT"]) {
		$row["ACTIVE"] = 1;
	}
	if ($row["level"] == 0) {
		// Root category
		$id_root_kat = $row["ID_KAT"];
	}
	$row["KAT_ROOT"] = $id_root_kat;
	$row["ARTICLE_COUNT"] = $db->fetch_atom("SELECT count(*) FROM `ad_master` WHERE (STATUS&3)=1 AND FK_KAT=".$row["ID_KAT"]);
}
*/

//$SILENCE = false;

require_once "sys/lib.nestedsets.php";
require_once "sys/lib.shop_kategorien.php";
require_once "../sys/lib.pub_kategorien.php";

function count_articles(&$row) {
	global $db;

	$kat_childs = $db->fetch_nar("SELECT ID_KAT, 1 as VAL FROM `kat` WHERE LFT>=".$row["LFT"]." AND RGT<=".$row["RGT"]);
	$row["COUNT_CHILDS"] = $db->fetch_atom("SELECT count(*) FROM `".$row["KAT_TABLE"]."` WHERE (STATUS&3)=1 AND FK_KAT IN (".implode(",", array_keys($kat_childs)).")");
	$row["COUNT"] = $db->fetch_atom("SELECT count(*) FROM `".$row["KAT_TABLE"]."` WHERE (STATUS&3)=1 AND FK_KAT=".$row["ID_KAT"]);
}

if (!empty($_REQUEST["do"])) {
	if ($_REQUEST["do"] == "quickedit") {
		$id_ad = $_POST["ID_AD"];
		$ad_table = $_POST["AD_TABLE"];
		$error = array();
		// Error checking
		if (empty($_POST["PRODUKTNAME"])) {
			$error[] = "Kein Anzeigen-Titel angegeben!";
		}
		$bf_top = 0;
		if (is_array($_POST["B_TOP"])) {
			foreach ($_POST["B_TOP"] as $index => $value)
				$bf_top += $value;
		}
		// Return result
		header('Content-type: application/json');
		if (empty($error)) {
			$query_fields = "PRODUKTNAME='".mysql_escape_string($_POST["PRODUKTNAME"])."', ".
				"STAMP_END='".$_POST["STAMP_END_y"]."-".$_POST["STAMP_END_m"]."-".$_POST["STAMP_END_d"]." 23:59:59'";
			$query_product = "UPDATE `".mysql_escape_string($ad_table)."` SET ".$query_fields." WHERE ID_".strtoupper($ad_table)."=".$id_ad;

			//////////////// IMENSO //////////////////////
			$PRODUKTNAME_EN =  translateText( mysql_escape_string($_POST["PRODUKTNAME"]));
			$query_fields = $query_fields.", PRODUKTNAME_EN='".$PRODUKTNAME_EN."'";
			$query_master = "UPDATE `ad_master` SET ".$query_fields.", B_TOP=".(int)$bf_top.",
					B_TOP_LIST=".Rest_MarketplaceAds::getTopValueByFlags($bf_top)." WHERE ID_AD_MASTER=".$id_ad;
			if (($db->querynow($query_product) !== false) && ($db->querynow($query_master) !== false)) {
				die(json_encode( array("okay" => true) ));
			} else {
				$error_str = "Datenbankfehler beim Speichern! Debug:\n\n".$query_master."\n\n".$query_product;
				die(json_encode( array("okay" => true, "error" => $error_str) ));
			}
		} else {
			$error_str = "Fehler beim Speichern:\n\n".implode("\n", $error);
			die(json_encode( array("okay" => false, "error" => $error_str) ));
		}
	}
}

global $kat;

$perpage = 40; // Elemente pro Seite
$limit = ((($_REQUEST['npage'] ? $_REQUEST['npage'] : $_REQUEST['npage']=1)-1)*$perpage);

$kat = new TreeCategories("kat", 1);
$id_kat = ($_REQUEST["ID_KAT"] ? (int)$_REQUEST["ID_KAT"] : 0);
$id_kat_root = $kat->tree_get_parent();

$kat_cache = new CategoriesCache();

$tpl_content->addvar("ID_KAT", ($_REQUEST["ID_KAT"] != $id_kat_root ? (int)$_REQUEST["ID_KAT"] : 0));

// Erfolgsmeldung ausgeben
if ($_REQUEST["delete_ok"])
$tpl_content->addvar("delete_ok", 1);
if ($_REQUEST["activate_ok"])
$tpl_content->addvar("activate_ok", 1);
if ($_REQUEST["deactivate_ok"])
    $tpl_content->addvar("deactivate_ok", 1);
if ($_REQUEST["cache_ok"])
$tpl_content->addvar("cache_ok", 1);
if (!empty($_REQUEST['done'])) {
    $tpl_content->addvar("done", 1);
    $tpl_content->addvar("done_".$_REQUEST['done'], 1);
}

// Aktion: Anzeige neu cachen
if (isset($_REQUEST["cache"])) {
	$id_article = (int)$_REQUEST["cache"];
	$kat_table = $db->fetch_atom("SELECT KAT_TABLE FROM `kat` WHERE ID_KAT=".$id_kat);

	$db->querynow("UPDATE `ad_temp` SET DONE=0 WHERE FK_AD=".$id_article." AND `TABLE`='".mysql_escape_string($kat_table)."'");

	die(forward("index.php?page=articles&search=".$_REQUEST["search"]."&npage=".(int)$_REQUEST['npage']."&cache_ok=1".($_REQUEST["show_kat"] ? "&ID_KAT=".$_REQUEST["show_kat"] : "")));
}
// Aktion: Anzeige löschen
if (isset($_REQUEST["delete"])) {
	$id_article = (int)$_REQUEST["delete"];
	$kat_table = $db->fetch_atom("SELECT KAT_TABLE FROM `kat` WHERE ID_KAT=".$id_kat);

	include_once "../sys/lib.ads.php";
    Ad_Marketplace::deleteAd($id_article, $kat_table);
	die(forward("index.php?page=articles&search=".$_REQUEST["search"]."&npage=".(int)$_REQUEST['npage']."&delete_ok=1".($_REQUEST["show_kat"] ? "&ID_KAT=".$_REQUEST["show_kat"] : "")));
}
if($_REQUEST['lock']) {
	$id_article = (int)$_REQUEST["lock"];
	$db->querynow("
		UPDATE
			ad_master
		SET
			ADMIN_STAT=1
		WHERE
			ID_AD_MASTER=".$id_article);
	$_REQUEST["deactivate"] = $id_article;
}
// Aktion: Anzeige deaktivieren
if (isset($_REQUEST["deactivate"])) {
    $id_article = (int)$_REQUEST["deactivate"];
    $kat_table = $db->fetch_atom("SELECT KAT_TABLE FROM `kat` WHERE ID_KAT=".$id_kat);

    include_once "../sys/lib.ads.php";
    AdManagment::Disable($id_article, $kat_table);

    die(forward("index.php?page=articles&search=".$_REQUEST["search"]."&npage=".(int)$_REQUEST['npage']."&deactivate_ok=1".($_REQUEST["show_kat"] ? "&ID_KAT=".$_REQUEST["show_kat"] : "")));
}
// Aktion: Anzeige freischalten/bestätigen
if (isset($_REQUEST["confirm"])) {
    $id_article = (int)$_REQUEST["confirm"];
    $kat_table = $db->fetch_atom("SELECT KAT_TABLE FROM `kat` WHERE ID_KAT=".$id_kat);
    include_once "../sys/lib.ads.php";
    AdManagment::Unlock($id_article, $kat_table);

    die(forward("index.php?page=articles&search=".$_REQUEST["search"]."&npage=".(int)$_REQUEST['npage']."&done=confirm".($_REQUEST["show_kat"] ? "&ID_KAT=".$_REQUEST["show_kat"] : "")));
}
// Aktion: Anzeige freischalten/bestätigen
if (isset($_REQUEST["confirm_user"])) {
    $id_user = (int)$_REQUEST["confirm_user"];
    $arAds = $db->fetch_nar("SELECT ID_AD_MASTER, AD_TABLE FROM `ad_master` WHERE FK_USER=".$id_user." AND CONFIRMED=0");
    include_once "../sys/lib.ads.php";
    foreach ($arAds as $id_article => $kat_table) {
        AdManagment::Unlock($id_article, $kat_table);
    }
    die(forward("index.php?page=articles&search=".$_REQUEST["search"]."&npage=".(int)$_REQUEST['npage']."&done=confirm".($_REQUEST["show_kat"] ? "&ID_KAT=".$_REQUEST["show_kat"] : "")));
}
// Aktion: Anzeige "ablehnen"
if (isset($_REQUEST["decline"])) {
    $id_article = (int)$_REQUEST["decline"];
    $kat_table = $db->fetch_atom("SELECT KAT_TABLE FROM `kat` WHERE ID_KAT=".$id_kat);
    include_once "../sys/lib.ads.php";
    AdManagment::UnlockDecline($id_article, $kat_table, $_REQUEST["REASON"]);

    die(forward("index.php?page=articles&search=".$_REQUEST["search"]."&npage=".(int)$_REQUEST['npage']."&done=confirm".($_REQUEST["show_kat"] ? "&ID_KAT=".$_REQUEST["show_kat"] : "")));
}
if (isset($_REQUEST["decline_user"])) {
    AdManagment::UnlockDeclineUser((int)$_REQUEST["decline_user"], $_REQUEST["REASON"]);

    die(forward("index.php?page=articles&search=".$_REQUEST["search"]."&npage=".(int)$_REQUEST['npage']."&done=confirm".($_REQUEST["show_kat"] ? "&ID_KAT=".$_REQUEST["show_kat"] : "")));
}
if($_REQUEST['unlock']) {
	$id_article = (int)$_REQUEST["unlock"];
	$db->querynow("
		UPDATE
			ad_master
		SET
			ADMIN_STAT=NULL
		WHERE
			ID_AD_MASTER=".$id_article);
	$_REQUEST["activate"] = $id_article;
}
// Aktion: Anzeige aktivieren
if (isset($_REQUEST["activate"])) {
	$id_article = (int)$_REQUEST["activate"];
	$kat_table = $db->fetch_atom("SELECT KAT_TABLE FROM `kat` WHERE ID_KAT=".$id_kat);

	include_once "../sys/lib.ads.php";
	AdManagment::Enable($id_article, $kat_table);

	die(forward("index.php?page=articles&search=".$_REQUEST["search"]."&npage=".(int)$_REQUEST['npage']."&activate_ok=1".($_REQUEST["show_kat"] ? "&ID_KAT=".$_REQUEST["show_kat"] : "")));
}



if ($id_kat > 1) {
	// Kategorie-Baum auslesen
	global $kats_open;
	$kats = $kat_cache->kats_read($id_kat);
	$kats_open = array();

	$path = $kat_cache->kats_read_path($id_kat);
	if (count($path) > 1) {
		foreach ($path as $index => $path_dat) {
			$kats_open[] = $path_dat["ID_KAT"];
		}
	}

	$kat_ids = array();
	$kat_ids[] = $id_kat;

	if (!empty($kats)) {
		foreach ($kats as $index => $kat_data) {
			$kat_ids[] = (int)$kat_data["ID_KAT"];
		}
	}
	// Kategorie-Baum darstellen
	$ar_active = $kat->element_read($id_kat);
	$id_parent = $kat->tree_get_parent($id_kat);
	$ar_parent = $kat->element_read($id_parent);
	$ar_tree = $kat->element_get_childs($id_parent);
	$tpl_content->addvar("ACTIVE_ID", $id_kat);
	$tpl_content->addvar("ACTIVE_NAME", $ar_active["V1"]);
	$tpl_content->addvar("PARENT_ID", $id_parent);
	$tpl_content->addvar("PARENT_NAME", $ar_parent["V1"]);
	$tpl_content->addlist("kat_selector", $ar_tree, "tpl/de/articles.row_kat.htm", 'hide_childs');
} else {
	$ar_tree = $kat->element_get_childs($id_kat_root);
	$tpl_content->addlist("kat_selector", $ar_tree, "tpl/de/articles.row_kat.htm", 'hide_childs');
}

// Alt
//$baum = tree_show_nested($ar_tree, 'tpl/de/cache_marktplatz_list.row.htm', 'hide_childs', $actions, (int)$id_kat, 'ID_KAT');
//$tpl_content->addvar("kat_selector", $baum);

$orders = array
(
array
(
          'text' => 'Beginndatum',
          'value' => 'a.STAMP_START',
),
array
(
          'text' => 'Enddatum',
          'value' => 'a.STAMP_END',
),
array
(
          'text' => 'Benutzer',
          'value' => 'a.USERNAME',
),
array
(
          'text' => 'Anzeigentitel',
          'value' => 'a.PRODUKTNAME',
),
);

$orders_html = array();
for($i=0; $i<count($orders); $i++)
{
	$selected = ($_REQUEST['ORDERBY'] == $orders[$i]['value'] ? ' selected' : '');
	$orders_html[] = '<option value="'.stdHtmlentities($orders[$i]['value']).'"'.$selected.'>'.stdHtmlentities($orders[$i]['text']).'</option>';
}
$tpl_content->addvar('orders', implode("\n", $orders_html));
unset($orders_html, $orders);

$sort_by = ($_REQUEST['ORDERBY'] ? $_REQUEST['ORDERBY'] : "a.STAMP_START");
$sort_dir = (array_key_exists('UPDOWN', $_REQUEST) ? ($_REQUEST['UPDOWN'] ? "DESC" : "ASC") : "DESC");

$searchStatus = ($_REQUEST['STATUS'] ?: 127 - 16 - 64);	// No deleted or unconfirmed ads by default
$searchTop = 3;
$search_hash = null;

$where = array();
$where[] = "a.CRON_DONE=1";
if (!empty($_POST) || ($_GET["quicksearch"] == 1)) {
	$lifetime = time()+(60*60*24);
	$search_hash = md5(microtime());
	$search_hash = substr($search_hash, 0, 15);

	$ar_search = array
	(
        'QUERY' => $search_hash,
        'LIFETIME' => date("Y-m-d H:i:s", $lifetime),
        'S_STRING' => serialize($_REQUEST),
        'S_WHERE' => ""
        );
        $id = $db->update("searchstring", $ar_search);
        $search_data = $_REQUEST;
}
if (!empty($_REQUEST["search"])) {
	$search_hash = $_REQUEST["search"];
	$search_data = unserialize($db->fetch_atom("SELECT S_STRING FROM `searchstring` WHERE `QUERY`='".mysql_escape_string($search_hash)."'"));
}

// STAT fix (warum ist "STAT" im template 1 wenn nicht gesetzt?!)
$search_data["STAT"] = ($search_data["STAT"] ? $search_data["STAT"] : 0);

$tpl_content->addvar("SEARCH_HASH", $search_hash);
$tpl_content->addvars($search_data);

if ($search_hash !== null) {
	$tpl_content->addvar("SEARCH_RESULT", 1);
} else {
	$tpl_content->addvar("SHOW_PIE", 1);
}

if (!empty($search_data)) {
	// Suche gestartet

	$tpl_content->addvars($search_data);
	if (!empty($search_data['TITLE'])) {
		$where[] = "a.PRODUKTNAME LIKE '".$search_data['TITLE']."%'";
	}
	if ($search_data['FK_AUTOR'] > 0) {
		$where[] = "a.FK_USER=".(int)$search_data['FK_AUTOR'];
	}
	if (!empty($search_data["STATUS"])) {
		$searchStatus = array_sum($search_data["STATUS"]);
	}
	if (!empty($search_data["B_TOP"])) {
		if (!in_array(1, $search_data["B_TOP"])) {
			// No top ads
			$where[] = "a.B_TOP=0";
		} else if (!in_array(2, $search_data["B_TOP"])) {
			// No regular ads
			$where[] = "a.B_TOP>0";
		}
	}
	
	/*
	if ($search_data['STAT'] > 0) {
		if ($search_data['STAT'] == 1) {
            $where[] = "(a.STATUS&3)=1 AND a.DELETED=0";
        } else if ($search_data['STAT'] == 2) {
			$where[] = "(a.STATUS&3)=2 AND a.DELETED=0";
		} else if ($search_data['STAT'] == 3) {
            $where[] = "a.DELETED=1";
        } else if ($search_data['STAT'] == 4) {
            $where[] = "(a.STATUS&2)=0 AND a.CONFIRMED=0";
        } else {
            $where[] = "(a.STATUS&3)=3 AND a.DELETED=0";
        }
	} else {
        $where[] = "a.DELETED=0";
    }
	if($search_data['B_TOP'])
	{
		$where[] = 'a.B_TOP>0';
	}
	*/
	if (array_key_exists('ORDERBY', $search_data)) {
		$sort_by = $search_data['ORDERBY'];
	}
	if (array_key_exists('UPDOWN', $search_data)) {
		$sort_dir = ($search_data['UPDOWN'] ? "DESC" : "ASC");
	}
}

// Ensure there can be any results
if (($searchStatus & 31) == 0) {
	// Active / Timed out / Deactivated / Sold / Deleted
	$searchStatus |= 1;
}
if (($searchStatus & 96) == 0) {
	// Confirmed / Unconfirmed
	$searchStatus |= 32;
}
// Status filter
$arStatusAllowed = array();
if (($searchStatus & 1) == 1) {
	// Active ads
	$arStatusAllowed[] = 1;
}
if ((($searchStatus & 2) == 2) || (($searchStatus & 4) == 4)) {
	// Disabled/timed out ads
	$arStatusAllowed[] = 2;
}
if (($searchStatus & 8) == 8) {
	// Sold ads
	$arStatusAllowed[] = 0;
}
if (!empty($arStatusAllowed)) {
	$where[] = "a.STATUS IN (".implode(", ", $arStatusAllowed).")";
}
if (($searchStatus & 2) == 0) {
	// No disabled ads
	$where[] = "a.STAMP_DEACTIVATE IS NULL";
} else if (($searchStatus & 4) == 0) {
	// No timed out ads, but disabled ones
	$where[] = "a.STAMP_DEACTIVATE IS NOT NULL";
}
if (($searchStatus & 16) == 0) {
	// No deleted ads
	$where[] = "a.DELETED=0";
}
if (($searchStatus & 32) == 0) {
	// No confirmed ads
	$where[] = "a.CONFIRMED=0";
} else if (($searchStatus & 64) == 0) {
	// No unconfirmed ads
	$where[] = "a.CONFIRMED=1";
}

$tpl_content->addvar("UPDOWN", ($sort_dir == "DESC" ? 1 : 0));

$tpl_content->addvar("STATUS", $searchStatus);
$tpl_content->addvar("B_TOP", $searchTop);

if (($id_kat != $id_kat_root) && !empty($kat_ids)) {
	$where[] = "a.FK_KAT in (".implode(",", $kat_ids).")";
}

$where = implode(" AND ", $where);
if (!empty($search_data['ART_ID'])) {
	$where = "a.ID_AD_MASTER=".$search_data['ART_ID'];
}

$all = $db->fetch_atom("SELECT count(a.ID_AD_MASTER) FROM `ad_master` a ".($where ? " WHERE ".$where : ""));
if (($_REQUEST['npage'] > 0) && (($_REQUEST['npage']-1) * $perpage >= $all) && ($all > 0)) {
    $pageLast = floor(($all - 1) / $perpage) + 1;
    die(forward("index.php?page=articles&ID_KAT=".$id_kat."&search=".$search_hash."&npage=".$pageLast."&done=".$_REQUEST['done']));
}
#echo "SELECT count(*) FROM `ad_master` a ".($where ? " WHERE ".$where : "");
$query = "
  	SELECT
  		a.*,
		DATEDIFF(NOW(), a.STAMP_START) as RUNTIME,
  		u.NAME as USERNAME,
		IF(a.FK_USER = 1, '', MD5(u.PASS)) as SIG_OWNER,
		DATEDIFF(a.STAMP_END, NOW()) as TIMEOUT_DAYS,
  		(SELECT count(*) FROM `ad_images` im WHERE im.FK_AD=a.ID_AD_MASTER) as IMAGE_COUNT,
  		(SELECT count(*) FROM `ad_upload` up WHERE up.FK_AD=a.ID_AD_MASTER) as UPLOAD_COUNT,
  		(SELECT count(*) FROM `ad_sold` so WHERE so.FK_AD=a.ID_AD_MASTER) as TRANSACTION_COUNT,
  		(SELECT k.V1 FROM `string_kat` k WHERE k.FK=a.FK_KAT AND BF_LANG=128 AND S_TABLE='kat') as KAT_NAME,
  		'".$search_hash."' as `search`,
  		".(int)$_REQUEST['npage']." as `npage`,
  		(SELECT ve.ID_VERSTOSS FROM verstoss ve WHERE ve.FK_AD = a.ID_AD_MASTER LIMIT 1) as ID_VERSTOSS
	FROM `ad_master` a
	LEFT JOIN `user` u ON u.ID_USER = a.FK_USER
	WHERE ".$where."
	ORDER BY ".$sort_by." ".$sort_dir."
	LIMIT ".$limit.",".$perpage;
#die($query);
$articles = $db->fetch_table($query);

foreach($articles as $key => $article) {
	if($article['ID_VERSTOSS']) {
		$articles[$key] = array_merge($article, $db->fetch1("SELECT ve.GRUND, ve.STAMP AS STAMP_VERSTOSS FROM verstoss ve WHERE ve.ID_VERSTOSS = '".(int)$article['ID_VERSTOSS']."'"));
	}
	//////////////// IMENSO //////////////////////
	$adTable = $article["AD_TABLE"];
    $articles[$key]['PRODUKTNAME'] = $db->fetch_atom("SELECT PRODUKTNAME FROM 
        hdb_table_$adTable WHERE ID_HDB_TABLE_$adTable=".(int)$article["FK_PRODUCT"]);
}

$tpl_content->addlist('liste', $articles, 'tpl/de/articles.row.htm');
$tpl_content->addvar("npage", $_REQUEST['npage']);
$tpl_content->addvar("pager", htm_browse($all, $_REQUEST['npage'], "index.php?page=articles&ID_KAT=".$id_kat."&search=".$search_hash."&npage=", $perpage));

// Moderate ads
$tpl_content->addvar('MODERATE_ADS', $nar_systemsettings['MARKTPLATZ']['MODERATE_ADS']);

?>