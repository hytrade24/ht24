<?php

$articleUserId = (int)$tpl_content->vars['OVERRIDE_USER_ID'];

// User Info
$userdata = $db->fetch1("select VORNAME as USER_VORNAME, NACHNAME as USER_NACHNAME, NAME as USER_NAME, FIRMA as USER_FIRMA, CACHE as USER_CACHE, STAMP_REG as USER_STAMP_REG, LASTACTIV as USER_LASTACTIV, URL as USER_URL, STRASSE as USER_STRASSE , PLZ as USER_PLZ, ORT as USER_ORT, ID_USER as USER_ID_USER, UEBER as USER_UEBER, ROUND(RATING) as USER_lastrate,TIMESTAMPDIFF(YEAR,GEBDAT,CURDATE()) as USER_age, TEL as USER_TEL from user where ID_USER=". $articleUserId); // Userdaten lesen
$tpl_content->addvars($userdata);

require_once 'sys/lib.vendor.php';
require_once 'sys/lib.vendor.category.php';

$vendorManagement = VendorManagement::getInstance($db);
$vendorManagement->setLangval($langval);

$isUserVendor = $vendorManagement->isUserVendorByUserId($articleUserId);
$tpl_content->addvar("USER_IS_VENDOR", $isUserVendor);

// Impressum Tab
$userHasImpressum = $db->fetch_atom("SELECT (IMPRESSUM <> '') FROM usercontent WHERE FK_USER = '".(int)$articleUserId."'");
$tpl_content->addvar("USER_HAS_IMPRESSUM", $userHasImpressum);

if($isUserVendor) {
    $tmp = $vendorManagement->fetchByUserId($articleUserId);


    $vendor = $vendorManagement->fetchByVendorId($tmp['ID_VENDOR']);
    $vendorTemplate = array();
    foreach($vendor as $key=>$value) { $vendorTemplate['VENDOR_'.$key] = $value; }

    // Kategorie Liste
    $vendorTemplate['VENDOR_LOGO'] = ($vendorTemplate['VENDOR_LOGO'] != "")?'cache/vendor/logo/'.$vendorTemplate['VENDOR_LOGO']:null;
    $vendorTemplate['USER_ID_USER'] = $vendor['FK_USER'];

    $tpl_content->addvars($vendorTemplate);
}

require_once 'sys/lib.job.php';
$jobManagement = JobManagement::getInstance($db);
$hasJobs = (count($jobManagement->fetchAllJobsByUserId($articleUserId)) > 0);
$tpl_content->addvar("USER_HAS_JOBS", $hasJobs);
$tpl_content->addvar("USER_HAS_NEWS", $db->fetch_atom("SELECT count(*) FROM `news` WHERE FK_AUTOR=".$articleUserId." AND OK=3"));

require_once $ab_path."sys/lib.club.php";
$clubManagement = ClubManagement::getInstance($db);
$userClubIds = $clubManagement->getUserClubIds($articleUserId);
if (count($userClubIds) > 0) {
    $countClubs = $db->fetch_atom("SELECT count(*) FROM `club` WHERE ID_CLUB IN (".implode(", ", $userClubIds).") AND (STATUS&1)=1");
}

$countAds = $db->fetch_atom("SELECT count(*) FROM `ad_master` WHERE FK_USER=".$articleUserId." AND (STATUS&3)=1");

$tpl_content->addvar("USER_HAS_CLUBS", ($countClubs > 0));
$tpl_content->addvar("USER_CLUB_COUNT", $countClubs);
$tpl_content->addvar("USER_ADS_COUNT", $countAds);