<?php
/* ###VERSIONSBLOCKINLCUDE### */



require_once 'sys/lib.chat.php';
require_once 'sys/lib.chat.user.php';
require_once 'sys/lib.chat.user.virtual.php';

$chatManagement = ChatManagement::getInstance($db);
$chatUserManagement = ChatUserManagement::getInstance($db);
$chatUserVirtualManagement = ChatUserVirtualManagement::getInstance($db);



$ar_ad = $db->fetch1("
	SELECT
		ar.ID_AD_REQUEST AS ID_AD_REQUEST,
		ar.FK_KAT AS FK_KAT,
		ar.FK_USER,
		ar.PRODUKTNAME,
		u.`NAME` AS TO_USER
	FROM
		ad_request ar
	LEFT JOIN
		user u ON ar.FK_USER=u.ID_USER
	WHERE
		ID_AD_REQUEST=" . (int)$_REQUEST['ID_AD_REQUEST']);

$tpl_content->addvars($ar_ad);

if (!empty($_POST)) {
    foreach ($_POST as $key => $value) {
        $_POST[$key] = $value;
    }
    $err = array();

    if (empty($_POST['BODY'])) $err[] = 'Keine Nachricht eingegeben!';
    if (!$uid) {
        if (empty($_POST['SENDER'])) {
            $err[] = "Bitte geben Sie Ihren Namen an!";
        }
        if (empty($_POST['SENDER_MAIL'])) {
            $err[] = "Bitte geben Sie Ihre Emailadresse an";
        }
        if (!secure_question($_REQUEST)) {
            $err[] = "Ihre Antwort auf die frage war nicht korrekt!";
        }
    }
    if (empty($err)) {

        try {
            $chatId = $chatManagement->addChatByParam(array(
                'SUBJECT' => $_POST['SUBJECT'],
                'FK_AD_REQUEST' => (int)$_REQUEST['ID_AD_REQUEST']
            ));
            $chatManagement->addUserToChat($chatId, $_POST['FK_USER']);

            if($uid) {
                // echter User
                $chatManagement->addUserToChat($chatId, $uid);
                $chatManagement->postMessageByUser($chatId, $uid, $_POST['BODY']);
            } else {
                // virtuellen User
                $virtualUser = $chatUserVirtualManagement->get($_POST['SENDER_MAIL'], $_POST['SENDER']);
                $chatManagement->addVirtualUserToChat($chatId, $virtualUser['ID_CHAT_USER_VIRTUAL']);
                $chatManagement->postMessageByVirtualUser($chatId, $virtualUser['ID_CHAT_USER_VIRTUAL'], $_POST['BODY']);
            }

            $tpl_content->addvar("SENDED", 1);
        } catch(Exception $e) {
            echo $e->getMessage(); die();
        }



    } // kein fehler
    else {
        $tpl_content->addvars($_REQUEST);
        $tpl_content->addvar("err", implode("<br />", $err));
    }
} else {
    $tpl_content->addvar("SUBJECT", $ar_ad["PRODUKTNAME"]);
}
?>