<?php
/* ###VERSIONSBLOCKINLCUDE### */

#$SILENCE = false;
function DummyCheckPackets($id_ad) {
    return TRUE;
}

function CheckPackets($id_ad) {
	global $uid, $tpl_content, $nar_systemsettings, $order_new, $ar_order;

	// Abo-Pakete können unverändert belassen werden, ansonsten erneut abziehen
	if (!$order_new->isRecurring()) {
		// Alle bestandteile erneut berechnen
		if (count($ar_order["ads_used"]) > 0) {
			$id_type = PacketManagement::getType($ar_order["ads_type"]);
			foreach ($ar_order["ads_used"] as $index => $id_article) {
				$order_new->itemAddContent("ad", $id_article);
			}
		}
		if (count($ar_order["images_used"]) > 0) {
			$id_type = PacketManagement::getType($ar_order["images_type"]);
			foreach ($ar_order["images_used"] as $index => $id_image) {
				$order_new->itemAddContent("image", $id_image);
			}
		}
		if (count($ar_order["downloads_used"]) > 0) {
			$id_type = PacketManagement::getType($ar_order["downloads_type"]);
			foreach ($ar_order["downloads_used"] as $index => $id_upload) {
				$order_new->itemAddContent("download", $id_upload);
			}
		}
	}
	return TRUE;
}

global $order, $ar_order;

require_once "sys/lib.shop_kategorien.php";
require_once "sys/lib.ads.php";
require_once $ab_path . "sys/packet_management.php";
$packets = PacketManagement::getInstance($db);
$kat = new TreeCategories("kat", 1);

$id_ad = (!empty($_POST) ? $_POST["ad_id"] : (int)$ar_params[1]);
$id_kat = (!empty($_POST) ? $_POST["ad_kat"] : (int)$ar_params[2]);
$id_packet_order = $db->fetch_atom("SELECT FK_PACKET_ORDER FROM `ad_master` WHERE ID_AD_MASTER=".(int)$id_ad." AND FK_USER=".(int)$uid);
$kat_table = $db->fetch_atom("SELECT KAT_TABLE FROM `kat` WHERE ID_KAT=".mysql_real_escape_string($id_kat));
$ar_kat = $kat->element_read($id_kat);

$ad_data = $db->fetch1("SELECT * FROM ad_master WHERE ID_AD_MASTER = '".(int)$id_ad."'");

$runtime = 0;

$tpl_content->addvar("FREE_ADS", $nar_systemsettings["MARKTPLATZ"]["FREE_ADS"]);
// Anzeigenpakete
if (!$nar_systemsettings["MARKTPLATZ"]["FREE_ADS"]) {
	if($id_packet_order != 0) {
		$order = $packets->order_get($id_packet_order);

		$ar_order = $order->getPacketUsage($id_ad);

		$ar_required = array(
			PacketManagement::getType("ad_once") => count($ar_order["ads_used"]) + count($ar_order["ads_new"]),
			PacketManagement::getType("image_once") => count($ar_order["images_used"]) + count($ar_order["images_new"]),
			PacketManagement::getType("video_once") => count($ar_order["videos_used"]) + count($ar_order["videos_new"]),
			PacketManagement::getType("download_once") => count($ar_order["downloads_used"]) + count($ar_order["downloads_new"])
		);
		$ar_required_abo = array(
			PacketManagement::getType("ad_abo") => count($ar_order["ads_used"]) + count($ar_order["ads_new"]),
			PacketManagement::getType("image_abo") => count($ar_order["images_used"]) + count($ar_order["images_new"]),
			PacketManagement::getType("video_abo") => count($ar_order["videos_used"]) + count($ar_order["videos_new"]),
			PacketManagement::getType("download_abo") => count($ar_order["downloads_used"]) + count($ar_order["downloads_new"])
		);
	} else {
		$ar_required = array(PacketManagement::getType("ad_once") => 1);
		$ar_required_abo = array(PacketManagement::getType("ad_abo") => 1);;
	}


	if ($ar_kat["B_FREE"]) {
		$tpl_content->addvar("allow_free", 1);
	}

	$tpl_content->addvar('AD_MENGE', (int)$ad_data['MENGE']);


    $ar_packets = array_merge($packets->order_find_collections($uid, $ar_required), $packets->order_find_collections($uid, $ar_required_abo));

	$tpl_content->addlist("liste_packets", $ar_packets, "tpl/".$s_lang."/my-marktplatz-extend.row_packet.htm");
}

$err = FALSE;
if (!empty($_POST)) {
	$tpl_content->addvars($_POST);
    $runtime = $db->fetch_atom("SELECT VALUE FROM lookup WHERE ID_LOOKUP=".mysql_real_escape_string($_POST["LU_LAUFZEIT"])." ORDER BY ABS(VALUE) ASC");
}
if ($_POST["FK_PACKET_ORDER"] > 0) {
	if($ad_data['MENGE'] <= 0 && ((int)$_POST['MENGE'] <= 0)) {
		$tpl_content->addvar('err', 1);
		$tpl_content->addvar('err_menge', 1);
		$err = TRUE;
	}

	if(!$err) {
		if($ad_data['MENGE'] <= 0 && ((int)$_POST['MENGE'] > 0)) {
			$db->update("ad_master", array('ID_AD_MASTER' => $id_ad, 'MENGE' => (int)$_POST['MENGE'], 'FK_PACKET_ORDER' => (int)$_POST["FK_PACKET_ORDER"]));
			$db->update($kat_table, array('ID_'.strtoupper($kat_table) => $id_ad, 'MENGE' => (int)$_POST['MENGE'], 'FK_PACKET_ORDER' => (int)$_POST["FK_PACKET_ORDER"]));
		} else {
			$db->update("ad_master", array('ID_AD_MASTER' => $id_ad, 'FK_PACKET_ORDER' => (int)$_POST["FK_PACKET_ORDER"]));
			//$db->update($kat_table, array('ID_'.strtoupper($kat_table) => $id_ad, 'FK_PACKET_ORDER' => (int)$_POST["FK_PACKET_ORDER"]));
		}

		if($nar_systemsettings["MARKTPLATZ"]["FREE_ADS"]) {
			if (AdManagment::ExtendRuntime($id_ad, $kat_table, $runtime, "DummyCheckPackets")) {
				$tpl_content->addvar('success', 1);
			}
		} else {
			$order_new = $packets->order_get($_POST["FK_PACKET_ORDER"]);
			$ar_order_new = $order_new->getPacketUsage($id_ad);
			include_once "sys/lib.ads.php";
			if (AdManagment::ExtendRuntime($id_ad, $kat_table, $runtime, "CheckPackets")) {
				$tpl_content->addvar('success', 1);
			} else {
                $tpl_content->addvar('err', 1);
                $tpl_content->addvar('err_unknown', 1);
            }
		}
	}
}

$tpl_content->addvar("ad_id", $id_ad);
$tpl_content->addvar("ad_kat", $id_kat);

$tpl_content->addvar("error_extend", 1);

?>