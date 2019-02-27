<?php
/* ###VERSIONSBLOCKINLCUDE### */


$SILENCE=false;
require_once "sys/tabledef.php";
$table = new tabledef();

if (array_key_exists("table", $_REQUEST)) {
	$table->getTableById((int)$_REQUEST['table'], true);
} else {
	$table->getTable($_REQUEST['table_name'], false);
}
#echo ht(dump($table));die();
$tpl_content->addvar("table", $table->table);

if($_REQUEST['order'])
{
	$table->reorder($_REQUEST['id'], $_REQUEST['order']);
	die(forward("index.php?page=table_edit&table=".$_REQUEST['table']));
}

if($_REQUEST['repair'])
{
	$table->repair_order($_REQUEST['table']);
	die(forward("index.php?page=table_edit&table=".$_REQUEST['table']));
}

if($_REQUEST['translationsFromMaster']) {
	// Copy translations
	$db->querynow("
		REPLACE INTO `string_field_def`
			(S_TABLE, FK, BF_LANG, T1, V1, V2)
		SELECT 'field_def' AS S_TABLE, fa.ID_FIELD_DEF AS FK, sfm.BF_LANG, sfm.T1, sfm.V1, sfm.V2
			FROM `field_def` fa
			JOIN `field_def` fm ON fa.F_NAME=fm.F_NAME AND fm.FK_TABLE_DEF=1
			JOIN `string_field_def` sfm ON sfm.S_TABLE='field_def' AND sfm.FK=fm.ID_FIELD_DEF
			WHERE fa.FK_TABLE_DEF=".(int)$_REQUEST['table']);
	// Update language bitfields
	$db->querynow("
		UPDATE `field_def`
		SET BF_LANG_FIELD_DEF=(SELECT SUM(BF_LANG) FROM `string_field_def` WHERE S_TABLE='field_def' AND FK=ID_FIELD_DEF)
		WHERE FK_TABLE_DEF=".(int)$_REQUEST['table']);
	die(forward("index.php?page=table_edit&table=".$_REQUEST['table']));
}

if(count($_POST['ORDERS']))
{
	$adTableManagment = Ad_Table_AdTableManagement::getInstance($db);
	
	$orders = $_POST['ORDERS'];
	echo ht(dump($_POST["ENABLED"]));
	foreach($orders as $id => $f_order)
	{
		$db->querynow("
			UPDATE
				field_def
			SET
				B_ENABLED=".($_POST['ENABLED'][$id] ? 1 : 0).",
				F_ORDER=".(int)$f_order."
			WHERE
				ID_FIELD_DEF=".(int)$id);
		if ($_POST['ENABLED'][$id]) {
			$adTableManagment->createFieldMapping($id);
		}
	}
	die(forward("index.php?page=table_edit&table=".$_REQUEST['table']));
}
if (isset($_POST['DESC_NAME']) && ($table->ar_table['V1'] != $_POST['DESC_NAME'])) {
	$is_known = $db->fetch_atom("SELECT count(*) FROM `string_app`
			WHERE S_TABLE='table_def' AND FK=".(int)$_REQUEST['table']." AND BF_LANG=".(int)$langval);
	if ($is_known) {
		// Name geändert
		$db->querynow("UPDATE `string_app`
				SET V1='".mysql_real_escape_string($_POST['DESC_NAME'])."'
				WHERE S_TABLE='table_def' AND FK=".(int)$_REQUEST['table']." AND BF_LANG=".(int)$langval);
	} else {
		// Name/Übersetzung hinzugefügt
		$db->querynow("INSERT INTO `string_app` (S_TABLE, FK, BF_LANG, V1)
				VALUES ('table_def', ".(int)$_REQUEST['table'].", ".(int)$langval.",
					'".mysql_real_escape_string($_POST['DESC_NAME'])."');");
	}
	$db->querynow("UPDATE `table_def` SET BF_LANG_APP=(BF_LANG_APP|".(int)$langval.")
      WHERE ID_TABLE_DEF=".(int)$_REQUEST['table'].";");
	die(forward("index.php?page=table_edit&table=".$_REQUEST['table']));
}

if($table->table == 'artikel_master')
{
	$tpl_content->addvar("IS_MASTER", 1);
}

$table->getFields();
$tpl_content->addvars($table->ar_table);

$all = count($table->tables[$table->table]['FIELDS_ORDERED']);
$counter = 0;

function is_last(&$row, $i)
{
	global $counter, $all;
	$counter += 1;
	if($counter == 1)
	{
		$row['is_first'] = 1;
	}
	if($counter == $all)
	{
		$row['is_last'] = 1;
	}
}

$tpl_content->addlist("fields", $table->tables[$table->table]['FIELDS_ORDERED'], "tpl/de/table_edit.field.htm", 'is_last');
#echo ht(dump($table->tables));

?>