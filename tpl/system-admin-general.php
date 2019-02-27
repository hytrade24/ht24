<?php
/* ###VERSIONSBLOCKINLCUDE### */

if (!$_SESSION["USER_IS_ADMIN"]) {
    die(forward($tpl_content->tpl_uri_action("404")));
}

switch ($ar_params[2]) {
    case 'hide':
        // Disable admin menu for this login
        $_SESSION["USER_IS_ADMIN"] = 0;
        header("Content-Type: application/json");
        die(json_encode( true ));
    default:
        break;
}