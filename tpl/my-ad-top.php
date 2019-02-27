<?php

require_once $ab_path."sys/packet_management.php";

$id_packet = PacketManagement::getType("ad_top_abo");
$id_ad_master = (!empty($_POST["FK_TARGET"]) ? (int)$_POST["FK_TARGET"] : (int)$ar_params[1]);
$lu_laufzeit_t = (!empty($_POST["LU_LAUFZEIT_T"]) ? (int)$_POST["LU_LAUFZEIT_T"] : (int)$ar_params[2]);
$bf_options = (int)$ar_params[3];
if (!empty($_POST["BF_OPTIONS"])) {
	$bf_options = 0;
	foreach ($_POST["BF_OPTIONS"] as $index => $value) {
		$bf_options += $value;
	}
}

if ($nar_systemsettings['MARKTPLATZ']['EXTENDED_TOP_ADS']) {
	$packets = PacketManagement::getInstance($db);
	$ar_top_types = array(
		PacketManagement::getType("ad_top_pin_abo"),
		PacketManagement::getType("ad_top_slider_abo"),
		PacketManagement::getType("ad_top_color_abo"),
		PacketManagement::getType("ad_top_custom_abo")
	);
	foreach ($ar_top_types as $index => $id) {
		$id_packet_price = $db->fetch_atom("SELECT ID_PACKET_PRICE FROM `packet_price`
				WHERE FK_PACKET=".$id." AND FK_USERGROUP=".(int)$user["FK_USERGROUP"]);
		$ar_packet = $packets->getSingle($id_packet_price);
		$tax = $db->fetch1("SELECT * FROM `tax` WHERE ID_TAX=".$ar_packet["FK_TAX"]);
		$ar_packet["PRICE_BRUTTO"] = round($ar_packet["PRICE"] * (1 + $tax["TAX_VALUE"] / 100), 2);
        $tpl_content->addvar("V1_".$index, $ar_packet["V1"]);
        $tpl_content->addvar("V2_".$index, $ar_packet["V2"]);
        $tpl_content->addvar("T1_".$index, $ar_packet["T1"]);
		$tpl_content->addvar("PRICE_".$index, $ar_packet["PRICE_BRUTTO"]);
	}
	$tpl_content->addvar("BF_OPTIONS", $bf_options);
}

$tpl_content->addvar("EXTENDED_TOP_ADS", $nar_systemsettings['MARKTPLATZ']['EXTENDED_TOP_ADS']);
$tpl_content->addvar("LU_LAUFZEIT_T", $id_packet);
if ($id_ad_master > 0) {
	$query = "SELECT
			ID_AD_MASTER, PRODUKTNAME,
			DATEDIFF(STAMP_END, CURDATE()) as DAYS_LEFT,
			B_TOP
		FROM `ad_master` WHERE ID_AD_MASTER=".$id_ad_master;
	$ar_ad = $db->fetch1($query);
	$tpl_content->addvars($ar_ad);
	$tpl_content->addvar("ID_PACKET", $id_packet);
	$tpl_content->addvar("FK_TARGET", $ar_ad["ID_AD_MASTER"]);
	if ($lu_laufzeit_t > 0) {
		$runtime_days = (int)$db->fetch_atom("SELECT VALUE FROM `lookup` WHERE ID_LOOKUP=".$lu_laufzeit_t." AND art='LAUFZEIT_T'");
		$tpl_content->addvar("LU_LAUFZEIT_T", $lu_laufzeit_t);
		$tpl_content->addvar("DAYS_RUNTIME", $runtime_days);
	} else {
		$tpl_content->addvar("DAYS_RUNTIME", 0);
	}
} else {
	$tpl_content->addvar("ERROR", 1);
}

// Prüfen ob bereits Top-Features gebucht wurden, die noch nicht bezahlt sind
$ar_orders = $db->fetch_table("
	SELECT
		o.FK_PACKET, i.FK_INVOICE
	FROM `packet_order` o
	JOIN `packet_order_usage` u ON u.ID_PACKET_ORDER=o.ID_PACKET_ORDER AND u.FK=".(int)$id_ad_master."
	LEFT JOIN `packet_order_invoice` i ON i.FK_PACKET_ORDER=o.ID_PACKET_ORDER
	WHERE
		o.FK_USER=".$uid." AND (o.STAMP_END IS NULL)
		AND o.STATUS=0 AND o.FK_PACKET IN (".implode(",", $ar_top_types).")");
if (!empty($ar_orders)) {
    $bfTop = (int)$tpl_content->vars["B_TOP"];
    $ar_invoices = array();
    foreach ($ar_orders as $orderIndex => $arOrder) {
        if ($arOrder["FK_PACKET"] == PacketManagement::getType("ad_top")) {
            $bfTop = 15;
        } else if ($arOrder["FK_PACKET"] == PacketManagement::getType("ad_top_pin_abo")) {
            $bfTop |= 1;
        } else if ($arOrder["FK_PACKET"] == PacketManagement::getType("ad_top_slider_abo")) {
            $bfTop |= 2;
        } else if ($arOrder["FK_PACKET"] == PacketManagement::getType("ad_top_color_abo")) {
            $bfTop |= 4;
        } else if ($arOrder["FK_PACKET"] == PacketManagement::getType("ad_top_custom_abo")) {
            $bfTop |= 8;
        }
        $ar_invoices[ $arOrder["FK_INVOICE"] ] = $arOrder;
    }
    $tpl_content->addvar("B_TOP", $bfTop);
    $tpl_content->addvar("PENDING", 1);
    $tpl_content->addlist("PENDING_INVOICES", array_values($ar_invoices), "tpl/".$s_lang."/my-ad-top.invoice.htm");
}

?>