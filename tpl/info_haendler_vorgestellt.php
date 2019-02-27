<?php
/* ###VERSIONSBLOCKINLCUDE### */


$subtplConfig = Template_Modules_SubTemplates::create($tpl_content, "info_haendler_vorgestellt", "Händler vorgestellt");
$id_kat = $subtplConfig->addOptionText("ID_KAT", "Kategorie-ID", false);
$id_users = $subtplConfig->addOptionText("ID_USER", "User-ID(s)", false);
$cacheLifetime = $subtplConfig->addOptionLookup("CACHE_LIFETIME", "Cache gültig für", "CACHE_LIFE", false);
$maxCount = $subtplConfig->addOptionIntRange("COUNT", "Anzahl Top-Anbieter", false, 1, 1, 8);
$countPerRow = $subtplConfig->addOptionIntRange("COUNT_PER_ROW", "Anzahl pro Zeile", false, 2, 1, 8);
$displayAds = $subtplConfig->addOptionIntRange("DISPLAY_ADS", "Anzahl Anzeigen pro Anbieter", false, 4, 0, 8);
$template = $subtplConfig->addOptionSelectList("TEMPLATE", "Darstellung", 'vendor', array(
    'vendor'					=> "Statisch (immer in der gleichen Größe)",
    'vendor_dynamic' 	=> "Dynamisch (Größe ändert sich je nach Inhalt)",
    'vendor_row' 			=> "Zeile (Anbieter & Anzeigen als Boxen)"
));
$hideParent = $subtplConfig->addOptionHidden("HIDE_PARENT", "Eltern-Element ausblenden wenn leer", false);
$subtplConfig->finishOptions();

$arSettings = array(
	"ID_KAT" 					=> $id_kat,
	"ID_VENDOR" 			=> $id_users,
	"LANG" 						=> $s_lang,
	"CACHE_LIFETIME" 	=> $cacheLifetime,
	"COUNT" 					=> $maxCount,
	"COUNT_PER_ROW" 	=> $countPerRow,
	"DISPLAY_ADS" 		=> $displayAds,
	"TEMPLATE" 				=> $template,
	"HIDE_PARENT"			=> $hideParent
);

$cacheHash = md5( serialize($arSettings) );

$tpl_content->addvar("CACHE_LIFETIME", $cacheLifetime);
$tpl_content->addvar("COUNT", $maxCount);
$tpl_content->addvar("COUNT_PER_ROW", $countPerRow);
$tpl_content->addvar("DISPLAY_ADS", $displayAds);
$tpl_content->addvar("HIDE_PARENT", $hideParent);

require_once 'sys/lib.vendor.php';

$vendorManagement = VendorManagement::getInstance($db);


$file_name = $ab_path . 'cache/marktplatz/start_haendlervorgestellt.' . $cacheHash . '.htm';
$file = @filemtime($file_name);
$now = time();
$diff = (($now - $file) / 60);
if (($diff >= $cacheLifetime) || $_SESSION["USER_IS_ADMIN"]) {
	$arJoins = array();
	$arWhere = array("v.STATUS = 1", "u.TOP_USER = 1");
	if ($id_kat > 0) {
		// Get category details
		include_once $ab_path."sys/lib.shop_kategorien.php";
		$kat = new TreeCategories("kat", 4);
		$row_kat = $kat->element_read($id_kat);
		if (is_array($row_kat)) {
			// Show ads from a specific category
			$arKatIds = $db->fetch_col("
				SELECT ID_KAT
				  FROM `kat`
				WHERE
				  (LFT >= ".$row_kat["LFT"].") AND
				  (RGT <= ".$row_kat["RGT"].") AND
				  (ROOT = ".$row_kat["ROOT"].")
				");
			$arJoins[] = "JOIN vendor_category vc ON v.ID_VENDOR=vc.FK_VENDOR AND vc.FK_KAT IN (".implode(",", $arKatIds).")";
		}
	}
	$vendorIds = array();
	if ($id_users > 0) {
		$id_users = explode(",", $id_users);
		$userIds = array();
		foreach ($id_users as $userIndex => $userId) {
			$userId = (int)trim($userId);
			if ($userId > 0) {
				$userIds[] = $userId;
			}
		}
		if (!empty($userIds)) {
			$vendorIds = $db->fetch_nar("SELECT v.ID_VENDOR, v.FK_USER FROM `vendor` v WHERE v.FK_USER IN (".implode(",", $userIds).")");
		}
	}
	if (empty($vendorIds)) {
		$vendorIds = $db->fetch_nar($q="
			SELECT
				v.ID_VENDOR, v.FK_USER
			FROM vendor v
			LEFT JOIN user u ON u.ID_USER = v.FK_USER
			".implode("\n", $arJoins)."
			".(!empty($arWhere) ? "WHERE ".implode(" AND ", $arWhere) : "")."
			GROUP BY
				v.ID_VENDOR
			ORDER BY
				RAND()
			LIMIT ".$maxCount);
	}
	
	$vendorList = array();
	foreach ($vendorIds as $userId => $vendorUserId) {
		$tmp = new Template("tpl/" . $s_lang . "/info_haendler_vorgestellt.".$template.".htm");
		$tmp->addvars($arSettings);
		// Anbieter-Details
        $vendor = $vendorManagement->fetchByVendorId($userId);
        $vendor['LOGO'] = ($vendor['LOGO'] != "")?'cache/vendor/logo/'.$vendor['LOGO']:null;
        $vendor['DESCRIPTION'] = substr(strip_tags($vendorManagement->fetchVendorDescriptionByLanguage($vendor['ID_VENDOR']), '<p><a><br>'), 0, 300);
		$tmp->addvars($vendor, 'VENDOR_');
		$tmp->addvar('SYSTEM_ALLOW_COMMENTS', $nar_systemsettings['MARKTPLATZ']['ALLOW_COMMENTS_VENDOR']);
		$vendorList[] = $tmp->process(false);
	}
    if($_SESSION["USER_IS_ADMIN"]) {
        $tpl_content->addvar("haendler_vorgestellt",  implode("\n", $vendorList));
    } else {
        // Write to cache
        @file_put_contents($file_name, implode("\n", $vendorList));
        chmod($file_name, 0777);
    }
}

if(!$_SESSION["USER_IS_ADMIN"]) {
    $tpl_content->addvar("haendler_vorgestellt",  @file_get_contents($file_name));
}
$tpl_content->addvars($user, "CURUSER_");
$tpl_content->isTemplateCached = true;
$tpl_content->isTemplateRecursiveParsable = true;
