<?php
/* ###VERSIONSBLOCKINLCUDE### */

function ListPackets(&$row, $i) {
	global $db, $price_summary, $price_summary_brutto, $_POST;
	if ($_POST["ID_PACKET"] == PacketManagement::getType("ad_top_abo")) {
		$row["TARGET"] = $db->fetch_atom("SELECT PRODUKTNAME FROM `ad_master` WHERE ID_AD_MASTER=".(int)$_POST["FK_TARGET"]);
	}
	$tax = $db->fetch1("SELECT * FROM `tax` WHERE ID_TAX=".$row["FK_TAX"]);
	$row["COUNT"] = $_POST["packets"][$row["ID_PACKET"]];
	$row["PRICE_ALL"] = $row["PRICE"] * $row["DAYS"];
	$row["PRICE_ALL_BRUTTO"] = round($row["PRICE_ALL"] * (1 + $tax["TAX_VALUE"] / 100), 2);
	$row["RUNTIME_NUM"] = $row["BILLING_FACTOR"] * $row["RUNTIME_FACTOR"];
	$row["CYCLE_".$row["BILLING_CYCLE"]] = 1;
	$price_summary = $price_summary + $row["PRICE_ALL"];
	$price_summary_brutto = $price_summary_brutto + $row["PRICE_ALL_BRUTTO"];
}

require_once $ab_path."sys/packet_management.php";
$packets = PacketManagement::getInstance($db);

