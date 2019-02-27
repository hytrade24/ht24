<?php
/* ###VERSIONSBLOCKINLCUDE### */



require_once "sys/tabledef.php";
$table = new tabledef();

$table->getTable($_REQUEST['table'],1);
$tpl_content->addvar("table", $table->table);
$tpl_content->addvar("ISMASTER", ($table->table == 'artikel_master' ? 1 : 0));
$tpl_content->addvar("IS_MASTER", ($table->table == 'artikel_master' ? 1 : 0));
$tpl_content->addvar("USE_EAN", $nar_systemsettings['MARKTPLATZ']['USE_PRODUCT_DB']);
$f_typ = NULL;


if($_REQUEST['F_NAME'])
{
	$table->getFields();
	if(!is_array($table->tables[$table->table]['FIELDS'][$_REQUEST['F_NAME']]))
	{
		#echo ht(dump($table->tables));
		die("Das Feld konnte nicht gefunden werden!");
	}
	else
	{
		if (isset($_REQUEST['ACTION'])) {
			// Quick-edit options
			$success = false;
			$ar_kats_changed = array();
			switch ($_REQUEST['ACTION']) {
				case 'enable_global':
					$query = "SELECT * FROM `kat` WHERE ROOT=1";
					if ($table->table != 'artikel_master') {
						$query .= " AND KAT_TABLE='".mysql_real_escape_string($table->table)."'";
					}
					$ar_kats = $db->fetch_table($query);
					$querys = array();
					foreach ($ar_kats as $index => $ar_kat) {
						if (!isset($table->tables[$ar_kat['KAT_TABLE']]['FIELDS'])) {
							$table->getTable($ar_kat['KAT_TABLE'], 1);
							$table->getFields();
						}
						$ar_field = $table->tables[$ar_kat['KAT_TABLE']]['FIELDS'][$_REQUEST['F_NAME']];
						if (!empty($ar_field)) {
							$query = "INSERT INTO `kat2field` (`FK_KAT`, `FK_FIELD`, `B_ENABLED`, `B_NEEDED`, `B_SEARCHFIELD`)
									VALUES (".(int)$ar_kat["ID_KAT"].", ".(int)$ar_field['ID_FIELD_DEF'].", 1, ".(int)$ar_field['B_NEEDED'].", ".(int)$ar_field['B_SEARCHFIELD'].")
									ON DUPLICATE KEY UPDATE `B_ENABLED`=1;";
							//var_dump($query);
							$db->querynow($query);
							$ar_kats_changed[] = (int)$ar_kat["ID_KAT"];
						} else {
							//var_dump($ar_kat);
						}
					}
					$success = true;
					break;
				case 'disable_global':
					$ar_field_ids = array_keys($db->fetch_nar("SELECT ID_FIELD_DEF FROM `field_def` WHERE F_NAME='".mysql_real_escape_string($_REQUEST['F_NAME'])."'"));
					$ar_kats_changed = array_keys($db->fetch_nar("SELECT FK_KAT FROM `kat2field` WHERE FK_FIELD IN (".implode(", ", $ar_field_ids).")"));
					$db->querynow("UPDATE `kat2field` SET B_ENABLED=0 WHERE FK_FIELD IN (".implode(", ", $ar_field_ids).")");
					$db->querynow("UPDATE `field_def` SET B_ENABLED=0 WHERE F_NAME='".mysql_real_escape_string($_REQUEST['F_NAME'])."'");
					$success = true;
					break;
			}

			if ($success) {
				include $ab_path."sys/lib.pub_kategorien.php";
				if ($table->table != 'artikel_master') {
					CategoriesBase::deleteCache();
				} else {
					foreach ($ar_kats_changed as $index => $id_kat) {
						CategoriesBase::deleteCache($id_kat);
					}
				}
			}

			header('Content-type: application/json');
			die(json_encode(array(
					"success"	=> ($success ? 1 : 0)
			)));
		} else {
			#echo ht(dump($table->tables[$table->table]['FIELDS'][$_REQUEST['F_NAME']]));
			$arField = $table->tables[$table->table]['FIELDS'][$_REQUEST['F_NAME']];
			#die(var_dump($arField));
			$tpl_content->addvars($arField);
			$f_typ = $table->tables[$table->table]['FIELDS'][$_REQUEST['F_NAME']]['F_TYP'];
			$changeable = ($table->tables[$table->table]['Rows'] > 0 ? 0 : 1);
			$tpl_content->addvar("CHANGEABLE", $changeable);
			### Warnings if master
			if($table->table == 'artikel_master')
			{
				$table->checkMasterData($_REQUEST['F_NAME']);
				$tpl_content->addvar("WARNINGS", $table->warning_data);
			}
		}
	}
} else {
	if($table->table != "artikel_master") {
		$index_next = 1;
		$table->getFields();
		foreach ($table->ar_table['FIELDS'] as $field_name => $ar_field) {
			if (preg_match("/^ARTIKEL_([0-9]{3,})$/i", $field_name, $ar_matches)) {
				$index = (int)$ar_matches[1];
				if ($index >= $index_next) $index_next = $index + 1;
			}
		}
		$tpl_content->addvar('SUGGESTION_F_NAME', 'ARTIKEL_'.sprintf("%03d", $index_next));
	}
}

// field types
$selected = NULL;
$tmp = array();
foreach($table->ar_field_types as $key => $ar)
{
	$selected = ($f_typ == $key ? ' selected' : '');
	$tmp[] = '<option value="'.$key.'" '.$selected.'>'.stdHtmlentities($ar['DESC']).'</option>';
}
$tpl_content->addvar("field_types", implode("\n", $tmp));

?>