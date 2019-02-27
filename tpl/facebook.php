<?php
/**
 * ebiz-trader
 *
 * @copyright Copyright (c) 2012 ebiz-consult e.K.
 * @version 6.5.3
 */

function killbb(&$row,$i) {
    $description = strip_tags(html_entity_decode($row['DSC']));
    if (strlen($description) > 250) {
        $description = substr($description, 0, 250)." ...";
    }
    $row['DSC'] = preg_replace("#(\[)([^\s\]]*)(\])#si", "", $description);
}

require_once $ab_path.'sys/lib.ad_constraint.php';
require_once $ab_path.'sys/lib.pub_kategorien.php';
$kat_cache = new CategoriesCache();

require_once $ab_path.'sys/lib.facebook.php';
$facebookConfig = array(
    'appId'                 => $nar_systemsettings["NETWORKS"]["FACEBOOK_APP_ID"],
    'secret'                => $nar_systemsettings["NETWORKS"]["FACEBOOK_APP_SECRET"],
    'allowSignedRequest'    => true
);
$facebookManagement = FacebookManagement::getInstance($db);
$facebookApi = new Facebook($facebookConfig);
$facebookSettings = false;

$action = $ar_params[1];
if (!empty($action)) {
    switch ($action) {
        case "add":
            if (is_array($_REQUEST['tabs_added'])) {
                $pageIds = array_keys($_REQUEST['tabs_added']);
                $pageId = $pageIds[0];
                $forwardUrl = "https://www.facebook.com/pages/-/".$pageId."?sk=app_".$nar_systemsettings["NETWORKS"]["FACEBOOK_APP_ID"];
                die(forward($forwardUrl));
            } else {
                $_SESSION["FACEBOOK_CODE"] = $_REQUEST["code"];
                $nextUrl = $tpl_content->tpl_uri_action_full("facebook,add");
                $forwardUrl = "https://www.facebook.com/dialog/pagetab?app_id=".$nar_systemsettings["NETWORKS"]["FACEBOOK_APP_ID"].
                    "&next=".urlencode($nextUrl);
                die(forward($forwardUrl));
            }
    }
}

$facebookPageId = false;
$facebookInfo = $facebookApi->getSignedRequest();
if ($facebookInfo !== null) {
    $facebookPageId = $facebookInfo['page']['id'];
    $facebookSettings = $facebookManagement->getUserFacebookSiteById($facebookPageId);
    if ($facebookInfo["page"]["admin"]) {
        $_SESSION["FACEBOOK_SIGNED_REQUEST"] = $facebookApi->getSignedRequest();
        $tpl_content->addvar("IS_PAGE_ADMIN", 1);
    } else {
        $_SESSION["FACEBOOK_SIGNED_REQUEST"] = null;
    }
} else {
    $facebookPageId = $_REQUEST['FACEBOOK_PAGE_ID'];
    $facebookSettings = $facebookManagement->getUserFacebookSiteById($facebookPageId);
}
if ($facebookSettings !== false) {
    $tpl_content->addvar("FACEBOOK_PAGE_ID", $facebookSettings["FK_PAGE_ID"]);
    $tpl_content->addvar("IS_CONFIGURED", 1);
} else {
    // Do not query any ads etc.
    return;
}
$additionalParams = "?FACEBOOK_PAGE_ID=".urlencode($facebookSettings["FK_PAGE_ID"]);
$userId = $facebookSettings["FK_USER"];
/*
 * BACKUP OHNE APP
if (!$userId) {
} else {
    $facebookSettings = unserialize($db->fetch_atom("SELECT SER_FB_TAB FROM `user` WHERE ID_USER=".$uid));
    $tpl_content->addvar("IS_CONFIGURED", 1);
}
 */

$katId = (int)$_REQUEST['FK_KAT'];
$katTable = 'ad_master';

if ($katId > 0) {
    $katTable = $db->fetch_atom("SELECT KAT_TABLE FROM `kat` WHERE ID_KAT=".$katId);
} else {
    require_once $ab_path."sys/lib.shop_kategorien.php";
    $kat = new TreeCategories("kat", 1);
    $katId = $kat->tree_get_parent();
}

$curpage = (isset($_REQUEST['PAGE']) ? (int)$_REQUEST['PAGE'] : ($action > 0 ? $action : 1) );
$perpage = ($facebookSettings['COUNT_PER_PAGE'] ? $facebookSettings['COUNT_PER_PAGE'] : 10); // Elemente pro Seite
$limit = (($curpage-1)*$perpage);

