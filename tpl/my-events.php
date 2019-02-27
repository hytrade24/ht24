<?php

require_once $ab_path."sys/lib.club.php";
require_once $ab_path."sys/lib.vendor.php";
$clubManagement = ClubManagement::getInstance($db);
$vendorManagement = VendorManagement::getInstance($db);

$show_allowed = array("created", "signedup");
// User can only create events if he's a vendor or member in a group.
$canCreateEvent = $vendorManagement->isUserVendorByUserId($uid) ||
        ($clubManagement->countClubsWhereUserIsMember($uid) > 0);

$show = (!empty($ar_params[2]) && in_array($ar_params[2], $show_allowed) ? $ar_params[2] : "created");
$tpl_content->addvar("show_".$show, 1);
$tpl_content->addvar("STAMP_TODAY", date("Y-m-d H:i:s"));
$tpl_content->addvar("CAN_CREATE_EVENT", $canCreateEvent);