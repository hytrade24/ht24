<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path."sys/lib.club.php";
$clubManagement = ClubManagement::getInstance($db);

$paginatorPage = ((int)$ar_params[3] ? (int)$ar_params[3] : 1);
$paginatorItemsPerPage = 20;
$paginatorOffset = ($paginatorItemsPerPage*$paginatorPage)-$paginatorItemsPerPage;

$userId = ((int)$ar_params[2] ? (int)$ar_params[2] : null);
$user_ = $db->fetch1("select VORNAME,NACHNAME,NAME,CACHE,STAMP_REG,LASTACTIV,URL,STRASSE,PLZ,ORT,LU_PROFESSION,ID_USER as USER_ID_USER ,UEBER, ROUND(RATING) as lastrate,TIMESTAMPDIFF(YEAR,GEBDAT,CURDATE()) as age,TEL,RATING,UEBER from user where ID_USER='". $userId."'");

if(($userId != null) && ($user != null)) {
	$countUserClubs = $clubManagement->countClubsWhereUserIsMember($userId);
	$userClubs = $clubManagement->getClubsByUser($userId, $langval, $paginatorPage, $paginatorItemsPerPage);
    foreach($userClubs as $key => $club) {
        $userClubs[$key]['LOGO'] = ($club['LOGO'] != "")?'cache/club/logo/'.$club['LOGO']:null;
    }

	$tpl_content->addlist("clubs", $userClubs, $ab_path.'tpl/'.$s_lang.'/view_user_clubs.row.htm');
	$pager = htm_browse_extended($countUserClubs, $paginatorPage, "view_user_clubs,".$ar_params[1].",".$userId.",{PAGE}", $paginatorItemsPerPage);
	$tpl_content->addvar("pager", $pager);
	$tpl_content->addvar("UID", $uid);

	#$tpl_content->addvars($user_, 'USER_');
}

$tpl_content->addvar("active_club", 1);
