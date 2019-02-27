<?php
/* ###VERSIONSBLOCKINLCUDE### */


global $ab_path;
require_once $ab_path.'sys/lib.chat.php';
require_once $ab_path.'sys/lib.chat.user.php';
require_once $ab_path.'sys/lib.chat.user.virtual.php';

$chatManagement = ChatManagement::getInstance($db);
$chatUserManagement = ChatUserManagement::getInstance($db);
$chatUserVirtualManagement = ChatUserVirtualManagement::getInstance($db);

$perpage = 20; // Elemente pro Seite
$limit = ((($_REQUEST['npage'] ? $_REQUEST['npage'] : $_REQUEST['npage']=1)-1)*$perpage);

if(isset($_GET['DO'])) {
    if($_GET['DO'] == 'delete' && isset($_GET['ID_CHAT'])) {
        $chatManagement->deleteById($_GET['ID_CHAT']);
    }
    if($_GET['DO'] == 'autoapprove' && isset($_GET['ID_CHAT'])) {
        $chatManagement->setChatAutoApprove($_GET['ID_CHAT']);
    }
    if($_GET['DO'] == 'unsetautoapprove' && isset($_GET['ID_CHAT'])) {
        $chatManagement->setChatAutoApprove($_GET['ID_CHAT'], false);
    }

    if(isset($_GET['returnto']) && $_GET['returnto'] == "message") {
        die(forward("index.php?page=chat_messages&ID_CHAT=".$_GET['ID_CHAT']));
    } else {
        die(forward("index.php?page=chat&npage=".$npage));
    }
}

$param = array("SEARCH_ANY" => 1);
if(isset($_GET['SUBJECT']) && $_GET['SUBJECT'] !== "") $param['SUBJECT'] = $_GET['SUBJECT'];
if(isset($_GET['CHAT_ID']) && $_GET['CHAT_ID'] !== "") $param['ID_CHAT'] = $_GET['CHAT_ID'];
if(isset($_GET['FK_AUTOR']) && $_GET['FK_AUTOR'] !== "") $param['CHAT_USER_ID'] = $_GET['FK_AUTOR'];
if(isset($_GET['FK_AD']) && $_GET['FK_AD'] !== "") $param['FK_AD'] = $_GET['FK_AD'];
if(isset($_GET['FK_TRANS']) && $_GET['FK_TRANS'] !== "") $param['FK_TRANS'] = $_GET['FK_TRANS'];
if(isset($_GET['FK_AD_REQUEST']) && $_GET['FK_AD_REQUEST'] !== "") $param['FK_AD_REQUEST'] = $_GET['FK_AD_REQUEST'];
if(isset($_GET['CHAT_STATUS']) && $_GET['CHAT_STATUS'] == 1) $param['IS_APPROVED'] = false;
if(isset($_GET['CHAT_STATUS']) && $_GET['CHAT_STATUS'] == 2) $param['IS_APPROVED'] = true;
if(isset($_GET['CHAT_TYPE']) && $_GET['CHAT_TYPE'] !== "") $param['CHAT_TYPE'] = $_GET['CHAT_TYPE'];


$chats = $chatManagement->fetchAllByParam(array_merge($param, array(
    'LIMIT' => $perpage,
    'OFFSET' => $limit
)));
$numberOfChats = $chatManagement->countByParam($param);

$tplChat = array();

foreach($chats as $key => $chat) {
    $chatUsers = $chatUserManagement->fetchAllChatUserByChatId($chat['ID_CHAT']);


    foreach($chatUsers as $uKey=>$chatUser) {
        $chat['USER_'.$uKey.'_TYPE'] = $chatUser['TYPE'];
        if($chatUser['TYPE'] == 0) {
            $tmpUser = $db->fetch1("SELECT * FROM user WHERE ID_USER = '".(int)$chatUser['FK_USER']."'");
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

    if($chat['FK_TRANS'] != null) {

    }

    $tplChat[] = $chat;
}


$tpl_content->addvar("pager", htm_browse($numberOfChats, $_REQUEST['npage'], "index.php?page=".$tpl_content->vars['curpage']."&".http_build_query($param)."&npage=", $perpage));
$tpl_content->addlist('liste', $tplChat, 'tpl/de/chat.row.htm');
$tpl_content->addvar("allchat", $numberOfChats);
$tpl_main->addvar('CHAT_AUTO_APPROVE', $nar_systemsettings['MARKTPLATZ']['CHAT_AUTO_APPROVE']);
$tpl_content->addvars($_GET);
