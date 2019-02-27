<?php
/* ###VERSIONSBLOCKINLCUDE### */

function setContentText(&$row, $i) {
	global $packets, $db, $s_lang, $tpl_content;
	if ($row["TYPE"] == "COLLECTION") {
		$row["PACKETS_TEXT"] = $packets->getCollectionContent($row["ID_PACKET"]);
	}
	$row["TYPE_".$row["TYPE"]] = 1;
	$row["RECURRING_".$row["RECURRING"]] = 1;
	$tax = $db->fetch1("SELECT * FROM `tax` WHERE ID_TAX=".$row["FK_TAX"]);
	// Steuern in %
	$row["TAX_PERCENT"] = $tax["TAX_VALUE"];
	// Preis mit Steuer
	$row["PRICE_BRUTTO"] = round($row["BILLING_PRICE"] * (1 + $tax["TAX_VALUE"] / 100), 2);
	// Laufzeiten
	if (is_array($row["RUNTIMES"])) {
		$ar_liste = array();
		foreach ($row["RUNTIMES"] as $index => $ar_row) {
			$ar_row["RUNTIME_NUM"] = $ar_row["BILLING_FACTOR"] * $ar_row["RUNTIME_FACTOR"];
			$ar_row["CYCLE_".$ar_row["BILLING_CYCLE"]] = 1;
	        $tpl_tmp = new Template("tpl/".$s_lang."/packets.row_runtime.htm", $tpl_content->table);
	        $tpl_tmp->addvars($ar_row);
	        $tpl_tmp->addvar('i', $index);
	        $tpl_tmp->addvar('even', 1-($index & 1));
	        $ar_liste[] = $tpl_tmp;
		}
		$row["RUNTIMES"] = $ar_liste;
	} else {
		$row["RUNTIMES"] = false;
	}
}

function ListPackets(&$row, $i) {
	global $db, $price_summary, $price_summary_brutto, $_POST;
	setContentText($row, $i);
	$tax = $db->fetch1("SELECT * FROM `tax` WHERE ID_TAX=".$row["FK_TAX"]);
	$row["COUNT"] = $_POST["packets"][$row["ID_PACKET"]];
	$row["PRICE_ALL"] = $row["BILLING_PRICE"];
	$row["PRICE_ALL_BRUTTO"] = ($row["PRICE_ALL"] * (1 + $tax["TAX_VALUE"] / 100));
	$row["RUNTIME_NUM"] = $row["BILLING_FACTOR"] * $row["RUNTIME_FACTOR"];
	$row["CYCLE_".$row["BILLING_CYCLE"]] = 1;
	$price_summary = $price_summary + $row["PRICE_ALL"];
	$price_summary_brutto = $price_summary_brutto + $row["PRICE_ALL_BRUTTO"];
}

#$SILENCE = false;

require_once $ab_path."sys/packet_management.php";
$packets = PacketManagement::getInstance($db);

$action = $ar_params[1];
$id_packet_order = (int)$ar_params[2];

