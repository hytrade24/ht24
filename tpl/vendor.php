<?php
/* ###VERSIONSBLOCKINLCUDE### */

function checkview($checkthis) {
	global $tpl_content,$uid,$get_uid,$db;

	switch($checkthis)
	{
		case 'ALL':
			return 1;
			break;
		case 'USER':
			if ($uid>0){
				return 1;
			} else {
				return 0;
			}
			break;
		case 'CONTACT':
			$data = $db->fetch_atom("select status from user_contact where ((FK_USER_A = '".$uid."' AND FK_USER_B = '".$get_uid."') OR (FK_USER_A = '".$get_uid."' AND FK_USER_B = '".$uid."'))");
			if ($data==1)
			 return $data;
			else
				return 0;
			break;
		default:
			return 0;
			break;
	}
}

function addUserPerms(&$row, $i) {
	global $db;
	$userid = (int)$row['USER_ID_USER'];
	if ($userid > 0) {
		$usercache = $row['USER_CACHE'];
		include ($GLOBALS['nar_systemsettings']['SITE']['USER_PATH'].$usercache."/".$userid."/useroptions.php");

		$row["showcontact"] = checkview($useroptions['LU_SHOWCONTAC']);
		$row["USER_ALLOW_CONTACS"] = $useroptions['ALLOW_CONTACS'];
		$row["USER_ALLOW_ADD_USER_CONTACT"] = $useroptions['ALLOW_ADD_USER_CONTACT'];
		$row["USER_SHOW_STATUS_USER_ONLINE"] = $useroptions['SHOW_STATUS_USER_ONLINE'];
		$row["VENDOR_ALLOW_CONTACS"] = $useroptions['ALLOW_CONTACS'];
		$row["VENDOR_ALLOW_ADD_USER_CONTACT"] = $useroptions['ALLOW_ADD_USER_CONTACT'];
		$row["VENDOR_SHOW_STATUS_USER_ONLINE"] = $useroptions['SHOW_STATUS_USER_ONLINE'];
	}
}

/**
 * Anbieter Suche
 *
 * Suche nach Anbietern mit folgenden Parametern
 *
 *
 */
require_once 'sys/lib.vendor.php';
require_once 'sys/lib.vendor.category.php';

$vendorManagement = VendorManagement::getInstance($db);
$vendorCategoryManagement = VendorCategoryManagement::getInstance($db);

$vendorManagement->setLangval($langval);
$vendorCategoryManagement->setLangval($langval);

$categoryId = ($ar_params[1] ? (int)$ar_params[1] : null);
$searchHash = ($ar_params[2] ? (string)$ar_params[2] : null);
$curpage = ($ar_params[3] ? (int)$ar_params[3] : 1);
$sort_by = ($ar_params[4] ? $ar_params[4] : "STANDARD");
$sort_dir = ($ar_params[5] ? $ar_params[5] : "DESC");
$viewTypeList = array(
	'LIST' => array('perpage' => 10, 'template' => 'vendor.row.htm'),
	'BOX' => array('perpage' => 20, 'template' => 'vendor.row_box.htm')
);
if ( $searchHash == null ) {
	$tpl_content->addvar("show_banner",1);
}
if ( $categoryId == null ) {
	$tpl_content->addvar("CATEGORY_676",1);
}
$viewType = (($ar_params[6] && array_key_exists($ar_params[6], $viewTypeList)) ? $ar_params[6] : "LIST");

if ( count($ar_params) == 1 ) {
	$tpl_content->addvar('SHOW_TOP_VENDOR',1);
}

$vendorsTop = array();
$countVendorsTop = 0;
$searchParameter = array();
$arKat = array();

$tpl_content->addvar("MAP_IDENT", "all");

