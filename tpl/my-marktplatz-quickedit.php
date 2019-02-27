<?php
/* ###VERSIONSBLOCKINLCUDE### */


/**
 * Schnell editieren einer Anzeige bzw. eines Imports
 * großteils aus der marktplatz_neu_desc.php übernommen
 *
 * @todo Redundanzen vermeiden
 */
require_once("sys/lib.pub_kategorien.php");
require_once("sys/lib.shop_kategorien.php");

// TODO: Warum war hier die Paketverwaltung eingebunden?
//require_once $ab_path."sys/packet_management.php";
//$packets = PacketManagement::getInstance($db);

$id_article = $_POST['ID_ANZEIGE'];
$id_kat = $_POST['ID_KAT'];
$isImport = isset($_POST['IS_IMPORT'])?$_POST['IS_IMPORT']:false;

$tpl_content->addvar("JSON_POST", json_encode($_POST));

if(!$isImport || $id_kat != null) {
	$b_sales = $db->fetch_atom("SELECT B_SALES FROM kat where ID_KAT=".$id_kat);
} else {
	$b_sales = 1;
}

$tpl_content->addvar("B_SALES", $b_sales);
$tpl_content->addvar("AD_CONSTRAINTS", $nar_systemsettings['MARKTPLATZ']['AD_CONSTRAINTS']);
$tpl_content->addvar("GOOGLE_API", $nar_systemsettings['SITE']['GOOGLE_API']);
$tpl_content->addvar("ALLOW_HTML", $nar_systemsettings['MARKTPLATZ']['ALLOW_HTML']);

