<?php
/* ###VERSIONSBLOCKINLCUDE### */


require_once 'sys/lib.user_contact.php';

$userContactId = (( int ) $ar_params [1] ? ( int ) $ar_params [1] : null);
$task = (( string ) $ar_params [2] ? ( string ) $ar_params [2] : null);
$userContactManagement = UserContactManagement::getInstance( $db );

	
if ($task == 'accept' && ($userContactManagement->existsUserContactRequestByIdAndAcceptorUserId($userContactId, $uid))) {
	$userContactManagement->acceptRequest($userContactId);	

	echo json_encode(array("success" => true));
} elseif($task == 'decline' && ($userContactManagement->existsUserContactRequestByIdAndAcceptorUserId($userContactId, $uid))) {
	$userContactManagement->declineRequest($userContactId);
	
	echo json_encode(array("success" => true));
} elseif($task == 'remove' && ($userContactManagement->existsUserContactRequestByIdAndAcceptorUserId($userContactId, $uid) OR $userContactManagement->existsUserContactRequestByIdAndRequestorUserId($userContactId, $uid))) {
	$userContactManagement->removeUserContact($userContactId);
	
	echo json_encode(array("success" => true));
}
