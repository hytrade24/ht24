<?php
/* ###VERSIONSBLOCKINLCUDE### */


require_once 'sys/lib.vendor.php';
require_once 'sys/lib.vendor.category.php';
require_once $ab_path."sys/packet_management.php";
require_once 'sys/lib.comment.php';

$userId = $uid;
$vendorManagement = VendorManagement::getInstance($db);
$vendorCategoryManagement = VendorCategoryManagement::getInstance($db);

$vendor = $vendorManagement->fetchByUserId($userId);
$vendorId = $vendor['ID_VENDOR'];

if (!empty($ar_params[1])) {
    $tpl_content->addvar("NOTICE_".strtoupper($ar_params[1]), 1);
}

$packets = PacketManagement::getInstance($db);

// Template Ausgabe
$tpl_content->addvars($vendor, "VENDOR_");
$tpl_content->addvars($user, "USER_");
$tpl_content->addvar("ID_PACKET", $packets->getType("vendor_top_abo"));
// TODO: Template funktion select/tpl_select anpassen!
$tpl_content->addvar("FK_COUNTRY", (isset($_POST["FK_COUNTRY"]) ? $_POST["FK_COUNTRY"] : $vendorTemplate["VENDOR_FK_COUNTRY"]));

$ar_orders = $db->fetch_table("
	SELECT
		ID_PACKET_ORDER, STATUS
	FROM `packet_order`
	WHERE
		FK_USER=".$uid." AND (STAMP_END IS NULL)
		AND STATUS=0 AND FK_PACKET=".$packets->getType("vendor_top_abo"));
if (!empty($ar_orders) || $user["TOP_USER"]) {
	$tpl_content->addvar("PENDING", 1);
}

$commentManagement = CommentManagement::getInstance($db, 'vendor');
$rating_data = $commentManagement->fetchVendorCommentsAvgrating($user["ID_USER"]);
$rating_avg = $rating_data["RATING_AVG"];

if ( intval($rating_data["COUNT"]) != 0 ) {
	$tpl_content->addvar("rating_avg", $rating_avg);

	$lookupManagement = Api_LookupManagement::getInstance($db);
//readByValue
	$report = $GLOBALS['nar_systemsettings']['SITE']['SITENAME'].' ';
	$report .= $lookupManagement->readByValue("WIDGET","reports")["V1"];

	$rating_str = $rating_data["COUNT"] . " ";
	$rating_str .= $lookupManagement->readByValue("WIDGET","ratings")["V1"];

	$firmdata = array(
		"NAME" => $vendor["NAME"],
		"REPORT" => $report,
		"rating" => $rating_avg,
		"total_ratings" => $rating_str
	);
	if ( $s_lang == "de" ) {
		$firmdata["formatted_rating"] = number_format($rating_avg, 2, ',', '.') . " von " . "5,00";
	}
	else if ( $s_lang == "en" ) {
		$firmdata["formatted_rating"] = number_format($rating_avg, 2, '.', ',') . " from " . "5.00";
	}

	$store_file = "cache/users/".$user["CACHE"]."/".$user["ID_USER"]."/widget_".$s_lang.".png";

	$commentManagement->generateRatingWidgetPNG(
		$firmdata,
		$store_file,
		true
	);
	$tpl_content->addvar("WIDGET_IMAGE",$store_file);
}
?>
