<?php
/* ###VERSIONSBLOCKINLCUDE### */


require_once 'sys/lib.user_contact.php';

$task = (( string ) $ar_params [1] ? ( string ) $ar_params [1] : "all");
$npage = ((int)$ar_params[2] ? (int)$ar_params[2] : 1);
$perpage = 8;
$offset = ($npage*$perpage)-$perpage;

$userContactManagement = UserContactManagement::getInstance($db);

if($task == null || $task == 'all') {
	$userContacts = $userContactManagement->fetchUserContactsByUserId($uid, UserContactManagement::STATUS_ACCEPTED, $offset .', ' . $perpage);
	$countUserContacts = $userContactManagement->countUserContactsByUserId($uid, UserContactManagement::STATUS_ACCEPTED);

	$tpl_content->addvar("showContacts", true);
} elseif($task == 'pending') {
	$userContacts = $userContactManagement->fetchUserContactsBySenderUserId($uid, UserContactManagement::STATUS_REQUESTED, $offset .', ' . $perpage);
	$countUserContacts = $userContactManagement->countUserContactsBySenderUserId($uid, UserContactManagement::STATUS_REQUESTED);

	$tpl_content->addvar("showRequestsPending", true);
} elseif($task == 'open') {
	$userContacts = $userContactManagement->fetchUserContactsByAcceptorUserId($uid, UserContactManagement::STATUS_REQUESTED, $offset .', ' . $perpage);
	$countUserContacts = $userContactManagement->countUserContactsByAcceptorUserId($uid, UserContactManagement::STATUS_REQUESTED);

	$tpl_content->addvar("showRequestsReceived", true);
}

$tpl_content->addvar("countContacts", $userContactManagement->countUserContactsByUserId($uid, UserContactManagement::STATUS_ACCEPTED));
$tpl_content->addvar("countRequestsPending", $userContactManagement->countUserContactsBySenderUserId($uid, UserContactManagement::STATUS_REQUESTED));
$tpl_content->addvar("countRequestsReceived", $userContactManagement->countUserContactsByAcceptorUserId($uid, UserContactManagement::STATUS_REQUESTED));

foreach($userContacts as $key=>$userContact) {
	$user = $db->fetch1("SELECT * FROM user WHERE ID_USER = '".mysql_escape_string($userContact['USER_ID'])."'");
	if($user) {
		$userContacts[$key]['USER_CACHE'] = $user['CACHE'];
		$userContacts[$key]['USER_ID_USER'] = $user['ID_USER'];
		$userContacts[$key]['USER_FIRMA'] = $user['FIRMA'];
		$userContacts[$key]['USER_STRASSE'] = $user['STRASSE'];
		$userContacts[$key]['USER_PLZ'] = $user['PLZ'];
		$userContacts[$key]['USER_ORT'] = $user['ORT'];
        $userContacts[$key]['UEBER'] = $user['UEBER'];
        $userContacts[$key]['RATING'] = $user['RATING'];
		$userContacts[$key]['IS_CONTACT'] = $userContact['STATUS'] == (UserContactManagement::STATUS_ACCEPTED);
	}
}
$tpl_content->addlist("liste", $userContacts, "tpl/".$s_lang."/my-contacts.row.htm");

$pager = htm_browse($countUserContacts, $npage, "/my-contacts,".$task.",", $perpage);
$tpl_content->addvar("pager", $pager);
