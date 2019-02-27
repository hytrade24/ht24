<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path."sys/lib.club.php";
require_once $ab_path."sys/lib.club.category.php";
$clubManagement = ClubManagement::getInstance($db);
$clubCategoryManagement = ClubCategoryManagement::getInstance($db);

$clubCategoryManagement->setLangval($langval);

$categoryId = ($ar_params[1] ? (int)$ar_params[1] : null);
$searchHash = ($ar_params[2] ? (string)$ar_params[2] : null);
$curpage = ($ar_params[3] ? (int)$ar_params[3] : 1);
$sort_by = ($ar_params[4] ? $ar_params[4] : "STAMP");
$sort_dir = ($ar_params[5] ? $ar_params[5] : "DESC");
$viewTypeList = array(
		'LIST' => array('perpage' => 10, 'template' => 'clubs.row.htm'),
		'BOX' => array('perpage' => 9, 'template' => 'clubs.row_box.htm')
);
$viewType = (($ar_params[6] && array_key_exists($ar_params[6], $viewTypeList)) ? $ar_params[6] : "BOX");
$perpage = $viewTypeList[$viewType]['perpage'];

$clubsTop = array();
$countClubsTop = 0;
$searchParameter = array();

if (!empty($searchHash)) {
	if($searchHash == "my-clubs" || $searchHash == "my-groups") {
        $searchParameter['FK_USER'] = $uid;
        $searchParameter['FK_USER_STATUS'] = "MEMBER";
        $tpl_content->addvar("SHOW_MYGROUPS", 1);
	} else {
        $tmp = $db->fetch_atom("SELECT S_STRING FROM `searchstring` WHERE `QUERY`='".mysql_real_escape_string($searchHash)."'");
        if ($tmp != "N;") {
            $searchParameter = unserialize($tmp);
        }
	}
}
if($categoryId != null) {    
    include_once "sys/lib.shop_kategorien.php";
    $kat = new TreeCategories("kat", 8);
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
if ($viewType == "BOX") {
	$ar_top = array_merge($searchParameter,
			array("ALLOW_MEMBER_REQUESTS" => true,"GET_CATEGORY" => true,"CATEGORY" => null, "TOP" => 1, "SORT" => "RAND()", "SORT_DIR" => "ASC")
	);
	$clubsTop = $clubManagement->getClubsByParams($ar_top);

	// TODO: Top-Clubs hinnzufügen?
	//$countClubsTop = $clubManagement->countClubsByParam($ar_top);
	// Werbung für Top-Anbieter einfügen
	//array_unshift($clubsTop, array("ADVERTISEMENT" => 1));
	//$countClubsTop++;
	//-----

	
}
$offset = (($curpage-1)*$perpage);

$searchParameter['LIMIT'] = $perpage;
$searchParameter['OFFSET'] = $offset;

$possibleSort = array(
    'STAMP' => 'c.STAMP',
    'NAME' => 'c.NAME',
    'MEMBERS' => 'MEMBERS',
    'COMMENTS' => 'COUNT_COMMENTS',
    'EVENTS' => 'COUNT_EVENTS',
    'GALLERY' => 'COUNT_GALLERY',
);
if(array_key_exists($sort_by, $possibleSort) && in_array($sort_dir, array('ASC', 'DESC'))) {
	$searchParameter['SORT'] = $possibleSort[$sort_by];
	$searchParameter['SORT_DIR'] = $sort_dir;

	$tpl_content->addvar(strtoupper("CUR_SORT_".$sort_by."_".$sort_dir), true);
}

/**
 * Liste auslesen
 */
$clubs = array();
$searchParameter['GET_CATEGORY'] = true;
$searchParameter['ALLOW_MEMBER_REQUESTS'] = true;
$clubsRegular = $clubManagement->getClubsByParams($searchParameter);
$countClubsRegular = $clubManagement->countClubsByParam($searchParameter);

if ($viewType == "BOX") {
	if ($countClubsTop > 0) {
		$i = 0;
		$i_top = 0;
		while (!empty($clubsRegular)) {
			if ( (count($clubsRegular) > 0) && (count($clubsTop) > 0) && (($i % 3) == 0)) {
				// 1 große links und 2 kleine Boxen rechts
				$club_top = array_shift($clubsTop);
				$club_top["BOX_BIG"] = 1;
				$club_top["PUSH"] = true;
				$clubs[] = $club_top;
				if (!empty($clubsRegular)) {
					$club_reg = array_shift($vendorsRegular);
					$club_reg["PULL"] = true;
					$clubs[] = $club_reg;
				}
				if (!empty($clubsRegular)) {
					$club_reg = array_shift($vendorsRegular);
					$club_reg["PULL"] = true;
					$clubs[] = $club_reg;
				}
				$i_top++;
			} else if ( (count($clubsRegular) > 1) && (count($clubsTop) > 0) && (($i % 3) == 2)) {
				// 1 große rechts und 2 kleine Boxen links
				$club_top = array_shift($clubsTop);
				$club_top["BOX_BIG"] = 1;
				$clubs[] = $club_top;
				if (!empty($clubsRegular))
					$clubs[] = array_shift($clubsRegular);
				if (!empty($clubsRegular))
					$clubs[] = array_shift($clubsRegular);
				$i_top++;
			} else if ( (count($clubsRegular) > 2) && (($i % 3) == 1) ) {
				// 3 kleine Boxen
				if (!empty($clubsRegular))
					$clubs[] = array_shift($clubsRegular);
				if (!empty($clubsRegular))
					$clubs[] = array_shift($clubsRegular);
				if (!empty($clubsRegular))
					$clubs[] = array_shift($clubsRegular);
			} else {
				// 1 kleine Box
				$clubs[] = array_shift($clubsRegular);
			}
			$i++;
		}
		if ( ($i == 1) && ($countClubsTop > 1) ) {
			// Zweite Zeile / Große Box rechts erzwingen
			$club_top = array_shift($clubsTop);
			$club_top["BOX_BIG"] = 1;
			$club_top["PUSH"] = true;
			$clubs[] = $club_top;
		}
		$tpl_content->addvar("TOP_CLUBS", $i_top);
	} else {
		$clubs = $clubsRegular;
	}
} else {
	$clubs = $clubsRegular;
}

#var_dump($clubs);
foreach($clubs as $key => $club) {
	$clubs[$key]['CLUB_LOGO'] = ($club['CLUB_LOGO'] != "")?'cache/club/logo/'.$club['CLUB_LOGO']:null;

}


// Kategorie
$cache_tree = $ab_path."cache/marktplatz/kat_club_".($categoryId != null ? (int)$categoryId : 0).".".$s_lang.".htm";
if (!file_exists($cache_tree)) {
    $categoryTree = $clubCategoryManagement->getClubCategoryTreeFlat($categoryId);

	//echo '<pre>';var_dump( $categoryTree );die();

	$tpl_cache = new Template("tpl/de/empty.htm");
	$tpl_cache->tpl_text = "{list}";
	$tpl_cache->addlist_fast("list", $categoryTree, "tpl/".$s_lang."/clubs.category.htm");
	$htm_cache = $tpl_cache->process();
    file_put_contents($cache_tree, $htm_cache);

    $tpl_content->addvar("CATEGORY_TREE", $htm_cache);
} else {
    $tpl_content->addvar("CATEGORY_TREE", file_get_contents($cache_tree));
}

$tpl_content->addvar('VIEW_TYPE', $viewType);
$tpl_content->addvar('VIEW_TYPE_'.$viewType, 1);
$tpl_content->addvar('SYSTEM_ALLOW_COMMENTS', $nar_systemsettings['MARKTPLATZ']['ALLOW_COMMENTS_CLUB']);

$tpl_content->addlist("clubs", $clubs, $ab_path.'tpl/'.$s_lang.'/'.$viewTypeList[$viewType]['template']);
$tpl_content->addvar("pager", htm_browse_extended($countClubsRegular, $curpage, "clubs,".$categoryId.",".$searchHash.",{PAGE},".$sort_by.",".$sort_dir.",".$viewType, $perpage));

$tpl_content->addvar("ALL_CLUBS", $countClubsRegular);

$tpl_content->addvars($searchParameter);
$tpl_content->addvar("SEARCH_FK_COUNTRY", $searchParameter["FK_COUNTRY"]);
$tpl_content->addvar("SEARCH_PLZ", $searchParameter["PLZ"]);
$tpl_content->addvar("SEARCH_ORT", $searchParameter["ORT"]);

$tpl_content->addvar("URI_CURPAGE", $curpage);
$tpl_content->addvar("URI_SEARCHHASH", $searchHash);
$tpl_content->addvar("URI_SORTBY", $sort_by);
$tpl_content->addvar("URI_SORTDIR", $sort_dir);

?>
