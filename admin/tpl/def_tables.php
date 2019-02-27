<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path.'sys/lib.hdb.databasestructure.php';
$manufacturerDatabaseStructureManagement = ManufacturerDatabaseStructureManagement::getInstance($db);

function get_categories(&$row, $i) {
	global $db, $langval;
	$query = "
		SELECT
			k.ID_KAT, s.V1
		FROM `kat` k
		LEFT JOIN `string_kat` s ON
    		s.S_TABLE='kat' AND s.FK=k.ID_KAT AND
    		s.BF_LANG=if(k.BF_LANG_KAT & ".$langval.", ".$langval.", 1 << floor(log(k.BF_LANG_KAT+0.5)/log(2)))
		WHERE
			k.ROOT=1 AND k.KAT_TABLE='".mysql_escape_string($row['T_NAME'])."' AND k.PARENT=1";
	$ar_categories = array_values($db->fetch_nar($query));
	$row["CATEGORIES"] = implode(", ", $ar_categories);
	$row["CATEGORIES_COUNT"] = count($ar_categories);
}

require_once "sys/tabledef.php";
$table = new tabledef();

if($_REQUEST['del'])
{
	$tableDef = $db->fetch1("SELECT * FROM table_def WHERE ID_TABLE_DEF = '".(int)$_REQUEST['del']."'");
	$tableDefName = $tableDef['T_NAME'];

	$result = $table->delTable($_REQUEST['del']);
	if($result) {
		$manufacturerDatabaseStructureManagement->deleteHdbTableByTableDefId($tableDefName);
	}
}
if(!empty($table->err))
{
	$tpl_content->addvar("err", implode("<br>", $table->err));
}
elseif($_REQUEST['del'] > 1)
{
	die(forward("index.php?page=def_tables&deleted=".$_REQUEST['del']));
}

$table->getTables(0, 1);
/*echo '<pre>';
var_dump($table->tables);
echo '<pre>';*/
$tpl_content->addlist("liste", $table->tables, "tpl/de/def_tables.row.htm", "get_categories");

#echo $db->lang_select('field_def');die();

?>