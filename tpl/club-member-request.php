<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path."sys/lib.club.php";
require_once $ab_path."sys/lib.club_member_request.php";
$clubManagement = ClubManagement::getInstance($db);
$clubMemberRequestManagement = ClubMemberRequestManagement::getInstance($db);

$clubId = $_REQUEST['ID_CLUB']?(int)$_REQUEST['ID_CLUB']:null;
$error = FALSE;

/** Club **/
$club = $clubManagement->getClubById($clubId);
if (!empty($club) && (($club['STATUS'] == 1)) && $uid) {
	if($club['ALLOW_MEMBER_REQUESTS'] == ClubManagement::ALLOW_MEMBER_REQUESTS_NOT_ALLOWED) {
		$tpl_content->addvar("err_member_request_not_allowed", 1);
		$tpl_content->addvar("err_failure", 1);
		$error = TRUE;

	}

	if($clubManagement->isMember($clubId, $uid) || $clubManagement->isClubOwner($clubId, $uid)) {
		$tpl_content->addvar("err_member_already", 1);
		$tpl_content->addvar("err_failure", 1);
		$error = TRUE;
	}

	if($clubMemberRequestManagement->existMembershipRequest($clubId, $uid) || $clubMemberRequestManagement->isMemberRequestBlocked($clubId, $uid)) {
		$tpl_content->addvar("err_membership_request_already", 1);
		$tpl_content->addvar("err_failure", 1);
		$error = TRUE;
	}


	if($club['ALLOW_MEMBER_REQUESTS'] == ClubManagement::ALLOW_MEMBER_REQUESTS_ALLOWED && !$error) {
		$clubManagement->addMember($clubId, $uid, TRUE);

		// Mail Senden
		$mailData = array(
			'FK_USER' => $uid,
			'FK_CLUB' => $clubId,
			'CLUB_MEMBER_REQUEST_USER_ID' => $userId
		);

		// Admin
		$club = $clubManagement->getClubById($clubId);
        $clubMemberRequestManagement->sendMailToUser($club['FK_USER'], 'CLUB_MEMBER_REQUEST_ADMIN_NOTICE', $mailData);

		// Mods
		$mods = $clubManagement->getMembersByClubId($clubId, FALSE, 1, 100, array(
			'cu.MODERATOR = 1'
		));
		foreach($mods as $key => $mod) {
            $clubMemberRequestManagement->sendMailToUser($mod['FK_USER'], 'CLUB_MEMBER_REQUEST_ADMIN_NOTICE', $mailData);
		}

		$tpl_content->addvar("MEMBERSHIP_SUCCESS", 1);
	}

	if($club['ALLOW_MEMBER_REQUESTS'] == ClubManagement::ALLOW_MEMBER_REQUESTS_CONFIRMATION && !$error) {
		$tpl_content->addvar('REQUEST_MEMBERSHIP', 1);
		$tpl_content->addvar("show_send_button", 1);


		if(isset($_POST) && $_POST['do'] == 'request_membership')  {
			if(isset($_POST['REASON']) && strlen($_POST['REASON']) >= 25) {
				$clubMemberRequestManagement->addMembershipRequestForUser($clubId, $uid, $_POST['REASON']);

				$tpl_content->addvar('REQUEST_MEMBERSHIP_SUCCESS', 1);
				$tpl_content->addvar('REQUEST_MEMBERSHIP', 0);
				$tpl_content->addvar("show_send_button", 0);
			} else {
				$tpl_content->addvar("err", 1);
				$tpl_content->addvar("err_reason", 1);
			}

			$tpl_content->addvars($_POST);
		}


	}

	// Logo
	$club['LOGO'] = ($club['LOGO'] != "" ? 'cache/club/logo/'.$club['LOGO'] : null);

	// Templatevariablen schreiben
	$tpl_content->addvars($club, "CLUB_");

	$tpl_content->addvar("IS_MEMBER_IN_CLUB", $clubManagement->isMember($clubId, $uid));
} else {
	$tpl_content->addvar("err_not_found", 1);
	$tpl_content->addvar("err_failure", 1);
}

?>