// Anzeigen Daten holen
if(!isset($_POST['DO'])) {
	$fields_data = $db->fetch1("
	      SELECT
	        FK_COUNTRY,
	        PLZ as ZIP,
	        ORT as CITY
	      FROM
	        user
	      WHERE
	        ID_USER=".$uid);

	if(!$isImport || $id_kat != null) {
		$kat_table = $db->fetch_atom("SELECT KAT_TABLE FROM `kat` WHERE ID_KAT=".$id_kat);
	} else {
		$kat_table = $_POST['KAT_TABLE'];
	}
	if(!$isImport) {
		$table = $kat_table;
	} else {
		$table = "import_tmp_".strtolower($_POST['TABLE']);
	}
	$tpl_content->addvar("KAT_TABLE", $kat_table);

	$fields_saved = $db->fetch1("SELECT * FROM `".$table."` WHERE ID_".strtoupper($kat_table)."=".$id_article);

	if(isset($fields_saved['FK_KAT']) && ($fields_saved['FK_KAT'] > 0)) {
		$id_kat = $fields_saved['FK_KAT'];
	}

	if(!$isImport) {
		$fields_saved_master = $db->fetch1("SELECT * FROM `ad_master` WHERE ID_AD_MASTER=".$id_article);
		$fields_saved = array_merge($fields_saved_master, $fields_saved);
	}

	$fields_saved["AD_AGB"] =(empty($fields_saved["AD_AGB"]) ? $db->fetch_atom("SELECT AGB FROM `usercontent` WHERE FK_USER=".$uid)
			 : $fields_saved["AD_AGB"]);
	$fields_saved["AD_WIDERRUF"] =
		(empty($fields_saved["AD_WIDERRUF"]) ? $db->fetch_atom("SELECT WIDERRUF FROM `usercontent` WHERE FK_USER=".$uid)
			 : $fields_saved["AD_WIDERRUF"]);

	if(!$isImport || $id_kat != null) {
		$fields_saved["tmp_listen"] = implode(",", array_keys($db->fetch_nar(
		  "SELECT f.F_NAME FROM field_def f
			  LEFT JOIN kat2field kf ON f.ID_FIELD_DEF = kf.FK_FIELD
			WHERE kf.FK_KAT=".$id_kat." AND f.F_TYP='LIST'")));
	} else {
		/*$fields_saved["tmp_listen"] = implode(",", array_keys($db->fetch_nar(
		  "SELECT f.F_NAME FROM field_def f
			  LEFT JOIN kat2field kf ON f.ID_FIELD_DEF = kf.FK_FIELD
			  LEFT JOIN kat k ON k.ID_KAT = kf.FK_KAT
			WHERE k.KAT_TABLE=".$kat_table." AND f.F_TYP='LIST'
			GROUP BY f.ID_FIELD_DEF")));*/
		$fields_saved['tmp_listen'] = implode(",", array_keys($db->fetch_nar(
		  "SELECT f.F_NAME FROM field_def f
			  LEFT JOIN kat2field kf ON f.ID_FIELD_DEF = kf.FK_FIELD
			  LEFT JOIN kat k ON k.ID_KAT = kf.FK_KAT
			WHERE k.KAT_TABLE = '".mysql_real_escape_string($kat_table)."' and f.F_TYP='LIST'
			GROUP BY f.ID_FIELD_DEF
			")));
	}
	if(!$isImport) {
		$fields_master = $db->fetch1("SELECT LU_LAUFZEIT, NOTIZ FROM `ad_master` WHERE ID_AD_MASTER=".$id_article);
		$fields_saved = array_merge($fields_master, $fields_saved);
	}
	$fields_data = array_merge($fields_saved, $fields_data);
} elseif($_POST['DO'] == 'SAVE_KAT') {
	$id_article = $_POST["ID_ANZEIGE"];
    $id_kat = $_POST["ID_KAT"];

	$kat_table = $db->fetch_atom("SELECT KAT_TABLE FROM `kat` WHERE ID_KAT=".$id_kat);
	if(!$isImport) {
		$table = $kat_table;
	} else {
		$table = "import_tmp_".strtolower($_POST['TABLE']);
	}

	if($isImport) {
	      $a = $db->querynow("UPDATE `".$table."`
	      	SET FK_KAT = '".mysql_real_escape_string($id_kat)."'
	      	WHERE
	      		ID_".strtoupper($kat_table)."=".mysql_real_escape_string($id_article)." AND FK_USER='".$uid."'
	      ");

    }

    echo json_encode(array("success" => true)); die();
} elseif($_POST['DO'] == 'SAVE') {

  	if(is_array($_POST['dateimplodes']) && !empty($_POST['dateimplodes']))
  	{
  		foreach($_POST['dateimplodes'] as $key => $value)
  		{
  			date_implode($_POST, $value);
  		}
  	}



    $fields_data = array();
    $id_article = $_POST["ID_ANZEIGE"];
    $id_kat = $_POST["ID_KAT"];
    unset($_POST["ID_ANZEIGE"]);
    unset($_POST["ID_KAT"]);

    $errors = array();

	$kat_table = $db->fetch_atom("SELECT KAT_TABLE FROM `kat` WHERE ID_KAT=".$id_kat);
	if(!$isImport) {
		$table = $kat_table;
	} else {
		$table = "import_tmp_".strtolower($_POST['TABLE']);
	}

    $fields_saved = $db->fetch1("SELECT * FROM `".$table."` WHERE ID_".strtoupper($kat_table)."=".$id_article);
    $fields_saved["tmp_listen"] = implode(",", array_keys($db->fetch_nar(
      "SELECT f.F_NAME FROM field_def f
          LEFT JOIN kat2field kf ON f.ID_FIELD_DEF = kf.FK_FIELD
        WHERE kf.FK_KAT=".$id_kat." AND f.F_TYP='LIST'")));

    $fields_data = array_merge($_POST, $fields_data);
    $fields_needed = explode(',', $fields_data["tmp_needed"]);
    $fields_needed[] = "LU_LAUFZEIT";

    $queryBuilder = array();

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
        unset($fields_data[$field]);
      }

      $queryBuilder[] = "`".$field."` = '".mysql_real_escape_string($fields_data[$field])."'";
    }

    if (!empty($errors)) {
       // Fehler als JSON zurückgeben
       echo json_encode(array('success' => false, 'errors' => $errors));
       die();
    } else {

      $fields_data["ID_".strtoupper($kat_table)] = $id_article;
      if($nar_systemsettings['MARKTPLATZ']['ALLOW_HTML'] == 0) {
      	$fields_data["BESCHREIBUNG"] = strip_tags($fields_data["BESCHREIBUNG"], "<a><span><p><u><em><strong><ol><ul><li>");
      } else {
      	$fields_data["BESCHREIBUNG"] = $fields_data["BESCHREIBUNG"];
      }
      if (empty($fields_data["AUTOBUY"])) {
      	$fields_data["AUTOBUY"] = 0;
      }
      $fields_data['FK_KAT'] = $id_kat;

      $queryBuilderFields = array('FK_KAT','PRODUKTNAME','BESCHREIBUNG', 'AUTOBUY', 'ZIP', 'CITY', 'FK_COUNTRY', 'LATITUDE', 'LONGITUDE', 'NOTIZ', 'ONLY_COLLECT', 'MWST', 'TRADE', 'AD_AGB', 'AD_WIDERRUF');
      foreach ($queryBuilderFields as $key => $field) {
      	$queryBuilder[] = "`".$field."` = '".mysql_real_escape_string($fields_data[$field])."'";
      }

      if($isImport) {
	      $a = $db->querynow("UPDATE `".$table."`
	      	SET ".implode(",", $queryBuilder)."
	      	WHERE
	      		ID_".strtoupper($kat_table)."=".mysql_real_escape_string($id_article)." AND FK_USER='".$uid."'
	      ");
      } else {

	      $db->update($table, $fields_data);
	      die();
	      $db->querynow("
	      	update
	      		verstoss
	      	set
	      		STAMP_AD_UPDATE=NOW()
	      	where
	      		FK_AD=".$id_article);
	      /*
	       * neuer code (9.2.2010)
	       */
	      $fields_data['ID_AD_MASTER'] = $id_article;
	      $db->update("ad_master", $fields_data);
      }

      echo json_encode(array('success' => true));
      die();
    }
}

$manufacturer = $db->fetch_atom("SELECT NAME FROM `manufacturers` WHERE ID_MAN=".$fields_data["FK_MAN"]);
$tpl_content->addvar("MANUFACTURER", $manufacturer);
$tpl_content->addvar("buying_enabled", ($nar_systemsettings["MARKTPLATZ"]["BUYING_ENABLED"] ? true : false));

  $ar_fields = "";
  if (($id_article > 0)) {
	$fields_data = AdConstraintManagement::appendAdContraintMapping($fields_data);
    // Article exists
    $tpl_content->addvar("ID_ANZEIGE", $id_article);
    $tpl_content->addvar("ID_KAT", $id_kat);
    $tpl_content->addvars($fields_data);

    if($id_kat != null) {
    	$tpl_content->addvar("input_fields", $ar_fields = CategoriesBase::getInputFieldsCache($id_kat, $fields_data, "tpl/".$s_lang."/my-marktplatz-neu.input.htm"));
    } else {
    	$fakeIdKat = $db->fetch_atom("SELECT ID_KAT FROM kat WHERE KAT_TABLE = '".$kat_table."' LIMIT 1");
    	$tpl_content->addvar("input_fields", $ar_fields = CategoriesBase::getInputFieldsCache($fakeIdKat, $fields_data, "tpl/".$s_lang."/my-marktplatz-neu.input.htm"));
    }

  }

  $tpl_content->addvar("TABLE", $_POST['TABLE']);
$tpl_content->addvar("IS_IMPORT", $isImport);
$tpl_content->addvar("HASH", time());

    // Schritte nummerieren
    $index_number = ($_SESSION["NEXT_STEP_1"] ? $_SESSION["NEXT_STEP_1"] : 1);
    // Produktbeschreibung
    $tpl_content->addvar("index_desc", sprintf("%02u", $index_number++));
    // Produktdetails
    if (!empty($ar_fields)) {
    	$tpl_content->addvar("index_fields", sprintf("%02u", $index_number++));
    }
    // Produkt Standort
    $tpl_content->addvar("index_location", sprintf("%02u", $index_number++));
    // Produkt Preis
    if ($b_sales) {
    	$tpl_content->addvar("index_price", sprintf("%02u", $index_number++));
    }
    // Produkt Standort
    $tpl_content->addvar("index_legal", sprintf("%02u", $index_number++));
    // Aktuellen schritt für nächste Seite seichern
    $_SESSION["NEXT_STEP_2"] = $index_number;


