<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path."sys/packet_management.php";
$packets = PacketManagement::getInstance($db);


$packetOrderId = (int)$_REQUEST["ID_PACKET_ORDER"];
$packetOrder = $packets->order_get($packetOrderId);

if ($packetOrder != null) {
	$packetOrderUser = get_user($packetOrder->getUserId());
	$cycleFactor = $packetOrder->getPaymentCycleFactor();
	$cycleUnit = $packetOrder->getPaymentCycleUnit();

	$tpl_content->addvars($packetOrderUser, "USER_PACKET_");
	$tpl_content->addvars(array(
		"ID_PACKET_ORDER" 		=> $packetOrderId,
		"CYCLE_".$cycleUnit		=> 1,
		"BILLING_FACTOR"		=> $cycleFactor,
		"BILLING_PRICE"			=> $packetOrder->getPaymentAmount(),
		"STAMP_START"			=> $packetOrder->getPaymentDateFirst(),
		"STAMP_NEXT"			=> $packetOrder->getPaymentDateNext(),
		"STAMP_END"				=> $packetOrder->getPaymentDateLast(),
		"STAMP_CANCEL_UNTIL"	=> $packetOrder->getPaymentDateCancel()
	));
	$ar_packets = $packets->getList(1, 512, $all, array("(TYPE='BASE' OR TYPE='BASE_ABO')", "(STATUS&1)=1"), array("TYPE ASC", "V1 ASC"));
	if (!empty($ar_packets)) {
		foreach ($ar_packets as $index => $ar_packet) {
			$id_packet = $ar_packet["ID_PACKET"];
			$ar_content = $packetOrder->getContentByType($id_packet);
			if (!empty($ar_content)) {
				$ar_packets[$index]["COUNT"] = (int)$ar_content["COUNT_MAX"];
				$ar_packets[$index]["COUNT_USED"] = (int)$ar_content["COUNT_USED"];
			} else {
				$ar_packets[$index]["COUNT"] = 0;
				$ar_packets[$index]["COUNT_USED"] = 0;
			}
			$ar_packets[$index]["TYPE_".$ar_packet["TYPE"]] = 1;
		}
		#var_dump($ar_packets);
		$tpl_content->addlist("liste", $ar_packets, "tpl/de/vertrag_edit.row.htm");
	}
	if ($packetOrder->isRecurring()) {
		$tpl_content->addvar("HIDE_PACKETS_ONCE", 1);
	} else {
		$tpl_content->addvar("HIDE_PACKETS_ABO", 1);
	}
}
if (!empty($_POST)) {
	$err = array();
	foreach ($_POST["COUNT"] as $id_packet => $count_max) {
		$ar_content = $packetOrder->getContentByType($id_packet);
		if ($ar_content["COUNT_MAX"] != $count_max) {
			// Anzahl wurde verändert
			$count = $ar_content["COUNT_USED"];
			if (($count > $count_max) & ($count_max != -1)) {
				$ar_packet = $packets->get($id_packet);
				$err[] = "Sie müssen dem Benutzer mindestens ".$count." ".($count > 1 ? $ar_packet["V2"] : $ar_packet["V1"]).
					" zur Verfügung stellen oder ".$ar_packet["V2"]." des Benutzers deaktivieren/löschen.";
			}
		}
	}
	if (isset($_POST["DO_CANCEL"])) {
		if (($_POST["DO_CANCEL"] == 1) && !$packetOrder->cancel()) {
			$err[] = "Unbekannter Fehler beim kündigen des Abos!";
		}
		if (($_POST["DO_CANCEL"] == 2) && !$packetOrder->cancelNow()) {
			$err[] = "Unbekannter Fehler beim kündigen des Abos!";
		}
	}
	if (empty($err)) {
		if ($_POST["PRICE"] != $packetOrder->getPaymentAmount()) {
			$price = (float)str_replace(',', '.', $_POST["PRICE"]);
			$db->querynow("UPDATE `packet_order` SET PRICE=".$price." WHERE ID_PACKET_ORDER=".$packetOrder->getOrderId());
		}
		foreach ($_POST["COUNT"] as $id_packet => $count_max) {
			$packetOrder->setContentCount($id_packet, $count_max);
		}
		die(forward("index.php?page=vertrag_edit&ID_PACKET_ORDER=".$packetOrderId));
	} else {
		$tpl_content->addvars($_POST);
		$tpl_content->addvar("error", " - ".implode("<br />\n - ", $err));
	}
}
//die(var_dump($packetOrder));

?>
