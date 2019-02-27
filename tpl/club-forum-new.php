<?php
/* ###VERSIONSBLOCKINLCUDE### */

/**
 * @author ebiz-consult
 * @copyright 2013
 */

include_once $ab_path.'sys/lib.groupforum.php';
require_once $ab_path."sys/lib.club.php";
$clubManagement = ClubManagement::getInstance($db);

$id_club = (int)$ar_params[2];
$action = (isset($ar_params[3]) ? $ar_params[3] : "new");
$id_discussion = (int)$ar_params[4];
$id_discussion_comment = (int)$ar_params[5];

$sort = array("STAMP_UPDATE" => "DESC");

/** Club **/
$ar_club = $clubManagement->getClubById($id_club);
$isUserMember = $clubManagement->isMember($id_club, $uid);
if (!empty($ar_club) && ($ar_club["FORUM_ENABLED"] && ($isUserMember || ($ar_club['FK_USER'] == $uid))) && (($ar_club['STATUS'] == 1) || ($ar_club['FK_USER'] == $uid))) {
    $ar_club['LOGO'] = ($ar_club['LOGO'] != "" ? 'cache/club/logo/'.$ar_club['LOGO'] : null);
    $forum = new GroupForum($id_club);
    if (!empty($_POST)) {
        switch ($action) {
            case 'reply':
                // Reply to a thread
                $idPost = $forum->newComment($id_discussion, $_POST['BODY']);
                $arThread = $forum->getThread($id_discussion);
                if ($idPost > 0) {
                    if ($ar_club["IS_ADMIN"] || $ar_club["IS_MODERATOR"]) {
                        $arComment = array(
                            "REVIEWED"  => (int)$_POST["REVIEWED"],
                            "ANNOUNCE"  => (int)$_POST["ANNOUNCE"],
                            "STICKY"    => (int)$_POST["STICKY"]
                        );
                        $forum->updateComment($idThread, $arComment, false);
                    }
                    $page = $forum->getThreadCommentPage($id_discussion, $idPost);
                    die(forward($tpl_content->tpl_uri_action("club-forum-view,".chtrans($arThread["NAME"]).",".$id_discussion.",".$page)));
                }
                break;
            case 'edit':
                $arThread = array(
                    "NAME"    => $_POST["NAME"],
                    "BODY"    => $_POST["BODY"],
                    "PUBLIC"  => $_POST["PUBLIC"]
                );
                if ($ar_club["IS_ADMIN"] || $ar_club["IS_MODERATOR"]) {
                    $arThread["REVIEWED"] = (int)$_POST["REVIEWED"];
                    $arThread["ANNOUNCE"] = (int)$_POST["ANNOUNCE"];
                    $arThread["STICKY"] = (int)$_POST["STICKY"];
                }
                if ($forum->updateThread($id_discussion, $arThread)) {
                    // Success
                    die(forward($tpl_content->tpl_uri_action("club-forum-view,".chtrans($_POST["NAME"]).",".$id_discussion.",1,saved")));
                }
                break;
            case 'edit_post':
                $arComment = array("BODY" => $_POST["BODY"]);
                /*
                if ($ar_club["IS_ADMIN"] || $ar_club["IS_MODERATOR"]) {
                    $arComment["REVIEWED"] = (int)$_POST["REVIEWED"];
                    $arComment["ANNOUNCE"] = (int)$_POST["ANNOUNCE"];
                    $arComment["STICKY"] = (int)$_POST["STICKY"];
                }
                */
                if ($forum->updateComment($id_discussion_comment, $arComment)) {
                    // Success
                    $arThread = $forum->getThread($id_discussion);
                    $page = $forum->getThreadCommentPage($id_discussion, $id_discussion_comment);
                    die(forward($tpl_content->tpl_uri_action("club-forum-view,".chtrans($arThread["NAME"]).",".$id_discussion.",".$page.",saved")));
                }
                break;
            case 'new':
            default:
                // New thread
                $idThread = $forum->newThread($_POST['NAME'], $_POST['BODY'], ($_POST['PUBLIC'] ? GroupForum::ACCESS_PUBLIC : GroupForum::ACCESS_PRIVATE));
                if ($idThread > 0) {
                    if ($ar_club["IS_ADMIN"] || $ar_club["IS_MODERATOR"]) {
                        $arThread = array(
                            "REVIEWED"  => (int)$_POST["REVIEWED"],
                            "ANNOUNCE"  => (int)$_POST["ANNOUNCE"],
                            "STICKY"    => (int)$_POST["STICKY"]
                        );
                        $forum->updateThread($idThread, $arThread, false);
                    }
                    //die("Success! ".$idThread);
                    die(forward($tpl_content->tpl_uri_action("club-forum-view,".chtrans($arThread["NAME"]).",".$idThread)));
                }
                break;
        }
        $tpl_content->addvars($_POST, "FORUM_");
    } else {
        $arThread = array();
        switch ($action) {
            case 'reply':
                // Reply to a thread
                $arThread = $forum->getThread($id_discussion);
                if (!$arThread["PUBLIC"] && !$clubManagement->isMember($id_club, $uid)) {
                    // User does not have access to this thread
                    die(forward($tpl_content->tpl_uri_action("club-forum-view,".$id_club.",no_access,".$idThread)));
                }
                unset($arThread["BODY"]);
                break;
            case 'edit':
                // Edit a thread
                $arThread = $forum->getThread($id_discussion);
                if (($uid != $arThread["FK_USER"]) && !$ar_club["IS_MODERATOR"] && !$ar_club["IS_ADMIN"]) {
                    die(forward( $tpl_content->tpl_uri_baseurl("/404.htm") ));
                }
                break;
            case 'edit_post':
                // Edit a post/comment
                $arThread = $forum->getThread($id_discussion);
                $arComment = $forum->getThreadComment($id_discussion_comment);
                if (($uid != $arComment["FK_USER"]) && !$ar_club["IS_MODERATOR"] && !$ar_club["IS_ADMIN"]) {
                    die(forward( $tpl_content->tpl_uri_baseurl("/404.htm") ));
                }
                $arThread["BODY"] = $arComment["BODY"];
                break;
            case 'new':
                $tpl_content->addvar("FORUM_PUBLIC", GroupForum::ACCESS_PUBLIC);
                break;
            default:
                break;
        }
        $tpl_content->addvars($arThread, "FORUM_");
    }
    $tpl_content->addvar("ACTION_".strtoupper($action), 1);
    $tpl_content->addvars($ar_club, "CLUB_");
} else {
    die(forward( $tpl_content->tpl_uri_baseurl("/404.htm") ));
}


$tpl_content->addvar("active_club_forum", 1);
?>