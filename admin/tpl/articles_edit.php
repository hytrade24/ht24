<?php
/* ###VERSIONSBLOCKINLCUDE### */





  include_once "sys/lib.nestedsets.php";
  include_once "sys/lib.shop_kategorien.php";
  include_once "../sys/lib.pub_kategorien.php";

  function count_articles(&$row) {
    global $db;

    $row["COUNT_TABLE"] = $db->fetch_atom("SELECT count(*) FROM `".$row["KAT_TABLE"]."`");
    $row["COUNT"] = $db->fetch_atom("SELECT count(*) FROM `ad_master` WHERE FK_KAT=".$row["ID_KAT"]);
  }

  function manufacturer_active(&$row) {
    global $fields_data;
    if ($row["ID_MAN"] == $fields_data["FK_MAN"]) {
      $row["ACTIVE"] = 1;
    }
  }

  $kat = new TreeCategories("kat", 1);
  $id_article = ($_REQUEST["edit"] ? (int)$_REQUEST["edit"] : 0);
  $id_kat = ($_REQUEST["ID_KAT"] ? (int)$_REQUEST["ID_KAT"] : $kat->tree_get_parent());
  $image_action = ($_REQUEST["doimage"] ? $_REQUEST["doimage"] : false);
  $id_image = ($_REQUEST["image"] ? (int)$_REQUEST["image"] : 0);


  $kat_table = $db->fetch_atom("SELECT KAT_TABLE FROM `kat` WHERE ID_KAT=".$id_kat);
  $kat_cache = new CategoriesCache();

  $kats = $kat_cache->kats_read($id_kat);
  if (!empty($kats)) {

    $tpl_cache = new Template(CacheTemplate::getHeadFile('tpl/'.$s_lang.'/cache_marktplatz_list.htm'));
    $tpl_cache->addlist('liste', $kats, CacheTemplate::getHeadFile('tpl/de/cache_marktplatz_list.row.htm'), 'count_articles');
    $tpl_main->addvar('kats', $tpl_cache->process());
  }

  if($_POST['KICKVERSTOSS']) {
		$db->querynow("delete from verstoss where FK_AD=".$id_article);
	}

  // -----------------------------------------------------------------------

  if ($image_action) {
    if (($image_action == "default") && ($id_image > 0)) {
      $db->querynow("UPDATE `ad_images` SET IS_DEFAULT=0 WHERE FK_AD=".$id_article);
      $db->querynow("UPDATE `ad_images` SET IS_DEFAULT=1 WHERE FK_AD=".$id_article." AND ID_IMAGE=".$id_image);
    }
    if (($image_action == "delete") && ($id_image > 0)) {
      $image_delete = $db->fetch1("SELECT * FROM `ad_images` WHERE FK_AD=".$id_article." AND ID_IMAGE=".$id_image);
      if ($image_delete["CUSTOM"] == 1) {
        unlink($ab_path.substr($image_delete["SRC"], 1));
        unlink($ab_path.substr($image_delete["SRC_THUMB"], 1));
      }
      $db->querynow("DELETE FROM `ad_images` WHERE ID_IMAGE=".$image_delete["ID_IMAGE"]);
      $image_default = $db->fetch_atom("SELECT count(*) FROM `ad_images` WHERE FK_AD=".$id_article." AND IS_DEFAULT=1");
      if ($image_default == 0)
        $db->querynow("UPDATE `ad_images` SET IS_DEFAULT=1 WHERE FK_AD=".$id_article." LIMIT 1");
    }
    die(forward("index.php?page=articles_edit&edit=".$id_article."&ID_KAT=".$id_kat));
  }

  if (empty($_POST)) {
    $fields_data = $db->fetch1("
      SELECT
        FK_COUNTRY,
        PLZ as ZIP,
        ORT as CITY
      FROM
        user
      WHERE
        ID_USER=".$uid);

    $kat_table = $db->fetch_atom("SELECT AD_TABLE FROM `ad_master` WHERE ID_AD_MASTER=".$id_article);
    $fields_saved = $db->fetch1("SELECT *, (DATEDIFF(STAMP_END,NOW())) AS DAYS_LEFT FROM `".$kat_table."` WHERE ID_".strtoupper($kat_table)."=".$id_article);
    $fields_saved["tmp_listen"] = implode(",", array_keys($db->fetch_nar(
      "SELECT f.F_NAME FROM field_def f
          LEFT JOIN kat2field kf ON f.ID_FIELD_DEF = kf.FK_FIELD
        WHERE kf.FK_KAT=".$id_kat." AND f.F_TYP='LIST'")));

    $fields_data = array_merge($fields_data, $fields_saved);
  } else {
    $fields_data = array();

    $errors = array();
    $fields_data = array_merge($_POST, $fields_data);
    $fields_needed = explode(',', $fields_data["tmp_needed"]);

    foreach ($_POST["tmp_type"] as $field => $type) {
      if (in_array($field, $fields_needed)) {
        // PFLICHTFELD!

        // Nicht ausgefülltes Pflicht-Feld
        if (($fields_data[$field] != 0) && empty($fields_data[$field]))
          $errors[] = "ERR_MISSING_".$field;

        // Nicht ausgefülltes Pflicht-Feld (LISTE!!)
        if (($type == "list") && ($fields_data[$field] == 0))
          $errors[] = "ERR_MISSING_".$field;
      }

      // Feld ist gesetzt
      if (!empty($fields_data[$field])) {
        // Keine Zahl in Integer-Feld
        if (($type == "int") && (!is_numeric($fields_data[$field])))
          $errors[] = "ERR_WRONG_".$field;
        // Keine Zahl in Float-Feld
        if (($type == "float") && (!is_numeric(str_replace(",", ".", $fields_data[$field]))))
          $errors[] = "ERR_WRONG_".$field;
        else if ($type == "float")
          $fields_data[$field] = str_replace(",", ".", $fields_data[$field]);
      } else {
      	if ($type == "checkbox") {
      		$fields_data[$field] = 0;
      	} else {
        	unset($fields_data[$field]);
      	}
      }
    }
    /*
    foreach ($_POST["tmp_type"] as $field => $type) {
      if (in_array($field, $fields_needed)) {
        // PFLICHTFELD!

        // Nicht ausgefülltes Pflicht-Feld
        if (empty($fields_data[$field]))
          $errors[] = "ERR_MISSING_".$field;

        // Nicht ausgefülltes Pflicht-Feld (LISTE!!)
        if (($type == "list") && ($fields_data[$field] == 0))
          $errors[] = "ERR_MISSING_".$field;
      }

      // Feld ist gesetzt
      if (!empty($fields_data[$field])) {
        // Keine Zahl in Integer-Feld
        if (($type == "int") && (!is_numeric($fields_data[$field])))
          $errors[] = "ERR_WRONG_".$field;
        // Keine Zahl in Float-Feld
        if (($type == "float") && (!is_numeric(str_replace(",", ".", $fields_data[$field]))))
          $errors[] = "ERR_WRONG_".$field;
        else if ($type == "float")
          $fields_data[$field] = str_replace(",", ".", $fields_data[$field]);
      } else {
        unset($fields_data[$field]);
      }
    }*/

    $date_start = time();
    $date_end = $date_start + ($fields_data["DAYS_LEFT"] * 24 * 60 * 60);
    $fields_data["STAMP_END"] = date("Y-m-d H:i:s", $date_end);


    if (!empty($errors)) {
      $tpl_content->addvar("errors", "- ".implode("<br />- ", $errors));
    } else {

      $fields_data['ID_AD_MASTER'] = $id_article;
      $db->update("ad_master", $fields_data);


      $kat_table = $db->fetch_atom("SELECT KAT_TABLE FROM `kat` WHERE ID_KAT=".$id_kat);
      $fields_data["ID_".strtoupper($kat_table)] = $id_article;
      $db->update($kat_table, $fields_data);

      die(forward("index.php?page=articles_edit&ID_KAT=".$id_kat."&edit=".$id_article));
    }
  }

  $manufacturers = $db->fetch_table("SELECT * FROM manufacturers");
  if (!empty($manufacturers)) {
    $tpl_content->addlist("liste_man", $manufacturers, $ab_path."tpl/de/marktplatz_neu_desc.row.htm", "manufacturer_active");
  }

  $article_images = $db->fetch_table("SELECT * FROM `ad_images` WHERE FK_AD=".$id_article);
  if (count($article_images) > 0) {
    $tpl_content->addlist("product_images", $article_images, "tpl/de/articles_edit.row_images.htm");
  }

  $tpl_content->addvar("COUNT_IMAGES", count($article_images));
  $tpl_content->addvar("COUNT_FILES", $db->fetch_atom("SELECT count(*) FROM `ad_upload` WHERE FK_AD=".$id_article));

  if (($id_article > 0) && ($id_kat > 0)) {
  	$ar_verstoss = $db->fetch1("select
  		v.ID_VERSTOSS,
  		v.FK_USER AS FK_MELDER,
  		v.GRUND,
  		v.STAMP AS STAMP_MELDUNG,
  		u.NAME AS MELDER
  	from
  		verstoss v
  	left join
  		user u on u.ID_USER=v.FK_USER
  	where
  		FK_AD=".$id_article);
  	if(!empty($ar_verstoss)) {
  		$tpl_content->addvars($ar_verstoss);
  	}

    // Article exists
    $tpl_content->addvar("ID_ANZEIGE", $id_article);
    $tpl_content->addvar("ID_KAT", $id_kat);
    $tpl_content->addvars($fields_data);
    $tpl_content->addvar("liste_fields", CategoriesBase::getInputFieldsCache($id_kat, $fields_data));
  }
?>