<?php
/* ###VERSIONSBLOCKINLCUDE### */

/**
 * @var PacketOrderMembershipRecurring|PacketOrderMembershipOnce|PacketOrderBase $membership_cur
 */
require_once $ab_path."sys/packet_management.php";
$packets = PacketManagement::getInstance($db);
$membership_cur = $packets->getActiveMembershipByUserId($uid);
if ($membership_cur != null) {
    $membershipOptions = $membership_cur->getPacketOptions();
    if (Api_TraderApiHandler::getInstance()->isPluginLoaded("VendorHomepage") && array_key_exists("vendorHomepage", $membershipOptions) && $membershipOptions["vendorHomepage"]["AVAILABLE"]) {
        $tpl_content->addvar("vendorHomepageAvailable", 1);
    }
}

$vendorId = (int)$tpl_content->vars["ID_VENDOR"];
if ($nar_systemsettings["MARKTPLATZ"]["MODERATE_VENDORS"]) {
    if ($tpl_content->vars["VENDOR_ID_VENDOR"] > 0) {
        $vendorId = (int)$tpl_content->vars["VENDOR_ID_VENDOR"];
    }
    if ($vendorId === 0) {
        $vendorId = $db->fetch_atom("SELECT ID_VENDOR FROM `vendor` WHERE FK_USER=".$uid);
    }
    $arVendor = $db->fetch1("SELECT ID_VENDOR, FK_USER, STATUS, MODERATED, DECLINE_REASON FROM `vendor` ".
        "WHERE ID_VENDOR=".$vendorId);
    $isConfirmed = ($arVendor["MODERATED"] == 1 ? true : false);
    $userAutoConfirm = $db->fetch_atom("SELECT AUTOCONFIRM_VENDORS FROM `user` WHERE ID_USER=".$uid);
    if ($isConfirmed && !$userAutoConfirm) {
        $tpl_content->addvar("WARNING_MODERATION", 1);
    }
    $tpl_content->addvars($arVendor, "VENDOR_");
} else {
    $tpl_content->addvar("VENDOR_MODERATED", 1);
}

require_once $ab_path."sys/lib.pub_kategorien.php";
$categoriesCache = new CategoriesCache();

$sql = 'SELECT FK_KAT FROM vendor_category WHERE FK_VENDOR = '.(int)$vendorId;
$vendor_categories = $db->fetch_col( $sql );
$tpl_content->addvar("VENDOR_FIELDS", $categoriesCache->hasKatFields($vendor_categories));