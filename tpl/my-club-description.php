<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path."sys/lib.club.php";

$clubManagement = ClubManagement::getInstance($db);
$clubId = (isset($ar_params[1]) ? $ar_params[1] : 0);
$clubAction = (isset($ar_params[2]) ? $ar_params[2] : "view");
$ar_club = array();

if (!empty($_POST)) {
	// Form abgeschickt
	$clubId = $_POST["ID_CLUB"];
	$clubAction = $_POST["do"];
}

if (!$clubId) {
	$liste = $clubManagement->getClubsByUser($uid);
	if (count($liste) == 1) {
		$url = $tpl_content->tpl_uri_action("my-club-description,".$liste[0]["ID_CLUB"].",view");
		die(forward($url));
	}
}

switch ($clubAction) {
	case 'saved':
		$tpl_content->addvar("saved", 1);
	case 'view':
		if ($clubId > 0 && $clubManagement->isMember($clubId, $uid)) {
			$ar_club = $clubManagement->getClubById($clubId);
		}
		$ar_club["ADMIN"] = ($ar_club["FK_USER"] == $uid);
		$ar_club["MODERATOR"] = ($ar_club["ADMIN"] || $clubManagement->isClubModerator($clubId, $uid));
		break;
	case 'save':
		if ($clubId > 0 && ($clubManagement->isClubOwner($clubId, $uid) || $clubManagement->isClubModerator($clubId, $uid))) {
			$err = array();
			$ar_club = array_merge($clubManagement->getClubById($clubId), $_POST);
			if ($clubManagement->updateCheckFields($ar_club, $err)) {
				$clubManagement->update($ar_club, $langval);
			}
			if (empty($err)) {
				// Success
				$url = $tpl_content->tpl_uri_action("my-club-description,".$clubId.",saved");
				die(forward($url));
			} else {
				// Failed!
				$ar_club["ADMIN"] = ($ar_club["FK_USER"] == $uid);
				$ar_club["MODERATOR"] = ($ar_club["ADMIN"] || $clubManagement->isClubModerator($clubId, $uid));
				$htm_errors = "<li>".implode("</li>\n<li>", get_messages("CLUB", implode(",", $err)))."</li>";
				$tpl_content->addvar("errors", $htm_errors);
			}
		}
		break;
}

// Sprachrelevante Felder
$languageSelection = $db->fetch_table("SELECT * FROM lang WHERE B_PUBLIC = 1");

foreach($languageSelection as $key=>$value) {
	$ar_club_lang = $clubManagement->getClubById($clubId, $value['BITVAL']);
	$languageSelection[$key]['CLUB_DESCRIPTION'] = $ar_club_lang["T1"];
}

$tpl_content->addlist("languageHeader", $languageSelection, $ab_path.'tpl/'.$s_lang.'/my-club-description.lang.header.htm');
$tpl_content->addlist("languageBody", $languageSelection, $ab_path.'tpl/'.$s_lang.'/my-club-description.lang.body.htm');


$tpl_content->addvars($ar_club, "CLUB_");

?>