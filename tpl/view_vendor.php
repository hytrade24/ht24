<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once 'sys/lib.vendor.php';
require_once 'sys/lib.vendor.category.php';
require_once 'sys/lib.vendor.gallery.php';
require_once 'sys/lib.vendor.place.php';
require_once 'sys/lib.article.php';

function killbb(&$row,$i)
{
	$row['BESCHREIBUNG'] = substr(strip_tags(html_entity_decode($row['BESCHREIBUNG'])), 0, 250);
	$row['BESCHREIBUNG'] = preg_replace("#(\[)([^\s\]]*)(\])#si", "", $row['BESCHREIBUNG']);
}

$vendorManagement = VendorManagement::getInstance($db);
$vendorCategoryManagement = VendorCategoryManagement::getInstance($db);
$vendorGalleryManagement = VendorGalleryManagement::getInstance($db);
$vendorPlacesManagement = VendorPlaceManagement::getInstance($db);
$articleManagement = ArticleManagement::getInstance($db);

$vendorManagement->setLangval($langval);
$vendorCategoryManagement->setLangval($langval);
$vendorPlacesManagement->setLangval($langval);
$articleManagement->setLangval($langval);

$userId = ($ar_params[2] ? (int)$ar_params[2] : null);
$tmp = $vendorManagement->fetchByUserId($userId);
$actionEx = (!empty($ar_params[3]) ? $ar_params[3] : false);
$userIsAdmin = $db->fetch_atom("SELECT count(*) FROM `role2user` ru JOIN `role` r ON r.ID_ROLE=ru.FK_ROLE AND FK_USER=".$uid." WHERE r.LABEL='Admin'");

$vendor = $vendorManagement->fetchByVendorId($tmp['ID_VENDOR']);
$vendorManagement->extendSingle($vendor);

Tools_UserStatistic::getInstance()->log_data($tmp['ID_VENDOR'], "vendor", "VIEW");

$vendorTemplate = array();

$tpl_main->addvar('newstitle',$vendor['FIRMA']);

if(!$userIsAdmin && ($userId == null || $vendor == null || !$vendorManagement->isUserVendorByUserId($userId))) { die(forward("/view_user,berni,".$userId.".htm")); }

/**
 * Info-Text anzeigen?
 */
if (!empty($actionEx)) {
    $tpl_content->addvar("info_".$actionEx, 1);
}

if ($userIsAdmin) {
    if ($_REQUEST['decline'] > 0) {
        $id_event = (int)$_REQUEST["decline"];
        $vendorManagement->adminDecline($id_event, $_REQUEST["REASON"]);
        die(forward($tpl_content->tpl_uri_action("view_vendor,".$ar_params[1].",".$userId.",declined")));
    }

    if ($_REQUEST["ajax"] == "unlockEvent") {
        header('Content-type: application/json');
        die(json_encode(array(
            "success"   => $vendorManagement->adminAccept($tmp['ID_VENDOR'])
        )));
    }
}
$vendor_root_category = 676;

// Template aufbereiten
foreach($vendor as $key=>$value) { $vendorTemplate['VENDOR_'.$key] = $value; }

if($vendorTemplate['VENDOR_CHANGED'] == '0000-00-00 00:00:00') {
    $vendorTemplate['VENDOR_CHANGED'] = 0;
}

// Kategorie Liste
$categories = $vendorCategoryManagement->fetchAllVendorCategoriesByVendorId($vendor['ID_VENDOR']);
foreach($categories as $category) {
    if(isset($category["IS_PREFERRED"]) && $category["IS_PREFERRED"] == 1) {
        $vendorTemplate["ID_KAT_PRIMARY"] = $category["FK_KAT"];
    }
}
$tpl_categories = new Template($ab_path."tpl/".$s_lang."/vendor.row.categories.htm");
$tpl_categories->addlist("categories", $categories, $ab_path.'tpl/'.$s_lang.'/vendor.row.categories.row.htm');
$vendorTemplate['VENDOR_CATEGORIES'] = $tpl_categories->process();

$vendor_big_image = null;

