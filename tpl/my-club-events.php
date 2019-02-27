<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path."sys/lib.club.php";

$clubManagement = ClubManagement::getInstance($db);
$clubId = (isset($ar_params[1]) ? $ar_params[1] : 0);
$clubAction = (isset($ar_params[2]) ? $ar_params[2] : "view");
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
$tpl_content->addvar("GROUP_CLUB_ID_CLUB", "GROUP_" . $ar_club["ID_CLUB"]);
$tpl_content->addvar("STAMP_TODAY", date("Y-m-d H:i:s"));

?>