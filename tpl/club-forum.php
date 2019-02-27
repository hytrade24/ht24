<?php
/* ###VERSIONSBLOCKINLCUDE### */

/**
 * @author ebiz-consult
 * @copyright 2013
 */

include_once $ab_path.'sys/lib.groupforum.php';
require_once $ab_path."sys/lib.club.php";
$clubManagement = ClubManagement::getInstance($db);

#Seitenzähler
$id_club = (int)$ar_params[2];
$curpage = ($ar_params[3] ? $ar_params[3] : $ar_params[3]=1);
$perpage = 20; //$nar_systemsettings['MARKTPLATZ']['CLUB_MEMBERVIEWS']; // Elemente pro Seite
$commentsCountInitial = 3;
$offset = (($curpage-1)*$perpage);
$searchhash = ($ar_params[4] ? $ar_params[4] : false);
$arSearch = array();
$sortField = ($ar_params[5] ? $ar_params[5] : "STAMP_REPLY");
$sortOrder = ($ar_params[6] ? $ar_params[6] : "DESC");
$notice = (isset($ar_params[7]) ? $ar_params[7] : false);

if ($notice !== false) {
    $tpl_content->addvar("NOTICE_".strtoupper($notice), 1);
}

$sort = array("ANNOUNCE" => "DESC", $sortField => $sortOrder, "STAMP_CREATE" => "DESC");

if (isset($_POST['SEARCH'])) {
    if (!empty($_POST['SEARCH'])) {
        if (!is_array($_SESSION["CLUB_SEARCH_FORUM"])) {
            $_SESSION["CLUB_SEARCH_FORUM"] = array();
        }
        $arSearch = array("FULLTEXT" => $_POST['SEARCH']);
        $searchhash = substr(md5(serialize($arSearch)), 0, 12);
        $_SESSION["CLUB_SEARCH_FORUM"][$searchhash] = $arSearch;
        $url = $tpl_content->tpl_uri_action("group-forum,".$ar_params[1].",".$id_club.",1,".$searchhash.",".$sortField.",".$sortOrder);
        die(forward($url));
    }
} else if (($searchhash !== false) && array_key_exists($searchhash, $_SESSION["CLUB_SEARCH_FORUM"])) {
    $arSearch = $_SESSION["CLUB_SEARCH_FORUM"][$searchhash];
}

$tpl_content->addvar("CUR_SORT_".$sortField."_".$sortOrder, 1);

/** Club **/
$ar_club = $clubManagement->getClubById($id_club);
$isUserMember = $clubManagement->isMember($id_club, $uid);
if (!empty($ar_club) && ($ar_club["FORUM_ENABLED"] && ($isUserMember || $ar_club["FORUM_PUBLIC"])) && (($ar_club['STATUS'] == 1) || ($ar_club['FK_USER'] == $uid))) {
    $ar_club['LOGO'] = ($ar_club['LOGO'] != "" ? 'cache/club/logo/'.$ar_club['LOGO'] : null);

    if (!$isUserMember) {
        $arSearch["PUBLIC"] = 1;
    }
    $forum = new GroupForum($id_club);
    $all = 0;
    $ar_threads = $forum->getThreads($sort, $perpage, $offset, $all, $arSearch);

    foreach ($ar_threads as $threadIndex => $thread) {
        //var_dump($thread);
        $ar_threads[$threadIndex]["LAST_PAGE"] = $forum->getThreadCommentPage($thread["ID_CLUB_DISCUSSION"]);
    }


    //die(var_dump($ar_threads));
    $tpl_content->addvars($arSearch, "SEARCH_");
    $tpl_content->addvars($ar_club, "CLUB_");
    $tpl_content->addlist("liste", $ar_threads, "tpl/".$s_lang."/club-forum.row.htm");
    $tpl_content->addvar("ALL_THREADS", $all);
    $tpl_content->addvar("USER_IS_CLUB_MEMBER", $isUserMember);
    $urlPager = "club-forum,".addnoparse(chtrans($ar_club["NAME"])).",".$id_club.",{PAGE},".$searchhash.",".$sortField.",".$sortOrder;
    $tpl_content->addvar("pager", htm_browse_extended($all, $curpage, $urlPager, $perpage));
} else {
    $url = $tpl_content->tpl_uri_baseurl("/404.htm");
    die(forward( $url ));
}


$tpl_content->addvar("active_club_forum", 1);
?>