$search_select = array();
$search_where = array();
$search_having = array();
$search_join = array();
$search_group_by = false;

$sort = array();
$sort_by = strtoupper($_POST['SORT_BY'] ? $_POST['SORT_BY'] : "RUNTIME");
$sort_dir = strtoupper($_POST['SORT_DIR'] ? $_POST['SORT_DIR'] : "DESC");
$sort_fields = array("PRODUKTNAME", "ZIP", "PREIS", "RUNTIME", "AD_LIKES", "TIME_AVAILABLE", "COMMENTS");
$sort_directions = array("DESC", "ASC");
$sort_vars = array();
foreach($sort_fields as $index => $field) {
    $sort_vars["SORT_".$field] = "DESC";
}
if (in_array($sort_by, $sort_fields) && in_array($sort_dir, $sort_directions)) {
    $sort[] = " adt.B_TOP_LIST DESC ";
    if ($sort_by == "RUNTIME")
    {
        $sort[] = "adt.STAMP_START ".$sort_dir;
    } elseif($sort_by == "AD_LIKES") {
        $sort[] = "adt.AD_LIKES ".$sort_dir;
    } elseif($sort_by == "TIME_AVAILABLE") {
        $sort[] = "TIME_AVAILABLE ".$sort_dir;
    } elseif($sort_by == "COMMENTS") {
        $sort[] = "COUNT_COMMENTS ".$sort_dir;
        $sort[] = "adt.ALLOW_COMMENTS ".$sort_dir;
    } else {
        $sort[] = "adt.".$sort_by." ".$sort_dir;
    }
    $sort_vars["SORT_DIRECTION"] = strtolower($sort_dir);
    $sort_vars["SORT_BY"] = strtolower($sort_by);
    $sort_vars["SORT_BY_".$sort_by] = ($sort_dir == "DESC" ? 1 : 2);
    $sort_vars["SORT_".$sort_by] = ($sort_dir == "DESC" ? "DESC" : "ASC");
    $string = str_replace(' ', '_', $sort_by."_".$sort_dir);
    $string = preg_replace("/^([a-z]{1,3}\.)/s", '', $string);
    $tpl_content->addvar("CUR_ORDER_".$string, $string);
} else {
    $sort_vars["SORT_DIRECTION"] = "asc";
    $sort_vars["SORT_BY"] = "RUNTIME";
    $sort_vars["SORT_BY_RUNTIME"] = 2;
    $sort_vars["SORT_RUNTIME"] = "DESC";
    $sort[] = " adt.B_TOP_LIST DESC ";
    $sort[] = "STAMP_START DESC";
}

// Filter by user
$search_where[] = "adt.FK_USER=".(int)$userId;
$tpl_content->addvar("USERNAME", $db->fetch_atom("SELECT NAME FROM `user` WHERE ID_USER=".(int)$userId));

/**
 * Search options
 */
