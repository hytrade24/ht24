<?php
/* ###VERSIONSBLOCKINLCUDE### */


$SILENCE=false;
include 'sys/lib.import_export_filter.php';
include 'sys/lib.import.php';

$import = new import();

if($_REQUEST['ok'] == 1) {
	$tpl_content->addvar("ok", 1);
}

if(count($_POST)) {
	if(!$_POST['ID_IMPORT_FILTER']) {
		$create = $import->create_filter($_POST);
		#echo var_export($create);
		if(!$create) {
			$import->rollback_create();
			$_POST['err'] = implode("<br />", $import->error);
			$tpl_content->addvars($_POST);
		} else {
			die(forward("index.php?page=importfilter_edit&ok=1&ID_IMPORT_FILTER=".$create));
		}
	} else {
		if(!$_POST['B_AKTIV']) {
			$_POST['B_AKTIV'] = 0;
		}
		$import->change_table($_POST['ID_IMPORT_FILTER'], $_POST['FK_TABLE_DEF']);
		$db->update("import_filter", $_POST);
		die(forward("index.php?page=importfilter_edit&ok=1&ID_IMPORT_FILTER=".$_POST['ID_IMPORT_FILTER']));
	}
} elseif($id_import = (int)$_REQUEST['ID_IMPORT_FILTER']) {
	$ar = $import->get_filter_data($id_import);
	$tpl_content->addvars($ar);
} else {
	$tpl_content->addvar("TRENNER", ';');
}

?>