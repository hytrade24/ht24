<?php
/* ###VERSIONSBLOCKINLCUDE### */


global $ab_path;
require_once $ab_path.'sys/lib.chat.php';
require_once $ab_path.'sys/lib.chat.user.php';
require_once $ab_path.'sys/lib.chat.user.virtual.php';
require_once $ab_path.'sys/lib.chat.messages.php';
require_once $ab_path.'sys/lib.chat.user.read.message.php';
require_once $ab_path.'sys/lib.user.php';

$chatManagement = ChatManagement::getInstance($db);
$chatUserManagement = ChatUserManagement::getInstance($db);
$chatUserVirtualManagement = ChatUserVirtualManagement::getInstance($db);
$chatMessagesManagement = ChatMessageManagement::getInstance($db);
$chatUserReadMessageManagement = ChatUserReadMessageManagement::getInstance($db);
$userManagement = UserManagement::getInstance($db);

$npage = ((int)$ar_params[2] ? (int)$ar_params[2] : 1);
$modus = ((int)$ar_params[1] ? (int)$ar_params[1] : 1);

$perpage = 15;
$limit = ($perpage*$npage)-$perpage;

$param = array(
	'CHAT_USER_ID' => $uid,
	'READABLE_BY_USERID' => $uid,
	'CHAT_USER_STATUS' => ChatUserManagement::STATUS_ACTIVE,
	'MODUS' => ($modus == 1)?'INBOX':'OUTBOX',
	'LIMIT' => $perpage,
	'OFFSET' => $limit
);

if (isset($_REQUEST['SEARCH_CONTENT']) && $_REQUEST['SEARCH_CONTENT'] != "") {
	$param['SEARCH_CONTENT'] = $_REQUEST['SEARCH_CONTENT'];
}
if (isset($_REQUEST['SEARCH_MODUS']) && $_REQUEST['SEARCH_MODUS'] != "") {
	$modus = (int)$_REQUEST['SEARCH_MODUS'];
	$param['MODUS'] = ($modus == 1)?'INBOX':'OUTBOX';
}
if (isset($_REQUEST['SEARCH_USER']) && $_REQUEST['SEARCH_USER'] != "") {
	$param['SEARCH_USER'] = $_REQUEST['SEARCH_USER'];
}
if (isset($_REQUEST['SEARCH_FK_TYPE']) && $_REQUEST['SEARCH_FK_TYPE'] != "") {
	$param['SEARCH_FK_TYPE'] = $_REQUEST['SEARCH_FK_TYPE'];
	$param['SEARCH_FK_CONTENT'] = $_REQUEST['SEARCH_FK_CONTENT'];
}
if (isset($_REQUEST['SEARCH_UNREAD']) && $_REQUEST['SEARCH_UNREAD'] == 1) {
	$param['SEARCH_UNREAD'] = $_REQUEST['SEARCH_UNREAD'];
}

$chats = $chatManagement->fetchAllByParam($param);
$numberOfChats = $chatManagement->countByParam($param);
$tplChat = array();

foreach($chats as $key => $chat) {
    $chatUsers = $chatUserManagement->fetchAllChatUserByChatId($chat['ID_CHAT']);


    foreach($chatUsers as $uKey=>$chatUser) {
        if($chatUser['FK_USER'] != $uid) {

			$chat['USER_TYPE'] = $chatUser['TYPE'];
            if($chatUser['TYPE'] == 0) {
                $tmpUser = $db->fetch1("SELECT * FROM user WHERE ID_USER = '".$chatUser['FK_USER']."'");
                $chat['USER_ID'] = $chatUser['FK_USER'];
                $chat['USER_NAME'] = $tmpUser['NAME'];
                $chat['USER_VIRTUAL'] = $tmpUser['IS_VIRTUAL'];

				$chatUserFull = $userManagement->fetchFullDatasetById($chat['USER_ID']);
				$chat['USER_LOGO'] = $chatUserFull['LOGO'];
				$chat['USER_VORNAME'] = $chatUserFull['VORNAME'];
				$chat['USER_NACHNAME'] = $chatUserFull['NACHNAME'];
				$chat['USER_FIRMA'] = $chatUserFull['FIRMA'];

            } else {
                $tmpUser = $chatUserVirtualManagement->find($chatUser['FK_CHAT_USER_VIRTUAL']);
                $chat['USER_VIRTUAL_ID'] = $chatUser['FK_CHAT_USER_VIRTUAL'];
                $chat['USER_EMAIL'] = $tmpUser['EMAIL'];
                $chat['USER_NAME'] = $tmpUser['NAME'];
            }

        } else {
			$ownChatUser = $chatUser;
		}
    }

    $lastMessage = $chatMessagesManagement->fetchLastMessageByChatAndUser($chat['ID_CHAT'], $uid, $modus);
    $chat['LASTMESSAGE'] = substr($lastMessage['MESSAGE'], 0, 256);
	$chat['MESSAGE_COUNT'] = $chatMessagesManagement->countByChatId($chat['ID_CHAT']);

	$realLastMessage = $chatMessagesManagement->fetchLastMessageByChatAndUser($chat['ID_CHAT'], $uid);
	if($realLastMessage['SENDER'] == $ownChatUser['ID_CHAT_USER']) {
		$chat['IS_LASTMESSAGE_FROM_ME'] = true;
	}

    $chatManagement->extendRelation($chat);

    // Contact information
    if ($nar_systemsettings["MARKTPLATZ"]["CHAT_SHOW_CONTACT"]) {
        $userPartner = $chatManagement->findPartnerUser($chat['ID_CHAT']);
        require_once 'sys/lib.vendor.php';
        $vendorManagement = VendorManagement::getInstance($db);
        $chat = array_merge($chat, array_flatten($userPartner, true, "_", "CONTACT_"));
        if ($userPartner["ID_USER"] > 0) {
            $chat["CONTACT_IS_VENDOR"] = $vendorManagement->isUserVendorByUserId($userPartner["ID_USER"]);
        }
    }
	
    // has chat unread messages
    $hasChatUnreadMessagesForUser = $chatUserReadMessageManagement->existUnreadMessagesInChatForUser($chat['ID_CHAT'], $uid);
    $chat['MARK_UNREAD'] = $hasChatUnreadMessagesForUser;

    $tplChat[] = $chat;
}


if ($nar_systemsettings["MARKTPLATZ"]["CHAT_SHOW_CONTACT"]) {
    $tpl_content->addvar("SHOW_CONTACT", 1);
}

#echo ht(dump($lastresult));
$tpl_content->addlist('liste', $tplChat, 'tpl/'.$s_lang.'/my-msg.row.htm');

$additionalParams = $_GET;
unset($additionalParams['page']);

$pager = htm_browse_extended($numberOfChats, $npage, "my-msg,".$modus.",{PAGE}", $perpage);
$tpl_content->addvar("pager", htm_browse_extended($numberOfChats, $npage, "my-msg,".$modus.",{PAGE}", $perpage, 5, '?'.http_build_query($additionalParams)));

$tpl_content->addvar("all", $numberOfChats);
$tpl_content->addvar("npage", $npage);

$tpl_content->addvar("modus", $modus);
$tpl_content->addvars($_REQUEST);

//sqlString ($SEQ)
?>