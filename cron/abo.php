<?php
/* ###VERSIONSBLOCKINLCUDE### */

global $db;
$GLOBALS['langval'] = 128;
require_once $ab_path."sys/packet_management.php";
require_once $ab_path."sys/lib.billing.invoice.php";

$packets = PacketManagement::getInstance($db);
$billingInvoiceManagement = BillingInvoiceManagement::getInstance($db);

#####################################
##   Ausgelaufene verlängern       ##
#####################################
$ar_packets_renew = $db->fetch_table("SELECT ID_PACKET_ORDER, FK_USER, FK_PACKET_RUNTIME, PRICE FROM `packet_order`
	WHERE (STAMP_NEXT <= NOW()) AND (STAMP_END <= NOW()) AND (FK_COLLECTION IS NULL)");
foreach ($ar_packets_renew as $index => $ar_packet) {
	if ($ar_packet["FK_PACKET_RUNTIME"] > 0) {
		$arPacketsNew = $packets->order($ar_packet["FK_PACKET_RUNTIME"], $ar_packet["FK_USER"], 1, NULL, NULL, $ar_packet["PRICE"]);
		$db->querynow("UPDATE `packet_order` SET STAMP_NEXT=NULL WHERE ID_PACKET_ORDER=".$ar_packet["ID_PACKET_ORDER"]);
		if ((count($arPacketsNew) > 0) && ($arPacketsNew[0] > 0)) {
			$id_packet_order_new = (int)$arPacketsNew[0];
			// Trigger api event for new invoice
			Api_TraderApiHandler::getInstance($GLOBALS['db'])->triggerEvent(
				Api_TraderApiEvents::PACKET_RENEW, array(
					"FK_USER" => $ar_packet['FK_USER'],
					"FK_PACKET_ORDER_OLD" => $ar_packet['ID_PACKET_ORDER'], "FK_PACKET_ORDER_NEW" => $id_packet_order_new,
				)
			);
			// Assign user ads to new packet
			
			$ar_failed = array();
			$order = $packets->order_get($ar_packet['ID_PACKET_ORDER']);
			if ($order->moveContent($id_packet_order_new, $ar_failed)) {
				//eventlog("info", "Moved packet content from ".$ar_packet['ID_PACKET_ORDER']." to ".$id_packet_order_new, var_export($ar_failed, true));
			} else {
				eventlog("error", "Failed to move packet content from ".$ar_packet['ID_PACKET_ORDER']." to ".$id_packet_order_new, var_export($ar_failed, true));
			}
		}
	}
}
if (!empty($ar_packets_renew)) {
  // Sicherstellen das zunächst alle ausgelaufenen verlängert wurden bevor weitere Aktionen durchgeführt werden
  return;
}

#####################################
##   Nächste Rechnung stellen      ##
#####################################
$ar_packets_recalc = $db->fetch_table("SELECT ID_PACKET_ORDER, FK_USER, FK_PACKET_RUNTIME, PRICE, BILLING_CYCLE, BILLING_FACTOR FROM `packet_order`
	WHERE (STAMP_NEXT <= NOW()) AND (STAMP_END > NOW()) AND (FK_COLLECTION IS NULL) AND (FK_PACKET_RUNTIME IS NOT NULL)");
foreach ($ar_packets_recalc as $index => $ar_packet) {
	if ($ar_packet["FK_PACKET_RUNTIME"] > 0) {
		if($billingInvoiceManagement->shouldChargeAtOnceByUserId($ar_packet["FK_USER"])) {
			$id_invoice = $packets->new_invoice($ar_packet["FK_PACKET_RUNTIME"], $ar_packet["FK_USER"], 1, $ar_packet["PRICE"]);
			if ($id_invoice > 0) {
				// Trigger api event for new invoice
				Api_TraderApiHandler::getInstance($GLOBALS['db'])->triggerEvent(
					Api_TraderApiEvents::PACKET_NEW_INVOICE, array(
						"ID_PACKET" => $ar_packet["FK_PACKET"], "ID_PACKET_RUNTIME" => $ar_packet["FK_PACKET_RUNTIME"], 
                        "ID_PACKET_ORDER" => $ar_packet["ID_PACKET_ORDER"], "FK_USER" => $ar_packet["FK_USER"], "COUNT" => $ar_packet["COUNT"], 
						"FK_INVOICE" => $id_invoice, "FK_BILLING_BILLABLEITEM" => null
					)
				);
			}
		} else {
			$id_billableitem = $packets->new_billableitem($ar_packet["FK_PACKET_RUNTIME"], $ar_packet["FK_USER"], 1, $ar_packet["PRICE"]);
			if ($id_billableitem > 0) {
				// Trigger api event for new invoice
				Api_TraderApiHandler::getInstance($GLOBALS['db'])->triggerEvent(
					Api_TraderApiEvents::PACKET_NEW_INVOICE, array(
						"ID_PACKET" => $ar_packet["FK_PACKET"], "ID_PACKET_RUNTIME" => $ar_packet["FK_PACKET_RUNTIME"], 
                        "ID_PACKET_ORDER" => $ar_packet["ID_PACKET_ORDER"], "FK_USER" => $ar_packet["FK_USER"], "COUNT" => $ar_packet["COUNT"], 
						"FK_INVOICE" => null, "FK_BILLING_BILLABLEITEM" => $id_billableitem
					)
				);
			}
		}
		$db->querynow("UPDATE `packet_order`
				SET STAMP_NEXT=DATE_ADD(STAMP_NEXT, interval ".$ar_packet["BILLING_FACTOR"]." ".$ar_packet["BILLING_CYCLE"].") 
				WHERE ID_PACKET_ORDER=".$ar_packet["ID_PACKET_ORDER"]);
	}
}
if (!empty($ar_packets_recalc)) {
  // Sicherstellen das zunächst alle Rechnungen gestellt wurden bevor weitere Aktionen durchgeführt werden
  return;
}

#####################################
##   Ausgelaufene deaktivieren     ##
#####################################
$ar_packets_disable = array_keys($db->fetch_nar("SELECT ID_PACKET_ORDER FROM `packet_order`
	WHERE (STAMP_END <= NOW()) AND (FK_COLLECTION IS NULL) AND (STATUS=1)"));

foreach ($ar_packets_disable as $id_packet_order) {
	$order = $packets->order_get($id_packet_order);
	if ($order != NULL) {
        if ($order->isRecurring()) {
            $order->cancelNow();
        } else {
            $order->cancelNow();
            $order->deactivate();
        }
	}
}


?>