if ($categoryId > 0) {
    include_once "sys/lib.shop_kategorien.php";
    $kat = new TreeCategories("kat", 4);
		$arKat = $kat->element_read($categoryId);
    
    if (empty($arKat)) {
      header("HTTP/1.0 404 Not Found");
      $tpl_content->LoadText("tpl/".$s_lang."/404.htm");
      return;
    }
	
    $tpl_main->addvars($arKat, "KAT_CUR_");
	
    if (!empty($arKat['V2'])) {
      $tpl_main->vars['pagetitle'] = $arKat['V2']." - ".$tpl_main->vars['pagetitle'];
    }
		if (!empty($arKat['T1'])) {
			$tpl_main->vars['metatags'] = $arKat['T1'];
		}
		$tpl_content->addvar("MAP_IDENT", "k".$categoryId);
}

if (!empty($searchHash)) {
	$tmp = $db->fetch_atom("SELECT S_STRING FROM `searchstring` WHERE `QUERY`='".mysql_real_escape_string($searchHash)."'");
	if($tmp != "N;") { $searchParameter = unserialize($tmp); } else { $searchParameter = array(); }
	$new_arr = array();
	foreach ( $searchParameter as $index => $row ) {
		if ( is_array($row) ) {
			if ( isset($row["VON"]) && isset($row["BIS"]) ) {
				$searchParameter[$index."_"."VON"] = $row["VON"];
				$searchParameter[$index."_"."BIS"] = $row["BIS"];
			}
			if ( isset($row["VON"]) ) {
				$searchParameter[$index."_"."VON"] = $row["VON"];
			}
			if ( isset($row["BIS"]) ) {
				$searchParameter[$index."_"."BIS"] = $row["BIS"];
			}
			else {
				foreach ( $searchParameter[$index] as $value ) {
					$new_arr[$index."_".$value] = 1;
				}
			}
		}
	}
	$searchParameter = array_merge( $searchParameter, $new_arr );

	$tpl_content->addvar("MAP_IDENT", "h".$searchHash);
}
if($categoryId != null) {
	$searchParameter['CATEGORY'] = $categoryId;
}
if ($viewType == "BOX") {
	$ar_top = array_merge($searchParameter,
		array("CATEGORY" => null, "TOP" => 1, "SORT" => "RAND()", "SORT_DIR" => "ASC")
	);
	$vendorsTop = $vendorManagement->fetchAllByParam($ar_top);
	$countVendorsTop = $vendorManagement->countByParam($ar_top);
	// Werbung für Top-Anbieter einfügen
	array_unshift($vendorsTop, array("ADVERTISEMENT" => 1));
	$countVendorsTop++;
	// Box-Ansicht
	if ($countVendorsTop > 2) {
		// 3 große Boxen
		$perpage = 12;
	} else if ($countVendorsTop > 1) {
		// 2 große Boxen
		$perpage = 16;
	} else {
		// 1 große Box
		$perpage = 20;
	}
} else {
	// Listen-Ansicht
	$perpage = 10; // Elemente pro Seite
}
$offset = (($curpage-1)*$perpage);

$searchParameter['LIMIT'] = $perpage;
$searchParameter['OFFSET'] = $offset;

$possibleSort = array(
    'CHANGED' => 'v.CHANGED',
    'NAME' => 'VENDOR_FIRMA',
    'COMMENTS' => 'COUNT_COMMENTS',
    'EVENTS' => 'COUNT_EVENTS',
    'GALLERY' => 'COUNT_GALLERY',
	'STANDARD'  =>  'STANDARD'
);
if(array_key_exists($sort_by, $possibleSort) && in_array($sort_dir, array('ASC', 'DESC'))) {
    $searchParameter['SORT'] = $possibleSort[$sort_by];
    $searchParameter['SORT_DIR'] = $sort_dir;
} else {
	$searchParameter['SORT'] = 'STANDARD';
	$searchParameter['SORT_DIR'] = "DESC";
}
$tpl_content->addvar(strtoupper("CUR_SORT_".$sort_by."_".$sort_dir), true);

/**
 * Liste auslesen
 */
