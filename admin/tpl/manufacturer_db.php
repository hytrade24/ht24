<?php

require_once $ab_path . 'sys/lib.cache.adapter.php';
require_once $ab_path . 'sys/lib.hdb.php';
require_once $ab_path . 'sys/lib.hdb.merge.php';
$cacheAdapter = new CacheAdapter();
$manufacturerDatabaseManagement = ManufacturerDatabaseManagement::getInstance($db);
$manufacturerMergeManagement = ManufacturerMergeManagement::getInstance($db);


if(isset($_REQUEST['DO_MASSCONFIRM']) && !empty($_REQUEST['SELECT_MANUFACTURER'])) {
	foreach($_REQUEST['SELECT_MANUFACTURER'] as $key => $selectManufacturerId) {
		$manufacturerDatabaseManagement->updateManufacturerById($selectManufacturerId, array(
			'CONFIRMED' => 1
		));

	}
	$tpl_content->addvar("success_mass", count($_REQUEST['SELECT_MANUFACTURER']));
}
if(isset($_REQUEST['DO_MASSUNCONFIRM']) && !empty($_REQUEST['SELECT_MANUFACTURER'])) {
	foreach($_REQUEST['SELECT_MANUFACTURER'] as $key => $selectManufacturerId) {
		$manufacturerDatabaseManagement->updateManufacturerById($selectManufacturerId, array(
			'CONFIRMED' => 0
		));

	}
	$tpl_content->addvar("success_mass", count($_REQUEST['SELECT_MANUFACTURER']));
}
if(isset($_REQUEST['DO_MASSDELETE']) && !empty($_REQUEST['SELECT_MANUFACTURER'])) {
	foreach($_REQUEST['SELECT_MANUFACTURER'] as $key => $selectManufacturerId) {
		$manufacturerDatabaseManagement->deleteManufacturerById((int)$selectManufacturerId);
	}
	$tpl_content->addvar("success_delete", count($_REQUEST['SELECT_MANUFACTURER']));
}

if(isset($_REQUEST['DO_START_MERGE']) && $_REQUEST['DO_START_MERGE'] == 1) {
	$manufacturerMergeManagement->startManufacturerMerge();
}
if(isset($_REQUEST['DO_CANCEL_MERGE']) && $_REQUEST['DO_CANCEL_MERGE'] == 1) {
	$manufacturerMergeManagement->cancelManufacturerMerge();
}
if(isset($_REQUEST['DO_ADDMERGETOOL']) && !empty($_REQUEST['SELECT_MANUFACTURER'])) {
	foreach($_REQUEST['SELECT_MANUFACTURER'] as $key => $selectManufacturerId) {
		$manufacturer = $manufacturerDatabaseManagement->fetchManufacturerById($selectManufacturerId);
		$manufacturerMergeManagement->addManufacturerMergeData($manufacturer);
	}
}
if(isset($_REQUEST['DO_HDB_MERGE_DELETE']) && !empty($_REQUEST['SELECT_MANUFACTURER'])) {
	foreach($_REQUEST['SELECT_MANUFACTURER'] as $key => $selectManufacturerId) {
		$manufacturerMergeManagement->removeManufacturerMergeData($selectManufacturerId);
	}
}
if(isset($_REQUEST['DO_HDB_MERGE_RUN']) && !empty($_REQUEST['HDB_MERGE_MANUFACTURER_MAINENTRY'])) {
	$manufacturerMergeManagement->runManufacturerMerge($_REQUEST['HDB_MERGE_MANUFACTURER_MAINENTRY']);

	$tpl_content->addvar('success_merge', 1);
}

$perpage = 30; // Elemente pro Seite
$limit = ((($_REQUEST['npage'] ? $_REQUEST['npage'] : $_REQUEST['npage'] = 1) - 1) * $perpage);


$searchWhere = "";
if(isset($_REQUEST['SEARCH_NAME']) && $_REQUEST['SEARCH_NAME'] != "") {
	$searchWhere .= " AND NAME LIKE '%".mysql_real_escape_string(trim($_REQUEST['SEARCH_NAME']))."%' ";
	$tpl_content->addvar('SEARCH_NAME', $_REQUEST['SEARCH_NAME']);
}
if(isset($_REQUEST['SEARCH_ID_MAN']) && (int)$_REQUEST['SEARCH_ID_MAN'] > 0) {
	$searchWhere .= " AND ID_MAN = '".mysql_real_escape_string((int)$_REQUEST['SEARCH_ID_MAN'])."' ";
	$tpl_content->addvar('SEARCH_ID_MAN', $_REQUEST['SEARCH_ID_MAN']);
}

if (!isset($_REQUEST["unconfirmed"])) {
	$all = $db->fetch_atom("SELECT count(*) FROM manufacturers WHERE CONFIRMED=1");
	// Seitenzähler hinzufügen
	$tpl_content->addvar("pager", htm_browse($all, $_REQUEST['npage'], "index.php?page=manufacturer_db&npage=", $perpage));

	$manufacturers = $db->fetch_table('SELECT * FROM manufacturers WHERE CONFIRMED=1 '.$searchWhere.' ORDER BY `NAME` LIMIT ' . $limit . ',' . $perpage . '');
} else {
	$tpl_content->addvar("unconfirmed", 1);
	$all = $db->fetch_atom("SELECT count(*) FROM manufacturers WHERE CONFIRMED=0");
	// Seitenzähler hinzufügen
	$tpl_content->addvar("pager", htm_browse($all, $_REQUEST['npage'], "index.php?page=manufacturer_db&unconfirmed=1&npage=", $perpage));

	$manufacturers = $db->fetch_table('SELECT *
        FROM manufacturers WHERE CONFIRMED=0 '.$searchWhere.'  ORDER BY `NAME` LIMIT ' . $limit . ',' . $perpage . '');
}



$tpl_content->addlist("liste", $manufacturers, "tpl/de/manufacturer_db.row.htm");

if($manufacturerMergeManagement->isActiveManufacturerMerge()) {
	$tpl_content->addvar('HDB_MERGE_MANUFACTURER_SESSION_ACTIVE', 1);
	if($manufacturerMergeManagement->hasManufacturerMergeData()) {
		$tpl_content->addlist('HDB_MERGE_MANUFACTURER_DATA', $manufacturerMergeManagement->getManufacturerMergeData(), 'tpl/de/manufacturer_db.merge.row.htm');
	}
}


?>