<?php

require_once $ab_path . 'sys/lib.billing.invoice.php';
require_once $ab_path . 'sys/lib.billing.billableitem.php';
require_once $ab_path . 'sys/lib.billing.notification.php';

$billingInvoiceManagement = BillingInvoiceManagement::getInstance($db);
$billingBillableItemManagement = BillingBillableItemManagement::getInstance($db);
$billingNotificationManagement = BillingNotificationManagement::getInstance($db);

if ( !empty($_REQUEST) ) {

	if ( isset($_REQUEST["FK_BILLING_CANCEL"]) ) {

		$billing_cancel_item_email_arr = array();
		$row_billing_cancel = null;

		$template = new Template("tpl/".$s_lang."/cancel_request_edit.email.htm");

		$arInvoiceAcceptItems = array();
		$arInvoiceCancel = array();
		foreach ( $_REQUEST["FK_BILLING_CANCEL"] as $index => $row ) {
			$sql = '
              SELECT a.*, i.FK_BILLING_INVOICE
			  FROM billing_cancel a
			  LEFT JOIN `billing_invoice_item` i 
			  	ON i.ID_BILLING_INVOICE_ITEM=a.FK_BILLING_INVOICE_ITEM
			  WHERE a.ID_BILLING_CANCEL = ' . $row;
			$row_billing_cancel = $db->fetch1( $sql );
			$row_billing_cancel["ACTION"] = $_REQUEST["ACTION_TYPE_".$row];
			$row_billing_cancel["ADMIN_REMARKS"] = $_REQUEST["ADMIN_REMARKS"][$index];
			$arInvoiceCancel[$row] = $row_billing_cancel;
			if (( $row_billing_cancel["ACTION"] == "accept_and_cancel" )
				|| ( $row_billing_cancel["ACTION"] == "accept_and_keep" )) {
				$invoiceId = $row_billing_cancel["FK_BILLING_INVOICE"];
				if ($invoiceId === null) {
					// Billable item
					continue;
				}
				if (!array_key_exists($invoiceId, $arInvoiceAcceptItems)) {
					$arInvoiceAcceptItems[$invoiceId] = array($row);
				} else {
					$arInvoiceAcceptItems[$invoiceId][] = $row;
				}
			}
		}

		foreach ( $arInvoiceAcceptItems as $invoiceId => $acceptedItems ) {

			$invoiceItemCount = $db->fetch_atom("
				SELECT COUNT(ID_BILLING_INVOICE_ITEM) FROM `billing_invoice_item` 
				WHERE FK_BILLING_INVOICE=".(int)$invoiceId);

			if ($invoiceItemCount == count($acceptedItems)) {
				// TODO: Cancel whole invoice

				$dataPerformanceArr = array(
					"KEEP_PERFORMANCES" => array()
				);

				foreach ( $acceptedItems as $idBillingCancel ) {

					$row_billing_cancel = $arInvoiceCancel[$idBillingCancel];

					switch ($row_billing_cancel["ACTION"]) {
						default:
						case "accept_and_cancel":
						$dataPerformanceArr["KEEP_PERFORMANCES"][$row_billing_cancel["FK_BILLING_INVOICE_ITEM"]] = false;
							break;
						case "accept_and_keep":
							$dataPerformanceArr["KEEP_PERFORMANCES"][$row_billing_cancel["FK_BILLING_INVOICE_ITEM"]] = true;
							break;
					}

					$arInvoiceCancel[$idBillingCancel]["ACTION_SKIP"] = true;
				}
				$billingInvoiceManagement->setStatus($invoiceId, BillingInvoiceManagement::STATUS_CANCELED, $dataPerformanceArr);

				$billingInvoiceManagement->setCorrection($invoiceId);
			}
		}

		$corrected_invoices = array();

		foreach ( $arInvoiceCancel as $row => $row_billing_cancel ) {

			$billing_cancel = array(
				"ID_BILLING_CANCEL"     =>  $row,
				"LAST_MODIFIED"         =>  date("Y-m-d H:i:s"),
				"ADMIN_REMARKS"         =>  $row_billing_cancel["ADMIN_REMARKS"]
			);

			$row_billing_cancel_with_details = null;

			$sql = null;
			if ( !is_null($row_billing_cancel["FK_BILLING_INVOICE_ITEM"]) ) {
				$sql = 'SELECT a.*, b.DESCRIPTION, b.QUANTITY, b.FK_TAX, b.PRICE, b.FK_BILLING_INVOICE
						FROM billing_cancel a
						INNER JOIN billing_invoice_item b
						ON a.ID_BILLING_CANCEL = '.$row.'
						AND b.ID_BILLING_INVOICE_ITEM = a.FK_BILLING_INVOICE_ITEM';
			} else if ( !is_null($row_billing_cancel["FK_BILLING_BILLABLEITEM"]) ) {
				$sql = 'SELECT a.*, b.DESCRIPTION, b.QUANTITY, b.FK_TAX, b.PRICE
						FROM billing_cancel a
						INNER JOIN billing_billableitem b
						ON a.ID_BILLING_CANCEL = '.$row.'
						AND b.ID_BILLING_BILLABLEITEM = a.FK_BILLING_BILLABLEITEM';
			}
			$row_billing_cancel_with_details = $db->fetch1( $sql );

			switch ($row_billing_cancel["ACTION"]) {
		        default:
		        case "none":
		          // No action
		          break;
		        case "accept_and_cancel":
		          // Accept request (cancel service)
		          $billing_cancel["STATUS"] = "done";
		          $billing_cancel["ACTION"] = "accept_and_revoke";
		          if (array_key_exists("ACTION_SKIP", $row_billing_cancel)) {
		            break;
		          }
		          // Remove item
		          if ( !is_null($row_billing_cancel["FK_BILLING_INVOICE_ITEM"]) ) {
		            // Invoice item
		            $billingInvoiceManagement->deleteItem(
		              $row_billing_cancel["FK_BILLING_INVOICE_ITEM"], false, $row
		            );
		            array_push($corrected_invoices,$row_billing_cancel["FK_BILLING_INVOICE"]);
		          } else if ( !is_null($row_billing_cancel["FK_BILLING_BILLABLEITEM"]) ) {
		            // Billable item
		            $billingBillableItemManagement->deleteBillableItem(
		              $row_billing_cancel["FK_BILLING_BILLABLEITEM"], false, $row
		            );
		          }
		          break;
		        case "accept_and_keep":
		          // Accept request (keep service)
		          $billing_cancel["STATUS"] = "done";
		          $billing_cancel["ACTION"] = "accept_and_keep";
			        if (array_key_exists("ACTION_SKIP", $row_billing_cancel)) {
				        break;
			        }
		          // Remove item
		          if ( !is_null($row_billing_cancel["FK_BILLING_INVOICE_ITEM"]) ) {
		            // Invoice item
		            $billingInvoiceManagement->deleteItem(
		              $row_billing_cancel["FK_BILLING_INVOICE_ITEM"], true, $row
		            );
			        array_push($corrected_invoices,$row_billing_cancel["FK_BILLING_INVOICE"]);

		          }	else if ( !is_null($row_billing_cancel["FK_BILLING_BILLABLEITEM"]) ) {
		            // Billable item
		            $billingBillableItemManagement->deleteBillableItem(
		              $row_billing_cancel["FK_BILLING_BILLABLEITEM"], true, $row
		            );
		          }
		          break;
		        case "deny":
		          // Deny request
		          $billing_cancel["STATUS"] = "rejected";
		          $billing_cancel["ACTION"] = "rejected";
		          break;
		        case "shelve":
		          // Shelve request
		          $billing_cancel["STATUS"] = "shelve";
		          $billing_cancel["ACTION"] = "shelve";
		          break;
			}

			$item = null;
			if ( $_REQUEST["ACTION_TYPE_".$row] != "none" ) {
				$item = $db->update("billing_cancel", $billing_cancel);
				array_push(
					$billing_cancel_item_email_arr,
					array(
						"ID_BILLING_CANCEL" =>  $billing_cancel["ID_BILLING_CANCEL"],
						"CUSTOMER_REMARKS"  =>  $row_billing_cancel["CUSTOMER_REMARKS"],
						"ADMIN_REMARKS"     =>  $billing_cancel["ADMIN_REMARKS"],
						"STATUS"            =>  $billing_cancel["STATUS"],
						"DESCRIPTION"       =>  $row_billing_cancel_with_details["DESCRIPTION"]
					)
				);
				$fk_billing_invoice = null;
				if ( !is_null($row_billing_cancel_with_details["FK_BILLING_INVOICE"]) ) {
					$fk_billing_invoice = $row_billing_cancel_with_details["FK_BILLING_INVOICE"];
				}
			}
		}

		foreach ( array_unique($corrected_invoices) as $invoice ) {
			$billingInvoiceManagement->setCorrection( $invoice );
		}

		if ( count($billing_cancel_item_email_arr) > 0 ) {
			$template->addlist("liste",$billing_cancel_item_email_arr,"tpl/".$s_lang."/cancel_request_edit.email.row.htm");

			$sql = '
              SELECT u.VORNAME, u.NACHNAME
              FROM user u
              WHERE u.ID_USER = ' . $row_billing_cancel["FK_USER"];

			$result = $db->fetch1( $sql );

			$email_arr = array(
				"liste"     =>  $template->process( true ),
				"VORNAME"   =>  $result["VORNAME"],
				"NACHNAME"   =>  $result["NACHNAME"]
			);
			sendMailTemplateToUser(0, $row_billing_cancel["FK_USER"], 'STORNO_ANFRAGE_BEARBEITET', $email_arr);
		}
		$tpl_content->addvar("success","Aktion erfolgreich ausgeführt");
	}
}


if ( isset($_REQUEST["FK_USER"]) ) {
	if ( $_REQUEST["FK_USER"] != "" ) {
		$fk_user = $_REQUEST["FK_USER"];

		$arWhere = array();

		if ( isset($_REQUEST["STATUS"]) ) {
			if ($_REQUEST["STATUS"] != "alle") {
				array_push(
					$arWhere,
					' AND c.STATUS = "' . $_REQUEST["STATUS"] . '"'
				);
			}
			$tpl_content->addvar("STATUS",$_REQUEST["STATUS"]);
		}

		$sql = 'SELECT u.NAME, u.VORNAME, u.NACHNAME, u.EMAIL, u.STRASSE, u.PLZ, u.ORT
					FROM user u
					WHERE u.ID_USER = ' . $fk_user;

		$row = $db->fetch1( $sql );
		$tpl_content->addvars($row);
		$tpl_content->addvar("FK_USER",$fk_user);

		$sql = 'SELECT c.*, IFNULL(i.FK_BILLING_INVOICE,ci.FK_BILLING_INVOICE) as FK_BILLING_INVOICE,
 				IFNULL(i.DESCRIPTION,IFNULL(b.DESCRIPTION,ci.DESCRIPTION)) as DESCRIPTION,
				i.DESCRIPTION as INVOICE_ITEM_DESCRIPTION, 
				b.DESCRIPTION as BILLABLE_ITEM_DESCRIPTION, s.V1,
				IFNULL(i.PRICE,IFNULL(b.PRICE,ci.PRICE)) as PRICE
					FROM `billing_cancel` c 
					LEFT JOIN `billing_invoice_item` i 
					ON c.FK_BILLING_INVOICE_ITEM = i.ID_BILLING_INVOICE_ITEM 
					LEFT JOIN `billing_billableitem` b
					ON c.FK_BILLING_BILLABLEITEM = b.ID_BILLING_BILLABLEITEM
					LEFT JOIN `billing_cancel_item` ci
					ON c.ID_BILLING_CANCEL = ci.FK_BILLING_CANCEL
					LEFT JOIN `lookup` l
					ON l.ID_LOOKUP = c.FK_LOOKUP
					LEFT JOIN `string` s
					ON s.FK = c.FK_LOOKUP
					AND s.S_TABLE = "lookup"
					AND s.BF_LANG = l.BF_LANG
					WHERE c.FK_USER = '.$_REQUEST["FK_USER"];
		$sql .= implode(" ", $arWhere);
		$sql .= ' ORDER BY FK_BILLING_INVOICE';

		$result = $db->fetch_table( $sql );

		$old_invoice = null;
		$have_billable_items = 0;
		foreach ( $result as $index => $row ) {

			if ( is_null($old_invoice) || $old_invoice != $row["FK_BILLING_INVOICE"] ) {
				$result[$index]["NEW"] = 1;
			}
			else {
				$result[$index]["NEW"] = 0;
			}
			$old_invoice = $row["FK_BILLING_INVOICE"];

			if ( !is_null($row["FK_BILLING_BILLABLEITEM"]) ) {
				$have_billable_items = 1;
				$result[$index]["DESCRIPTION_LINK"] = "index.php?page=billing_billableitem";
				$result[$index]["DESCRIPTION_LINK"] .= "&ID_BILLING_BILLABLEITEM=";
				$result[$index]["DESCRIPTION_LINK"] .= $result[$index]["FK_BILLING_BILLABLEITEM"];
			}
			else if ( !is_null($row["FK_BILLING_INVOICE_ITEM"]) ) {
				$result[$index]["DESCRIPTION_LINK"] = "index.php?page=invoice_view";
				$result[$index]["DESCRIPTION_LINK"] .= "&ID_BILLING_INVOICE=";
				$result[$index]["DESCRIPTION_LINK"] .= $result[$index]["FK_BILLING_INVOICE"];
				$result[$index]["DESCRIPTION_LINK"] .= "&highlight=";
				$result[$index]["DESCRIPTION_LINK"] .= $result[$index]["FK_BILLING_INVOICE_ITEM"];
			}
			$result[$index]["STATUS_".$row["STATUS"]] = "1";
			$result[$index]["ACTION_".$row["ACTION"]] = "1";

		}
		$tpl_content->addvar("HAVE_BILLABLE_ITEMS",$have_billable_items);
		$tpl_content->addlist("liste",$result,"tpl/".$s_lang."/cancel_request_edit.row.htm");
	}
}