$vendors = array();
if($searchHash == "NO_SEARCH_RESULTS") {
    $tpl_content->addvar("NO_SEARCH_RESULTS", "1");
    $vendorsRegular = array();
    $countVendorsRegular = 0;
} else {
    $vendorsRegular = $vendorManagement->fetchAllByParam($searchParameter);
    $countVendorsRegular = $vendorManagement->countByParam($searchParameter);
    $vendorManagement->extendList($vendorsRegular);
}

/** google map **/

if ($nar_systemsettings['MARKTPLATZ']['SHOW_MAP_VENDOR'] && $nar_systemsettings['MARKTPLATZ']['SHOW_MAP_ALL'] && $curpage == 1) {
    include_once $ab_path . 'sys/lib.map.php';
    $googleMaps = GoogleMaps::getInstance();
    $whereQueryWithJoin = $vendorManagement->buildWhereQueryWithJoins($searchParameter);

    $t = get_language();
    $langvalAsCode = $t['0'];

    $search_query = "select
                        group_concat(r.json) as json
                        from (
                            select
                                concat('{',
                                    'ID:', v.FK_USER,
                                    ',LONGITUDE:', v.LONGITUDE,
                                    ',LATITUDE:', v.LATITUDE,
                                '}') as json
                                FROM
					                vendor v
					            JOIN user u ON u.ID_USER = v.FK_USER
					            LEFT JOIN vendor_place p ON v.ID_VENDOR = p.FK_VENDOR
					            LEFT JOIN vendor_category c ON v.ID_VENDOR = c.FK_VENDOR
					            LEFT JOIN searchdb_index_".$langvalAsCode." si ON (si.S_TABLE = 'vendor' AND si.FK_ID = v.ID_VENDOR)
            					LEFT JOIN searchdb_words_".$langvalAsCode." sw ON sw.ID_WORDS = si.FK_WORDS
					            ".$whereQueryWithJoin[1]."
					            WHERE
					                1 = 1
					        		" . $whereQueryWithJoin[0] . "
					            GROUP BY v.ID_VENDOR
                        ) as r";

    if ($searchHash != null) {
        if (!$googleMaps->cacheFileExists('vendor', 'h'.$searchHash) || $googleMaps->isExpired('vendor', 'h'.$searchHash)) {
            $db->querynow('set session group_concat_max_len=4294967295');
            $data = $db->fetch_atom($search_query);

            $googleMaps->generateCacheFile('vendor', 'h'.$searchHash, "[".$data."]", false);
        }

        $tpl_content->addvar('SHOW_MAP', true);
    }
    elseif ($categoryId != null  && !$nar_systemsettings['MARKTPLATZ']['SHOW_MAP_SEARCH_VENDOR']) {
    	if (!$googleMaps->cacheFileExists('vendor', 'k'.$categoryId) || $googleMaps->isExpired('vendor', 'k'.$categoryId)) {
            $db->querynow('set session group_concat_max_len=4294967295');
            $data = $db->fetch_atom($search_query);

            $googleMaps->generateCacheFile('vendor', 'k'.$categoryId, "[".$data."]", false);
        }

        $tpl_content->addvar('SHOW_MAP', true);
    }
    elseif ($searchHash == null  && $categoryId == null && !$nar_systemsettings['MARKTPLATZ']['SHOW_MAP_SEARCH_VENDOR']) {
        if (!$googleMaps->cacheFileExists('vendor', 'all') || $googleMaps->isExpired('vendor', 'all')) {
            $db->querynow('set session group_concat_max_len=4294967295');
            $data = $db->fetch_atom($search_query);

            $googleMaps->generateCacheFile('vendor', 'all', "[".$data."]", false);
        }

        $tpl_content->addvar('SHOW_MAP', true);
    }
}

