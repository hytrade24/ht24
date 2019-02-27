<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path."sys/lib.calendar_event.php";
$calendarEventManagement = CalendarEventManagement::getInstance($db);
$searchParameter = array();

$categoryId = ($ar_params[1] ? (int)$ar_params[1] : null);
if ($categoryId === 0) {
    $categoryId = null;
    $tpl_content->addvar("HIDE_INTRO", 1);
}
$searchHash = ($ar_params[2] ? $ar_params[2] : false);
$sort_by = ($ar_params[4] ? $ar_params[4] : "CHANGED");
$sort_dir = ($ar_params[5] ? $ar_params[5] : "DESC");

$tpl_content->addvar("MAP_IDENT", "all");

if ($categoryId > 0) {
	$tpl_content->addvar("MAP_IDENT", "k".$categoryId);
}

if($searchHash !== false) {
	$tmp = $db->fetch_atom("SELECT S_STRING FROM `searchstring` WHERE `QUERY`='".mysql_real_escape_string($searchHash)."'");
	if ($tmp != "N;") {
		$searchParameter = unserialize($tmp);
	}
	$tpl_content->addvar("MAP_IDENT", "h".$searchHash);
}

$viewTypeList = array(
	'LIST' => array(
		'TEMPLATE'	=> 'tpl/'.$s_lang.'/calendar_events.list_row.htm'
	),
	'BOX' => array()
);
$viewType = (($ar_params[6] && array_key_exists($ar_params[6], $viewTypeList)) ? $ar_params[6] : "LIST");

if(($viewType == "LIST") && empty($searchParameter) && empty($_POST)) {
	$searchParameter['STAMP_END_GT'] = date("Y-m-d");
}
if($categoryId != null) {    
    include_once "sys/lib.shop_kategorien.php";
    $kat = new TreeCategories("kat", 7);
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
    $searchParameter['CATEGORY'] = $categoryId;
}

$searchParameter["MODERATED"] = 1;
$searchParameter["SORT_BY"] = "STAMP_START";
$searchParameter["SORT_DIR"] = "ASC";

    /** google map **/
if ($nar_systemsettings['MARKTPLATZ']['SHOW_MAP_EVENT'] && $nar_systemsettings['MARKTPLATZ']['SHOW_MAP_ALL']) {
    include_once $ab_path . 'sys/lib.map.php';
    $googleMaps = GoogleMaps::getInstance();
    $sqlWhere = $calendarEventManagement->generateWhereQuery($searchParameter);
    $sqlJoin = $calendarEventManagement->generateJoinQuery($searchParameter);
    $sqlHaving = $calendarEventManagement->generateHavingQuery($searchParameter);

    $search_query = "select
                        group_concat(r.json) as json
                        from (
                            select
                                concat('{',
                                    'ID:', ce.ID_CALENDAR_EVENT,
                                    ',LONGITUDE:', ce.LONGITUDE,
                                    ',LATITUDE:', ce.LATITUDE,
                                '}') as json
                                FROM `calendar_event` ce
								".$sqlJoin."
								WHERE 1=1 ".($sqlWhere ? " ".$sqlWhere : "")." AND ce.PRIVACY = 1
								GROUP BY ce.ID_CALENDAR_EVENT
								".(empty($sqlHaving) ? "" : "HAVING ".implode(" AND ", $sqlHaving))."
                        ) as r";

    if ($searchHash != null) {
        if (!$googleMaps->cacheFileExists('event', 'h'.$searchHash) || $googleMaps->isExpired('event', 'h'.$searchHash)) {
            $db->querynow('set session group_concat_max_len=4294967295');
            $data = $db->fetch_atom($search_query);

            $googleMaps->generateCacheFile('event', 'h'.$searchHash, "[".$data."]", false);
        }

        $tpl_content->addvar('SHOW_MAP', true);
    }
    elseif ($categoryId != null  && !$nar_systemsettings['MARKTPLATZ']['SHOW_MAP_SEARCH_EVENT']) {
        if (!$googleMaps->cacheFileExists('event', 'k'.$categoryId) || $googleMaps->isExpired('event', 'k'.$categoryId)) {
            $db->querynow('set session group_concat_max_len=4294967295');
            $data = $db->fetch_atom($search_query);

            $googleMaps->generateCacheFile('event', 'k'.$categoryId, "[".$data."]", false);
        }

        $tpl_content->addvar('SHOW_MAP', true);
    }
    elseif ($searchHash == null  && $categoryId == null && !$nar_systemsettings['MARKTPLATZ']['SHOW_MAP_SEARCH_EVENT']) {
        if (!$googleMaps->cacheFileExists('event', 'all') || $googleMaps->isExpired('event', 'all')) {
            $db->querynow('set session group_concat_max_len=4294967295');
            $data = $db->fetch_atom($search_query);

            $googleMaps->generateCacheFile('event', 'all', "[".$data."]", false);
        }

        $tpl_content->addvar('SHOW_MAP', true);
    }
}

// Kategorie
$cache_tree = $ab_path."cache/marktplatz/kat_calendar_events_".($categoryId != null ? (int)$categoryId : 0).".".$s_lang.".htm";
if (!file_exists($cache_tree) ) {
    $categoryTree = $calendarEventManagement->getCalendarEventCategoryTreeFlat($searchParameter['CATEGORY']);
    
    $tpl_cache = new Template("tpl/de/empty.htm");
    $tpl_cache->tpl_text = "{list}";
    $tpl_cache->addlist_fast("list", $categoryTree, "tpl/" . $s_lang . "/calendar_events.category.htm");
    $htm_cache = $tpl_cache->process();
    file_put_contents($cache_tree, $htm_cache);
    
    $tpl_content->addvar("CATEGORY_TREE", $htm_cache);
} else {
    $tpl_content->addvar("CATEGORY_TREE", file_get_contents($cache_tree));
}

$tpl_content->addvar('VIEW_TYPE', $viewType);
$tpl_content->addvar('VIEW_TYPE_'.$viewType, 1);
$tpl_content->addvar(strtoupper("CUR_SORT_".$sort_by."_".$sort_dir), true);

$tpl_content->addvars($searchParameter, "SEARCH_");
$tpl_content->addvar("SEARCH_HASH", $searchHash);
