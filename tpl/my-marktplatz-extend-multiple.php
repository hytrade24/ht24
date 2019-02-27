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

global $order, $ar_order, $order_new;

require_once "sys/lib.shop_kategorien.php";
require_once "sys/lib.ads.php";
require_once $ab_path . "sys/packet_management.php";
$packets = PacketManagement::getInstance($db);
$kat = new TreeCategories("kat", 1);

$where = array();
// Search by status
switch ($_REQUEST['filter']) {
    case 'active':
    default:
        $where[] = "(am.STATUS&3 = 1 OR (am.STATUS&3 = 0 AND am.CONFIRMED=0)) AND (am.DELETED=0)";
        break;
    case 'timeout':
        $where[] = "am.STATUS&3 = 1 AND (am.DELETED=0) AND (DATEDIFF(STAMP_END, NOW())<14)";
        break;
    case 'declined':
        $where[] = "am.CONFIRMED=2 AND (am.DELETED=0)";
        break;
    case 'disabled':
        $where[] = "am.STATUS&1 = 0 AND (am.DELETED=0) AND (STAMP_END IS NOT NULL OR am.CONFIRMED=0)";
        break;
}
// Search for id
if ($_REQUEST['ID_AD_MASTER'] > 0) {
    $id = (int)$_REQUEST['ID_AD_MASTER'];
    $where[] = "am.ID_AD_MASTER=".mysql_real_escape_string($id);
    $tpl_content->addvar("ID_AD_MASTER", $id);
}
// Search for name (product)
if (!empty($_REQUEST['PRODUKTNAME'])) {
    $name = trim($_REQUEST['PRODUKTNAME']);
    if(!empty($name)) {
        $where[] = "am.PRODUKTNAME LIKE '%".mysql_real_escape_string($name)."%'";
        $tpl_content->addvar("PRODUKTNAME", $name);
    }
}
// Search for manufacturer
if (!empty($_REQUEST["HERSTELLER"])) {
    $manufacturer = trim($_REQUEST['HERSTELLER']);
    if(!empty($manufacturer)) {
        $where[] = "m.NAME LIKE '%".mysql_real_escape_string($manufacturer)."%'";
        $tpl_content->addvar("HERSTELLER", $manufacturer);
    }
}
// Search for category
if ($_REQUEST["FK_KAT"] > 0) {
    $searchKatId = (int)$_REQUEST['FK_KAT'];
    $row_kat = $kat->element_read($searchKatId);
    if($row_kat) {
        $ids_kats = $db->fetch_nar("
				SELECT ID_KAT
				  FROM `kat`
				WHERE
				  (LFT >= ".$row_kat["LFT"].") AND
				  (RGT <= ".$row_kat["RGT"].") AND
				  (ROOT = ".$row_kat["ROOT"].")
				");
        $ids_kats = "(".implode(",",array_keys($ids_kats)).")";
        $where[] = "am.FK_KAT IN ".$ids_kats;
    }
    $tpl_content->addvar("SEARCH_FK_KAT", $searchKatId);
}
$where = (count($where) ? "\nAND ".implode("\nAND ", $where) : '');
$ar_ads = $db->fetch_table($q = "
	SELECT
			SQL_CALC_FOUND_ROWS
    		am.*,
    		am.ID_AD_MASTER AS ID_ARTIKEL,
    		LEFT(am.BESCHREIBUNG, 250) AS DSC,
    		DATEDIFF(am.STAMP_END, NOW()) as TIMELEFT,
    		DATEDIFF(NOW(), am.STAMP_END) as TIMEOUT_DAYS,
    		DATEDIFF(am.STAMP_END, am.STAMP_DEACTIVATE) as TIME_LEFT,
    		s.V1 as KAT,
    		sc.V1 as LAND,
    		i.SRC AS SRC_FULL,
    		i.SRC_THUMB,
        	m.NAME as MANUFACTURER,
        	(SELECT AMOUNT FROM `comment_stats` WHERE `TABLE`='ad_master' AND FK=am.ID_AD_MASTER) as COMMENTS
    	FROM
    		ad_master am
    	LEFT JOIN
			string_kat s on s.S_TABLE='kat'
			and s.FK=am.FK_KAT
			and s.BF_LANG=if(".$langval." & ".$langval.", ".$langval.", 1 << floor(log(".$langval."+0.5)/log(2)))
    	LEFT JOIN
			string sc on sc.S_TABLE='country'
			and sc.FK=am.FK_COUNTRY
			and sc.BF_LANG=if(".$langval." & ".$langval.", ".$langval.", 1 << floor(log(".$langval."+0.5)/log(2)))
		LEFT JOIN
			ad_images i ON am.ID_AD_MASTER = i.FK_AD AND i.IS_DEFAULT=1
		LEFT JOIN `manufacturers` m ON m.ID_MAN=am.FK_MAN
		WHERE
    		am.FK_USER=".$uid." ".$where."
    	GROUP BY
            am.ID_AD_MASTER");

$tpl_content->addvar("COUNT_ADS", count($ar_ads));
$tpl_content->addvar("FREE_ADS", $nar_systemsettings["MARKTPLATZ"]["FREE_ADS"]);
$tpl_content->addvars($_POST);
// Anzeigenpakete
if (!$nar_systemsettings["MARKTPLATZ"]["FREE_ADS"]) {
	if($id_packet_order != 0) {
		$order = $packets->order_get($id_packet_order);

		$ar_order = $order->getPacketUsage($id_ad);

		$ar_required = array(
			PacketManagement::getType("ad_once") => 1
		);
		$ar_required_abo = array(
			PacketManagement::getType("ad_abo") => 1
		);
	} else {
		$ar_required = array(PacketManagement::getType("ad_once") => 1);
		$ar_required_abo = array(PacketManagement::getType("ad_abo") => 1);;
	}
    $ar_packets = array_merge($packets->order_find_collections($uid, $ar_required), $packets->order_find_collections($uid, $ar_required_abo));
	$tpl_content->addlist("liste_packets", $ar_packets, "tpl/".$s_lang."/my-marktplatz-extend.row_packet.htm");
    $tpl_content->addvar("allow_free", 1);
}

$err = FALSE;
$runtime = false;
if (!empty($_POST["LU_LAUFZEIT"])) {
	$tpl_content->addvars($_POST);
    $runtime = $db->fetch_atom("SELECT VALUE FROM lookup WHERE ID_LOOKUP=".mysql_real_escape_string($_POST["LU_LAUFZEIT"])." ORDER BY ABS(VALUE) ASC");
}
if ($_POST["FK_PACKET_ORDER"] > 0) {
    /*
	if($ad_data['MENGE'] <= 0 && ((int)$_POST['MENGE'] <= 0)) {
		$tpl_content->addvar('err', 1);
		$tpl_content->addvar('err_menge', 1);
		$err = TRUE;
	}
    */
	if(!$err) {
        $success = 0;
        $warning = 0;
        $errors = array();
        foreach ($ar_ads as $adIndex => $ad_data) {
            if($ad_data['MENGE'] <= 0) {
                $db->update("ad_master", array('ID_AD_MASTER' => $ad_data['ID_AD_MASTER'], 'MENGE' => 1, 'FK_PACKET_ORDER' => (int)$_POST["FK_PACKET_ORDER"]));
                $db->update($ad_data['AD_TABLE'], array('ID_'.strtoupper($ad_data['AD_TABLE']) => $ad_data['ID_AD_MASTER'], 'MENGE' => 1, 'FK_PACKET_ORDER' => (int)$_POST["FK_PACKET_ORDER"]));
            } else {
                $db->update("ad_master", array('ID_AD_MASTER' => $ad_data['ID_AD_MASTER'], 'FK_PACKET_ORDER' => (int)$_POST["FK_PACKET_ORDER"]));
                $db->update($ad_data['AD_TABLE'], array('ID_'.strtoupper($ad_data['AD_TABLE']) => $ad_data['ID_AD_MASTER'], 'FK_PACKET_ORDER' => (int)$_POST["FK_PACKET_ORDER"]));
            }

            if($nar_systemsettings["MARKTPLATZ"]["FREE_ADS"]) {
                if (AdManagment::ExtendRuntime($ad_data['ID_AD_MASTER'], $ad_data['AD_TABLE'], $runtime, "DummyCheckPackets")) {
                    $tpl_content->addvar('success', 1);
                }
            } else {
                $order_new = $packets->order_get($_POST["FK_PACKET_ORDER"]);
                $allowFree = $db->fetch_atom("SELECT B_FREE FROM `kat` WHERE ID_KAT=".(int)$ad_data['FK_KAT']);
                if ($allowFree || ($order_new->getPaymentAmount() > 0)) {
                    $ar_order_new = $order_new->getPacketUsage($ad_data['ID_AD_MASTER']);
                    include_once "sys/lib.ads.php";
                    if (AdManagment::ExtendRuntime($ad_data['ID_AD_MASTER'], $ad_data['AD_TABLE'], $runtime, "CheckPackets")) {
                        $success++;
                    } else {
                        $warning++;
                    }
                } else {
                    eventlog("warning", "Anzeige aktivieren fehlgeschlagen. [id=".$id_ad."]", "Anzeigen in dieser Kategorie sind nur mit kostenpflichtigem Anzeigenpaket möglich!");
                    $warning++;
                    $errors["kat_paid"] = 1;
                }
            }
        }
        if ($success > 0) {
            $tpl_content->addvar('success', $success);
        } else {
            $tpl_content->addvar('err', 1);
            if (empty($errors)) {
                $tpl_content->addvar('err_unknown', 1);
            } else {
                foreach ($errors as $errorName => $errorOccured) {
                    $tpl_content->addvar('err_'.$errorName, $errorOccured);
                }
            }
        }
        $tpl_content->addvar('warning', $warning);
	}
}

$tpl_content->addvar("error_extend", 1);

?>