if ($viewType == "BOX") {
	if ($countVendorsTop > 0) {
		$i = 0;
		$i_top = 0;
		while (!empty($vendorsRegular)) {
			if ( (count($vendorsRegular) > 0) && (count($vendorsTop) > 0) && (($i % 3) == 0)) {
				// 1 große links und 2 kleine Boxen rechts
				$vendor_top = array_shift($vendorsTop);
				$vendor_top["BOX_BIG"] = 1;
				$vendor_top["PUSH"] = true;
				$vendors[] = $vendor_top;
				if (!empty($vendorsRegular)) {
					$vendor_reg = array_shift($vendorsRegular);
					$vendor_reg["PULL"] = true;
					$vendors[] = $vendor_reg;
				}
				if (!empty($vendorsRegular)) {
					$vendor_reg = array_shift($vendorsRegular);
					$vendor_reg["PULL"] = true;
					$vendors[] = $vendor_reg;
				}
				$i_top++;
			} else if ( (count($vendorsRegular) > 1) && (count($vendorsTop) > 0) && (($i % 3) == 2)) {
				// 1 große rechts und 2 kleine Boxen links
				$vendor_top = array_shift($vendorsTop);
				$vendor_top["BOX_BIG"] = 1;
				$vendors[] = $vendor_top;
				if (!empty($vendorsRegular))
					$vendors[] = array_shift($vendorsRegular);
				if (!empty($vendorsRegular))
					$vendors[] = array_shift($vendorsRegular);
				$i_top++;
			} else if ( (count($vendorsRegular) > 2) && (($i % 3) == 1) ) {
				// 3 kleine Boxen
				if (!empty($vendorsRegular))
					$vendors[] = array_shift($vendorsRegular);
				if (!empty($vendorsRegular))
					$vendors[] = array_shift($vendorsRegular);
				if (!empty($vendorsRegular))
					$vendors[] = array_shift($vendorsRegular);
			} else {
				// 1 kleine Box
				$vendors[] = array_shift($vendorsRegular);
			}
			$i++;
		}
		if ( ($i == 1) && ($countVendorsTop > 1) ) {
			// Zweite Zeile / Große Box rechts erzwingen
			$vendor_top = array_shift($vendorsTop);
			$vendor_top["BOX_BIG"] = 1;
			$vendor_top["PUSH"] = true;
			$vendors[] = $vendor_top;
		}
		$tpl_content->addvar("TOP_VENDORS", $i_top);
	} else {
		$vendors = $vendorsRegular;
	}
} else {
	$vendors = $vendorsRegular;
}

//.......
/*$vendorSearchWords = $vendorManagement->fetchAllSearchWordsByUserIdAndLanguage(
	$vendor['FK_USER'],
	$s_lang
);*/
//.......
$vendor_keywords = array();
//.......

$vendors = array_intersect_key(
	$vendors,
	array_unique(
		array_column(
			$vendors,
			'ID_VENDOR'
		)
	)
);

foreach($vendors as $key => $vendor) {
    $categories = $vendorCategoryManagement->fetchAllVendorCategoriesByVendorId($vendor['ID_VENDOR']);

    $tpl_categories = new Template($ab_path."tpl/".$s_lang."/vendor.row.categories.htm");
    $tpl_categories->addlist("categories", $categories, $ab_path.'tpl/'.$s_lang.'/vendor.row.categories.row.htm');
    $vendors[$key]['VENDOR_LOGO'] = ($vendor['LOGO'] != "") ? 'cache/vendor/logo/'.$vendor['LOGO'] : null;
    $vendors[$key]['VENDOR_CATEGORIES'] = $tpl_categories->process();

	//$empty_template = new Template("tpl/de/empty.htm");
	$template_keywords = new Template("tpl/".$s_lang."/vendor-searchword.list.htm");
	$template_keywords->isTemplateRecursiveParsable = true;
	$template_keywords->isTemplateCached = true;
	$cachedir = $GLOBALS['ab_apth']."cache/vendor/keywords";
	if (!is_dir($cachedir)) {
		mkdir($cachedir,0777,true);
	}
	$cachefile_keywords = $cachedir."/".$GLOBALS['s_lang']."."."vendor_keywords_".$vendor["USER_ID_USER"]."_".$vendor["ID_VENDOR"].".htm";
	$cache_str = null;
	$cacheFileLifeTime = $GLOBALS['nar_systemsettings']['CACHE']['LIFETIME_CATEGORY'];
	$modifyTime = @filemtime($cachefile_keywords);
	$diff = ((time() - $modifyTime) / 60);
	if (($diff > $cacheFileLifeTime) || !file_exists($cachefile_keywords)) {
		$vendors[$key]['VENDOR_KEYWORDS'] = $vendorManagement->fetchAllSearchWordsByUserIdAndLanguage($vendor['USER_ID_USER'], $s_lang);
		$template_keywords->addlist("VENDOR_KEYWORDS_LIST",$vendors[$key]['VENDOR_KEYWORDS'],"tpl/".$s_lang."/vendor-searchword.row.htm");
		$vendors[$key]['VENDOR_KEYWORDS_TPL'] = $template_keywords->process(true);

		file_put_contents($cachefile_keywords, $vendors[$key]['VENDOR_KEYWORDS_TPL']);
	}
	else {
		$vendors[$key]['VENDOR_KEYWORDS_TPL'] = file_get_contents($cachefile_keywords);
	}

}

