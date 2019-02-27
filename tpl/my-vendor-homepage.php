<?php
/* ###VERSIONSBLOCKINLCUDE### */

/**
 * Check if the homepage feature is available to this user
 * @var PacketOrderMembershipRecurring|PacketOrderMembershipOnce|PacketOrderBase $membership_cur
 */
require_once $ab_path."sys/packet_management.php";
$packets = PacketManagement::getInstance($db);
$membership_cur = $packets->getActiveMembershipByUserId($uid);
if ($membership_cur != null) {
    $membershipOptions = $membership_cur->getPacketOptions();
    if (!array_key_exists("vendorHomepage", $membershipOptions) || !$membershipOptions["vendorHomepage"]["AVAILABLE"]) {
        die(forward( $tpl_content->tpl_uri_action("my-vendor") ));
    }
} else {
    die(forward( $tpl_content->tpl_uri_action("my-vendor") ));
}


require_once 'sys/lib.vendor.php';
require_once 'sys/lib.vendor.category.php';

$userId = $uid;
$vendorManagement = VendorManagement::getInstance($db);
$vendorHomepageManagement = Api_VendorHomepageManagement::getInstance($db);

$vendor = $vendorManagement->fetchByUserId($userId);
$vendorId = $vendor['ID_VENDOR'];

$vendorHomepage = $vendorHomepageManagement->fetchOneAsObject(array("FK_USER" => $userId));

if (!empty($ar_params[1])) {
    $tpl_content->addvar("NOTICE", 1);
    $tpl_content->addvar("NOTICE_".strtoupper($ar_params[1]), 1);
}

if(isset($_POST) && ($_POST['DO'] == 'SAVE') && !array_key_exists("is_user_media", $_REQUEST)) {
    $errors = array();
    if ($_POST["DOMAIN_TYPE"] == "SUBDOMAIN") {
        // Add subdomain
        $vendorHomepageExists = $db->fetch_atom("SELECT COUNT(*) FROM `vendor_homepage` WHERE DOMAIN_SUB LIKE '".mysql_real_escape_string($_POST['DOMAIN_SUB'])."'");
        if (($vendorHomepageExists > 0) && !$_POST["ID_VENDOR_HOMEPAGE"]) {
            $errors["INVALID_DUPLICATE"] = 1;
        }
        if (!preg_match("/^[a-z0-9]+[a-z0-9-]*[a-z0-9]+$/i", $_POST["DOMAIN_SUB"])) {
            $errors["INVALID_SUBDOMAIN"] = 1;
        }
    } else if (($_POST["DOMAIN_TYPE"] == "DOMAIN_NEW") || ($_POST["DOMAIN_TYPE"] == "DOMAIN_EXISTING")) {
        // TODO: Vollwertige Domain eintragen/Bestellen
        $errors["INVALID_DOMAIN_TYPE"] = 1;
    } else {
        $errors["INVALID_DOMAIN_TYPE"] = 1;
    }
    if (empty($errors)) {
        $_POST["FK_USER"] = $userId;
        if ($vendorHomepage !== false) {
            // Update
            if ($_POST["DOMAIN_TYPE"] == "SUBDOMAIN") {
                $vendorHomepage->setDomainSub($_POST["DOMAIN_SUB"]);
            } else {
                $vendorHomepage->setDomainFull($_POST["DOMAIN_FULL"]);
            }
            if ($vendorHomepage->getActive() == Api_Entities_VendorHomepage::STATUS_DECLINED) {
                $vendorHomepage->setActive( Api_Entities_VendorHomepage::STATUS_PENDING );
                $vendorHomepage->setStampStart( date("Y-m-d H:i:s") );
            }
            if ($vendorHomepage->updateDatabase()) {
                // Set user css
                $userCssParams = new Api_Entities_EventParamContainer(array(
                    "ID_USER"   => $uid,
                    "USER_CSS"  => $_POST["USER_CSS"],
                ));
                Api_TraderApiHandler::getInstance()->triggerEvent("VENDOR_HOMEPAGE_PLUGIN_SAVE_USER_CSS", $userCssParams);
                // Set user footer
                $userFooterParams = new Api_Entities_EventParamContainer(array(
                    "ID_USER"   => $uid,
                    "USER_FOOTER"  => $_POST["USER_FOOTER"],
                ));
                Api_TraderApiHandler::getInstance()->triggerEvent("VENDOR_HOMEPAGE_PLUGIN_SAVE_USER_FOOTER", $userFooterParams);
                // Upload banners/images
                require_once $ab_path."sys/lib.user_media.php";
               	$userMedia = new UserMediaManagement($db, "vendor_homepage", $uid);
                $userMedia->save($vendorHomepage->getId(), $_POST['META']);
                // Clear user cache
                Api_TraderApiHandler::getInstance()->triggerEvent("VENDOR_HOMEPAGE_PLUGIN_CACHE_USER", $uid);
                die(forward( $tpl_content->tpl_uri_action("my-vendor-homepage,SUCCESS") ));
            }
        } else {
            // Create
            if ($vendorHomepageManagement->createNew($_POST)) {
                die(forward( $tpl_content->tpl_uri_action("my-vendor-homepage,SUCCESS") ));
            }
        }
        $errors["INVALID_DATABASE_ERROR"] = 1;
        // Eintragen!
    }
    $tpl_content->addvar("ERROR", 1);
    $tpl_content->addvars($errors, "ERROR_");
    $tpl_content->addvars($_POST, "HOMEPAGE_");
    /*
    $tpl_content->addvar("DO_SAVE", true);
    $tpl_content->addvar("SAVE_RESULT", $saveResult);
    */
    $vendor = $vendorManagement->fetchByUserId($userId);
}

