<?php

if (!empty($ar_params[1])) {
    $tpl_content->addvar("NOTIFICATION_".strtoupper($ar_params[1]), 1);
}

$user_content = $db->fetch1("SELECT * FROM `usercontent` WHERE FK_USER=".(int)$uid);

if (isset($_POST["IMPRESSUM"])) {
    if (!is_array($user_content)) {
        $db->querynow("INSERT INTO `usercontent` (FK_USER, IMPRESSUM) VALUES (".(int)$uid.", '".mysql_real_escape_string($_POST["IMPRESSUM"])."')");
    } else {
        $db->querynow("UPDATE `usercontent` SET IMPRESSUM='".mysql_real_escape_string($_POST["IMPRESSUM"])."' WHERE FK_USER=".(int)$uid);
    }
    die(forward( $tpl_content->tpl_uri_action("my-vendor-imprint,saved") ));
}

if (is_array($user_content)) {
    $tpl_content->addvar("IMPRESSUM", $user_content["IMPRESSUM"]);
}