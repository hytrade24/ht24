<?php
/* ###VERSIONSBLOCKINLCUDE### */



require_once("sys/lib.pub_kategorien.php");
require_once("sys/lib.shop_kategorien.php");
require_once "admin/sys/tabledef.php";

// TODO: Warum war hier die Paketverwaltung eingebunden?
//require_once $ab_path."sys/packet_management.php";
//$packets = PacketManagement::getInstance($db);

$idArticles = $_POST['rows'];
$id_kat = $_POST['ID_KAT'];
$isImport = isset($_POST['IS_IMPORT'])?$_POST['IS_IMPORT']:false;

$tpl_content->addvar("JSON_POST", json_encode($_POST));
$tpl_content->addvar("JSON_ROWS", json_encode($_POST['rows']));

if(!$isImport || $id_kat != null) {
	$b_sales = $db->fetch_atom("SELECT B_SALES FROM kat where ID_KAT=".$id_kat);
} else {
	$b_sales = 1;
}

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


$tpl_content->addvar("B_SALES", $b_sales);
$tpl_content->addvar("GOOGLE_API", $nar_systemsettings['SITE']['GOOGLE_API']);
$tpl_content->addvar("ALLOW_HTML", $nar_systemsettings['MARKTPLATZ']['ALLOW_HTML']);


tabledef::getFieldInfo($kat_table);
$colSelectOptions = '';

$excludeCols = array("ID_".strtoupper($kat_table) ,"FK_USER", "LONGITUDE", "LATITUDE","STATUS","FK_MAN","FK_PRODUCT", "STAMP_START", "STAMP_END", "STAMP_DEACTIVATE");

foreach (tabledef::$field_info as $key=>$col) {
	if(!in_array($col['F_NAME'], $excludeCols)) {
		$colSelectOptions .= '<option value="'.$col['F_NAME'].'">'.trim($col['V1']).'</option>';
	}
}
#$colSelectOptions .= '<option value="LU_LAUFZEIT">Laufzeit der Anzeige</option>';


if(!$isImport || $id_kat != null) {
	$fields_saved["tmp_listen"] = implode(",", array_keys($db->fetch_nar(
	  "SELECT f.F_NAME FROM field_def f
		  LEFT JOIN kat2field kf ON f.ID_FIELD_DEF = kf.FK_FIELD
		WHERE kf.FK_KAT=".$id_kat." AND f.F_TYP='LIST'")));
} else {

	$fields_saved['tmp_listen'] = implode(",", array_keys($db->fetch_nar(
	  "SELECT f.F_NAME FROM field_def f
		  LEFT JOIN kat2field kf ON f.ID_FIELD_DEF = kf.FK_FIELD
		  LEFT JOIN kat k ON k.ID_KAT = kf.FK_KAT
		WHERE k.KAT_TABLE = '".mysql_real_escape_string($kat_table)."' and f.F_TYP='LIST'
		GROUP BY f.ID_FIELD_DEF
		")));
}

if($_POST['DO'] == 'SAVE') {

	if(is_array($_POST['dateimplodes']) && !empty($_POST['dateimplodes'])) 	{
  		foreach($_POST['dateimplodes'] as $key => $value) {
  			date_implode($_POST, $value);
  		}
  	}

  	$fields_data = array();
  	$fields_data = array_merge($_POST, $fields_data);

  	$fields_needed = explode(',', $fields_data["tmp_needed"]);
    $fields_needed[] = "LU_LAUFZEIT";

    $changeField = $_POST['SELECT_FIELD'];

    if (in_array($changeField, $fields_needed)) {
    	$type = $_POST["tmp_type"][$changeField];
    	$field = $changeField;

    	if (($fields_data[$field] != 0) && empty($fields_data[$field]))
          $errors[] = "ERR_MISSING_".$field;

        // Nicht ausgefülltes Pflicht-Feld (LISTE!!)
        if (($type == "list") && ($fields_data[$field] == 0))
          $errors[] = "ERR_MISSING_".$field;

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
	    }
    }

    if (!empty($errors)) {
       // Fehler als JSON zurückgeben
       echo json_encode(array('success' => false, 'errors' => $errors));
       die();
    } else {
    	if($nar_systemsettings['MARKTPLATZ']['ALLOW_HTML'] == 0) {
      		$fields_data["BESCHREIBUNG"] = strip_tags($fields_data["BESCHREIBUNG"], "<a><span><p><u><em><strong><ol><ul><li>");
	    } else {
	      	$fields_data["BESCHREIBUNG"] = $fields_data["BESCHREIBUNG"];
	    }
	    if (empty($fields_data["AUTOBUY"])) {
	      	$fields_data["AUTOBUY"] = 0;
	    }

	    foreach ($idArticles as $key=>$row) {

	    	$a = $db->querynow("UPDATE `".$table."`
		      	SET ".mysql_real_escape_string($changeField)." = '".mysql_real_escape_string($fields_data[$changeField])."'
		      	WHERE
		      		ID_".strtoupper($kat_table)."=".mysql_real_escape_string($row)." AND FK_USER='".$uid."'
		    ");

	    	if(!$isImport) {
	    		$a = $db->querynow("UPDATE `ad_master`
			      	SET ".mysql_real_escape_string($changeField)." = '".mysql_real_escape_string($fields_data[$changeField])."'
			      	WHERE
			      		ID_AD_MASTER=".mysql_real_escape_string($row)." AND FK_USER='".$uid."'
			    ");

	      	}

	    }
    	echo json_encode(array('success' => true));
      	die();
    }

} else {
	$fields_saved["B_SALES"] = $b_sales;
	if($id_kat != null) {
    	$tpl_content->addvar("input_fields", $ar_fields = CategoriesBase::getInputFieldsCache($id_kat, $fields_saved, "tpl/".$s_lang."/my-marktplatz-neu.input.htm"));
    } else {
    	$tpl_content->addvar("input_fields", $ar_fields = CategoriesBase::getCategoryInputFieldsCache($kat_table, $fields_saved, "tpl/".$s_lang."/my-marktplatz-neu.input.htm"));
    }

	$tpl_content->addvar("COL_SELECT_OPTIONS", $colSelectOptions);
	$tpl_content->addvar("TABLE", $_POST['TABLE']);
	$tpl_content->addvar("KAT_TABLE", $kat_table);
	$tpl_content->addvar("IS_IMPORT", $isImport);
}
