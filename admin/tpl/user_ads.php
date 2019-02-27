<?php
/* ###VERSIONSBLOCKINLCUDE### */



$id_user = (int)$_REQUEST['ID_USER'];
$tpl_content->addvar("ID_USER", $id_user);
$name_= $db->fetch_atom("
	SELECT
		NAME
	FROM
		user
	WHERE
		ID_USER=".$id_user);

$tpl_content->addvar("NAME",$name_);
$tpl_content_links->addvar("NAME_", $name_);



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

  include_once "sys/lib.nestedsets.php";
  include_once "sys/lib.shop_kategorien.php";
  include_once "../sys/lib.pub_kategorien.php";

  function count_articles(&$row) {
    global $db;

    $kat_childs = $db->fetch_nar("SELECT ID_KAT, 1 as VAL FROM `kat` WHERE LFT>=".$row["LFT"]." AND RGT<=".$row["RGT"]);
    $row["COUNT_CHILDS"] = $db->fetch_atom("SELECT count(*) FROM `".$row["KAT_TABLE"]."` WHERE (STATUS&3)=1 AND FK_KAT IN (".implode(",", array_keys($kat_childs)).")");
    $row["COUNT"] = $db->fetch_atom("SELECT count(*) FROM `".$row["KAT_TABLE"]."` WHERE (STATUS&3)=1 AND FK_KAT=".$row["ID_KAT"]);
  }

  $perpage = 20; // Elemente pro Seite
  $limit = ((($_REQUEST['npage'] ? $_REQUEST['npage'] : $_REQUEST['npage']=1)-1)*$perpage);

  $kat = new TreeCategories("kat", 1);
  $id_kat = ($_REQUEST["ID_KAT"] ? (int)$_REQUEST["ID_KAT"] : $kat->tree_get_parent());
  $id_kat_root = $kat->tree_get_parent();

  $kat_table = $db->fetch_atom("SELECT KAT_TABLE FROM `kat` WHERE ID_KAT=".$id_kat);
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

  $sort_by = ($_REQUEST['ORDERBY'] ? $_REQUEST['ORDERBY'] : "a.STAMP_START");
  $sort_dir = "DESC";

  $where = "FK_USER = ".$id_user." AND STATUS&3 = 1 ";

  $all = $db->fetch_atom("SELECT count(*) FROM `ad_master` a ".($where ? " WHERE ".$where : ""));
  $query = "
  	SELECT
  		a.*,
		DATEDIFF(NOW(), a.STAMP_START) as RUNTIME,
  		u.NAME as USERNAME,
  		(SELECT count(*) FROM `ad_images` i WHERE i.FK_AD=a.ID_AD_MASTER) as IMAGE_COUNT,
  		(SELECT k.V1 FROM `string_kat` k WHERE k.FK=a.FK_KAT AND BF_LANG=128 AND S_TABLE='kat') as KAT_NAME
	FROM `ad_master` a
		LEFT JOIN `user` u
			ON u.ID_USER = a.FK_USER
		".($where ? " WHERE ".$where : "")."
		ORDER BY STAMP_START DESC
	LIMIT ".$limit.",".$perpage;

  $articles = $db->fetch_table($query);

  $tpl_content->addlist('liste', $articles, 'tpl/de/user_ads.row.htm');
  $tpl_content->addvar("pager", htm_browse($all, $_REQUEST['npage'], "index.php?page=articles&ID_KAT=".$id_kat."&search=".$search_hash."&npage=", $perpage));

  $tpl_content_links->addvar("ALL", $all);

  $ar_ads = $db->fetch1("
  	SELECT
  		COUNT(*) AS `MONTH`,
  		(
			SELECT
				COUNT(*)
			FROM
		  		ad_master
		  	WHERE
		  		FK_USER=".$id_user."
		  		AND STATUS&3 = 1
		  		AND MONTH(STAMP_START) = MONTH(DATE_SUB(CURDATE(), interval 1 MONTH))
  		) AS LAST
  	FROM
  		ad_master
  	WHERE
  		FK_USER=".$id_user."
  		AND STATUS&3 = 1
  		AND MONTH(STAMP_START) = MONTH(CURDATE())");
  $tpl_content_links->addvars($ar_ads);
$tpl_content_links->addvar("ID_USER", $id_user);

?>