// Gallerie
$galleries = $vendorGalleryManagement->fetchAllByUserId($vendor['FK_USER']);
if ( count($galleries) > 0 ) {
	$vendor_big_image = '/'.$s_lang.'/cache/vendor/gallery/'.$galleries[0]['FILENAME'];
	$tpl_content->addvar("VENDOR_BIG_IMAGE",$vendor_big_image);
}
foreach($galleries as $key => $gallery) {
    $galleries[$key]['FILENAME'] = 'cache/vendor/gallery/'.$gallery['FILENAME'];
}
$tpl_content->addlist("VENDOR_GALLERY", $galleries, $ab_path.'tpl/'.$s_lang.'/view_vendor.gallery.htm');

// Gallerie Video
$galleryVideos = $vendorGalleryManagement->fetchAllVideosByUserId($vendor['FK_USER']);

$tpl_content->addlist("VENDOR_GALLERY_VIDEO", $galleryVideos, $ab_path.'tpl/'.$s_lang.'/view_vendor.gallery_video.htm');


//suchwörter

$cachedir = $GLOBALS['ab_apth']."cache/vendor/keywords";
if (!is_dir($cachedir)) {
	mkdir($cachedir,0777,true);
}
$cachefile_keywords = $cachedir."/".$GLOBALS['s_lang']."."."vendor_keywords_".$vendor["USER_ID"]."_".$vendor["ID_VENDOR"].".htm";
$cacheFileLifeTime = $GLOBALS['nar_systemsettings']['CACHE']['LIFETIME_CATEGORY'];
$modifyTime = @filemtime($cachefile_keywords);
$diff = ((time() - $modifyTime) / 60);
if (($diff > $cacheFileLifeTime) || !file_exists($cachefile_keywords)) {
	$vendorSearchWords = $vendorManagement->fetchAllSearchWordsByUserIdAndLanguage($vendor['FK_USER'], $s_lang);
	$template_keywords = new Template("tpl/".$s_lang."/vendor-searchword.list.htm");
	$template_keywords->isTemplateRecursiveParsable = true;
	$template_keywords->isTemplateCached = true;
	$template_keywords->addlist("VENDOR_KEYWORDS_LIST",$vendorSearchWords,"tpl/".$s_lang."/vendor-searchword.row.htm");
	$cache_str = $template_keywords->process(true);
	file_put_contents($cachefile_keywords, $cache_str);
	$tpl_content->addvar("VENDOR_KEYWORDS",$cache_str);
}
else {
	$vendorSearchWordsHtm = file_get_contents($cachefile_keywords);
	$vendorSearchWordsStr = trim(str_replace(",&nbsp;", ",", strip_tags($vendorSearchWordsHtm)), ",");
	$vendorSearchWordsText = explode(",", $vendorSearchWordsStr);
	$vendorSearchWords = [];
    foreach ($vendorSearchWordsText as $searchWord) {
        $vendorSearchWords[] = [
            "wort" => $searchWord
        ];
	}
	$tpl_content->addvar("VENDOR_KEYWORDS", $vendorSearchWordsHtm);
}



	$vendorParams = array(
		//'ID_VENDOR' =>  $vendor['ID_VENDOR']
		'FK_USER'   =>  $vendor['FK_USER']
	);

// Places
$places = $vendorPlacesManagement->fetchAllByUserId($vendor['FK_USER']);
$tpl_content->addvar("JSON_VENDOR_PLACES", json_encode($places));
$tpl_content->addlist("VENDOR_PLACES", $places, $ab_path.'tpl/'.$s_lang.'/view_vendor.place.htm');

$vendorTemplate['VENDOR_LOGO'] = ($vendorTemplate['VENDOR_LOGO'] != "")?'cache/vendor/logo/'.$vendorTemplate['VENDOR_LOGO']:null;
$vendorTemplate['VENDOR_DESCRIPTION'] = $vendorManagement->fetchVendorDescriptionByLanguage($vendor['ID_VENDOR']);
$vendorTemplate['USER_ID_USER'] = $vendor['FK_USER'];
$vendorTemplate['MODERATED'] = $vendor['MODERATED'];
$vendorTemplate['DECLINE_REASON'] = $vendor['DECLINE_REASON'];
$vendorTemplate['active_vendor'] = true;

// TODO: Schönere Lösung implementeren

// Read default meta tags
$article_kat_meta = $tpl_main->vars['metatags'];

