<?php
/* ###VERSIONSBLOCKINLCUDE### */


require_once 'sys/lib.user_contact.php';

$userId = (( int ) $ar_params [1] ? ( int ) $ar_params [1] : null);
$task = (( string ) $ar_params [2] ? ( string ) $ar_params [2] : null);
$userContactManagement = UserContactManagement::getInstance( $db );
$user = $db->fetch1( "SELECT * from user where ID_USER=" . $userId );

if ($user != null) {
	$tpl_content->addvars( $user );
	
	include_once ($GLOBALS['nar_systemsettings']['SITE']['USER_PATH'].$user['CACHE']."/".$user['ID_USER']."/useroptions.php");
	
	if ($task == 'add') {
		$isAddUserContactAllowed = $useroptions['ALLOW_ADD_USER_CONTACT'];
		
		$message = $_POST ['message'];
		
		try {
			if(!$isAddUserContactAllowed) throw new Exception("");
			
			$userContactManagement->requestUserContact( $uid, $user ['ID_USER'], $message);
			
			// Send message as reminder
			require_once 'sys/lib.chat.php';
			require_once 'sys/lib.chat.user.php';
			// [[ translation : marketplace : contact.add.title :: Kontakt hinzufügen ]]
			$chatTitle = Translation::readTranslation("marketplace", "contact.request", null, array(), "Kontaktanfrage");
			$chatHint = Translation::readTranslation("marketplace", "contact.request.hint", null, array(), "Hinweis: Die ausstehenden Kontaktanfragen finden Sie unter 'Mein Account > Meine Einstellungen > Meine Kontakte'.");
			$chatManagement = ChatManagement::getInstance($db);
			$chatId = $chatManagement->addChat($chatTitle);
			$chatManagement->addUserToChat($chatId, $user["ID_USER"]);
   			$chatManagement->addUserToChat($chatId, $uid);
			$chatManagement->postMessageByUser($chatId, $uid, (!empty($message) ? $message."\n\n" : "").$chatHint);
			
			$tpl_content->addvar( "SUCCESS", true );
		} catch ( Exception $e ) {
			$tpl_content->addvar( "ERROR", true );
			$tpl_content->addvar( "ERROR_FAIL", true );
		}
	
	} else {
	
	}
} else {
	$tpl_content->addvar( "ERROR", true );
	$tpl_content->addvar( "ERROR_USER_NOT_FOUND", true );
}