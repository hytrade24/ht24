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
$id_club_discussion = (int)$ar_params[2];
$id_club = $db->fetch_atom("SELECT FK_CLUB FROM `club_discussion` WHERE ID_CLUB_DISCUSSION=".$id_club_discussion);
$tpl_content->vars['OVERRIDE_CLUB_ID'] = $id_club;
$curpage = ($ar_params[3] ? $ar_params[3] : $ar_params[3]=1);
$notice = (isset($ar_params[4]) ? $ar_params[4] : false);
$perpage = 10; //$nar_systemsettings['MARKTPLATZ']['CLUB_MEMBERVIEWS']; // Elemente pro Seite
$commentsCountInitial = 3;
$offset = ($curpage > 1 ? (($curpage-1)*$perpage) - 1 : 0);

if ($notice !== false) {
    $tpl_content->addvar("NOTICE_".strtoupper($notice), 1);
}

$sort = array($sortField => $sortOrder);

/** Club **/
$ar_club = $clubManagement->getClubById($id_club);
$isUserMember = $clubManagement->isMember($id_club, $uid);
if (!empty($ar_club) && ($ar_club["FORUM_ENABLED"] && ($isUserMember || $ar_club["FORUM_PUBLIC"])) && (($ar_club['STATUS'] == 1) || ($ar_club['FK_USER'] == $uid))) {
    $ar_club['LOGO'] = ($ar_club['LOGO'] != "" ? 'cache/club/logo/'.$ar_club['LOGO'] : null);

    $forum = new GroupForum($id_club);
    $all = 0;
    $ar_thread = $forum->getThread($id_club_discussion);
    if (!$ar_thread["PUBLIC"] && !$clubManagement->isMember($id_club, $uid)) {
        // User does not have access to this thread
        die(forward( $tpl_content->tpl_uri_baseurl("/404.htm") ));
    }
    if (!empty($_POST)) {
        if ($_POST['CLOSE']) {
            $forum->setClosed($id_club_discussion, GroupForum::DISCUSSION_CLOSE);
            die(forward( $tpl_content->tpl_uri_action("club-forum-view,".chtrans($ar_thread["NAME"]).",".$id_club_discussion.",".$curpage.",closed") ));
        }
        if ($_POST['OPEN']) {
            $forum->setClosed($id_club_discussion, GroupForum::DISCUSSION_OPEN);
            die(forward( $tpl_content->tpl_uri_action("club-forum-view,".chtrans($ar_thread["NAME"]).",".$id_club_discussion.",".$curpage.",opened") ));
        }
        if ($_POST['DELETE']) {
            $forum->deleteThread($id_club_discussion);
            die(forward( $tpl_content->tpl_uri_action("club-forum,".chtrans($ar_club["NAME"]).",".$id_club.",,,,,deleted") ));
        }
    }
    $forum->updateViewsCounter($id_club_discussion);
    $ar_comments = array();
    if ($offset == 0) {
        $ar_comments[] = $ar_thread;
    }
    $ar_comments = array_merge($ar_comments, $forum->getThreadComments($id_club_discussion, $perpage-1, $offset, $all, false));
    foreach ($ar_comments as $commentIndex => $ar_comment) {
        $ar_comments[$commentIndex]["BODY"] = GroupForum::ProcessBody($ar_comment["BODY"]);
    }
    
    //die(var_dump($ar_threads));
    $tpl_content->addvar("USER_IS_MEMBER", ($isUserMember || ($ar_club['FK_USER'] == $uid)));   // Member or owner
    $tpl_content->addvars($ar_thread, "THREAD_");
    $tpl_content->addvars($ar_club, "CLUB_");
    $tpl_content->addlist("liste", $ar_comments, "tpl/".$s_lang."/club-forum-view.row.htm");
    $tpl_content->addvar("ALL_POSTS", $all + 1);
    $tpl_content->addvar("pager", htm_browse_extended($all+1, $curpage, "club-forum-view,".chtrans($ar_thread["NAME"]).",".$id_club_discussion.",{PAGE}", $perpage));
} else {
    $url = $tpl_content->tpl_uri_baseurl("/404.htm");
    die(forward( $url ));
}


$tpl_content->addvar("active_club_forum", 1);
?>