$vendorSearchWords = $vendorManagement->fetchAllSearchWordsOfAllUsersWithLanguage($s_lang);

$vendorSearchWords2 = array_slice($vendorSearchWords,0,20);
$tpl_content->addlist("list_keywords",$vendorSearchWords2,"tpl/de/vendor.keywords.htm");

foreach ( $vendorSearchWords as $searchWord ) {
	$data = new stdClass();
	$data->text = $searchWord["wort"];
	$data->weight = intval( $searchWord["count_keywords"] ) * 0.01;
	$data->link = '#';
	$data->onclick = "searchVendorByText('".$data->text."')";
	array_push($vendor_keywords, $data);
}
$tpl_content->addvar('vendors_keywords', json_encode($vendor_keywords));

$tpl_content->addvar('VIEW_TYPE', $viewType);
$tpl_content->addvar('VIEW_TYPE_'.$viewType, 1);

$tpl_content->addvar('SYSTEM_ALLOW_COMMENTS', $nar_systemsettings['MARKTPLATZ']['ALLOW_COMMENTS_VENDOR']);
$tpl_content->addlist("vendors", $vendors, $ab_path.'tpl/'.$s_lang.'/'.$viewTypeList[$viewType]['template'], 'addUserPerms');
$tpl_content->addvar("pager", htm_browse_extended($countVendorsRegular, $curpage, "anbieter,".$categoryId.",".$searchHash.",{PAGE},".$sort_by.",".$sort_dir.",".$viewType."|KAT_NAME={urllabel(KAT_V1)}", $perpage));

$tpl_content->addvar("ALL_VENDORS", $countVendorsRegular);

// Kategorie
$cache_tree = $ab_path."/cache/marktplatz/kat_anbieter_".($categoryId != null ? (int)$categoryId : 0).".".$s_lang.".htm";
if (!file_exists($cache_tree)) {
	$categoryTree = $vendorCategoryManagement->getVendorCategoryTreeFlat($categoryId);

	$tpl_cache = new Template("tpl/de/empty.htm");
	$tpl_cache->tpl_text = "{list}";
	$tpl_cache->addlist_fast("list", $categoryTree, "tpl/".$s_lang."/vendor.category.htm");
	$htm_cache = $tpl_cache->process();
	file_put_contents($cache_tree, $htm_cache);

	$tpl_content->addvar("CATEGORY_TREE", $htm_cache);

} else {
	$tpl_content->addvar("CATEGORY_TREE", file_get_contents($cache_tree));
}

$tpl_content->addvars($searchParameter);
$tpl_content->addvar("SEARCH_PLZ", $searchParameter["PLZ"]);
$tpl_content->addvar("SEARCH_ORT", $searchParameter["ORT"]);

$tpl_content->addvar("URI_CURPAGE", $curpage);
$tpl_content->addvar("URI_SEARCHHASH", $searchHash);
$tpl_content->addvar("URI_SORTBY", $sort_by);
$tpl_content->addvar("URI_SORTDIR", $sort_dir);

// print_r($tpl_content->vars);