// Generate specific meta description
$meta_description = trim(strip_tags($vendorTemplate['VENDOR_DESCRIPTION']));
if (strlen($meta_description) > 160) {
	// Text kürzen auf 160-200 Zeichen
	$meta_description_len = strrpos(substr($meta_description, 0, 200), " ");
	$meta_description = $vendor['FIRMA']." ".substr($meta_description, 0, $meta_description_len);
}
if (!empty($meta_description)) {
    // Replace default description with the speific one generated
	$article_kat_meta = preg_replace('/(<meta name="description" content=)"(.*)"(>)/i', '$1"'.$meta_description.'"${3}', $article_kat_meta);
}

// Generate specific meta keywords
$meta_keywords = array();
foreach ( $vendorSearchWords as $keyword ) {
    array_push($meta_keywords, $keyword['wort']);
}

if (!empty($meta_keywords)) {
    $meta_keywords = implode(",",$meta_keywords);
    $article_kat_meta = preg_replace('/(<meta name="keywords" content=)"(.*)"(>)/i', '$1"'.$meta_keywords.'"${3}', $article_kat_meta);   
}

// Replace the default meta tags in the template
$tpl_main->vars['metatags'] = $article_kat_meta;
// ------

// User Einstellungen
$user  = $db->fetch1("select VORNAME as USER_VORNAME, NACHNAME as USER_NACHNAME, NAME as USER_NAME, FIRMA as USER_FIRMA, CACHE as USER_CACHE, STAMP_REG as USER_STAMP_REG, LASTACTIV as USER_LASTACTIV, URL as USER_URL, STRASSE as USER_STRASSE , PLZ as USER_PLZ, ORT as USER_ORT, ID_USER as USER_ID_USER, UEBER as USER_UEBER, ROUND(RATING) as USER_lastrate,TIMESTAMPDIFF(YEAR,GEBDAT,CURDATE()) as USER_age, TEL as USER_TEL from user where ID_USER=". $vendor['FK_USER']); // Userdaten lesen
include_once ($GLOBALS['nar_systemsettings']['SITE']['USER_PATH'].$user['USER_CACHE']."/".$vendor['FK_USER']."/useroptions.php");

$tpl_content->addvar("VENDOR_ALLOW_CONTACS",$useroptions['ALLOW_CONTACS']);
$tpl_content->addvar("VENDOR_ALLOW_CONTACS",$useroptions['ALLOW_CONTACS']);
$tpl_content->addvar("VENDOR_ALLOW_ADD_USER_CONTACT",$useroptions['ALLOW_ADD_USER_CONTACT']);
$tpl_content->addvar("isuser", ($uid > 0)?1:0);
$tpl_content->addvar("comments_enabled", ($nar_systemsettings["MARKTPLATZ"]["ALLOW_COMMENTS_VENDOR"] ? true : false));
$tpl_content->addvar("vendors_admin_moderated", ($nar_systemsettings["MARKTPLATZ"]["MODERATE_VENDORS"] ? true : false));
$tpl_content->addvar("DAYS_ADS_NEW", $nar_systemsettings["MARKTPLATZ"]["DAYS_ADS_NEW"]);
$tpl_content->addvars($vendorTemplate);

$queryAds = Rest_MarketplaceAds::getQueryByParams(["FK_USER" => $vendor["FK_USER"]]);
$countAds = $queryAds->fetchCount();

$tpl_content->addvar("VENDOR_AD_COUNT",$countAds);

//die(var_dump($countAds));

$tpl_content->addvar("USER_IS_ADMIN", $userIsAdmin);
$tpl_content->addvar("MODERATED", $vendorTemplate['MODERATED']);
if ($article_data_master['MODERATED'] == 2) {
    $tpl_content->addvar("DECLINE_REASON", $vendorTemplate['DECLINE_REASON']);
}

