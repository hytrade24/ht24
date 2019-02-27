<?php
/**
 * Created by PhpStorm.
 * User: jura
 * Date: 16.01.14
 * Time: 16:04
 */

require_once $ab_path.'sys/lib.groupforum.php';
require_once $ab_path."sys/lib.club.php";

$clubManagement = ClubManagement::getInstance($db);
$clubId = (isset($ar_params[1]) ? $ar_params[1] : 0);
$clubFilter = (isset($ar_params[2]) ? $ar_params[2] : "all");
$curpage = ($ar_params[3] ? $ar_params[3] : $ar_params[3]=1);
$perpage = 10; //$nar_systemsettings['MARKTPLATZ']['CLUB_MEMBERVIEWS']; // Elemente pro Seite
$commentsCountInitial = 3;
$offset = (($curpage-1)*$perpage);
$searchhash = ($ar_params[4] ? $ar_params[4] : false);
$arSearch = array();
$sortField = ($ar_params[5] ? $ar_params[5] : "STAMP_CREATE");
$sortOrder = ($ar_params[6] ? $ar_params[6] : "DESC");

// Hide Actions if not moderator or owner
$club_owner = $clubManagement->isClubOwner($clubId, $uid);
if (!$club_owner && !$clubManagement->isClubModerator($clubId, $uid)) {
    die(forward( $tpl_content->tpl_uri_action("404") ));
}

$ar_club = $clubManagement->getClubById($clubId);
$ar_club["ADMIN"] = $ar_club["IS_ADMIN"];
$ar_club["MODERATOR"] = $ar_club["IS_ADMIN"] || $ar_club["IS_MODERATOR"];
$ar_club["FORUM_FILTER"] = $clubFilter;
$ar_club["FORUM_SORT_FIELD"] = $sortField;
$ar_club["FORUM_SORT_ORDER"] = $sortOrder;
$forum = new GroupForum($clubId);

if (isset($_REQUEST['done']) !== false) {
    $tpl_content->addvar("NOTICE_".strtoupper($_REQUEST['done']), 1);
}
if (!empty($_POST)) {
    $done = "";
    $urlParamPos = strpos($_SERVER['HTTP_REFERER'], '?');
    $urlBase = ($urlParamPos !== false ? substr($_SERVER['HTTP_REFERER'], 0, $urlParamPos) : $_SERVER['HTTP_REFERER']);
    if (isset($_POST["CLOSE"])) {
        if ($forum->setClosed((int)$_POST["CLOSE"], GroupForum::DISCUSSION_CLOSE)) {
            $done = "closed";
        }
    }
    if (isset($_POST["OPEN"])) {
        if ($forum->setClosed((int)$_POST["OPEN"], GroupForum::DISCUSSION_OPEN)) {
            $done = "opened";
        }
    }
    if (isset($_POST["DELETE"])) {
        if ($forum->deleteThread((int)$_POST["DELETE"])) {
            $done = "deleted";
        }
    }
    if (isset($_POST["REVIEW"])) {
        if ($forum->setReviewed((int)$_POST["REVIEW"])) {
            $done = "reviewed";
        }
    }
    die(forward($urlBase."?done=".$done));
}

$all = 0;
$sort = array("ANNOUNCE" => "DESC", "STICKY" => "DESC", $sortField => $sortOrder);
if (!$clubManagement->isMember($clubId, $uid)) {
    $arSearch["PUBLIC"] = 1;
}
switch ($clubFilter) {
    case 'unconfirmed':
        $arSearch["REVIEWED"] = 0;
        break;
    case 'confirmed':
        $arSearch["REVIEWED"] = 1;
        break;
    case 'all':
    default:
        break;
}
$ar_threads = $forum->getThreads($sort, $perpage, $offset, $all, $arSearch);

