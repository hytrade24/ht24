<?php
/* ###VERSIONSBLOCKINLCUDE### */

$id_table = (int)($_REQUEST['table'] ? $_REQUEST['table'] : $_REQUEST['FK_TABLE_DEF']);
$SILENCE=false;
require_once "sys/tabledef.php";
$table = new tabledef();
$SILENCE=false;

$tpl_content->addvar("ID_TABLE_DEF", $id_table);

if (!empty($_POST)) {
	// Abgeschickt
	$id_field_group = (int)$_REQUEST['ID_FIELD_TAB'];
	if ($id_field_group > 0) {
		// Berarbeiten
		$ar_selected = (is_array($_POST['GRUPPE']) ? $_POST['GRUPPE'] : array());
		foreach ($ar_selected as $index => $value) {
			$ar_selected[$index] = (int)$value;
		}
		$db->querynow("UPDATE `field_group` SET FK_FIELD_TAB=NULL WHERE FK_FIELD_TAB=".$id_field_group);
        if (!empty($ar_selected)) {
            $db->querynow("UPDATE `field_group` SET FK_FIELD_TAB=".$id_field_group." WHERE ID_FIELD_GROUP IN (".implode(",", $ar_selected).")");
        }

		$id_field_group = $db->update("field_tab", $_POST);

		die(forward("index.php?page=field_tab_edit&ok=1&frame=ajax&table=".$id_table."&ID_FIELD_TAB=".$id_field_group));
	} else {
		// Neu
		$err = array();
		if(empty($_POST['V1'])) {
			$err[] = "Bitte einen Namen angeben!";
		}
		$check = $db->fetch_atom("
			SELECT s.FK
			FROM `string_field_tab` s
			LEFT JOIN `field_tab` f ON
				s.FK=f.ID_FIELD_TAB AND s.S_TABLE='field_tab'
					AND s.BF_LANG=if(f.BF_LANG_FIELD_TAB & ".$langval.", ".$langval.", 1 << floor(log(f.BF_LANG_FIELD_TAB+0.5)/log(2)))
			WHERE
				s.V1='".sqlString($_POST['V1'])."'");
		if($check && ($check != $_POST['ID_FIELD_TAB'])) {
			$err[] = "Einen Schritt mit diesem Namen haben Sie bereits angelegt!";
		}
		if(!empty($err)) {
			$tpl_content->addvar("err", implode("<br />", $err));
			$tpl_content->addvars($_POST);
		} else {
			if(!is_array($_POST['GRUPPE'])) {
				$_POST['GRUPPE'] = array();
			}
            $_POST['FK_TABLE_DEF'] = $id_table;
            $_POST['SER_FIELDS'] = serialize($_POST['GRUPPE']);
			$id_field_group = $db->update("field_tab", $_POST);
			$ar_selected = $_POST['GRUPPE'];
			foreach ($ar_selected as $index => $value) {
				$ar_selected[$index] = (int)$value;
			}
			$db->querynow("UPDATE `field_group` SET FK_FIELD_TAB=".$id_field_group." WHERE ID_FIELD_GROUP IN (".implode(",", $ar_selected).")");
			die(forward("index.php?page=field_tab_edit&ok=1&frame=ajax&table=".$id_table."&ID_FIELD_TAB=".$id_field_group));
		}
	}
}

if($_REQUEST['ID_FIELD_TAB'])
{
	if(count($_POST) && is_array($_POST['GRUPPE']))
	{
		$ar_selected = $_POST['GRUPPE'];
	}
	else
	{
		$ar_selected = array();
		$res = $db->querynow("
			SELECT
				ID_FIELD_GROUP
			FROM
				field_group
			WHERE
				FK_FIELD_TAB=".(int)$_REQUEST['ID_FIELD_TAB']);
		while($row = mysql_fetch_row($res['rsrc']))
		{
			$ar_selected[] = $row[0];
		}
	}
	$g_list = $db->fetch_table($q = "
        select
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
            FK_TABLE_DEF=".$_REQUEST['table']."
        ORDER BY t.F_ORDER ASC");
    foreach ($g_list as $g_index => $arField) {
        if (($arField['FK_FIELD_TAB'] > 0) && ($arField['FK_FIELD_TAB'] != $_REQUEST['ID_FIELD_TAB'])) {
            $sql = "SELECT s.V1 FROM `field_tab` t
                LEFT JOIN string_field_tab s ON s.S_TABLE='field_tab' AND s.FK=t.ID_FIELD_TAB
                    AND s.BF_LANG=if(t.BF_LANG_FIELD_TAB & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_FIELD_TAB+0.5)/log(2)))
                WHERE ID_FIELD_TAB=".$arField['FK_FIELD_TAB'];
            $g_list[$g_index]['FIELD_TAB'] = $db->fetch_atom($sql);
        }
    }

    $tpl_content->addlist("g_list", $g_list, 'tpl/de/field_tab_edit.row.htm');
}
if(empty($_POST)) {
	if($_REQUEST['ID_FIELD_TAB']) {
		$sql = "select
            t.*,
            s.V1,
            s.V2,
            s.T1
        from
            `field_tab` t
        left join
            string_field_tab s on s.S_TABLE='field_tab'
            and s.FK=t.ID_FIELD_TAB
            and s.BF_LANG=if(t.BF_LANG_FIELD_TAB & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_FIELD_TAB+0.5)/log(2)))
		WHERE
			ID_FIELD_TAB=".$_REQUEST['ID_FIELD_TAB'];
		$ar_tab = $db->fetch1($sql);
		#echo ht(dump($ar_group));
		$tpl_content->addvars($ar_tab);
	}
}
if($_REQUEST['ok'])
{
	$tpl_content->addvar("ok", 1);
}
?>