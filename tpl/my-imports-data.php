<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once 'sys/lib.ad_import.php';
require_once "admin/sys/tabledef.php";

$importFileId = ((int)$_POST['ID_IMPORT_FILE'] ? (int)$_POST['ID_IMPORT_FILE'] : (int)$ar_params[1]);
$userId = $uid;

$adImportManagement = AdImportManagement::getInstance($db);

if(!$adImportManagement->existsImportFileByUserId($importFileId, $uid)) { die(); }

$categoryTable = $adImportManagement->getCategoryTableByImportFileId($importFileId);
$importIdent = $adImportManagement->getImportIdentByImportFileId($importFileId);

tabledef::getFieldInfo($categoryTable);

if(isset($_POST['DO'])) {
	if($_POST['DO'] == "runImport") {
        if(isset($_POST['FK_PACKET_ORDER']) && $_POST['FK_PACKET_ORDER'] != "") { $packetId = $_POST['FK_PACKET_ORDER']; } else { $packetId = null; }

        $adImportManagement->updateImportPacket($importFileId, $userId, $packetId);
		$adImportManagement->updateImportFileStatus($importFileId, AdImportManagement::IMPORT_FILE_STATUS_IMPORT);

	} elseif($_POST['DO'] == "revalidateImportFile") {
		$adImportManagement->updateImportFileStatus($importFileId, AdImportManagement::IMPORT_FILE_STATUS_VALIDATE);
	} elseif($_POST['DO'] == "updateImport") {
		$importId = $_POST['ID_IMPORT'];
		$adImportManagement->updateImportStatus($importFileId, $importId, AdImportManagement::IMPORT_STATUS_VALIDATION);
	} elseif($_POST['DO'] == "deleteImport") {
		$rows = array();
		if(!is_array($_POST['rows'])) {	$rows[] = $_POST['rows']; } else { $rows = $_POST['rows']; }

		foreach ($rows as $key=>$rowId) {
			$adImportManagement->removeImport($importFileId, $rowId, $uid);
		}

	}
	die();
}

$colNames = array();
$colModel = array();

$excludeCols = array("FK_USER", "STATUS","FK_MAN", "FK_PRODUCT", "STAMP_START", "STAMP_END", "STAMP_DEACTIVATE");

$colNames[] = "'IMPORT_STATUS'";
$colModel[] = "{
	name: 'IMPORT_STATUS',
	align: 'left',
	index: 'IMPORT_STATUS',
	hidden: true
}";

foreach (tabledef::$field_info as $key=>$col) {
	if(!in_array($col['F_NAME'], $excludeCols)) {
		$colNames[] = "'".trim(str_replace("'", "\\'", $col['V1']))."'";

		switch ($col['F_TYP']) {
			case 'TEXT': $formatter = ""; break;
			case 'LONGTEXT':  $formatter = ""; break;
			case 'LIST': $formatter = ""; break;
			case 'CHECKBOX':  $formatter = "formatter: 'checkbox',"; break;
			default:  $formatter = "";
		}

		switch ($col['F_NAME']) {
			default: $hidden = 'false'; break;
		}

		$colModel[] = "{
				name: '".$col['F_NAME']."',
				align: 'left',
				index: '".$col['F_NAME']."',
				hidden: ".$hidden.",
				".$formatter."
				search: false
		}";
	}
}

$colNames[] = "'Vorschaubild'";
$colModel[] = "{
	name: 'IMPORT_PIC',
	align: 'left',
	index: 'IMPORT_PIC'
}";

$tpl_content->addvar("ID_IMPORT_FILE", $importFileId);
$tpl_content->addvar("COL_NAMES", implode(",", $colNames));
$tpl_content->addvar("COL_MODELS", implode(",", $colModel));
$tpl_content->addvar("IMPORT_KAT_TABLE", $categoryTable);
$tpl_content->addvar("IMPORT_IDENT", strtoupper($importIdent));
$tpl_content->addvar("GOOGLE_API", $nar_systemsettings['SITE']['GOOGLE_API']);
$tpl_content->addvar("ALLOW_HTML", $nar_systemsettings['MARKTPLATZ']['ALLOW_HTML']);

// Anzeigenpakete
require_once $ab_path."sys/packet_management.php";
$packets = PacketManagement::getInstance($db);

$tpl_content->addvar("FREE_ADS", $nar_systemsettings["MARKTPLATZ"]["FREE_ADS"]);

$ar_required = array(PacketManagement::getType("ad_once") => 1);
$ar_required_abo = array(PacketManagement::getType("ad_abo") => 1);
$ar_packets = array_merge($packets->order_find_collections($uid, $ar_required), $packets->order_find_collections($uid, $ar_required_abo));

$tpl_content->addlist("liste_packets", $ar_packets, "tpl/".$s_lang."/my-imports-data.row-packets.htm");