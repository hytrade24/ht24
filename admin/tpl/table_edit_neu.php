<?php
/* ###VERSIONSBLOCKINLCUDE### */


$SILENCE=false;
require_once "sys/tabledef.php";
require_once $ab_path.'sys/lib.hdb.databasestructure.php';

$table = new tabledef();
$manufacturerDatabaseStructureManagement = ManufacturerDatabaseStructureManagement::getInstance($db);


$table->getTables(0, 1);

$tmp = array();
foreach($table->tables as $key => $value)
{
	if ( $key != "vendor_master" ) {
		$selected = ($_REQUEST['table'] == $key ? ' selected' : '');
		$tmp[] ='<option value="'.$key.'"'.$selected.'>'.stdHtmlentities($value['V1']).'</option>';
	}
}

$tpl_content->addvar("OPTS", implode("\n", $tmp));
unset($tmp);

if(count($_POST))
{
	$tpl_content->addvars($_POST);
	$err = array();

	if(empty($_POST['V1']))
	{
		$err[] = 'Bitte einen namen für die Tabelle angeben!';
	}
	else
	{
		$check = $db->fetch_atom("
			SELECT
				V1
			FROM
				string_app
			WHERE
				S_TABLE='table_def'
				AND V1='".sqlString($_POST['V1'])."'");
		if($check)
		{
			$err[] = "Dieser Name wird bereits verwendet!";
		}
	}

	if(empty($err))
	{
		//die(ht(dump($_POST)));
		// systemname der tabelle erstellen
		$n = 1;
		if (count($table->tables) > 0) {
			$tableCount = count($table->tables);
			$tableNames = array_keys($table->tables);
			foreach ($tableNames as $index => $name) {
				if (preg_match("/artikel_([0-9]{3})/", $name, $ar_matches)) {
					$tableIndex = (int)$ar_matches[1];
					if ($tableIndex >= $n) {
						$n = $tableIndex + 1;
					}
				}
			}
		}
		$num = sprintf('%03d', $n);
		$_POST['T_NAME_SHORT'] = $num;
		$_POST['T_NAME'] = "artikel_".$num;
		$_POST['STAMP_CREATE'] = $_POST['STAMP_UPDATE'] = date('Y-m-d H:i:s');
		$id = $db->update("table_def", $_POST);

		$table->getTable($_POST['table'], true);
		$table->make_copy($_POST['T_NAME'], $_POST['table'], $_POST['FELD']);

		if(empty($table->err))
		{
			$manufacturerDatabaseStructureManagement->createHdbTableFromArticleTable($id);
			die(forward("index.php?page=table_edit&table=".$id));
		}
		else
		{
			$tpl_content->addvar("err", implode("<br />", $table->err));
		}


	}	// no errors
	else
	{
		$tpl_content->addvar("err", implode("<br />", $err));
	}	// error occured
}

?>