if (!empty($_POST)) {
	$id_usergroup = (int)$user["FK_USERGROUP"];
	$id_target = 0;
	$lu_runtime = "LAUFZEIT";
	$ar_packets = array();
	if (!is_array($_POST["ID_PACKET"])) {
		// Einzelne Bestellung
		$id_packet = (int)$_POST["ID_PACKET"];
		if ($id_packet == PacketManagement::getType("ad_top_abo")) {
			$lu_runtime = "LAUFZEIT_T";
		}
		if ($id_packet == PacketManagement::getType("vendor_top_abo")) {
			$lu_runtime = "LAUFZEIT_V";
		}
		$id_runtime = (int)$_POST["LU_".$lu_runtime];
		$id_target = (int)$_POST["FK_TARGET"];
		$runtime_days = (int)$db->fetch_atom("SELECT VALUE FROM `lookup`
			WHERE ID_LOOKUP=".$id_runtime." AND art='".$lu_runtime."'");
		if (is_array($_POST["BF_OPTIONS"])) {
			$bf_options = 0;
			if ($id_packet == PacketManagement::getType("ad_top_abo")) {
				$bf_top_current = $db->fetch_atom("SELECT B_TOP FROM `ad_master` WHERE ID_AD_MASTER=".$id_target);
				if (in_array(1, $_POST["BF_OPTIONS"]) && (($bf_top_current & 1) == 0)) {
					$ar_packets[PacketManagement::getType("ad_top_pin_abo")] = array(
						'COUNT' 	=> $runtime_days,
						'RUNTIME'	=> $id_runtime,
						'TARGET'	=> $id_target,
					);
					$bf_options += 1;
				}
				if (in_array(2, $_POST["BF_OPTIONS"]) && (($bf_top_current & 2) == 0)) {
					$ar_packets[PacketManagement::getType("ad_top_slider_abo")] = array(
						'COUNT' 	=> $runtime_days,
						"RUNTIME" 	=> $id_runtime,
						'TARGET'	=> $id_target,
					);
					$bf_options += 2;
				}
				if (in_array(4, $_POST["BF_OPTIONS"]) && (($bf_top_current & 4) == 0)) {
					$ar_packets[PacketManagement::getType("ad_top_color_abo")] = array(
						'COUNT' 	=> $runtime_days,
						"RUNTIME" 	=> $id_runtime,
						'TARGET'	=> $id_target,
					);
					$bf_options += 4;
				}
				if (in_array(8, $_POST["BF_OPTIONS"]) && (($bf_top_current & 8) == 0)) {
					$ar_packets[PacketManagement::getType("ad_top_custom_abo")] = array(
						'COUNT' 	=> $runtime_days,
						"RUNTIME" 	=> $id_runtime,
						'TARGET'	=> $id_target
					);
					$bf_options += 8;
				}
			}
			$tpl_content->addvar("BF_OPTIONS", $bf_options);
			if (empty($ar_packets)) {
				die(forward($_SERVER['HTTP_REFERER']));
			}
		} else {
			$ar_packets[$id_packet] = array(
				'COUNT' 	=> $runtime_days,
				'RUNTIME'	=> $id_runtime,
				'TARGET'	=> $id_target
			);
		}
	} else {
		// Mehrere Pakete/Paketbestandteile bestellen
		foreach ($_POST["ID_PACKET"] as $index => $id_packet) {
			$lu_runtime = "LAUFZEIT";
			if (($id_packet == PacketManagement::getType("ad_top_abo"))
				|| ($id_packet == PacketManagement::getType("ad_top_pin_abo")) || ($id_packet == PacketManagement::getType("ad_top_slider_abo"))
				|| ($id_packet == PacketManagement::getType("ad_top_color_abo")) || ($id_packet == PacketManagement::getType("ad_top_custom_abo"))) {
				$lu_runtime = "LAUFZEIT_T";
			}
			if ($id_packet == PacketManagement::getType("vendor_top_abo")) {
				$lu_runtime = "LAUFZEIT_V";
			}
			$id_runtime = (int)$_POST["LU_".$lu_runtime][$index];
			$id_target = (int)$_POST["FK_TARGET"][$index];
			$runtime_days = (int)$db->fetch_atom("SELECT VALUE FROM `lookup` WHERE ID_LOOKUP=".$id_runtime);
			$ar_packets[$id_packet] = array(
				'COUNT' 	=> $runtime_days,
				'RUNTIME'	=> $id_runtime,
				'TARGET'	=> $id_target
			);
		}
	}

	if ($runtime_days > 0) {
		global $price_summary, $price_summary_brutto;
		$price_summary = 0;
		$price_summary_brutto = 0;
		$ar_packet_list = array();
		$id_order = null;
		foreach ($ar_packets as $id_packet => $ar_options) {
			$id_packet_price = $db->fetch_atom("SELECT ID_PACKET_PRICE FROM `packet_price`
				WHERE FK_PACKET=".$id_packet." AND FK_USERGROUP=".$id_usergroup);
			if ($id_packet_price > 0) {
				$runtime_text = $db->fetch_atom("SELECT s.V1 FROM `lookup` l
					LEFT JOIN `string` s ON s.S_TABLE='lookup' AND s.FK=l.ID_LOOKUP AND
						s.BF_LANG=if(l.BF_LANG & ".$langval.", ".$langval.", 1 << floor(log(l.BF_LANG+0.5)/log(2)))
					WHERE l.ID_LOOKUP=".(int)$ar_options["RUNTIME"]);
				// Add packet
				$ar_current = $packets->getSingle($id_packet_price);
				$ar_current["DAYS"] = $runtime_days;
				$ar_current["RUNTIME_TXT"] = $runtime_text;
				$ar_packet_list[] = $ar_current;
				// Set label
				$ar_packets[$id_packet]['PRICE'] = $ar_current;
				$ar_packets[$id_packet]['LABEL'] = $runtime_text." ".$ar_current["V1"].": ID #".$ar_options['TARGET'];
			} else {
				unset($ar_packets[$id_packet]);
			}
		}

		// Gutschein
		if ($nar_systemsettings["MARKTPLATZ"]["COUPON_ENABLED"]) {
			$tpl_content->addvar("OPTION_COUPON_ENABLED", 1);

			$tpl_content->addvar('COUPON_WIDGET_TARGET_TYPE', 'SERVICE');
			$tpl_content->addvar('COUPON_WIDGET_TARGETS', array_keys($ar_packets));
			$tpl_content->addvar('COUPON_WIDGET_TARGETS_JSON', json_encode(array_keys($ar_packets)));
		}


		if (isset($_POST["finish"]) && !empty($ar_packets)) {
			$err = false;

			// Coupon Codes
			$couponManagement = Coupon_CouponManagement::getInstance($db);
			$couponUsageManagement = Coupon_CouponUsageManagement::getInstance($db);
			$couponUsage = null;


			try {
				$couponUsage = $couponUsageManagement->fetchActivatedCouponUsageByUserId($_POST['COUPON_CODE_USAGE'], $uid, 'SERVICE', array_keys($ar_packets));
			} catch(Exception $e) {
				$tpl_content->addvar('error_coupon', $e->getMessage());
				$err = true;
				$couponUsage = null;
			}


			// Rechnung stellen
			require_once $ab_path."sys/lib.billing.invoice.php";
			require_once $ab_path."sys/lib.billing.billableitem.php";
			$billingInvoiceManagement = BillingInvoiceManagement::getInstance($db);
			$billingBillableItemManagement = BillingBillableItemManagement::getInstance($db);
			// Neue Abrechnung erzeugen
			$ar_billing_items = array();
			foreach ($ar_packets as $id_packet => $ar_options) {
				$ar_price = $ar_options['PRICE'];
				if ($ar_price['PRICE'] > 0) {
					$billing_item = array(
							"DESCRIPTION" 	=> (empty($ar_options['LABEL']) ? $ar_price["V1"] : $ar_options['LABEL']),
							"QUANTITY"		=> $ar_options['COUNT'],
							"PRICE"			=> $ar_price['PRICE'],
							"FK_TAX"		=> $ar_price["FK_TAX"],
							"REF_TYPE"		=> BillingInvoiceItemManagement::REF_TYPE_PACKET,
							"REF_FK"		=> NULL
					);

					$ar_billing_items[] = $billing_item;

					if ($couponUsage != NULL) {
						$couponUsageManagement = Coupon_CouponUsageManagement::getInstance($db);
						if ($couponUsageManagement->isCouponsUsageCompatible($couponUsage, 'SERVICE', $id_packet)) {
							$couponBillingItem = $couponUsageManagement->useCouponForTarget($couponUsage, $billing_item);

							if(!empty($couponBillingItem)) {
								$ar_billing_items[] = $couponBillingItem;
								$couponUsageManagement->setUsageStateToUsed($couponUsage['ID_COUPON_CODE_USAGE']);
							}
						}
					}
				}
			}


			if(!$err) {
				$status = 1;
				$fk_invoice = NULL;
				$fk_billableitem = NULL;
				if (!empty($ar_billing_items)) {
					// Create invoice
					$ar_billingdata = array(
							"FK_USER" => $uid, "__items" => $ar_billing_items
					);
					if ($billingInvoiceManagement->shouldChargeAtOnceByUserId($uid)) {
						$fk_invoice = $billingInvoiceManagement->createInvoice($ar_billingdata);
					} else {
						$fk_billableitem = $billingBillableItemManagement->createMultipleBillableItems($ar_billingdata);
					}
					// Check usergroup
					$ar_usergroup = $db->fetch1("
						SELECT g.* FROM `user` u
							LEFT JOIN `usergroup` g ON g.ID_USERGROUP=u.FK_USERGROUP
						WHERE u.ID_USER=" . $uid);
					if ($ar_usergroup != FALSE) {
						$status = ($ar_usergroup["PREPAID"] ? 0 : 1);
					} else {
						$status = 0;
					}
				}
				// Abgeschickt! -> Rechnung stellen und Paket hinzufügen
				foreach ($ar_packets as $id_packet => $ar_options) {
					$id_order = $packets->order_single($id_packet, $uid, $ar_options['LABEL'], $ar_options['COUNT'], $fk_invoice, $fk_billableitem, $status);
					if ($id_order > 0) {
						$order = $packets->order_get($id_order);
						if ($ar_options["TARGET"] > 0) {
							$order->itemAdd((int)$ar_options["TARGET"]);
						}
						if ($order->isActive()) {
							// Ensure all items are enabled
							$order->activate();
						}
						if ($fk_invoice > 0) {
							$arInvoiceItems = $db->fetch_col("SELECT ID_BILLING_INVOICE_ITEM FROM `billing_invoice_item` WHERE FK_BILLING_INVOICE=".(int)$fk_invoice);
							$invoiceItemManagement = BillingInvoiceItemManagement::getInstance($db);
							$invoiceItemManagement->updateRef($arInvoiceItems[0], BillingInvoiceItemManagement::REF_TYPE_PACKET, $id_order);
						}
						if (!empty($fk_billableitem)) {
							$invoiceItemManagement = BillingInvoiceItemManagement::getInstance($db);
							$invoiceItemManagement->updateRefBillableItem($fk_billableitem[0], BillingInvoiceItemManagement::REF_TYPE_PACKET, $id_order);
						}
					}
				}
				if ($fk_invoice > 0) {
					// Show latest invoice
					die(forward("/my-pages/invoice," . $fk_invoice . ".htm"));
				} else {
					if (isset($_POST["success"])) {
						// Forward to success url
						die(forward($_POST["success"]));
					} else {
                        // Back to "My Account"
                        die(forward("/my-pages/"));
					}
				}
			}
		}
		// Noch nicht abgeschickt oder fehler -> Template variablen hinzufügen
		$tpl_content->addlist("liste", $ar_packet_list, "tpl/".$s_lang."/my-service.row.htm", "ListPackets");
		$tpl_content->addvar("PRICE", $price_summary);
		$tpl_content->addvar("PRICE_BRUTTO", $price_summary_brutto);
		$tpl_content->addvar("LAUFZEIT_VAR", "LU_".$lu_runtime);
		$tpl_content->addvar("LAUFZEIT_VAL", $_POST["LU_".$lu_runtime]);
		$tpl_content->addvars($_POST);
	}
}

?>