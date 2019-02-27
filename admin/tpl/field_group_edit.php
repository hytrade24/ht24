<?php
/* ###VERSIONSBLOCKINLCUDE### */


$id_table = (int)($_REQUEST['table'] ? $_REQUEST['table'] : $_REQUEST['FK_TABLE_DEF']);
$SILENCE=false;
require_once "sys/tabledef.php";
$table = new tabledef();

if($id_table)
{
	$table->getTableById((int)$id_table, true);
	#$tpl_content->addvars($table->ar_table);
	$tpl_content->addvar("FK_TABLE_DEF", $id_table);
}

if (!empty($_POST)) {
	// Abgeschickt
	$id_field_group = (int)$_REQUEST['ID_FIELD_GROUP'];
	if ($id_field_group > 0) {
		// Berarbeiten
		$ar_selected = $_POST['FELD'];
		if (is_array($ar_selected) && !empty($ar_selected)) {
			foreach ($ar_selected as $index => $value) {
				$ar_selected[$index] = (int)$value;
			}
		} else {
			$ar_selected = array();
		}
		$db->querynow("UPDATE `field_def` SET FK_FIELD_GROUP=NULL WHERE FK_FIELD_GROUP=".$id_field_group);
		if (!empty($ar_selected)) {
			$db->querynow("UPDATE `field_def` SET FK_FIELD_GROUP=".$id_field_group." WHERE ID_FIELD_DEF IN (".implode(",", $ar_selected).")");
		}

		$id_field_group = $db->update("field_group", $_POST);

		die(forward("index.php?page=field_group_edit&ok=1&frame=ajax&table=".$_POST['FK_TABLE_DEF']."&ID_FIELD_GROUP=".$id_field_group));
	} else {
		// Neu
		$err = array();
		if(empty($_POST['V1'])) {
			$err[] = "Bitte einen Namen angeben!";
		}
		$check = $db->fetch_atom("
			SELECT s.FK
			FROM `string_app` s
			LEFT JOIN `field_group` f ON
				s.FK=f.ID_FIELD_GROUP AND s.S_TABLE='field_group' AND s.V1='".sqlString($_POST['V1'])."'
					AND s.BF_LANG=if(f.BF_LANG_APP & ".$langval.", ".$langval.", 1 << floor(log(f.BF_LANG_APP+0.5)/log(2)))
			WHERE
				f.FK_TABLE_DEF=".(int)$id_table);
		if($check && ($check != $_POST['ID_FIELD_GROUP'])) {
			$err[] = "Eine Gruppe mit diesem namen haben Sie für diese Tabelle bereits angelegt!";
		}
		if(!empty($err)) {
			$tpl_content->addvar("err", implode("<br />", $err));
			$tpl_content->addvars($_POST);
		} else {
			if(!is_array($_POST['FELD'])) {
				$_POST['FELD'] = array();
			}
			$_POST['SER_FIELDS'] = serialize($_POST['FELD']);
			$ins = array();
			for($i=0; $i<count($_POST['FELD']); $i++) {
				$ins[] = "(".$_POST['FELD'][$i].", ".$_POST['ID_FIELD_GROUP'].")";
			}
			$db->querynow("
				DELETE FROM
					field2group
				WHERE
					FK_FIELD_GROUP=".$_POST['ID_FIELD_GROUP']);
			if(count($ins)) {
				$db->querynow("
					INSERT INTO
						field2group
					VALUES
						".implode(",", $ins));
			}
			$id_field_group = $db->update("field_group", $_POST);
			$ar_selected = $_POST['FELD'];
			foreach ($ar_selected as $index => $value) {
				$ar_selected[$index] = (int)$value;
			}
			$db->querynow("UPDATE `field_def` SET FK_FIELD_GROUP=".$id_field_group." WHERE ID_FIELD_DEF IN (".implode(",", $ar_selected).")");
			die(forward("index.php?page=field_group_edit&ok=1&frame=ajax&table=".$_POST['FK_TABLE_DEF']."&ID_FIELD_GROUP=".$id_field_group));
		}
	}
}

if($_REQUEST['ID_FIELD_GROUP'])
{
	if(count($_POST) && is_array($_POST['FELD']))
	{
		$ar_selected = $_POST['FELD'];
	}
	else
	{
		$ar_selected = array();
		$res = $db->querynow("
			SELECT
				FK_FIELD_DEF
			FROM
				field2group
			WHERE
				FK_FIELD_GROUP=".(int)$_REQUEST['ID_FIELD_GROUP']);
		while($row = mysql_fetch_row($res['rsrc']))
		{
			$ar_selected[] = $row[0];
		}
	}
	$table->getFields();
	$f_list = $table->tables[$table->table]['FIELDS_ORDERED'];
	$tpl_content->addlist("f_list", $f_list, 'tpl/de/field_group_edit.row.htm');
}
if(count($_POST))
{
	$err = array();
	if(empty($_POST['V1'])) {
		$err[] = "Bitte einen Namen angeben!";
	}
	$check = $db->fetch_atom("
		SELECT
			FK
		FROM
			string_app
		WHERE
			S_TABLE='field_group'
			AND V1='".sqlString($_POST['V1'])."'");
	if($check && ($check != $_POST['ID_FIELD_GROUP'])) {
		$err[] = "Eine Gruppe mit diesem namen haben Sie für diese Tabelle bereits angelegt!";
	}
	if(!empty($err)) {
		$tpl_content->addvar("err", implode("<br />", $err));
		$tpl_content->addvars($_POST);
	} else {
		if(!is_array($_POST['FELD']))
		{
			$_POST['FELD'] = array();
		}
		$_POST['SER_FIELDS'] = serialize($_POST['FELD']);
		$ins = array();
		for($i=0; $i<count($_POST['FELD']); $i++)
		{
			$ins[] = "(".$_POST['FELD'][$i].", ".$_POST['ID_FIELD_GROUP'].")";
		}
		$db->querynow("
			DELETE FROM
				field2group
			WHERE
				FK_FIELD_GROUP=".$_POST['ID_FIELD_GROUP']);
		if(count($ins))
		{
			$db->querynow("
				INSERT INTO
					field2group
				VALUES
					".implode(",", $ins));
		}
		$id = $db->update("field_group", $_POST);
		if($_POST['ID_FIELD_GROUP'])
		{
			$id = $_POST['ID_FIELD_GROUP'];
		}
		die(forward("index.php?page=field_group_edit&ok=1&frame=ajax&table=".$_POST['FK_TABLE_DEF']."&ID_FIELD_GROUP=".$id));
	}
}
else
{
	if($_REQUEST['ID_FIELD_GROUP'])
	{
		$sql = "select
			t.*,
			s.V1,
			s.V2,
			s.T1
		from
			`field_group` t
		left join
			string_app s on s.S_TABLE='field_group'
			and s.FK=t.ID_FIELD_GROUP
			and s.BF_LANG=if(t.BF_LANG_APP & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_APP+0.5)/log(2)))
		WHERE
			ID_FIELD_GROUP=".$_REQUEST['ID_FIELD_GROUP'];
		$ar_group = $db->fetch1($sql);
		#echo ht(dump($ar_group));
		$tpl_content->addvars($ar_group);
	}
}
if($_REQUEST['ok'])
{
	$tpl_content->addvar("ok", 1);
}
?>