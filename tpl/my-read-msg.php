<?php
/* ###VERSIONSBLOCKINLCUDE### */


global $ab_path;
require_once $ab_path . 'sys/lib.chat.php';
require_once $ab_path . 'sys/lib.chat.user.php';
require_once $ab_path . 'sys/lib.chat.user.virtual.php';
require_once $ab_path . 'sys/lib.chat.messages.php';
require_once $ab_path . 'sys/lib.chat.user.read.message.php';

$chatManagement = ChatManagement::getInstance($db);
$chatUserManagement = ChatUserManagement::getInstance($db);
$chatUserVirtualManagement = ChatUserVirtualManagement::getInstance($db);
$chatMessagesManagement = ChatMessageManagement::getInstance($db);
$chatUserReadMessageManagement = ChatUserReadMessageManagement::getInstance($db);

$id = (int)$ar_params[1];
if ($id) {
    if(isset($_POST) && $_POST['DO'] == 'REPLY') {
        if($chatUserManagement->existUserForChatId($id, $uid)) {

            if(isset($_POST['BODY']) && trim($_POST['BODY']) != "") {
                $chatManagement->postMessageByUser($id, $uid, $_POST['BODY']);

                die(forward($tpl_content->tpl_uri_action("my-read-msg,".$id)."#last"));
            } else {
                $tpl_content->addvar("err", 'Keine Nachricht eingegeben!');
                $tpl_content->addvar("err_reply", 1);
            }
        } else {
            die();
        }
    } elseif(isset($_REQUEST['DO']) && $_REQUEST['DO'] == 'DELETE') {
		if($chatUserManagement->existUserForChatId($id, $uid)) {
			$chatManagement->deleteForUserById($id, $uid);

            die(forward($tpl_content->tpl_uri_action("my-msg")));
		}
	} elseif(isset($_REQUEST['DO']) && $_REQUEST['DO'] == 'MARK_UNREAD') {
		if($chatUserManagement->existUserForChatId($id, $uid)) {
			$ownChatUser = $chatUserManagement->findUser($id, $uid);

			$chatUserReadMessageManagement->markMessageAsUnReadByChatUserId($id, $ownChatUser['ID_CHAT_USER']);

            die(forward($tpl_content->tpl_uri_action("my-msg")));
		}
	}

    $ownChatUser = $chatUserManagement->findUser($id, $uid);
    $chat = $chatManagement->find($id);
    $chatManagement->extendRelation($chat);

    // mark messages as read
    $chatUserReadMessageManagement->markAllMessagesInChatAsReadByChatUserId($chat['ID_CHAT'], $ownChatUser['ID_CHAT_USER']);

    $chatMessages = $chatMessagesManagement->fetchAllByParam(array(
        'CHAT_USER_ID' => $uid,
        'FK_CHAT' => $id
    ));
    $tplChatMessages = array();

    foreach($chatMessages as $key => $chatMessage) {
        $chatUser = $chatUserManagement->find($chatMessage['SENDER']);

        $chatMessage['SENDER_TYPE'] = $chatUser['TYPE'];
        if($chatUser['TYPE'] == 0) {
            $tmpUser = $db->fetch1("SELECT * FROM user WHERE ID_USER = '".$chatUser['FK_USER']."'");
            $chatMessage['SENDER_ID'] = $chatUser['FK_USER'];
            $chatMessage['SENDER_NAME'] = $tmpUser['NAME'];
            $chatMessage['SENDER_VIRTUAL'] = $tmpUser['IS_VIRTUAL'];
        } else {
            $tmpUser = $chatUserVirtualManagement->find($chatUser['FK_CHAT_USER_VIRTUAL']);
            $chatMessage['SENDER_VIRTUAL_ID'] = $chatUser['FK_CHAT_USER_VIRTUAL'];
            $chatMessage['SENDER_EMAIL'] = $tmpUser['EMAIL'];
            $chatMessage['SENDER_NAME'] = $tmpUser['NAME'];
        }
        $chatMessage['MESSAGE_OWN'] = (array_key_exists('SENDER_ID', $chatMessage) && ($chatMessage['SENDER_ID'] == $uid) ? true : false);
        $chatMessage['SHOWMESSAGE'] = (($chatMessage['APPROVED'] == 1) || ($chatUser['FK_USER'] == $uid))?1:0;

        $tplChatMessages[] = $chatMessage;
    }

    if ($nar_systemsettings["MARKTPLATZ"]["CHAT_SHOW_CONTACT"]) {
        $userPartner = $chatManagement->findPartnerUser($id);
        require_once 'sys/lib.vendor.php';
        $vendorManagement = VendorManagement::getInstance($db);
        $tpl_content->addvar("SHOW_CONTACT", 1);
        $tpl_content->addvars($userPartner, "CONTACT_");
        if ($userPartner["ID_USER"] > 0) {
            $tpl_content->addvar("CONTACT_IS_VENDOR", $vendorManagement->isUserVendorByUserId($userPartner["ID_USER"]));
        }
    }

	$hasChatUnreadMessagesForUser = $chatUserReadMessageManagement->existUnreadMessagesInChatForUser($chat['ID_CHAT'], $uid);
	$tpl_content->addvar('MARK_UNREAD', $hasChatUnreadMessagesForUser);

    $tpl_content->addlist("liste", $tplChatMessages, "tpl/" . $s_lang . "/my-read-msg.row.htm");
    $tpl_content->addvars($chat);

}
else {
    $tpl_content->addvar("err", 1);
    $tpl_content->addvar("err_notfound", 1);
}
?>