if ($action == "move") {
	$id_packet_order_target = (int)$ar_params[3];
	$ar_failed = array();
	$order = $packets->order_get($id_packet_order);
	if ($order->moveContent($id_packet_order_target, $ar_failed)) {
		die("success");
	} else {
		$tpl_mail = array();
		if (!empty($ar_failed["ad"])) {
			$ar_names = $db->fetch_nar("SELECT ID_AD_MASTER, PRODUKTNAME FROM `ad_master`
					WHERE ID_AD_MASTER IN (".implode(", ", $ar_failed["ad"]).")");
			if (!empty($ar_names)) {
				foreach ($ar_names as $id => $name) {
					$ar_names[$id] = $name." (#".$id.")";
				}
				$tpl_mail["list_ad"] = " - ".implode("\n - ", $ar_names);
			}
		}
		if (!empty($ar_failed["ad_top"])) {
			$ar_names = $db->fetch_nar("SELECT ID_AD_MASTER, PRODUKTNAME FROM `ad_master`
					WHERE ID_AD_MASTER IN (".implode(", ", $ar_failed["ad_top"]).")");
			if (!empty($ar_names)) {
				foreach ($ar_names as $id => $name) {
					$ar_names[$id] = $name." (#".$id.")";
				}
				$tpl_mail["list_ad_top"] = " - ".implode("\n - ", $ar_names);
			}
		}
		if (!empty($ar_failed["vendor_top"])) {
			$ar_names = $db->fetch_nar("SELECT ID_AD_MASTER, NAME FROM `vendor`
					WHERE ID_VENDOR IN (".implode(", ", $ar_failed["vendor_top"]).")");
			if (!empty($ar_names)) {
				foreach ($ar_names as $id => $name) {
					$ar_names[$id] = $name." (#".$id.")";
				}
				$tpl_mail["list_vendor_top"] = " - ".implode("\n - ", $ar_names);
			}
		}
		if (!empty($ar_failed["job"])) {
			$ar_names = $db->fetch_nar("SELECT j.ID_JOB, sj.V1 FROM `job` j
		            LEFT JOIN `string_job` sj ON
                		sj.FK=j.ID_JOB AND sj.S_TABLE='job' AND
                		sj.BF_LANG=if(j.BF_LANG_JOB & ".$langval.", ".$langval.", 1 << floor(log(j.BF_LANG_JOB+0.5)/log(2)))
					WHERE j.ID_JOB IN (".implode(", ", $ar_failed["job"]).")");
			if (!empty($ar_names)) {
				foreach ($ar_names as $id => $name) {
					$ar_names[$id] = $name." (#".$id.")";
				}
				$tpl_mail["list_job"] = " - ".implode("\n - ", $ar_names);
			}
		}
		if (!empty($ar_failed["news"])) {
			$ar_names = $db->fetch_nar("SELECT n.ID_JOB, sn.V1 FROM `job` j
		            LEFT JOIN `string_c` sn ON
                		sn.FK=j.ID_NEWS AND sn.S_TABLE='news' AND
                		sn.BF_LANG=if(n.BF_LANG_JOB & ".$langval.", ".$langval.", 1 << floor(log(n.BF_LANG_JOB+0.5)/log(2)))
					WHERE n.ID_NEWS IN (".implode(", ", $ar_failed["news"]).")");
			if (!empty($ar_names)) {
				foreach ($ar_names as $id => $name) {
					$ar_names[$id] = $name." (#".$id.")";
				}
				$tpl_mail["list_news"] = " - ".implode("\n - ", $ar_names);
			}
		}
		sendMailTemplateToUser(0, $uid, 'MEMBERSHIP_UPGRADE_WARNING', $tpl_mail);
		die(var_dump($tpl_mail));
	}
}
if ($action == "cancel") {
	$check = $db->fetch_atom("SELECT count(*) FROM `packet_order`
		WHERE ID_PACKET_ORDER=".$id_packet_order." AND STAMP_CANCEL_UNTIL > NOW() AND FK_USER=".$uid);
	$order = $packets->order_get($id_packet_order);
	if (($check > 0) && ($order != null)) {
		if ($order->cancel()) {
			die(forward("/my-pages/my-marktplatz/packets.htm"));
		} else {
			$tpl_content->addvar("error_not_found", 1);
		}
	} else {
		// Nichts ausgewählt
		$tpl_content->addvar("error_not_found", 1);
	}
}

$order_sent = false;

if (!empty($_POST)) {
	global $price_summary, $price_summary_brutto;
	$price_summary = 0;
	$price_summary_brutto = 0;
	$packet_ids = array();
	$_SESSION["current_order"]["packets"] = array();
	if ($_POST["FK_PACKET_RUNTIME"] > 0) {
		$id_runtime = (int)$_POST["FK_PACKET_RUNTIME"];
		$_SESSION["current_order"]["packets"][$id_runtime] = 1;
		$packet_ids[] = $id_runtime;
	}

	// Prüfung ob Profil ausgefüllt
	if (empty($user["VORNAME"]) || empty($user["NACHNAME"]) || empty($user["STRASSE"]) || empty($user["PLZ"]) || empty($user["ORT"])) {
		$tpl_content->addvar("error_noaddress", 1);
		if (empty($user["VORNAME"])) $tpl_content->addvar("error_addr_first", 1);
		if (empty($user["NACHNAME"])) $tpl_content->addvar("error_addr_last", 1);
		if (empty($user["STRASSE"])) $tpl_content->addvar("error_addr_street", 1);
		if (empty($user["PLZ"])) $tpl_content->addvar("error_addr_zip", 1);
		if (empty($user["ORT"])) $tpl_content->addvar("error_addr_city", 1);
	}

	if (empty($packet_ids)) {
		// Nichts ausgewählt
		$tpl_content->addvar("error_no_order", 1);
	} else {
		$ar_packets = array();
		foreach ($packet_ids as $index => $id_packet_runtime) {
    		$ar_packets[] = $packets->getFull($id_packet_runtime);
		}

		$tpl_content->addvar("buy", 1);
		$tpl_content->addlist("liste", $ar_packets, "tpl/".$s_lang."/packets.row_buy.htm", "ListPackets");
		$tpl_content->addvar("PRICE", $price_summary);
		$tpl_content->addvar("PRICE_BRUTTO", $price_summary_brutto);
		$order_sent = true;


		// Gutschein
		if ($nar_systemsettings["MARKTPLATZ"]["COUPON_ENABLED"]) {
			$tpl_content->addvar("OPTION_COUPON_ENABLED", 1);

			$tpl_content->addvar('COUPON_WIDGET_TARGET_TYPE', 'PACKET');
			$tpl_content->addvar('COUPON_WIDGET_TARGETS', array_values($packet_ids));
			$tpl_content->addvar('COUPON_WIDGET_TARGETS_JSON', json_encode($packet_ids));
		}
	}
}

if (!$order_sent) {
    $tpl_content->addvar("PRICE_SHOW_NETTO", $db->fetch_atom("SELECT PRICE_NETTO FROM `usergroup` WHERE ID_USERGROUP=".$user["FK_USERGROUP"]));
	$ar_packets = $packets->getUserList($user["FK_USERGROUP"], 1, 256, array(), array("F_ORDER ASC", "TYPE ASC"));
	$ar_orders = $db->fetch_nar("SELECT ID_PACKET_ORDER, FK_PACKET FROM `packet_order`
		WHERE `TYPE` IN ('COLLECTION', 'MEMBERSHIP') AND FK_COLLECTION IS NULL AND FK_USER=".(int)$uid."
		ORDER BY STATUS DESC, STAMP_START DESC");
	$ar_orders_user = array();
	foreach ($ar_orders as $id_packet_order => $fk_packet) {
		$order = $packets->order_get($id_packet_order);
		$isCollection = ($order->getType() == "COLLECTION") || ($order->getType() == "MEMBERSHIP");
		$ar_orders_user[] = array(
			"ID_PACKET_ORDER"					=> $id_packet_order,
			"NAME" 								=> $order->getPacketName(),
			"ACTIVE"							=> $order->isActive(),
			"TYPE_".$order->getType() 			=> 1,
			"RECURRING"						 	=> $order->isRecurring(),
			"INVOICE_COUNT"						=> $order->getInvoiceCount(false),
			"CONTENTS"							=> ($isCollection ? $order->getCollectionContent($langval, true) : $order->getType()),
			"STAMP_START"						=> $order->getPaymentDateFirst(),
			"STAMP_NEXT"						=> $order->getPaymentDateNext(),
			"STAMP_END"							=> $order->getPaymentDateLast(),
			"STAMP_CANCEL_UNTIL"				=> $order->getPaymentDateCancel(),
			"CANCEL_ENABLED"					=> ($order->isRecurring() ? $order->isCancelable() : false)
		);
	}
	$tpl_content->addlist("liste", $ar_packets, "tpl/".$s_lang."/packets.row.htm", "setContentText");
	$tpl_content->addlist("liste_user", $ar_orders_user, "tpl/".$s_lang."/packets.row_user.htm");
}

?>