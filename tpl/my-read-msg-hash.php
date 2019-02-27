<?php
/* ###VERSIONSBLOCKINLCUDE### */


global $ab_path;
require_once $ab_path . 'sys/lib.chat.php';
require_once $ab_path . 'sys/lib.chat.user.php';
require_once $ab_path . 'sys/lib.chat.user.virtual.php';
require_once $ab_path . 'sys/lib.chat.messages.php';

$chatManagement = ChatManagement::getInstance($db);
$chatUserManagement = ChatUserManagement::getInstance($db);
$chatUserVirtualManagement = ChatUserVirtualManagement::getInstance($db);
$chatMessagesManagement = ChatMessageManagement::getInstance($db);

$hash = $ar_params[1];
$chat = $chatManagement->getChatByHash($hash);

if ($hash && $chat != false) {


    if(isset($_POST) && $_POST['DO'] == 'REPLY') {
        $chatUserVirtual = $chatUserManagement->fetchVirtualUserByChatId($chat['ID_CHAT']);

        if($chatUserManagement->existVirtualUserForChatId($chat['ID_CHAT'], $chatUserVirtual['EMAIL'])) {

            if(isset($_POST['BODY']) && trim($_POST['BODY']) != "") {
                $chatManagement->postMessageByVirtualUser($chat['ID_CHAT'], $chatUserVirtual['ID_CHAT_USER_VIRTUAL'], $_POST['BODY']);

                die(forward("/my-pages/my-read-msg-hash,".$hash.".htm#last"));
            } else {
                $tpl_content->addvar("err", 'Keine Nachricht eingegeben!');
                $tpl_content->addvar("err_reply", 1);
            }
        } else {
            die();
        }
    }

    $chatMessages = $chatMessagesManagement->fetchAllByParam(array(
        'HASH' => $hash
    ));
    $tplChatMessages = array();

    foreach($chatMessages as $key => $chatMessage) {
        $chatUser = $chatUserManagement->find($chatMessage['SENDER']);

        $chatMessage['SENDER_TYPE'] = $chatUser['TYPE'];
        if($chatUser['TYPE'] == 0) {
            $tmpUser = $db->fetch1("SELECT * FROM user WHERE ID_USER = '".$chatUser['FK_USER']."'");
            $chatMessage['SENDER_ID'] = $chatUser['FK_USER'];
            $chatMessage['SENDER_NAME'] = $tmpUser['NAME'];
        } else {
            $tmpUser = $chatUserVirtualManagement->find($chatUser['FK_CHAT_USER_VIRTUAL']);
            $chatMessage['SENDER_VIRTUAL_ID'] = $chatUser['FK_CHAT_USER_VIRTUAL'];
            $chatMessage['SENDER_EMAIL'] = $tmpUser['EMAIL'];
            $chatMessage['SENDER_NAME'] = $tmpUser['NAME'];
        }
        $chatMessage['SHOWMESSAGE'] = ($chatMessage['APPROVED'] == 1 || $chatUser['TYPE'] == 1)?1:0;

        $tplChatMessages[] = $chatMessage;
    }

    $tpl_content->addlist("liste", $tplChatMessages, "tpl/" . $s_lang . "/my-read-msg.row.htm");
    $tpl_content->addvar("HASH", $hash);

}
else {
    $tpl_content->addvar("err", 1);
    $tpl_content->addvar("err_notfound", 1);
}
?>