<?php
/* ###VERSIONSBLOCKINLCUDE### */

include_once "sys/lib.nestedsets.php";
include_once "sys/lib.shop_kategorien.php";
include_once "sys/lib.ad_agent.php";

function hide_childs(&$row) {
	global $id_kat, $id_root_kat;
	$row["level"]--;
	if ($row["PARENT"] != $id_kat) {
		$row["HIDDEN"] = 1;
	}
	if ($row["level"] == 0) {
		// Root category
		$id_root_kat = $row["ID_KAT"];
	}
	$row["KAT_ROOT"] = $id_root_kat;
}

function count_articles(&$row) {
	global $db;
	$ids_kats = $db->fetch_nar($q="
		SELECT ID_KAT
			FROM `kat`
		WHERE
			(LFT >= " . $row["LFT"] . ") AND
			(RGT <= " . $row["RGT"] . ") AND
			(ROOT = " . $row["ROOT"] . ")");
	$ids_kats = "(" . implode(",", array_keys($ids_kats)) . ")";
	$row["ARTICLE_COUNT"] = $db->fetch_atom("
		SELECT COUNT(*)
			FROM `" . $row["KAT_TABLE"] . "`
		WHERE
			FK_KAT IN " . $ids_kats . " AND (STATUS&3)=1 AND (DELETED=0)");
}

function lists_add_kat($kat_data, $kat_rows, &$lists, $list_cols = 3, $depth = 0, $list_target = -1) {
	global $kat;
	if ($list_target == -1) {
		$list_min_count = -1;
		for ($list = 1; $list <= $list_cols; $list++) {
			if (($list_min_count < 0)
					|| (count($lists[$list]) < $list_min_count)) {
				$list_min_count = count($lists[$list]);
				$list_target = $list;
			}
		}
	}
	$childs = $kat->element_get_childs($kat_data["ID_KAT"]);

	$kat_data["CHILDS"] = count($childs);
	$kat_data["DEPTH"] = $depth;

	$lists[$list_target][] = $kat_data;
	foreach ($childs as $index => $child) {
		lists_add_kat($child, $kat_rows, $lists, $list_cols, $depth + 1,
				$list_target);
	}
}

function add_params(&$row, $i) {
	global $langval, $db;
	$ar_search = @unserialize($row["SEARCH_ARRAY"]);
	if (!empty($ar_search)) {
		$row["SEARCH_HASH"] = $ar_search["HASH"];
		$row["SEARCH_PARAMS"] = ad_agent::GetSearchDescription($ar_search, $langval);
		$count = $db->fetch_atom("SELECT count(*) FROM `searchstring` WHERE QUERY='".$ar_search["HASH"]."'");
		if ($count == 0) {
			$db->querynow("INSERT INTO `searchstring` (QUERY, S_STRING, S_WHERE, LIFETIME)
					VALUES ('".mysql_escape_string($row["SEARCH_HASH"])."',
						'".mysql_escape_string($row["SEARCH_ARRAY"])."',
						'".mysql_escape_string($row["SEARCH_WHERE"])."',
						NOW() + interval 7 day)");
		}
	}
}

if (isset($_REQUEST['ABO_SUBMIT'])) {
  	if ($_REQUEST['ABO_REQUEST'] == 1) {
  		$db->querynow("UPDATE `user` SET ABO_REQUEST=1 WHERE ID_USER=".$uid);
  	} else {
  		$db->querynow("UPDATE `user` SET ABO_REQUEST=0 WHERE ID_USER=".$uid);
  	}
  	die(forward("/my-pages/ad_agent,done,abo_request.htm"));
}

if ($ar_params[1] == "add") {
	$search_hash = $ar_params[2];
	if ($search_hash != "done") {
		$ar_search_full = $db->fetch1("SELECT S_STRING, S_WHERE FROM `searchstring` WHERE QUERY='".mysql_escape_string($search_hash)."'");
		$ar_search = unserialize($ar_search_full["S_STRING"]);
		$ar_search["HASH"] = $search_hash;

		$s_where = $ar_search_full["S_WHERE"];
		$tpl_content->addvar("new", 1);
		$tpl_content->addvar("SEARCH_HASH", $search_hash);
		$tpl_content->addvar("SEARCH_KAT", $ar_search["FK_KAT"]);
		$tpl_content->addvar("SEARCH_MAN", $ar_search["FK_MAN"]);
		$tpl_content->addvar("SEARCH_USER", $ar_search["FK_USER"]);
		$tpl_content->addvar("SEARCH_PARAMS", ad_agent::GetSearchDescription($ar_search, $langval));
		if (!empty($_POST)) {

			$add_months = $nar_systemsettings['MARKTPLATZ']['ANZEIGEN_AGENT_RUN_LIFECYCLE'];

			$current_date = date("Y-m-d H:i:s");
			$date_after_adding_months = date("Y-m-d H:i:s",strtotime("+".$add_months." months",strtotime($current_date)));

			$search_name = (empty($_POST["SEARCH_NAME"]) ? "---" : $_POST["SEARCH_NAME"]);
			$search_man = ($ar_search["FK_MAN"] > 0 ? (int)$ar_search["FK_MAN"] : "NULL");
			$search_user = ($ar_search["FK_USER"] > 0 ? (int)$ar_search["FK_USER"] : "NULL");


			$db->querynow("INSERT INTO `ad_agent` (FK_USER, HASH, SEARCH_NAME, SEARCH_KAT, SEARCH_MAN, SEARCH_USER, SEARCH_ARRAY, SEARCH_WHERE, STATUS, LIFE_CYCLE_ENDS, CREATED_AT)
					VALUES (".(int)$uid.", '".mysql_escape_string($search_hash)."', '".mysql_escape_string($search_name)."',
						".(int)$ar_search["FK_KAT"].", ".$search_man.", ".$search_user.",
						'".mysql_escape_string(serialize($ar_search))."', '".mysql_escape_string($s_where)."',1,'".$date_after_adding_months."','".$current_date."')");
			die(forward("ad_agent,add,done.htm"));
		}
	} else {
		$tpl_content->addvar("new_saved", 1);
	}
}

if ($ar_params[1] == "del") {
	$fail = false;
	$id_ad_agent = $ar_params[2];

	if (!$id_ad_agent) {
		// Keine Suchkriterien angegeben
		$fail = true;
		$tpl_content->addvar("error", 1);
		$tpl_content->addvar("error_notfound", 1);
	}
	if (!$fail) {
		$db->querynow("DELETE FROM `ad_agent` WHERE FK_USER=".$uid." AND ID_AD_AGENT=".$id_ad_agent);
		die(forward("ad_agent.htm"));
	}
}

if ($ar_params[1] == "pause") {
	$id_ad_agent = $ar_params[2];

	$table_array = array(
		"ID_AD_AGENT"   =>  $id_ad_agent,
		"STATUS"        =>  mysql_real_escape_string($_POST["STATUS"])
	);
	$db->update("ad_agent",$table_array);
	$data = new stdClass();
	$data->success = true;
	$data->msg = '';
	die(json_encode($data));
}

// Gesuch-Agent
$tpl_content->addvar("ABO_REQUEST", $db->fetch_atom("SELECT ABO_REQUEST FROM `user` WHERE ID_USER=".$uid));

// Vorhandene Einträge auslesen
$ad_agents = $db
		->fetch_table($q=
				"
  	SELECT
  		a.*,
  		string_kat.V1 as SEARCH_KAT_TXT,
  		man.NAME as SEARCH_MAN_TXT,
  		user.NAME as SEARCH_USER_TXT
  	FROM `ad_agent` a
  		LEFT JOIN `kat` kat
  			ON kat.ID_KAT=a.SEARCH_KAT
  		LEFT JOIN `string_kat` string_kat
  			ON string_kat.FK=a.SEARCH_KAT and string_kat.S_TABLE='kat' and
  				 string_kat.BF_LANG=if(kat.BF_LANG_KAT & " . $langval . ", "
						. $langval
						. ", 1 << floor(log(kat.BF_LANG_KAT+0.5)/log(2)))
  		LEFT JOIN `manufacturers` man
  			ON man.ID_MAN=a.SEARCH_MAN
  		LEFT JOIN `user` user
  			ON user.ID_USER=a.SEARCH_USER
  	WHERE a.FK_USER=" . $uid);

$tpl_content->addlist("liste", $ad_agents, "tpl/".$s_lang."/ad_agent.row.htm", "add_params");

//// Kategorien auslesen
//$kat = new TreeCategories("kat", 1);
//$id_kat = $kat->tree_get_parent();
//$kat_data = $kat->element_read($id_kat);
//
//$ar_tree = $kat->tree_get();
//
//$baum = tree_show_nested($ar_tree, "tpl/" . $s_lang . "/ad_agent.kat_row.htm",
//		'hide_childs', $actions, (int) $id_kat, 'ID_KAT');
//$tpl_content->addvar("kat_selector", $baum);

$tpl_content->addvar("IS_ABO_REQUEST_ACTION", ($ar_params[2] == 'abo_request'));

/*

$list_kats = $db->fetch_table("
  SELECT
    ID_KAT,
    PARENT,
    LFT, RGT, ROOT, KAT_TABLE,
    FLOOR((RGT - LFT) / 2) as COUNT
    FROM `kat`
  WHERE
    (LFT >= ".$kat_data["LFT"].") AND
    (RGT <= ".$kat_data["RGT"].") AND
    (ROOT = ".$kat_data["ROOT"].")
  ");
$kat_lists = array();

// Ab 15 Kategorien 2 Spalten, ab 30 Kats 3 Spalten.
$kat_lists_count = (count($list_kats) > 15 ? 2 : 1 );
for ($list_index = 1; $list_index <= $kat_lists_count; $list_index++) {
  $kat_lists[$list_index] = array();
}

foreach ($list_kats as $index => $kat_child) {
  if ($kat_child["PARENT"] == $id_kat) {
    lists_add_kat($kat->element_read($kat_child["ID_KAT"]), $kat_child["COUNT"], $kat_lists, $kat_lists_count);
  }
}

for ($list_index = 1; $list_index <= $kat_lists_count; $list_index++) {
  $tpl_content->addlist("kat_liste_".$list_index, $kat_lists[$list_index], "tpl/".$s_lang."/ad_agent.kat_row.htm");
}

$tpl_content->addvar("kat_selector", $baum);
 */
?>