$tpl_content->addvar("CUR_SORT_".$sortField."_".$sortOrder, 1);
$tpl_content->addvar("CUR_FILTER_".strtoupper($clubFilter), 1);
$tpl_content->addvars($ar_club, "CLUB_");
$tpl_content->addlist("liste", $ar_threads, "tpl/".$s_lang."/my-club-forum.row.htm");
$tpl_content->addvar("ALL_THREADS", $all);
$urlPager = "my-club-forum,".$clubId.",".$clubFilter.",{PAGE},".$searchhash.",".$sortField.",".$sortOrder;
$tpl_content->addvar("pager", htm_browse_extended($all, $curpage, $urlPager, $perpage));

#$id_discussion = ($ar_params[2] ? $ar_params[2] : 0);
#$forum = new GroupForum($id);

//// insert some content
//$thread1 = $forum->newThread("Thread 1", "Lorem ipsum dolor sit amet, consectetur adipisicing elit. Aliquid asperiores assumenda beatae dolor dolorem expedita id illo ipsa ipsum iure non officia quam quisquam quo repellendus veniam veritatis voluptatem, voluptatibus.");
//$thread2 = $forum->newThread("Thread 2", "Lorem ipsum dolor sit amet, consectetur adipisicing elit. Aliquid asperiores assumenda beatae dolor dolorem expedita id illo ipsa ipsum iure non officia quam quisquam quo repellendus veniam veritatis voluptatem, voluptatibus.", GroupForum::DISCUSSION_OPEN);
//$thread3 = $forum->newThread("Thread 3", "Lorem ipsum dolor sit amet, consectetur adipisicing elit. Aliquid asperiores assumenda beatae dolor dolorem expedita id illo ipsa ipsum iure non officia quam quisquam quo repellendus veniam veritatis voluptatem, voluptatibus.");
//$thread4 = $forum->newThread("Thread 4", "Lorem ipsum dolor sit amet, consectetur adipisicing elit. Aliquid asperiores assumenda beatae dolor dolorem expedita id illo ipsa ipsum iure non officia quam quisquam quo repellendus veniam veritatis voluptatem, voluptatibus.", GroupForum::DISCUSSION_OPEN);
//
//for ($i = 0; $i < 10; $i++) {
//    $forum->newComment($thread1, "Lorem ipsum dolor sit amet, consectetur adipisicing elit. Aliquid asperiores assumenda beatae dolor dolorem expedita id illo ipsa ipsum iure non officia quam quisquam quo repellendus veniam veritatis voluptatem, voluptatibus.");
//}
//
//for ($i = 0; $i < 7; $i++) {
//    $forum->newComment($thread2, "Lorem ipsum dolor sit amet, consectetur adipisicing elit. Aliquid asperiores assumenda beatae dolor dolorem expedita id illo ipsa ipsum iure non officia quam quisquam quo repellendus veniam veritatis voluptatem, voluptatibus.");
//}
//
//// set some flags
//$forum->setAnnounce($thread1);
//$forum->setPublic($thread2);
//$forum->setSticky($thread3);
//$forum->setClosed($thread4);
//
//
//// retrieve some data
//// all data unsorted and sorted
//print_r($forum->getThreads());
//print_r($forum->getThreads(
//    array(
//        "COMMENTS" => "ASC"
//    )
//));
//
//
//// specific thread
//print_r($forum->getThread($thread1));
//print_r($forum->getThreadComments($thread1));
//print_r($forum->getThreadCommentsCount($thread1));
//
//// update some data
//$forum->updateThread($thread1, array(
//    "BODY" => "update Text"
//));
//$forum->updateThread($thread2, array(
//    "NAME" => "New Name",
//    "BODY" => "New Body"
//));
//
//// delete some threads
//$forum->deleteThread($thread3);
//$forum->deleteThread($thread4);

//// delete all by group
//$forum->deleteThreads();


//// search forum
//print_r($forum->searchThreads(
//    array( // search parameter
//        "NAME" => "Thread",
//        "ANNOUNCE" => 1
//    ),
//    array( // sort
//        "CLOSED" => "DESC"
//    ),
//    1 // limit
//));
