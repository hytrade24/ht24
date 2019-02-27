<?php
/* ###VERSIONSBLOCKINLCUDE### */



if(!is_object($table))
{
	require_once "sys/tabledef.php";
	$table = new tabledef();	
}

$table->getMaster();

if($_REQUEST['table'])
{	
	$table->getTable($_REQUEST['table']);	
	$table->getFields();

	$tpl_content->addlist("FIELDS", $table->ar_table['FIELDS'], "tpl/de/table_fields.row.htm");
}

?>