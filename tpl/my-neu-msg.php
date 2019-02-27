<?php
/* ###VERSIONSBLOCKINLCUDE### */


require_once 'sys/lib.chat.php';
require_once 'sys/lib.chat.user.php';

$chatManagement = ChatManagement::getInstance($db);

$err = array();

$errorMessages = array(
	'USER_UNKNOWN' => Translation::readTranslation("marketplace", "message.new.error.username.unknown", null, array("NAME" => "NAMETO"), 'Keinen User mit dem Usernamen {htm(NAME)} gefunden'),
	'USER_MISSING' => Translation::readTranslation("marketplace", "message.new.error.username", null, array(), 'Keinen Usernamen gewählt'),
	'MESSAGE.SELF' => Translation::readTranslation("marketplace", "message.new.error.self", null, array(), 'Sie können keine Nachricht an sich selbst schicken.'),
	'EMPTY.MESSAGE' => Translation::readTranslation("marketplace", "message.new.error.message", null, array(), 'Keine Nachricht eingegeben!')
);

if ($_REQUEST["frame"] == "ajax") {
	$tpl_content->addvar("AJAX", 1);
}

// ist der User bereits erkannt?
if ($_REQUEST['sent']) {
    $tpl_content->addvar("sent", 1);
    return;
}

if (!empty($_REQUEST['do'])) {


	if (!empty($_REQUEST['NAMETO'])) //Name wurde eingegeben
	{
		$data = $db->fetch1("select NAME, ID_USER from user where NAME = '" . sqlString($_POST['NAMETO']) . "'");
		if ($data["ID_USER"] > 0) {
			$tpl_content->addvar("FK_USERID_TO", $data["ID_USER"]);
		} else {
			$err[] = $errorMessages['USER_UNKNOWN'];
		}
	} elseif ((int)$_REQUEST['FK_USERID_TO'] > 0) {
		$data = $db->fetch1("select NAME, ID_USER from user where ID_USER=" . $_REQUEST['FK_USERID_TO']);
	} else {
        $err[] = $errorMessages['USER_MISSING'];
	}

    $body = trim($_POST['BODY']);

	if($data["ID_USER"] == $uid) {
        $err[] = $errorMessages['MESSAGE.SELF'];
    }
    if (empty($body) ) {
        $err[] = $errorMessages['EMPTY.MESSAGE'];
    }

    #### senden wenn kein fehler
    if (empty($err)) {
        try {
			if(isset($_POST['FK_AD_ORDER']) && $_POST['FK_AD_ORDER'] != "") {
				$chatId = $chatManagement->addChatForOrder($_POST['FK_AD_ORDER'], $_POST['SUBJECT']);
			} elseif(isset($_POST['FK_TRANS_ID']) && $_POST['FK_TRANS_ID'] != "") {
                $chatId = $chatManagement->addChatForTransaction($_POST['FK_TRANS_ID'], $_POST['SUBJECT']);
            } else if(isset($_POST['FK_AD']) && $_POST['FK_AD'] != "") {
                $chatId = $chatManagement->addChatForAd($_POST['FK_AD'], $_POST['SUBJECT']);
            } else {
                $chatId = $chatManagement->addChat($_POST['SUBJECT']);
            }
            $chatManagement->addUserToChat($chatId, $data["ID_USER"]);

            $chatManagement->addUserToChat($chatId, $uid);
            $chatManagement->postMessageByUser($chatId, $uid, $body);


            $tpl_content->addvar("SENDED", 1);
        } catch(Exception $e) {
            echo $e->getMessage(); die();
        }

        if ($_REQUEST["frame"] == "ajax") {
            $url = $tpl_content->tpl_uri_action("my-neu-msg")."?frame=ajax&sent=1";
            die(forward($url));
            die(forward($tpl_content->tpl_uri_action("my-neu-msg")."?frame=ajax&sent=1"));
        } else {
            die(forward($tpl_content->tpl_uri_action("my-msg")));
        }
    } // kerin fehler
    else {
        $tpl_content->addvars($_REQUEST);
        $tpl_content->addvar("err", $tpl_content->parseTemplateString(implode("<br />", $err)));
    }
} // _REQUEST DO
else {
    $id_user = ($_REQUEST["id_user"] ? (int)$_REQUEST["id_user"] : (int)$ar_params[1]);
    $subject = ($_REQUEST["subject"] ? $_REQUEST["subject"] : $ar_params[2]);
    $id_ad = ($_REQUEST["id_ad"] ? (int)$_REQUEST["id_ad"] : (int)$ar_params[3]);
	$id_trans = ($_REQUEST["id_trans"] ? (int)$_REQUEST["id_trans"] : (int)$ar_params[4]);
	$id_order = ($_REQUEST["id_order"] ? (int)$_REQUEST["id_order"] : (int)$ar_params[5]);

    if ($_REQUEST["frame"] == "ajax") {
        $tpl_content->addvar("AJAX", 1);
        $subject = $subject;
    }

    if ($id_user) {
        $ar = $db->fetch1("select NAME as NAMETO, ID_USER as FK_USERID_TO from user where ID_USER=" . $id_user);
        if (!empty($ar)) $tpl_content->addvars($ar);
    } // id given
    if ($subject) {
        $tpl_content->addvar("SUBJECT", urldecode($subject));
    }
    if ($id_ad) {
        $tpl_content->addvar("FK_AD", $id_ad);
    }
    if ($id_trans) {
        $tpl_content->addvar("FK_TRANS_ID", $id_trans);
    }
	if ($id_order) {
		$tpl_content->addvar("FK_AD_ORDER", $id_order);
	}

} // kein DO


?>
