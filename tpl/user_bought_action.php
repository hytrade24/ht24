<?php
/* ###VERSIONSBLOCKINLCUDE### */

if (!$uid) {
    list($accessUser, $accessHash) = explode("!", $_SESSION['TRADER_USER_ACCESS_HASH']);
    $accessCheck = $db->fetch_atom("SELECT MD5(CONCAT(NAME,SALT,EMAIL)) FROM `user` WHERE ID_USER=".(int)$accessUser);
    if (!$accessUser || ($accessCheck != $accessHash)) {
        die(forward($tpl_content->tpl_uri_action("404")));
    } else {
        $uid = (int)$accessUser;
    }
}

// Load template
$tpl_content->LoadText("tpl/".$s_lang."/my-marktplatz-einkaeufe-action.htm");
$tpl_content->addvar("USER_IS_VIRTUAL", 1);
// Replace url targets
$arUrlReplace = array("my-marktplatz-einkaeufe" => "user_bought", "my-marktplatz-einkaeufe-action" => "user_bought_action", "my-neu-msg" => "user_msg_neu");
foreach ($arUrlReplace as $identFrom => $identTo) {
    $nar_ident2nav[$identFrom] = $nar_ident2nav[$identTo];
}

require_once "my-marktplatz-einkaeufe-action.php";

?>