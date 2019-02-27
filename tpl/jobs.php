<?php
/* ###VERSIONSBLOCKINLCUDE### */


require_once 'sys/lib.job.php';

$jobManagement = JobManagement::getInstance($db);
$jobManagement->setLangval($langval);


$categoryId = ($ar_params[1] ? (int)$ar_params[1] : null);
$searchHash = ($ar_params[2] ? (string)$ar_params[2] : null);
$curpage = ($ar_params[3] ? (int)$ar_params[3] : 1);
$sort_by = ($ar_params[4] ? $ar_params[4] : "STAMP");
$sort_dir = ($ar_params[5] ? $ar_params[5] : "DESC");

$perpage = 10; // Elemente pro Seite
$offset = (($curpage-1)*$perpage);

$tmp = $db->fetch_atom("SELECT S_STRING FROM `searchstring` WHERE `QUERY`='".mysql_real_escape_string($searchHash)."'");
if($tmp != "") { $searchParameter = unserialize($tmp); } else { $searchParameter = array(); }

if($categoryId != null) {    
    include_once "sys/lib.shop_kategorien.php";
    $kat = new TreeCategories("kat", 6);
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

$searchParameter['LIMIT'] = $perpage;
$searchParameter['OFFSET'] = $offset;
$searchParameter['PUBLISHED'] = true;
$searchParameter['JOIN_VENDOR'] = true;

$possibleSort = array('STAMP' => 'j.STAMP', 'NAME' => 'sj.V1');
if(array_key_exists($sort_by, $possibleSort) && in_array($sort_dir, array('ASC', 'DESC'))) {
    $searchParameter['SORT'] = $possibleSort[$sort_by];
    $searchParameter['SORT_DIR'] = $sort_dir;

    $tpl_content->addvar(strtoupper("CUR_SORT_".$sort_by."_".$sort_dir), true);
}

$jobs = $jobManagement->fetchAllByParam($searchParameter);
$countJobs = $jobManagement->countByParam($searchParameter);


foreach($jobs as $key => $adRequest) {
    /*$categories = $adRequests->fetchAllVendorCategoriesByVendorId($vendor['ID_VENDOR']);

    $tpl_categories = new Template($ab_path."tpl/".$s_lang."/ad_request.row.categories.htm");
    $tpl_categories->addlist("categories", $categories, $ab_path.'tpl/'.$s_lang.'/$adRequests.row.categories.row.htm');

    $vendors[$key]['VENDOR_LOGO'] = ($vendor['VENDOR_LOGO'] != "")?'cache/vendor/logo/'.$vendor['VENDOR_LOGO']:null;
    $vendors[$key]['VENDOR_CATEGORIES'] = $tpl_categories->process();*/
    #$adRequests[$key]['BESCHREIBUNG_KURZ'] = substr(strip_tags($adRequests[$key]['BESCHREIBUNG']), 0, 200);
}


$tpl_content->addlist("liste", $jobs, $ab_path.'tpl/'.$s_lang.'/jobs.row.htm');
$tpl_content->addvar("pager", htm_browse_extended($countJobs, $curpage, "jobs,".$categoryId.",".$searchHash.",{PAGE},".$sort_by.",".$sort_dir, $perpage));
$tpl_content->addvar("ALL_JOBS", $countJobs);

// Kategorie
$cache_tree = $ab_path."/cache/marktplatz/kat_jobs_".($categoryId != null ? (int)$categoryId : 0).".".$s_lang.".htm";
if (!file_exists($cache_tree) ) {
	$categoryTree = $jobManagement->getJobCategoryTreeFlat($categoryId);

    $tpl_cache = new Template("tpl/de/empty.htm");
    $tpl_cache->tpl_text = "{list}";
    $tpl_cache->addlist_fast("list", $categoryTree, "tpl/".$s_lang."/jobs.category.htm");
    $htm_cache = $tpl_cache->process();
	file_put_contents($cache_tree, $htm_cache);

	$tpl_content->addvar("CATEGORY_TREE", $htm_cache);

} else {
	$tpl_content->addvar("CATEGORY_TREE", file_get_contents($cache_tree));
}

$tpl_content->addvars($searchParameter);
$tpl_content->addvar("URI_CURPAGE", $curpage);
$tpl_content->addvar("URI_SEARCHHASH", $searchHash);
$tpl_content->addvar("URI_SORTBY", $sort_by);
$tpl_content->addvar("URI_SORTDIR", $sort_dir);
