<?php
/* ###VERSIONSBLOCKINLCUDE### */


require_once "sys/tabledef.php";
include 'sys/lib.import_export_filter.php';
include 'sys/lib.import.php';
require_once $ab_path.'sys/lib.hdb.databasestructure.php';

$table = new tabledef();
$import = new import();
$manufacturerDatabaseStructureManagement = ManufacturerDatabaseStructureManagement::getInstance($db);

$table->getTable($_REQUEST['table'], 1);
$fName = $_REQUEST['F_NAME'];

if ($fName != null) {
    $table->getFields();

    if (!is_array($table->tables[$table->table]['FIELDS'][$_REQUEST['F_NAME']])) {
        die("Das Feld konnte nicht gefunden werden!");
    } else {
        $table->deleteTableField($fName);
		$import->deleteTableFieldChange($fName, $_REQUEST['table']);
        $manufacturerDatabaseStructureManagement->deleteHdbTableField($fName, $_REQUEST['table']);


        // Erfolg - Cache neu schreiben
        include "../sys/lib.pub_kategorien.php";
		CategoriesBase::deleteCache();
		die("ok");
    }

}
die("Unbekannter Fehler!");