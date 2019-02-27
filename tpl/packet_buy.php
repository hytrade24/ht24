<?php
/* ###VERSIONSBLOCKINLCUDE### */



require_once $ab_path."sys/packet_management.php";
$packets = PacketManagement::getInstance($db);

$userdata = $db->fetch1("SELECT * FROM `user` WHERE ID_USER=".$uid);
$usercontent = $db->fetch1("SELECT * FROM usercontent WHERE FK_USER=".$uid);
$tpl_content->addvars($usercontent);
$action = (!empty($ar_params[1]) ? $ar_params[1] : $_REQUEST["action"]);

$setting_description = "Anzeigenpaket(e) fuer den Marktplatz";

if (empty($userdata["VORNAME"]) || empty($userdata["NACHNAME"]) ||
	empty($userdata["STRASSE"]) || empty($userdata["PLZ"]) || empty($userdata["ORT"])) {
	$tpl_content->addvar("error_noaddress", 1);
	if (empty($userdata["VORNAME"])) $tpl_content->addvar("error_addr_first", 1);
	if (empty($userdata["NACHNAME"])) $tpl_content->addvar("error_addr_last", 1);
	if (empty($userdata["STRASSE"])) $tpl_content->addvar("error_addr_street", 1);
	if (empty($userdata["PLZ"])) $tpl_content->addvar("error_addr_zip", 1);
	if (empty($userdata["ORT"])) $tpl_content->addvar("error_addr_city", 1);
} else {
	if (!empty($_POST)) {
		// Artikel auflisten
		$ar_items = array();
		$_SESSION["current_order"] = $_POST;
		$_SESSION["current_order"]["packets"] = array();
		if ($_POST["FK_PACKET_RUNTIME"] > 0) {
			$id_runtime = (int)$_POST["FK_PACKET_RUNTIME"];
			$_SESSION["current_order"]["packets"][$id_runtime] = 1;
		}
		$price_overall = 0;
    	foreach ($_SESSION["current_order"]["packets"] as $id => $count) {
    		$ar_packet = $packets->getFull($id);
    		if (!empty($ar_packet)) {
    			$ar_items[] = array(
					"TITLE"				=> substr($ar_packet["V1"], 0, 255),
					"DESCRIPTION"		=> substr($ar_packet["T1"], 0, 255),
					"PRODUCT_BRUTTO"	=> $ar_packet["BILLING_PRICE"],
					"PRODUCT_NETTO"		=> round($ar_packet["BILLING_PRICE"] / (100 + $tax_percent) * 100, 2),
					"FK_PRODUCT"		=> $id,
    				"COUNT"				=> $count
				);
				$price_overall += $ar_packet["BILLING_PRICE"] * $count;
    		}
    	}
    	$_SESSION["current_order"]["price_overall"] = $price_overall;

    	// Zahlung einleiten

		### Zahlung per rechnung
		$action = "done";


	}
	switch ($action) {

		case "done":
			$err = false;

			// Coupon Codes

			$couponManagement = Coupon_CouponManagement::getInstance($db);
			$couponUsageManagement = Coupon_CouponUsageManagement::getInstance($db);
			$couponUsage = null;
			try {
				$couponUsage = $couponUsageManagement->fetchActivatedCouponUsageByUserId($_POST['COUPON_CODE_USAGE'], $uid, 'PACKET', array_keys($_SESSION["current_order"]["packets"]));
			} catch(Exception $e) {
				$tpl_content->addvar('error_coupon', $e->getMessage());
				$err = true;
				$couponUsage = null;
			}


			if(!$err) {
				#die("TEST");
				$all_ok = TRUE;
				$packet_summary = array();
				$ar_packets = array();
				foreach ($_SESSION["current_order"]["packets"] as $id => $count) {
					$ar_packets[] = array(
							"id" => $id, "count" => $count, "tax" => $_SESSION["current_order"]["taxvalue"][$id]
					);
					$ar_packet = $packets->getFull($id);
					if (!empty($ar_packet)) {
						$packet_summary[] = $count . "x " . $ar_packet["V1"];
					}
				}
				// Pakete bestellen
				$fk_invoice = NULL;
				$packets->order_batch($_SESSION["current_order"]["packets"], $uid, $fk_invoice, $tmp = null, $couponUsage);
				if ($all_ok) {
					// Mail an den Käufer
					$mail_to = $uid;
					$mail_data = $userdata;
					$mail_data["liste"] = "- " . implode("\n- ", $packet_summary);

					sendMailTemplateToUser(0, $mail_to, 'MAIL_PACKET_BUY', $mail_data, FALSE);
					if ($fk_invoice > 0) {
						// Show latest invoice
						die(forward( $tpl_content->tpl_uri_action("invoice,".$fk_invoice) ));
					} else {
						// Show success page
						die(forward( $tpl_content->tpl_uri_action("packet_buy,ok,0".($fk_invoice > 0 ? "," . $fk_invoice : "")) ));
					}
				}
			}
			break;
		case "ok":
			$tpl_content->addvar("done", 1);
			$tpl_content->addvar("PREPAID", ($ar_params[2] ? 0 : 1));
			$tpl_content->addvar("FK_INVOICE", (int)$ar_params[3]);
			break;
	}
}
?>