$get_uid=$ar_params[2];
//$view = ($ar_params[3] ? $ar_params[3] : "uebersicht");
$fk_kat = (int)$ar_params[3];
/*
$list_vendor = $db->fetch_table($q ="
    	SELECT
    		SQL_CALC_FOUND_ROWS
    		am.*,
    		am.ID_AD_MASTER AS ID_AD,
            am.TRADE AS product_trade,
    		LEFT(am.BESCHREIBUNG, 250) AS DSC,
    		s.V1 as KAT,
    		(SELECT m.NAME FROM `manufacturers` m WHERE m.ID_MAN=am.FK_MAN) as MANUFACTURER,
    		sc.V1 as LAND,
    		i.SRC AS IMG_DEFAULT_SRC,
    		i.SRC_THUMB AS IMG_DEFAULT_SRC_THUMB
    	FROM
    		ad_master am
    	LEFT JOIN
			string_kat s on s.S_TABLE='kat'
			and s.FK=am.FK_KAT
			and s.BF_LANG=if(".$langval." & ".$langval.", ".$langval.", 1 << floor(log(".$langval."+0.5)/log(2)))
    	LEFT JOIN
			string sc on sc.S_TABLE='country'
			and sc.FK=am.FK_COUNTRY
			and sc.BF_LANG=if(".$langval." & ".$langval.", ".$langval.", 1 << floor(log(".$langval."+0.5)/log(2)))
		LEFT JOIN
			ad_images i ON am.ID_AD_MASTER = i.FK_AD AND i.IS_DEFAULT=1
		WHERE
    		am.FK_USER=".$get_uid."
    		AND am.STATUS&3 = 1 AND (am.DELETED=0)
    		".($fk_kat ? 'AND am.FK_KAT = '.$fk_kat : '')."
    	ORDER BY
    		B_TOP_LIST DESC,
    		STAMP_START DESC
    	LIMIT 8
    		");

Rest_MarketplaceAds::extendAdDetailsList($list_vendor);

$tpl_content->isTemplateRecursiveParsable = TRUE;
$tpl_content->isTemplateCached = TRUE;
$tpl_content->addlist('VENDOR_ADS', $list_vendor, $ab_path.'tpl/'.$s_lang.'/vendor.new.row_box.htm', 'killbb');
*/
$cachedir = $GLOBALS['ab_path']."cache/vendor/vendor_details";
if (!is_dir($cachedir)) {
	mkdir($cachedir,0777,true);
}
$cachefile_vendor_details = $cachedir."/".$GLOBALS['s_lang']."."."vendor_details_".$vendor["USER_ID"]."_".$vendor["ID_VENDOR"].".htm";
$cacheFileLifeTime = $GLOBALS['nar_systemsettings']['CACHE']['LIFETIME_CATEGORY'];
$modifyTime = @filemtime($cachefile_vendor_details);
$diff = ((time() - $modifyTime) / 60);
if (($diff > $cacheFileLifeTime) || !file_exists($cachefile_vendor_details)) {
	$vendor_categories_flatten = array();
	foreach ( $categories as $row ) {
		array_push($vendor_categories_flatten,$row["FK_KAT"]);
	}
	$vendorId = $vendor["ID_VENDOR"];
	$vendorGroups = $vendorManagement->getData_FieldsGroups();
	$arVendorFieldsGrouped = array();
	$arVendorFieldsHtml = array();
	$langval = $GLOBALS["lang_list"][$s_lang]["BITVAL"];

	foreach ( $vendorGroups as $groupIndex => $groupId ) {
		if (((int)$groupId == 0) && ($groupId !== null)) {
			continue;
		}
		$idGroup = (int)$groupId;
		$arVendorFields = $vendorManagement->getFields(
			$idGroup > 0 ? $idGroup : null,
			$vendor_categories_flatten,
			$s_lang
		);
		foreach ($arVendorFields as $fieldIndex => $fieldDetails) {
			$fieldDetails['VENDOR_ROOT_CATEGORY'] = $vendor_root_category;
			$fieldName = $fieldDetails["F_NAME"];
			$fieldValue = $vendorManagement->getData_Vendor($fieldName,$vendorId);
			// Check if field is visible
			if (($fieldValue === null) || ($fieldDetails["IS_SPECIAL"])) {
				continue;
			}
			$execute_hash = true;
			// Convert value (default types)
			switch ($fieldDetails["F_TYP"]) {
				case "LIST":
					$execute_hash = true;
					if ($fieldValue > 0) {
						$field_result = $db->fetch1("
			            SELECT V1, l.ID_LISTE_VALUES as FIELD_VALUE
			            FROM liste_values l
			              LEFT JOIN string_liste_values s ON
			              s.FK=l.ID_LISTE_VALUES AND s.S_TABLE='liste_values' AND
			              s.BF_LANG=if(l.BF_LANG_LISTE_VALUES & ".$langval.", ".$langval.", 1 << floor(log(l.BF_LANG_LISTE_VALUES+0.5)/log(2)))
			            WHERE l.FK_LISTE=".$fieldDetails["FK_LISTE"]." AND l.ID_LISTE_VALUES=".(int)$fieldValue
						);
						$fieldValue = $fieldDetails["VALUE"] = $field_result['V1'];
						$fieldDetails["FIELD_VALUE"] = $field_result['FIELD_VALUE'];
						$fieldDetails["IS_SET"] = 1;
					} else {
						$fieldDetails["IS_SET"] = 0;
					}
					break;
				case "MULTICHECKBOX":
				case "MULTICHECKBOX_AND":
					$execute_hash = false;
					$checkIdValues = trim($fieldValue, "x");
					if ($checkIdValues != "") {
						$arCheckValues = explode("x", $checkIdValues);

						$arCheckNames = $db->fetch_table(
							"SELECT sl.V1, l.ID_LISTE_VALUES as FIELD_VALUE FROM `liste_values` l
	                    LEFT JOIN `string_liste_values` sl ON sl.S_TABLE='liste_values' AND sl.FK=l.ID_LISTE_VALUES
	                      AND sl.BF_LANG=if(l.BF_LANG_LISTE_VALUES & " . $langval . ", " . $langval . ", 1 << floor(log(l.BF_LANG_LISTE_VALUES+0.5)/log(2)))
	                    WHERE l.ID_LISTE_VALUES IN (".mysql_real_escape_string(implode(", ", $arCheckValues)).")");

						foreach ( $arCheckNames as $index => $row ) {
							$arr = array();
							$arr[$fieldDetails['F_NAME']][] = $row['FIELD_VALUE'];
							$lifetime = time() + (60 * 60 * 24);
							$paramSer = serialize($arr);
							$hash = substr(md5("vendor ".$paramSer), 0, 15);
							$arCheckNames[$index]['SEARCHHASH'] = $hash;
							$arCheckNames[$index]['VENDOR_ROOT_CATEGORY'] = $vendor_root_category;
							$ar = array(
								'QUERY' => $hash,
								'LIFETIME' => date("Y-m-d H:i:s", $lifetime),
								'S_STRING' => $paramSer,
								'S_WHERE' => ""
							);
							$id_known = $db->fetch_atom("SELECT `ID_SEARCHSTRING` FROM `searchstring` WHERE QUERY='".mysql_real_escape_string($hash)."'");
							if ($id_known > 0) {}
							else {
								$id = $db->update("searchstring", $ar);
							}
						}
						$arCheckNames[count($arCheckNames)-1]["IS_LAST"] = 1;

						$fieldDetails["VALUE"] = '';
						$fieldDetails["F_TYP_".$fieldDetails["F_TYP"]] = 1;

						$tpl_vendor_extra = new Template("tpl/".$s_lang."/field.multicheckbox_and.htm");
						$tpl_vendor_extra->addlist("FIELD_TPL",$arCheckNames,"tpl/".$s_lang."/field.multicheckbox_and.row.htm");
						$fieldDetails['FIELD_TPL'] = $tpl_vendor_extra->process(true);

						$fieldDetails["IS_SET"] = 1;
					} else {
						$fieldDetails["IS_SET"] = 0;
					}
					break;
				case "DATE":
					$execute_hash = true;
					$timeValue = strtotime($fieldValue);
					$sql = 'SELECT '.$fieldDetails["F_NAME"].'
								FROM vendor_master vm
								WHERE vm.ID_VENDOR_MASTER = ' . $vendor["ID_VENDOR"];
					$fieldDetails['FIELD_VALUE'] = $db->fetch_atom( $sql );
					$fieldDetails['IS_SET'] = ($timeValue !== false ? 1 : 0);
					$fieldDetails["VALUE"] = date("d.m.Y", $timeValue);
					break;
				case "HTMLTEXT":
					$execute_hash = true;
					$fieldDetails['IS_SET'] = 0;
					$fieldDetails["VALUE"] = $fieldValue;
					if (true) {
						$arVendorFieldsHtml[] = $fieldDetails;
					}
					break;
				default:
					$execute_hash = true;
					$sql = 'SELECT '.$fieldDetails["F_NAME"].'
								FROM vendor_master vm
								WHERE vm.ID_VENDOR_MASTER = ' . $vendor["ID_VENDOR"];
					$fieldDetails['FIELD_VALUE'] = $db->fetch_atom( $sql );
					$fieldDetails["VALUE"] = $fieldValue;
					$fieldDetails["IS_SET"] = ($fieldDetails["VALUE"] == "" ? 0 : 1);
					break;
			}
			if ( $execute_hash ) {
				$arr = array();
				$arr[$fieldDetails['F_NAME']] = $fieldDetails['FIELD_VALUE'];
				$lifetime = time() + (60 * 60 * 24);
				$paramSer = serialize($arr);
				$hash = substr(md5("vendor ".$paramSer), 0, 15);
				$ar = array(
					'QUERY' => $hash,
					'LIFETIME' => date("Y-m-d H:i:s", $lifetime),
					'S_STRING' => $paramSer,
					'S_WHERE' => ""
				);
				$id_known = $db->fetch_atom("SELECT `ID_SEARCHSTRING` FROM `searchstring` WHERE QUERY='".mysql_real_escape_string($hash)."'");
				if ($id_known > 0) {
					$ar["ID_SEARCHSTRING"] = $id_known;
				}
				else {
					$id = $db->update("searchstring", $ar);
				}
				$fieldDetails['SEARCHHASH'] = $hash;
			}
			// Add to grouped array
			if (!array_key_exists($idGroup, $arVendorFieldsGrouped)) {
				$arVendorFieldsGrouped[$idGroup] = array();
			}
			$arVendorFieldsGrouped[$idGroup][] = $fieldDetails;
		}
	}
	$arVendorFieldsTpl = array();

	if (!empty($arVendorFieldsGrouped[0])) {
		$tplGroupGeneral = new Template("tpl/".$s_lang."/vendor_details.group.htm");
		$tplGroupGeneral->addlist_fast('liste', $arVendorFieldsGrouped[0], 'tpl/'.$s_lang.'/vendor_details.group.row.htm');
		$arVendorFieldsTpl[] = $tplGroupGeneral;
		unset($arVendorFieldsGrouped[0]);
	}
	$arFieldGroupIds = array_keys($arVendorFieldsGrouped);
	if (!empty($arFieldGroupIds)) {
		$arFieldGroupList = $db->fetch_table("
		SELECT t.ID_FIELD_GROUP, s.V1
		FROM `field_group` t
		LEFT JOIN string_app s ON s.S_TABLE='field_group' AND s.FK=t.ID_FIELD_GROUP
			AND s.BF_LANG=if(t.BF_LANG_APP & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_APP+0.5)/log(2)))
		WHERE ID_FIELD_GROUP IN (".implode(",", $arFieldGroupIds).")
		ORDER BY t.F_ORDER");

		foreach ($arFieldGroupList as $groupIndex => $groupDetails) {
			$tplGroup = new Template("tpl/".$s_lang."/vendor_details.group.htm");
			$tplGroup->addvars($groupDetails);
			$tplGroup->addlist_fast('liste', $arVendorFieldsGrouped[$groupDetails['ID_FIELD_GROUP']], 'tpl/'.$s_lang.'/vendor_details.group.row.htm');
			$arVendorFieldsTpl[] = $tplGroup;
		}
	}
	if (!empty($arVendorFieldsHtml)) {
		foreach ($arVendorFieldsHtml as $fieldIndex => $fieldDetails) {
			$tplHtmlField = new Template("tpl/".$s_lang."/vendor_details.fields.row_htmltext.htm");
			$tplHtmlField->addvars($fieldDetails);
			$arVendorFieldsTpl[] = $tplHtmlField;
		}
	}
	$tpl_content->addvar("fields", $arVendorFieldsTpl);

	file_put_contents($cachefile_vendor_details, $arVendorFieldsTpl);
}
else {
	$tpl_content->addvar(
		"fields",
		file_get_contents($cachefile_vendor_details)
	);
}
//............