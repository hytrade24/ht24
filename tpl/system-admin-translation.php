<?php
/* ###VERSIONSBLOCKINLCUDE### */

if (!$_SESSION["USER_IS_ADMIN"]) {
    die(forward($tpl_content->tpl_uri_action("404")));
}

switch ($ar_params[2]) {
    case 'enable':
        setcookie('ebizTranslationTool', 1, time() + 3600, '/');
        header("Content-Type: application/json");
        die(json_encode( true ));
    case 'disable':
        unset($_COOKIE['ebizTranslationTool']);
        setcookie('ebizTranslationTool', null, -1, '/');
        header("Content-Type: application/json");
        die(json_encode( true ));
    default:
        break;
}