<?php
/* ###VERSIONSBLOCKINLCUDE### */

if (!empty($ar_params[1])) {
    if (isset($_COOKIE["register_sale_code"])) {
        // Unset prev. cookie
        setcookie("register_sale_code", "", time() - 60, "/");
    }
    setcookie("register_sale_code", $ar_params[1], null, "/");
}

die(forward($tpl_content->tpl_uri_action("index")));