<?php
/* ###VERSIONSBLOCKINLCUDE### */


$subtplConfig = Template_Modules_SubTemplates::create($tpl_content, "suchmaske_head", "Suchmaske (Kopfzeile)");
$category = $subtplConfig->addOptionCheckbox("CATEGORY", "Kategorieauswahl darstellen", true);
$layout = $subtplConfig->addOptionSelectList("LAYOUT", "Layout", "minimal", array(
    "minimal"   => "Minimalistisch",
    "location"  => "Umkreissuche"
));
// Apply options to template
$tpl_content->LoadText("tpl/".$s_lang."/suchmaske_head.".$layout.".htm");
$tpl_content->addvar("SHOW_CATEGORY", $category);
$arUmkreisLookups = Api_LookupManagement::getInstance($db)->readByArt("UMKREIS");
if (!empty($arUmkreisLookups)) {
	$arUmkreisIds = array_keys($arUmkreisLookups);
	$tpl_content->addvar("DEFAULT_LU_UMKREIS", $arUmkreisIds[0]);
}

// Finish options configuration
$subtplConfig->finishOptions();
#die(var_dump($tpl_content));

if ($category) {
    $opts=array();
    $res = $db->querynow($q="
	select
		s.V1, s.FK
	from
		`kat` t
	left join
		string_kat s on s.S_TABLE='kat' and s.FK=t.ID_KAT
		and s.BF_LANG=if(t.BF_LANG_KAT & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_KAT+0.5)/log(2)))
	where
		t.B_VIS=1 and PARENT=64584
		and ROOT=1
	order by
		t.ORDER_FIELD");
    while($row = mysql_fetch_assoc($res['rsrc'])) {
        $selected = $GLOBALS['SEARCHED']['FK_KAT'] == $row['FK'] ? ' selected' : '';
        $opts[] = '<option value="'.$row['FK'].'" '.$selected.'>'.stdHtmlentities($row['V1']).'</option>';
    }
    $tpl_content->addvar("katopts", implode("\n", $opts));
}

$tpl_content->addvar("FK_COUNTRY", 1);

if(!empty($GLOBALS['SEARCHED']) && is_array($GLOBALS['SEARCHED']))
	$tpl_content->addvars( array_flatten($GLOBALS['SEARCHED'], "both") );
?>