$vendorTemplate = array();

foreach($vendor as $key => $value) {
    // für Template aufbereiten
    if(!in_array($key, array("ID_VENDOR", "FK_USER"))) {
        $vendorTemplate["VENDOR_".$key] = $value;
    } else {
        $vendorTemplate[$key] = $value;
    }
}
$vendorTemplate['VENDOR_LOGO'] = ($vendorTemplate['VENDOR_LOGO'] != "")?'cache/vendor/logo/'.$vendorTemplate['VENDOR_LOGO']:null;
/** @var Api_Plugins_VendorHomepage_Plugin $pluginVendorHomepage */
$pluginVendorHomepage = Api_TraderApiHandler::getInstance()->getPlugin("VendorHomepage");
if ($pluginVendorHomepage instanceof Api_TraderApiPlugin) {
    $vendorTemplate["USER_CSS"] = $pluginVendorHomepage->readUserCss($uid);
    $vendorTemplate["USER_FOOTER"] = $pluginVendorHomepage->readUserFooter($uid);
}

// zusätzliche Informationen über den User
$user = $db->fetch1("SELECT * FROM user WHERE ID_USER = '".mysql_real_escape_string($userId)."'");

if ($vendorHomepage !== false) {
    if ($vendorHomepage->getActive() == Api_Entities_VendorHomepage::STATUS_DECLINED) {
        // Declined by admin
        $tpl_content->addvar("ERROR", 1);
        $tpl_content->addvar("ERROR_DECLINED", 1);
    }
    $tpl_content->addvar("HOMEPAGE_CONFIGURED", 1);
    $tpl_content->addvars($vendorHomepage->asArray(true), "HOMEPAGE_");
} else {
    $tpl_content->addvar("HOMEPAGE_CONFIGURED", 0);
}
$tpl_content->addvar("MARKETPLACE_HOST", rtrim(str_replace(array("http://www.", "http://"), "", $GLOBALS["nar_systemsettings"]['SITE']['SITEURL']), "/"));

// Template Ausgabe
$tpl_content->addvars($vendorTemplate);
$tpl_content->addvars($user);