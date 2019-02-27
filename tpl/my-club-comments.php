<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path."sys/lib.club.php";

$clubManagement = ClubManagement::getInstance($db);
$clubId = (isset($ar_params[1]) ? $ar_params[1] : 0);
$clubAction = (isset($ar_params[2]) ? $ar_params[2] : "view");
$show = (!empty($ar_params[3]) ? $ar_params[3] : "received");

$pageCur = 1;
$pageItems = 10;

$tpl_content->addvar("SHOW", $show);
$tpl_content->addvar("show_".$show, 1);
$tpl_content->addvar("PAGE", $pageCur);
$tpl_content->addvar("PERPAGE", $pageItems);
// Presearch for club
$tpl_content->addvar("TYPE", "GROUP_".$clubId);
// Hide Actions if not moderator or owner
$club_owner = $clubManagement->isClubOwner($clubId, $uid);
if (!$club_owner && !$clubManagement->isClubModerator($clubId, $uid)) {
    $tpl_content->addvar("HIDE_ACTIONS", 1);
}

$ar_club = array();
if (!$clubId) {
	$liste = $clubManagement->getClubsByUser($uid);
	if (count($liste) == 1) {
		$url = $tpl_content->tpl_uri_action("my-club-description,".$liste[0]["ID_CLUB"].",view");
		die(forward($url));
	}
}

switch ($clubAction) {
	case 'view':
		if ($clubId > 0 && $clubManagement->isMember($clubId, $uid)) {
			$ar_club = $clubManagement->getClubById($clubId);
		}
		$ar_club["ADMIN"] = ($ar_club["FK_USER"] == $uid);
		$ar_club["MODERATOR"] = ($ar_club["ADMIN"] || $clubManagement->isClubModerator($clubId, $uid));
		break;
}

$tpl_content->addvars($ar_club, "CLUB_");
if ($show == "received") {
	$tpl_content->addvar("FK_USER_OWNER", $uid);
} else {
	$tpl_content->addvar("FK_USER", $uid);
}

$tpl_content->addvar("CLUB_OWNER", $club_owner);
$tpl_content->addvar("GROUP_CLUB_ID_CLUB", "GROUP_".$ar_club["ID_CLUB"]);
?>
