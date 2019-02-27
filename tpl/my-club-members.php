<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path."sys/lib.club.php";
require_once $ab_path."sys/lib.club_member_request.php";
// die(var_dump($_REQUEST));

$clubManagement = ClubManagement::getInstance($db);
$clubMemberRequestManagement = ClubMemberRequestManagement::getInstance($db);

$clubId = (isset($ar_params[1]) ? $ar_params[1] : 0);
$clubAction = (isset($ar_params[2]) ? $ar_params[2] : "view");

if (isset($_REQUEST["do"]) && isset($_REQUEST["ID_CLUB"])) {
	// Form abgeschickt
	$clubId = $_REQUEST["ID_CLUB"];
	$clubAction = $_REQUEST["do"];
}

if (!$clubId) {
	$liste = $clubManagement->getClubsByUser($uid);
	if (count($liste) == 1) {
		$url = $tpl_content->tpl_uri_action("my-club-members,".$liste[0]["ID_CLUB"].",view");
		die(forward($url));
	}
}

$perpage = 20;
$npage = ((int)$ar_params[3] ? $ar_params[3] : 1);
$all = $db->fetch_atom("SELECT count(*) FROM `club2user` WHERE FK_CLUB=".(int)$clubId)
	+ $db->fetch_atom("SELECT count(*) FROM `club_invite` WHERE FK_CLUB=".(int)$clubId);

$ar_club = array();
$ar_members = array();
$err = array();

switch ($clubAction) {
	case 'added':
	case 'saved':
	case 'kicked':
	case 'uninvited':
	case 'promoted':
	case 'mod_added':
	case 'mod_removed':
	case 'request_accepted':
	case 'request_declined':
	case 'request_blocked':

		$tpl_content->addvar($clubAction, 1);
		$clubAction = "view";
		break;
}

