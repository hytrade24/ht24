<?php
/* ###VERSIONSBLOCKINLCUDE### */

if (array_key_exists("access", $_REQUEST)) {
    $hashAccess = sha1(file_get_contents($ab_path."inc.server.php"));
    if ($_REQUEST["access"] == $hashAccess) {
        $_SESSION["USER_IS_ADMIN"] = 1;
    }
}
if (!$_SESSION["USER_IS_ADMIN"]) {
    die(forward($tpl_content->tpl_uri_action("404")));
}

$feature = preg_replace("/[^a-z0-9_-]+/i", "", $ar_params[1]);
if (file_exists(__DIR__."/system-admin-".$feature.".php")) {
    $tpl_content->LoadText("tpl/".$s_lang."/system-admin-".$feature.".htm");
    include __DIR__."/system-admin-".$feature.".php";
}