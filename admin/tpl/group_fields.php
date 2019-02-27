<?php
/* ###VERSIONSBLOCKINLCUDE### */

$showWhat = (array_key_exists("show", $_REQUEST) ? $_REQUEST["show"] : "groups");

$tpl_content->addvar("SHOW_".strtoupper($showWhat), 1);

require_once "sys/tabledef.php";

$table = new tabledef();
$table_id = (int)$_REQUEST['table'];

$table->getTableById($table_id, true);

if(preg_match('/^vendor/i', $table->ar_table["Name"])) {
    $SEARCH_BASE_GROUPS_OPTIONNAME = "SEARCH_BASE_GROUPS_VENDOR";
} else {
    $SEARCH_BASE_GROUPS_OPTIONNAME = "SEARCH_BASE_GROUPS";
}

if($_REQUEST['del'])
{
    $db->querynow("UPDATE `field_def` SET FK_FIELD_GROUP=NULL WHERE FK_FIELD_GROUP=".(int)$_REQUEST['del']);
    $db->querynow("
		DELETE FROM
			field2group
		WHERE
			FK_FIELD_GROUP=".(int)$_REQUEST['del']);
    $db->delete("field_group", $_REQUEST['del']);
}
if($_REQUEST['delTab'])
{
    $db->querynow("UPDATE `field_group` SET FK_FIELD_TAB=NULL WHERE FK_FIELD_TAB=".(int)$_REQUEST['delTab']);
    $db->delete("field_tab", $_REQUEST['delTab']);
}

if (array_key_exists("do", $_POST)) {
    switch($_POST["do"]) {
        case "updateSearchGroups":
            $arBaseGroups = array();
            $db->querynow("UPDATE `field_group` SET F_ORDER_SEARCH=NULL WHERE FK_TABLE_DEF=".(int)$table_id);
            foreach ($_POST["searchGroup"] as $sortOrder => $groupId) {
                if ((int)$groupId > 0) {
                    // Reguläre Gruppe
                    $db->querynow("UPDATE `field_group` SET F_ORDER_SEARCH=".((int)$sortOrder + 1)." WHERE ID_FIELD_GROUP=".(int)$groupId);
                } else {
                    // Spezial-Gruppe
                    $arBaseGroups[] = ((int)$sortOrder + 1).":".$groupId;
                }
            }
            updateSystemSettings( array("MARKTPLATZ" => array($SEARCH_BASE_GROUPS_OPTIONNAME => implode(",", $arBaseGroups))) );
            die(forward("index.php?page=group_fields&table=".$table_id."&show=search"));
    }
}

$SILENCE=false;
$tpl_content->addvars($table->ar_table);

$liste = $db->fetch_table("
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
		FK_TABLE_DEF=".$table_id."
	ORDER BY t.F_ORDER ASC
	");
foreach ($liste as $listeIndex => $arGroup) {
    $arFields = array_keys($db->fetch_nar("
			SELECT s.V1 FROM `field_def` t
			LEFT JOIN `string_field_def` s ON s.S_TABLE='field_def' and s.FK=t.ID_FIELD_DEF
					and s.BF_LANG=if(t.BF_LANG_FIELD_DEF & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_FIELD_DEF+0.5)/log(2)))
			WHERE
				t.FK_FIELD_GROUP=".(int)$arGroup["ID_FIELD_GROUP"]."
			ORDER BY
				t.FK_FIELD_GROUP ASC,
				t.F_ORDER ASC"));
    $liste[$listeIndex]["FIELD_COUNT"] = count($arFields);
    $liste[$listeIndex]["FIELD_LIST"] = implode(", ", $arFields);
}


$tpl_content->addlist("liste", $liste, "tpl/de/group_fields.row.htm");

$search_base_groups = array(
    "general"   => array(
        "ID_FIELD_GROUP"    => "general",
        "F_ORDER_SEARCH"    => true,
        "V1"                => "Allgemeine Suchfelder",
        "V2"                => "Hersteller, Name, Preis, Nicht gruppierte Felder, ..."
    ),
    "location"   => array(
        "ID_FIELD_GROUP"    => "location",
        "F_ORDER_SEARCH"    => true,
        "V1"                => "Umkreissuche",
        "V2"                => "Entfernung, PLZ, Ort, Land"
    )
);
$liste_search = $db->fetch_table("
	SELECT t.*, s.V1, s.V2, s.T1
	FROM `field_group` t
	left join
		string_app s on s.S_TABLE='field_group'
		and s.FK=t.ID_FIELD_GROUP
		and s.BF_LANG=if(t.BF_LANG_APP & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_APP+0.5)/log(2)))
	WHERE
		FK_TABLE_DEF=".$table_id."
	ORDER BY t.F_ORDER_SEARCH IS NULL ASC, t.F_ORDER_SEARCH ASC, t.F_ORDER
	");
$liste_search_base = explode(",", $nar_systemsettings["MARKTPLATZ"][$SEARCH_BASE_GROUPS_OPTIONNAME]);
foreach ($liste_search_base as $groupString) {
    list($groupOrder,$groupName) = explode(":", $groupString);
    if (array_key_exists($groupName, $search_base_groups)) {
        $groupIndex = 0;
        while (($groupIndex < count($liste_search)) && ($liste_search[$groupIndex]["F_ORDER_SEARCH"] !== NULL)
            && ($liste_search[$groupIndex]["F_ORDER_SEARCH"] < $groupOrder)) {
            $groupIndex++;
        }
        $search_base_groups[$groupName]["F_ORDER_SEARCH"] = $groupOrder;
        array_splice($liste_search, $groupIndex, 0, array($search_base_groups[$groupName]));
        unset($search_base_groups[$groupName]);
    }
}
foreach ($search_base_groups as $groupName => $groupDetails) {
    $groupDetails["F_ORDER_SEARCH"] = NULL;
    $liste_search[] = $groupDetails;
}


$tpl_content->addlist("liste_search_groups", $liste_search, "tpl/de/group_fields.row_search.htm");

$listeTabs = $db->fetch_table("
	select
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
    WHERE t.FK_TABLE_DEF=".$table_id."
	ORDER BY t.F_ORDER ASC
	");
foreach ($listeTabs as $listeIndex => $arTab) {
    $arGroups = array_keys($db->fetch_nar("
        SELECT s.V1 FROM `field_group` t
        LEFT JOIN `string_app` s ON s.S_TABLE='field_group' AND s.FK=t.ID_FIELD_GROUP
            AND s.BF_LANG=if(t.BF_LANG_APP & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_APP+0.5)/log(2)))
        WHERE t.FK_FIELD_TAB=".$arTab["ID_FIELD_TAB"]."
        ORDER BY t.F_ORDER ASC
	"));
    $listeTabs[$listeIndex]["GROUP_COUNT"] = count($arGroups);
    $listeTabs[$listeIndex]["GROUP_LIST"] = implode(", ", $arGroups);
}

$tpl_content->addlist("liste_tabs", $listeTabs, "tpl/de/group_fields.row_tabs.htm");

?>