switch ($clubAction) {
	case 'view':
	case 'request_view':
		if ($clubId > 0 && $clubManagement->isMember($clubId, $uid)) {
			$ar_club = $clubManagement->getClubById($clubId);
			$ar_club["ADMIN"] = ($ar_club["FK_USER"] == $uid);
			$ar_club["MODERATOR"] = ($ar_club["ADMIN"] || $clubManagement->isClubModerator($clubId, $uid));

			if($clubAction == 'view') {
				$ar_members = $clubManagement->getMembersByClubId($clubId, true, $npage, $perpage);
			}

			// Requests
			if($clubAction == 'view') {
				$requestLimit = 10;
				$requestOffset =  0;
			} else {
				$requestLimit = $perpage;
				$requestOffset =  ($npage - 1) * $perpage;
				$tpl_content->addvar('hide_members', 1);
				$tpl_content->addvar('request_view', 1);
			}

			if($clubManagement->isClubOwner($clubId, $uid) || $clubManagement->isClubModerator($clubId, $uid)) {
				$clubMemberRequests = $clubMemberRequestManagement->fetchAllByParam(array(
					'FK_CLUB' => $clubId,
					'STATUS' => ClubMemberRequestManagement::STATUS_REQUEST_OPEN,
					'SORT' => 'cmr.STATUS ASC, cmr.STAMP_CREATE DESC',
					'SORT_DIR' => ' ',
					'LIMIT' => $requestLimit,
					'OFFSET' => $requestOffset
				));
				$numberOfMemberRequests = $clubMemberRequestManagement->countByParam(array(
					'FK_CLUB' => $clubId
				));

				foreach($clubMemberRequests as $key => $clubMemberRequest) {
					$clubMemberRequests[$key]['SHORT_REASON'] = substr(strip_tags($clubMemberRequest['REASON']), 0, 80);
				}

				if($clubAction == 'request_view') {
					$all = $numberOfMemberRequests;
				}

				$tpl_content->addlist("liste_requests", $clubMemberRequests, "tpl/".$s_lang."/my-club-members.row_requests.htm");
				$tpl_content->addvar("count_member_requests", $numberOfMemberRequests);

			}
		}
		break;
	case 'kick':
		if ($clubId > 0 && $clubManagement->isClubOwner($clubId, $uid)) {
			$id_user = ($ar_params[3] ? (int)$ar_params[3] : (int)$_REQUST["id"]);
			if ($clubManagement->remMember($clubId, $id_user)) {
				die(forward("my-club-members,".$clubId.",kicked.htm"));
			} else {
				$err[] = "RIGHTS_KICK";
			}
		}
		break;
	case 'mod_add':
		if ($clubId > 0 && $clubManagement->isClubOwner($clubId, $uid)) {
			$id_user = ($ar_params[3] ? (int)$ar_params[3] : (int)$_REQUST["id"]);
			if ($clubManagement->addModerator($clubId, $id_user)) {
				die(forward("my-club-members,".$clubId.",mod_added.htm"));
			} else {
				$err[] = "RIGHTS_KICK";
			}
		}
		break;
	case 'mod_rem':
		if ($clubId > 0 && $clubManagement->isClubOwner($clubId, $uid)) {
			$id_user = ($ar_params[3] ? (int)$ar_params[3] : (int)$_REQUST["id"]);
			if ($clubManagement->remModerator($clubId, $id_user)) {
				die(forward("my-club-members,".$clubId.",mod_removed.htm"));
			} else {
				$err[] = "RIGHTS_KICK";
			}
		}
		break;
	case 'lead':
		if ($clubId > 0 && $clubManagement->isClubOwner($clubId, $uid)) {
			$id_user = ($ar_params[3] ? (int)$ar_params[3] : (int)$_REQUST["id"]);
			if ($clubManagement->setClubOwner($clubId, $id_user)) {
				die(forward("my-club-members,".$clubId.",promoted.htm"));
			} else {
				$err[] = "RIGHTS_KICK";
			}
		}
		break;
	case 'uninvite':
		if ($clubId > 0 && ($clubManagement->isClubOwner($clubId, $uid) || $clubManagement->isClubModerator($clubId, $uid))) {
			$id_invite = ($ar_params[3] ? (int)$ar_params[3] : (int)$_REQUEST["id"]);
			if ($id_invite > 0) {
				if ($clubManagement->cancelInvite($clubId,  $id_invite)) {
					die(forward("my-club-members,".$clubId.",uninvited.htm"));
				}
			} else {
				$err[] = "RIGHTS_KICK";
			}
		}
		break;
	case 'add_known':
		if ($clubId > 0 && ($clubManagement->isClubOwner($clubId, $uid) || $clubManagement->isClubModerator($clubId, $uid))) {
			$ar_result = array("success" => false, "errors" => "Unknown error!");
			if (empty($_REQUEST["USERNAME"])) {
				$err[] = "INVITE_USERNAME";
			}
			if (empty($err)) {
				// Eingaben korrekt
				if ($clubManagement->inviteMemberByUserName($clubId, $_REQUEST["USERNAME"], $_REQUEST["MESSAGE"])) {
					$ar_result["success"] = true;
				} else {
					$err[] = "INVITE_USER_UNKNOWN";
				}
			}
			if (!empty($err)) {
				// Fehler
				$ar_result["errors"] = "<li>".implode("</li>\n<li>", get_messages("CLUB", implode(",", $err)))."</li>";
			}
			header("Content-Type: application/json");
			die(json_encode($ar_result));
		}
		break;
	case 'add_new':
		if ($clubId > 0 && ($clubManagement->isClubOwner($clubId, $uid) || $clubManagement->isClubModerator($clubId, $uid))) {
			$ar_result = array("success" => false, "errors" => "Unknown error!");
			if (empty($_REQUEST["NAME"])) {
				$err[] = "INVITE_REALNAME";
			}
			if (empty($_REQUEST["EMAIL"])) {
				$err[] = "INVITE_EMAIL";
			}
			if (empty($err)) {
				// Eingaben korrekt
				if ($clubManagement->inviteMemberByMail($clubId, $_REQUEST["NAME"], $_REQUEST["EMAIL"], $_REQUEST["MESSAGE"])) {
					$ar_result["success"] = true;
				}
			}
			if (!empty($err)) {
				// Fehler
				$ar_result["errors"] = "<li>".implode("</li>\n<li>", get_messages("CLUB", implode(",", $err)))."</li>";
			}
			header("Content-Type: application/json");
			die(json_encode($ar_result));
		}
		break;
	case 'request_accept':
		$clubMemberRequestId = ($ar_params[3] ? (int)$ar_params[3] : (int)$_REQUEST["ID_CLUB_MEMBER_REQUEST"]);
		$clubMemberRequest = $clubMemberRequestManagement->fetchById($clubMemberRequestId);

		if($clubMemberRequest != NULL && $clubMemberRequest['FK_CLUB'] == $clubId && ($clubManagement->isClubOwner($clubId, $uid) || $clubManagement->isClubModerator($clubId, $uid))) {
			$clubMemberRequestManagement->acceptMemberRequest($clubMemberRequestId);
		}

		die(forward("my-club-members,".$clubId.",request_accepted.htm"));
		break;
	case 'request_decline':
		$clubMemberRequestId = ($ar_params[3] ? (int)$ar_params[3] : (int)$_REQUEST["ID_CLUB_MEMBER_REQUEST"]);
		$clubMemberRequest = $clubMemberRequestManagement->fetchById($clubMemberRequestId);

		if($clubMemberRequest != NULL && $clubMemberRequest['FK_CLUB'] == $clubId && ($clubManagement->isClubOwner($clubId, $uid) || $clubManagement->isClubModerator($clubId, $uid))) {
			$clubMemberRequestManagement->declineMemberRequest($clubMemberRequestId);
		}

		die(forward("my-club-members,".$clubId.",request_declined.htm"));
		break;
	case 'request_block':
		$clubMemberRequestId = ($ar_params[3] ? (int)$ar_params[3] : (int)$_REQUEST["ID_CLUB_MEMBER_REQUEST"]);
		$clubMemberRequest = $clubMemberRequestManagement->fetchById($clubMemberRequestId);

		if($clubMemberRequest != NULL && $clubMemberRequest['FK_CLUB'] == $clubId && ($clubManagement->isClubOwner($clubId, $uid) || $clubManagement->isClubModerator($clubId, $uid))) {
			$clubMemberRequestManagement->blockMemberRequest($clubMemberRequestId);
		}

		die(forward("my-club-members,".$clubId.",request_blocked.htm"));
		break;
}



$tpl_content->addvars($ar_club, "CLUB_");
$tpl_content->addlist("liste", $ar_members, "tpl/".$s_lang."/my-club-members.row.htm");
$tpl_content->addvar("pager", htm_browse_extended($all, $npage, "my-club-members,".$clubId.",".$clubAction.",{PAGE}", $perpage));

?>