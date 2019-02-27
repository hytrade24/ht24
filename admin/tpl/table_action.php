<?php
/* ###VERSIONSBLOCKINLCUDE### */



require_once "sys/tabledef.php";
include 'sys/lib.import_export_filter.php';
include 'sys/lib.import.php';
require_once $ab_path.'sys/lib.hdb.databasestructure.php';

$table = new tabledef();
$import = new import();
$manufacturerDatabaseStructureManagement = ManufacturerDatabaseStructureManagement::getInstance($db);


$msg = 'ERR';
$text = $err =  array();
$table->getTable($_REQUEST['table']);

switch($_REQUEST['ACT'])
{
	case "FIELD_EDIT":
		#echo ht(dump($_REQUEST));
		$_REQUEST['V1'] = $_REQUEST['V1'];
		$_REQUEST['V2'] = $_REQUEST['V2'];
		$_REQUEST['T1'] = $_REQUEST['T1_DESC']."||".$_REQUEST['T1_HELP'].
			"§§§".$_REQUEST['T2_DESC']."||".$_REQUEST['T2_HELP'].
			"§§§".$_REQUEST['T3_DESC']."||".$_REQUEST['T3_HELP'];
		$_REQUEST['ITEMS'] = $_REQUEST['ITEMS'];
		#die(ht(dump($_REQUEST)));

		if(!$_REQUEST['B_SEARCH'])
		{
			$_REQUEST['B_SEARCH'] = 0;
		}
		if(!$_REQUEST['B_NEEDED'])
		{
			$_REQUEST['B_NEEDED'] = 0;
		}
		if(!$_REQUEST['B_HDB_ENABLED'])
		{
			$_REQUEST['B_HDB_ENABLED'] = 0;
		}
		if($_REQUEST['F_TYP'] == 'LIST' || $_REQUEST['F_TYP'] == 'VARIANT')
		{
			if($_REQUEST['FK_LISTE'] == 'NEW' && empty($_REQUEST['ITEMS']))
			{
				$err[] = "Bitte geben Sie mind. einen Listenwert an!";
			}
			if(!$_REQUEST['FK_LISTE'])
			{
				$err[] = "Bitte w&auml;hlen Sie eine Auswahlliste, oder erstellen Sie eine neue!";
			}
		}	// field type is list
		if(!$_REQUEST['table'])
		{
			$err[] = "Keine Tabelle erkannt! Bitte Fenster schlie&szlig;en und einen neuen Versuch starten!";
		}
		if(isset($_REQUEST['SQL_FIELD']) && !preg_match("/^([_a-zA-Z0-9]+)$/", $_REQUEST['SQL_FIELD'])) {
			$err[] = "Der SQL Feld Name darf nur aus Buchstaben, Zahlen und Unterstrichen bestehen";
		}
		if(!$_REQUEST['V1'])
		{
			$err[] = "Kein Name f&uuml;r das Feld gew&auml;hlt!";
		}
		else
		{
			$check = $db->fetch_atom($q="
				SELECT
					s.FK
				FROM
					string_field_def s
				JOIN
					field_def f on s.FK=f.ID_FIELD_DEF
				JOIN
					table_def t on f.FK_TABLE_DEF=t.ID_TABLE_DEF
				WHERE
					s.V1='".sqlString($_REQUEST['V1'])."'
					AND s.FK <> ".(int)$_REQUEST['ID_FIELD_DEF']);
			$check = 0;
			if($check > 0)
			{
				$err[] = "Der Name wird in dieser Tabelle bereits verwendet!";
			}
		}
		if(!$_REQUEST['F_TYP'])
		{
			$err[] = "Kein Feldtyp gew&auml;hlt!";
		}
		if(empty($err))
		{
			$id_field = $table->saveField($_REQUEST);

			if(!empty($table->err))
			{
				$err = $table->err;
			} else {
				$import->updateTableFieldChange($_REQUEST);
				$manufacturerDatabaseStructureManagement->updateHdbTableFieldChange($_REQUEST);

                if (!($_REQUEST["ID_FIELD_DEF"] > 0)) {
                    $ar_katlist = array_keys(
                        $db->fetch_nar("SELECT ID_KAT FROM `kat` WHERE KAT_TABLE='".mysql_real_escape_string($_REQUEST['table'])."'")
                    );
                    $ar_kat2field = array();
                    if (isset($_REQUEST["INITIALLY_ENABLED"])) {
                        foreach ($ar_katlist as $index => $katId) {
                            $ar_kat2field[] = "(".$katId.", ".$id_field.", 1, ".$_REQUEST['B_NEEDED'].", ".($_REQUEST['B_SEARCH'] > 0 ? 1 : 0).")";
                        }
                    } else {
                        foreach ($ar_katlist as $index => $katId) {
                            $ar_kat2field[] = "(".$katId.", ".$id_field.", 0, ".$_REQUEST['B_NEEDED'].", ".($_REQUEST['B_SEARCH'] > 0 ? 1 : 0).")";
                        }
                    }
                    $query = "INSERT IGNORE INTO `kat2field` (FK_KAT, FK_FIELD, B_ENABLED, B_NEEDED, B_SEARCHFIELD) ".
                        "VALUES \n  ".implode(",\n   ", $ar_kat2field);
                    $db->querynow($query);
                }
			}
		}	// no error -> saveField()
		break;
	default:
		$err[] = "Keine Aktion f&uuml;r ".stdHtmlentities($_REQUEST['ACT'])." gefunden!";
}	// switch action

if(!empty($err))
{
	die(implode("<br>", $err));
}
else
{
	die("OK");
}

?>