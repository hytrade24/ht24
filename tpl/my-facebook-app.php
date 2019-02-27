<?php
/**
 * ebiz-trader
 *
 * @copyright Copyright (c) 2012 ebiz-consult e.K.
 * @version 6.5.3
 */

require_once $ab_path.'sys/lib.facebook.php';

$facebookConfig = array(
    'appId'                 => $nar_systemsettings["NETWORKS"]["FACEBOOK_APP_ID"],
    'secret'                => $nar_systemsettings["NETWORKS"]["FACEBOOK_APP_SECRET"],
    'allowSignedRequest'    => true
);

$notification = $ar_params[1];
if (!empty($notification)) {
    $tpl_content->addvar("NOTIFICATION_".strtoupper($notification), 1);
}

$facebookManagement = FacebookManagement::getInstance($db);
$facebookApi = new Facebook($facebookConfig);
$facebookInfo = $_SESSION["FACEBOOK_SIGNED_REQUEST"];
$facebookPage = $facebookManagement->getUserFacebookSite($uid);

if (!empty($_POST)) {
    if (isset($_POST['FACEBOOK_LINK_ADD'])) {
        // Add link to facebook site
        $facebookPageId = $_POST['FACEBOOK_LINK_ADD'];
        if (($facebookInfo !== null) && ($facebookInfo["page"]["id"] == $facebookPageId) && ($facebookInfo["page"]["admin"])) {
            if ($facebookManagement->addUserFacebookSite($uid, $facebookPageId)) {
                // Success
                unset($_SESSION["FACEBOOK_SIGNED_REQUEST"]);
                die(forward($tpl_content->tpl_uri_action("my-facebook-app,added")));
            }
        }
        // Error
        $tpl_content->addvar("err", 1);
        $tpl_content->addvar("err_facebook_auth", 1);
    }
    if (isset($_POST['FACEBOOK_LINK_REMOVE'])) {
        // Remove link to facebook site
        $facebookPageId = $_POST['FACEBOOK_LINK_REMOVE'];
        if ($facebookPage['FK_PAGE_ID'] == $facebookPageId) {
            if ($facebookManagement->removeUserFacebookSite($uid, $facebookPageId)) {
                // Success
                die(forward($tpl_content->tpl_uri_action("my-facebook-app,removed")));
            }
        }
    }
    if (isset($_POST['SAVE']) && is_array($_POST["SETTINGS"])) {
        /*
         * BACKUP OHNE APP
         *
        $query = "UPDATE `user` SET SER_FB_TAB='".mysql_real_escape_string(serialize($_POST["SETTINGS"]))."' WHERE ID_USER=".$uid;
        $queryRes = $db->querynow($query);
        if ($queryRes["rsrc"]) {
            // Success
            die(forward($tpl_content->tpl_uri_action("my-facebook-app,saved")));
        }
        */
        if ($facebookManagement->configureUserFacebookSite($uid, $_POST["SETTINGS"])) {
            // Success
            die(forward($tpl_content->tpl_uri_action("my-facebook-app,saved")));
        }
    }
}

if ($facebookPage !== false) {
    $facebookPageId = $facebookPage['FK_PAGE_ID'];
    $facebookPageDetails = $facebookApi->api('/'.$facebookPageId);
    $tpl_content->addvar("FACEBOOK_PAGE_EXISTS", 1);
    $tpl_content->addvar("FACEBOOK_PAGE_URL", "https://www.facebook.com/pages/-/".$facebookPageId."?sk=app_".$nar_systemsettings["NETWORKS"]["FACEBOOK_APP_ID"]);
    $tpl_content->addvar("FACEBOOK_PAGE_ID", $facebookPageId);
    $tpl_content->addvar("FACEBOOK_PAGE_NAME", $facebookPageDetails["name"]);
    $tpl_content->addvars($facebookPage, "SETTINGS_");
} else {
    if (($facebookInfo !== null) && $facebookInfo["page"]["admin"]) {
        $facebookPageId = $facebookInfo["page"]["id"];
        $facebookPageDetails = $facebookApi->api('/'.$facebookPageId);
        $tpl_content->addvar("FACEBOOK_PAGE_AVAILABLE", 1);
        $tpl_content->addvar("FACEBOOK_PAGE_URL", "https://www.facebook.com/pages/-/".$facebookPageId."?sk=app_".$nar_systemsettings["NETWORKS"]["FACEBOOK_APP_ID"]);
        $tpl_content->addvar("FACEBOOK_PAGE_ID", $facebookPageId);
        $tpl_content->addvar("FACEBOOK_PAGE_NAME", $facebookPageDetails["name"]);
    }
}

$tpl_content->addvar("FACEBOOK_MAX_ADS_PER_PAGE", FacebookManagement::FACEBOOK_MAX_ADS_PER_PAGE);