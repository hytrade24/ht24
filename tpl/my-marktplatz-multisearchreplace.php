<?php
/* ###VERSIONSBLOCKINLCUDE### */



require_once("sys/lib.pub_kategorien.php");
require_once("sys/lib.shop_kategorien.php");
require_once "sys/lib.packets.php";
require_once "admin/sys/tabledef.php";

$idArticles = $_POST['rows'];
$id_kat = $_POST['ID_KAT'];

$tpl_content->addvar("JSON_POST", json_encode($_POST));
$tpl_content->addvar("JSON_ROWS", json_encode($_POST['rows']));


if($id_kat == null || $id_kat == 1) {
    $id_kat = 1;
    $kat_table = "ARTIKEL_MASTER";
}else {
    $kat_table = $db->fetch_atom("SELECT KAT_TABLE FROM `kat` WHERE ID_KAT=".$id_kat);
}

$validColumns = array();

tabledef::getFieldInfo($kat_table);
$colSelectOptions = '';
$excludeCols = array("ID_".strtoupper($kat_table) ,"FK_USER", "ZIP", "CITY", "IMPORT_IDENTIFIER","LONGITUDE", "LATITUDE","STATUS","FK_MAN","FK_PRODUCT", "STAMP_START", "STAMP_END", "STAMP_DEACTIVATE");

foreach (tabledef::$field_info as $key=>$col) {
    if(in_array($col['F_TYP'], array("TEXT", "LONGTEXT")) && !in_array($col['F_NAME'], $excludeCols)) {
        $colSelectOptions .= '<option value="'.$col['F_NAME'].'">'.trim($col['V1']).'</option>';
        $validColumns[] = $col['F_NAME'];
    }
}

if(isset($_POST['SELECT_FIELD'])) $_POST['SELECT_FIELD'] = $_POST['SELECT_FIELD'];
if(isset($_POST['SEARCH'])) $_POST['SEARCH'] = $_POST['SEARCH'];
if(isset($_POST['REPLACE'])) $_POST['REPLACE'] = $_POST['REPLACE'];

if($_POST['DO'] == 'REPLACE') {
	if(isset($_POST['SELECT_FIELD']) && isset($_POST['SEARCH']) && $_POST['SEARCH'] !== "" && isset($_POST['REPLACE']) && in_array($_POST['SELECT_FIELD'], $validColumns)) {
        foreach ($idArticles as $key=>$row) {
            $whereSearch = "`".mysql_real_escape_string($_POST['SELECT_FIELD'])."` LIKE '%".mysql_real_escape_string($_POST['SEARCH'])."%' ";
            $replaceSearch = "`".mysql_real_escape_string($_POST['SELECT_FIELD'])."` = REPLACE(".mysql_real_escape_string($_POST['SELECT_FIELD']).", '".mysql_real_escape_string($_POST['SEARCH'])."', '".mysql_real_escape_string($_POST['REPLACE'])."') ";
            $categoryTable = $db->fetch_atom("
                SELECT
                    k.KAT_TABLE
                FROM ad_master am
                JOIN kat k ON k.ID_KAT = am.FK_KAT
                WHERE
                    am.ID_AD_MASTER = '".mysql_real_escape_string($row)."'
                    AND FK_USER = '".$uid."'
            ");

            $result = $db->querynow("
                UPDATE
                    `".$categoryTable."`
                SET
                    ".$replaceSearch."
                WHERE
                    ".$whereSearch."
                    AND ID_".strtoupper($categoryTable)." = '".mysql_real_escape_string($row)."'
                    AND FK_USER = '".$uid."'
            ");
   

            if($result['rsrc'] == true) {
                // Ad Master updaten
                // Mï¿½glichkeit das zu updatende Spalte nicht existiert
                try {
                    $masterResult = $db->querynow("
                        UPDATE
                            ad_master
                        SET
                            ".$replaceSearch."
                        WHERE
                            ID_AD_MASTER = '".mysql_real_escape_string($row)."'
                            AND FK_USER = '".$uid."'
                    ");
                } catch(Exception $e) { }
            }

        }
        echo json_encode(array('success' => true));
        die();
    }
    echo json_encode(array('success' => false));
    die();
} elseif($_POST['DO'] == 'SEARCH') {
    if(isset($_POST['SELECT_FIELD']) && isset($_POST['SEARCH']) && in_array($_POST['SELECT_FIELD'], $validColumns)) {

        $count = 0;
        foreach ($idArticles as $key=>$row) {
            $whereSearch = "`".mysql_real_escape_string($_POST['SELECT_FIELD'])."` LIKE '%".mysql_real_escape_string($_POST['SEARCH'])."%' ";

            $categoryTable = $db->fetch_atom("
                SELECT
                    k.KAT_TABLE
                FROM ad_master am
                JOIN kat k ON k.ID_KAT = am.FK_KAT
                WHERE
                    am.ID_AD_MASTER = '".mysql_real_escape_string($row)."'
                    AND FK_USER = '".$uid."'
            ");

            $searchCount = $db->fetch_atom("
                SELECT
                    COUNT(*)
                FROM `".$categoryTable."`
                WHERE
                    ".$whereSearch."
                    AND ID_".strtoupper($categoryTable)." = '".mysql_real_escape_string($row)."'
                    AND FK_USER = '".$uid."'
            ");
            $count += $searchCount;
        }

        echo json_encode(array('success' => true, 'countAffectedRows' => $count));
        die();
    }
    echo json_encode(array('success' => false));
    die();
} else {

	$tpl_content->addvar("COL_SELECT_OPTIONS", $colSelectOptions);
	$tpl_content->addvar("ID_KAT", $id_kat);
}
