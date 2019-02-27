<?php
/* ###VERSIONSBLOCKINLCUDE### */

$categoryId = (array_key_exists("ID_KAT", $tpl_content->vars) && $tpl_content->vars["ID_KAT"] ? (int)$tpl_content->vars["ID_KAT"] : (int)$ar_params[1]);
if ($categoryId > 0) {
    include_once "sys/lib.shop_kategorien.php";
    $kat = new TreeCategories("kat", 1);
    $row_kat = $kat->element_read($categoryId);
    $tpl_content->addvar("ID_KAT", $categoryId);
	$tpl_content->addvars($row_kat, "KAT_");
}
$hideTabs = (array_key_exists("HIDE_TABS", $tpl_content->vars) && $tpl_content->vars["HIDE_TABS"] ? 1 : 0);

$cacheFile = $ab_path."cache/marktplatz/hersteller_".$s_lang."_".$categoryId."_".$hideTabs.".htm";

$cacheFileLifeTime = $nar_systemsettings['CACHE']['LIFETIME_CATEGORY'];
$modifyTime = @filemtime($cacheFile);
$diff = ((time()-$modifyTime)/60);

if(($diff > $cacheFileLifeTime) || !file_exists($cacheFile)) {
	$tplCategoriesContent = new Template("tpl/".$s_lang."/hersteller.content.htm");

	$manufacturers = array();
	if ($categoryId > 0) {
	    $tplCategoriesContent->addvar("ID_KAT", $categoryId);
        $ids_kats = $db->fetch_nar("
            SELECT ID_KAT FROM `kat`
            WHERE (LFT >= ".$row_kat["LFT"].") AND (RGT <= ".$row_kat["RGT"].") AND (ROOT = ".$row_kat["ROOT"].")");
        $ids_kats = "(".implode(",",array_keys($ids_kats)).")";
        $manufacturers = $db->fetch_table('
            SELECT
                m.ID_MAN, m.NAME,
                (SELECT count(*) FROM `ad_master` WHERE FK_MAN=m.ID_MAN AND (STATUS&3)=1 AND (DELETED=0) AND FK_KAT IN '.$ids_kats.') as AD_COUNT,
                UPPER(LEFT(m.NAME,1)) as zeichen,
                "1" as level
            FROM `manufacturers` m
            LEFT JOIN `man_group_mapping` mgm ON mgm.FK_MAN=m.ID_MAN
            LEFT JOIN `man_group` mg ON mg.ID_MAN_GROUP=mgm.FK_MAN_GROUP
            LEFT JOIN `man_group_category` mgc ON mg.ID_MAN_GROUP=mgc.FK_MAN_GROUP
            WHERE
                CONFIRMED=1 AND mgc.FK_KAT='.(int)$categoryId.'
            GROUP BY m.ID_MAN
            HAVING AD_COUNT > 0
            ORDER BY m.NAME');
    } else {
        $manufacturers = $db->fetch_table('
            SELECT
                ID_MAN, NAME,
                (SELECT count(*) FROM `ad_master` WHERE FK_MAN=ID_MAN AND (STATUS&3)=1 AND (DELETED=0)) as AD_COUNT,
                UPPER(LEFT(NAME,1)) as zeichen,
                "1" as level
            FROM `manufacturers`
            WHERE
                CONFIRMED=1
            HAVING AD_COUNT > 0
            ORDER BY NAME');
    }
    
	$characterList = array();
	$manufacturerList = array();
	$manufacturerTemplateList = array();

	$lastCharacter = '';
	foreach ($manufacturers as $key => $manufacturer){
		$firstChar = $manufacturer['zeichen'];

		if(!array_key_exists($firstChar, $characterList)) {
			$characterList[$firstChar] = array('ZEICHEN' => $manufacturer['zeichen'], 'MAN_COUNT' => 0);
			$manufacturerList[$firstChar] = array();
		}

		$characterList[$firstChar]['MAN_COUNT']++;
		$manufacturerList[$firstChar][] = $manufacturer;
	}


	foreach($manufacturerList as $key => $values) {
		$tmpTemplate = new Template("tpl/".$s_lang."/hersteller.section.htm");
		$tmpTemplate->addlist("liste", $values, "tpl/".$s_lang."/hersteller.row.htm");
		$tmpTemplate->addvar('zeichen', $key);

		$manufacturerTemplateList[] = $tmpTemplate;
	}


	$tplCategoriesContent->addlist("index_alphabet", $characterList, "tpl/".$s_lang."/hersteller.register_row.htm");
	$tplCategoriesContent->addvar("liste", $manufacturerTemplateList);

	$htmlManufacturerer = $tplCategoriesContent->process(TRUE);
	file_put_contents($cacheFile, $htmlManufacturerer);
	chmod($cacheFile, 0777);
	$tpl_content->addvar("MANUFACTURERS_CONTENT", $htmlManufacturerer);
} else {
	$tpl_content->addvar("MANUFACTURERS_CONTENT", file_get_contents($cacheFile));
}

?>