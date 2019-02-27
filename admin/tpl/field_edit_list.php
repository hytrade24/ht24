<?php
/* ###VERSIONSBLOCKINLCUDE### */



$selected_value = ($_REQUEST['FK'] ? (int)$_REQUEST['FK'] : 0);
$selected_list = null;

### globale Listen
$res = $db->querynow("
	SELECT
		ID_LISTE, LIST_GLOBAL, NAME
	from
		liste
	WHERE
		LIST_GLOBAL = 1
		OR FK_FIELD_DEF=".(int)$_REQUEST['ID_FIELD_DEF']);
$ar = array();
while($row = mysql_fetch_assoc($res['rsrc']))
{
	$selected = '';
	if ($selected_value == $row['ID_LISTE']) {
		$selected_list = $row;
		$selected = ' selected';
	}
	$ar[] = '<option value="'.$row['ID_LISTE'].'" '.$selected.'>'.stdHtmlentities($row['NAME']).'</option>';
}

$tpl_content->addvar("global_lists", implode("\n", $ar));
if (!empty($selected_list)) {
	$queryListItems = "select slv.V1
             from liste_values lv
               left join string_liste_values slv on slv.FK = lv.ID_LISTE_VALUES AND slv.S_TABLE = 'liste_values' AND slv.BF_LANG = if(lv.BF_LANG_LISTE_VALUES & ".$langval.", ".$langval.", 1<<floor(log(lv.BF_LANG_LISTE_VALUES+0.5)/log(2)))
             where lv.FK_LISTE = ".$selected_list["ID_LISTE"]." ORDER BY lv.ORDER ASC";
	$arListItems = $db->fetch_col($queryListItems);
	$tpl_content->addvars($selected_list);
	$tpl_content->addvar("ITEMS", implode("\n", $arListItems));
}

?>