$ids_kats = false;
if ($_REQUEST['search'] == 1) {
    $additionalParams .= "&search=1";
    $tpl_content->addvars($_POST, "SEARCH_");
    // === Search sent ===

    if ($katId > 0) {
        $tpl_content->addvar("SEARCH_FK_KAT", $katId);
        $additionalParams .= "&FK_KAT=".$katId;
        $row_kat = $db->fetch1("SELECT * FROM `kat` WHERE ID_KAT=".(int)$katId);
        // Get all category ids to be searched
        $ids_kats = $db->fetch_nar("
        SELECT ID_KAT
          FROM `kat`
        WHERE
          (LFT >= ".$row_kat["LFT"].") AND
          (RGT <= ".$row_kat["RGT"].") AND
          (ROOT = ".$row_kat["ROOT"].")");
        $ids_kats = "(".implode(",",array_keys($ids_kats)).")";
    }
    if (!empty($_REQUEST["TEXT"])) {
        $searchText = $_REQUEST["TEXT"];
        $tpl_content->addvar("SEARCH_TEXT", $searchText);
        $additionalParams .= "&TEXT=".urlencode($searchText);
        $search_join[] = "JOIN ad_search ON ad_search.FK_AD = a.".strtoupper("ID_".$katTable)." AND ad_search.lang = '".mysql_real_escape_string($s_lang)."' ";
        $search_where[] = "(MATCH (ad_search.STEXT) AGAINST ('".generateFulltextSearchstring($searchText)."' IN BOOLEAN MODE))";
    }
}

$all = $db->fetch_atom($query = "SELECT	COUNT(*) FROM (
		SELECT
			adt.ID_AD_MASTER
		FROM `".$katTable."` a
		JOIN ad_master adt ON a.ID_".strtoupper($katTable)." = adt.ID_AD_MASTER
		".implode(" ", $search_join)."
		WHERE
			(adt.STATUS&3)=1 AND (adt.DELETED=0) ".($ids_kats !== false ? " AND adt.FK_KAT IN ".$ids_kats : "").
            ($search_where ? " AND ".implode(" AND ", $search_where) : "")."
            ".(($search_group_by || $search_having) ? "GROUP BY adt.ID_AD_MASTER" : "" )."
            ".($search_having ? "HAVING ".$search_having : "")."
    ) tmp");

$ads = $db->fetch_table($q="
		SELECT
			adt.*,
			adt.ID_AD_MASTER as ID_AD,
			adt.BESCHREIBUNG AS DSC, adt.TRADE AS product_trade,
			m.NAME as MANUFACTURER,
			(SELECT
				s.V1
				FROM `kat` k
				LEFT JOIN `string_kat` s ON s.S_TABLE='kat' AND s.FK=k.ID_KAT
				AND s.BF_LANG=if(k.BF_LANG_KAT & ".$langval.", ".$langval.", 1 << floor(log(k.BF_LANG_KAT+0.5)/log(2)))
				WHERE k.ID_KAT=a.FK_KAT
			) as KAT,
			(SELECT slang.V1 FROM `string` slang WHERE slang.S_TABLE='country' AND slang.FK=a.FK_COUNTRY
				AND slang.BF_LANG='".$langval."' LIMIT 1) as LAND,
			(SELECT i.SRC_THUMB FROM `ad_images` i WHERE i.IS_DEFAULT=1 AND i.FK_AD=ID_AD LIMIT 1) as SRC_THUMB,
			(SELECT i.SRC FROM `ad_images` i WHERE i.IS_DEFAULT=1 AND i.FK_AD=ID_AD LIMIT 1) as SRC,
			(SELECT AMOUNT FROM `comment_stats` WHERE `TABLE` = 'ad_master' AND FK = adt.ID_AD_MASTER) as COUNT_COMMENTS,
			adt.B_TOP".
    (!empty($search_select) ? ",\n			".implode(",\n			", $search_select) : "")."
		FROM `".$katTable."` a
		LEFT JOIN `manufacturers` m ON m.ID_MAN=a.FK_MAN
		JOIN ad_master adt ON a.ID_".strtoupper($katTable)." = adt.ID_AD_MASTER
		".implode(" ", $search_join)."
		WHERE
			(adt.STATUS&3)=1 AND (adt.DELETED=0)
			".($ids_kats !== false ? " AND adt.FK_KAT IN ".$ids_kats : "")."
            ".($search_where ? " AND ".implode(" AND ", $search_where) : "")."
		    ".(($search_group_by || $search_having) ? "GROUP BY adt.ID_AD_MASTER" : "" )."
		    ".($search_having ? "HAVING ".$search_having : "").
    "ORDER BY ".implode(",",$sort)."
		LIMIT ".$limit.",".$perpage);
Rest_MarketplaceAds::extendAdDetailsList($ads);

#die($q);
// Categories
$tpl_content->addvar('liste_categories', $tpl_content->process_text($kat_cache->cacheKatSelectOptions(1, $userId)));

// Ad list
$tpl_content->isTemplateRecursiveParsable = TRUE;
$tpl_content->isTemplateCached = TRUE;
$tpl_content->addvars($sort_vars);
$tpl_content->addlist("liste_ads", $ads, "tpl/".$s_lang."/facebook.row.htm", 'killbb');
$tpl_content->addvar("pager", htm_browse_extended($all, $curpage, "facebook,{PAGE}", $perpage, 5, $additionalParams));
$tpl_content->addvar('VIEW_TYPE', 'LIST');
$tpl_content->addvar('VIEW_TYPE_LIST', 1);
$tpl_content->addvar('VIEW_TYPE_BOX', 0);
$tpl_content->addvar('noads', 1);
