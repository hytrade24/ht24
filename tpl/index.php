<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path.'sys/lib.ad_constraint.php';

#$SILENCE=false;
/*
if ($nar_systemsettings['SITE']['TEMPLATE_TRANSLATION_TOOL'] && array_key_exists('ebizTranslationTool', $_COOKIE)) {
    Translation::readTranslation("marketplace", "user.likes");
}
*/

### altes Zeug
function killbb(&$row,$i)
{
	$row['DSC'] = substr(strip_tags($row['DSC']), 0, 250);
	$row['DSC'] = preg_replace("#(\[)([^\s\]]*)(\])#si", "", $row['DSC']);
}

$tpl_content->addvar('noads',1); // Werbung aus Kategorie ausschalten

$max_ads = $nar_systemsettings['MARKTPLATZ']['INDEX_NEWADS'];
$max_top = $nar_systemsettings['MARKTPLATZ']['INDEX_TOPADS'];

$tpl_content->addvar("MARKTPLATZ_INDEX_NEWADS", $max_ads);
$tpl_content->addvar("MARKTPLATZ_INDEX_TOPADS", $max_top);

$articlesOnline = $db->fetch_atom("SELECT COUNT(DISTINCT PRODUKTNAME) FROM `ad_master` WHERE STATUS=1");
$round = floor($articlesOnline/1000);
$final = $round *1000;
$tpl_content->addvar("ARTICLE_COUNT_ONLINE", $final);

?>