<?php
/* ###VERSIONSBLOCKINLCUDE### */



global $ab_path;
require_once $ab_path.'sys/lib.chat.php';
require_once $ab_path.'sys/lib.chat.user.php';
require_once $ab_path.'sys/lib.chat.user.virtual.php';
require_once $ab_path.'sys/lib.chat.messages.php';

$chatManagement = ChatManagement::getInstance($db);
$chatUserManagement = ChatUserManagement::getInstance($db);
$chatUserVirtualManagement = ChatUserVirtualManagement::getInstance($db);
$chatMessagesManagement = ChatMessageManagement::getInstance($db);

if(!isset($_GET['ID_CHAT'])) {
    die();
}

if(isset($_GET['DO'])) {
    if($_GET['DO'] == 'delete' && isset($_GET['ID_CHAT_MESSAGE'])) {
        $chatMessagesManagement->deleteById($_GET['ID_CHAT_MESSAGE']);
    }
    if($_GET['DO'] == 'approve' && isset($_GET['ID_CHAT_MESSAGE'])) {
        $chatManagement->approveMessage($_GET['ID_CHAT'], $_GET['ID_CHAT_MESSAGE']);
    }
	if($_GET['DO'] == 'disapprove' && isset($_GET['ID_CHAT_MESSAGE'])) {
		$chatManagement->disApproveMessage(
			$_GET['ID_CHAT'],
			$_GET['ID_CHAT_MESSAGE']
		);
	}
}

$chat = $chatManagement->find($_GET['ID_CHAT']);

$chatUsers = $chatUserManagement->fetchAllChatUserByChatId($chat['ID_CHAT']);

foreach($chatUsers as $uKey=>$chatUser) {
    $chat['USER_'.$uKey.'_TYPE'] = $chatUser['TYPE'];
    if($chatUser['TYPE'] == 0) {
        $tmpUser = $db->fetch1("SELECT * FROM user WHERE ID_USER = '".$chatUser['FK_USER']."'");
        $chat['USER_'.$uKey.'_ID'] = $chatUser['FK_USER'];
        $chat['USER_'.$uKey.'_NAME'] = $tmpUser['NAME'];
        $chat['USER_'.$uKey.'_EMAIL'] = $tmpUser['EMAIL'];
    } else {
        $tmpUser = $chatUserVirtualManagement->find($chatUser['FK_CHAT_USER_VIRTUAL']);
        $chat['USER_'.$uKey.'_ID'] = $chatUser['FK_CHAT_USER_VIRTUAL'];
        $chat['USER_'.$uKey.'_EMAIL'] = $tmpUser['EMAIL'];
    }

}

if($chat['FK_AD'] != null) {
    $tmpAd = $db->fetch1("SELECT * FROM ad_master WHERE ID_AD_MASTER = '".$chat['FK_AD']."'");
    $chat['FK_AD_NAME'] = $tmpAd['PRODUKTNAME'];
}
if($chat['FK_AD_REQUEST'] != null) {
    $tmpAd = $db->fetch1("SELECT * FROM ad_request WHERE ID_AD_REQUEST = '".$chat['FK_AD_REQUEST']."'");
    $chat['FK_AD_REQUEST_NAME'] = $tmpAd['PRODUKTNAME'];
}

$chatMessages = $chatMessagesManagement->fetchAllByParam(array(
    'FK_CHAT' => $_GET['ID_CHAT']
));
$tplChatMessages = array();

foreach($chatMessages as $key => $chatMessage) {
    $chatSender = $chatUserManagement->find($chatMessage['SENDER']);

    $chatMessage['SENDER_TYPE'] = $chatSender['TYPE'];
    if($chatSender['TYPE'] == 0) {
        $tmpUser = $db->fetch1("SELECT * FROM user WHERE ID_USER = '".$chatSender['FK_USER']."'");
        $chatMessage['SENDER_ID'] = $chatSender['FK_USER'];
        $chatMessage['SENDER_NAME'] = $tmpUser['NAME'];
        $chatMessage['SENDER_EMAIL'] = $tmpUser['EMAIL'];
    } else {
        $tmpUser = $chatUserVirtualManagement->find($chatSender['FK_CHAT_USER_VIRTUAL']);
        $chatMessage['SENDER_ID'] = $chatSender['FK_CHAT_USER_VIRTUAL'];
        $chatMessage['SENDER_EMAIL'] = $tmpUser['EMAIL'];
    }

    $chatMessage['SENDER_IS_USER_0'] = ($chat['USER_0_ID'] == $chatMessage['SENDER_ID'])?1:0;
    if(strlen($chatMessage['MESSAGE']) > 200) {
        $chatMessage['MESSAGE_INTRO'] = substr($chatMessage['MESSAGE'], 0, 200);
    }

    $chatMessage['IS_MSG_READ'] = $db->fetch_atom("SELECT count(1) as count
		FROM  chat_user_read_message a
		WHERE a.FK_CHAT_MESSAGE = ".$chatMessage['ID_CHAT_MESSAGE']);

    $tplChatMessages[] = $chatMessage;
}

$tpl_content->addvars($chat);
$tpl_content->addlist('liste', $tplChatMessages, 'tpl/de/chat_messages.row.htm');