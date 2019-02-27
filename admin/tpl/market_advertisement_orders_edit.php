<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once($ab_path."sys/lib.billing.invoice.php");
require_once($ab_path."sys/lib.billing.invoice.item.php");
require_once($ab_path."sys/lib.billing.billableitem.php");

/**
 * Read the current check state of a category
 *
 * @param $row
 * @param $i
 */
function read_state(&$row, $i) {
	global $db, $uid, $id_ad_user;
	$id_checked = $db->fetch_atom("
		SELECT
			ID_ADVERTISEMENT_KAT
		FROM
			`advertisement_kat`
		WHERE
			FK_ADVERTISEMENT_USER=".(int)$id_ad_user." AND
			FK_KAT=".(int)$row["ID_KAT"]."
	");
	if ($id_checked > 0) {
		$row["CHECKED"] = 1;
	} else {
		$row["CHECKED"] = 0;
	}
}

global $id_ad_user;

$SILENCE = false;

$id_kat_root = 1;
$ar_kat_root = array();
$id_ad_user = $_SESSION['id_ad_edit'] = (!empty($_REQUEST['id']) ? (int)$_REQUEST['id'] : (int)$_SESSION['id_ad_edit']);
$is_found = $db->fetch_atom("SELECT count(*) FROM `advertisement_user` WHERE ID_ADVERTISEMENT_USER=".$id_ad_user);

//die("SELECT count(*) FROM `advertisement_user` WHERE ID_ADVERTISEMENT_USER=".$id_ad_user);

$action = (isset($_REQUEST['action']) ? $_REQUEST['action'] : "read");
$target = (isset($_REQUEST['target']) ? $_REQUEST['target'] : $id_kat_root);

if ($is_found == 0) {
	$tpl_content->addvar("error_not_found", 1);
	return;
} else {
	include_once "sys/lib.shop_kategorien.php";
	$kat = new TreeCategories("kat", $id_kat_root);

	$ar_ad_user = $db->fetch1("
		SELECT
			*,
			(SELECT ID_BILLING_BILLABLEITEM FROM `billing_billableitem` WHERE FK_USER = au.FK_USER AND REF_FK = au.ID_ADVERTISEMENT_USER AND REF_TYPE = ".BillingInvoiceItemManagement::REF_TYPE_ADVERTISEMENT." LIMIT 1) AS FK_BILLING_BILLABLEITEM,
			(SELECT count(*) FROM `advertisement_kat` WHERE FK_ADVERTISEMENT_USER=ID_ADVERTISEMENT_USER) AS CATEGORYS
		FROM
			`advertisement_user` au
		WHERE
			ID_ADVERTISEMENT_USER=".$id_ad_user);
	if (empty($ar_ad_user)) {
		$tpl_content->addvar("error_not_found", 1);
		return;
	}
	$tpl_content->addvars($ar_ad_user);

	if (!empty($_REQUEST['ajax'])) {
		$tpl_content->addvar("ajax", 1);
	}

	if (!empty($_POST["book"])) {
		$id_ad = (int)$ar_ad_user["FK_ADVERTISEMENT"];
		$num_kats = (int)$db->fetch_atom("SELECT count(*) FROM `advertisement_kat` WHERE FK_ADVERTISEMENT_USER=".$id_ad_user);
		$date_from = strtotime($ar_ad_user["STAMP_START"]);
		$date_to = strtotime($ar_ad_user["STAMP_END"]);
		if (($date_from < $date_to) && ($num_kats > 0) && ($id_ad > 0)) {
			// Update price
			$price = $db->fetch_atom("
				SELECT
					SUM(PRICE)
				FROM
					`advertisement_kat`
				WHERE
					FK_ADVERTISEMENT_USER=".$id_ad_user);
			// Einstellungen okay!
			$db->querynow("
				UPDATE
					`advertisement_user`
				SET
					PRICE=".(float)$price.",
					DONE=1,
                    ENABLED =1
				WHERE
					ID_ADVERTISEMENT_USER=".$id_ad_user);
			die(forward("/my-pages/my-pages/advertisement,done.htm"));
		}
		$tpl_content->addvar("done", 1);
	} else {
		if ($action == "read") {
			// Get target category id (or use root if none given)
			$id_kat = (int)$target;
			// Read child nodes of target categoy
			if ($ar_kat_root = $kat->element_get_childs($id_kat)) {
				foreach ($ar_kat_root as $index => $ar_kat) {
					// Check for childs
					$ar_kat_root[$index]["HAS_CHILDS"] = $kat->element_has_childs($ar_kat["ID_KAT"]);
					$ar_kat_root[$index]['ID_AD_USER'] = $id_ad_user;
				}
				// Output category list
				$tpl_content->addlist("liste_kat", $ar_kat_root, "tpl/de/market_advertisement.row_kat.htm", read_state);
			}
			if ($target == $id_kat_root) {
				// Regular call (no ajax)
				$ar_advertisements = $db->fetch_table("
					SELECT
						a.*,
						s.*
					FROM
						`advertisement` a
					LEFT JOIN
						`string_advertisement` s ON
						s.S_TABLE='advertisement' AND s.FK=a.ID_ADVERTISEMENT AND
						s.BF_LANG=if(a.BF_LANG_ADVERTISEMENT & ".$langval.", ".$langval.", 1 << floor(log(a.BF_LANG_ADVERTISEMENT+0.5)/log(2)))
					");
				$tpl_content->addlist("liste_pos", $ar_advertisements, "tpl/de/market_advertisement.row_pos.htm");
			}
		}
		/**
		 * Confirm / Deploy / Invoice
		 * Bestätigen / Ausliefern / Rechnung
		 */
		if ($action == "confirm") {
			$ar_result = array(
				"success"	=> 1,
				"changed"	=> false
			);
			$status_cur = $db->fetch1("
				SELECT
					u.*,
					(DATEDIFF(STAMP_END,STAMP_START)+1) as DAYS,
					if(FK_INVOICE IS NOT NULL,1,0) as INVOICE,
					s.V1 as AD_NAME
				FROM
					`advertisement_user` u
				LEFT JOIN
					`advertisement` a ON
					a.ID_ADVERTISEMENT=u.FK_ADVERTISEMENT
				LEFT JOIN
					`string_advertisement` s ON
					s.S_TABLE='advertisement' AND s.FK=a.ID_ADVERTISEMENT AND
					s.BF_LANG=if(a.BF_LANG_ADVERTISEMENT & ".$langval.", ".$langval.", 1 << floor(log(a.BF_LANG_ADVERTISEMENT+0.5)/log(2)))
				WHERE
					ID_ADVERTISEMENT_USER=".$id_ad_user);
			$status_new = array(
				"CONFIRMED"		=> ((int)$target & 1),
				"ENABLED"		=> (((int)$target & 2) / 2) & ((int)$target & 1),
				"INVOICE"		=> ((int)$target & 4) / 4,
				"FK_INVOICE"	=> $status_cur["FK_INVOICE"]
			);
			// Rechnungsstellung nur mit Bestätigung
			$status_new["CONFIRMED"] = $status_new["CONFIRMED"] | $status_old["INVOICE"];
			// Aktiviertung nur mit Bestätigung
			$status_new["ENABLED"] = $status_new["ENABLED"] & $status_new["CONFIRMED"];
			if (($status_cur["CONFIRMED"] != $status_new["CONFIRMED"]) ||
				($status_cur["ENABLED"] != $status_new["ENABLED"])) {
				// Settings changed
				$db->querynow("
					UPDATE `advertisement_user` SET
						CONFIRMED=".(int)$status_new["CONFIRMED"].",
						ENABLED=".(int)$status_new["ENABLED"]."
					WHERE ID_ADVERTISEMENT_USER=".$id_ad_user);
				$ar_result["changed"] = true;
			}
			// Deliver invoice?
			if (($status_new["INVOICE"] == 1) && ($status_cur["INVOICE"] == 0)) {
                $id_user_sales = $db->fetch_atom("SELECT FK_USER_SALES FROM `user` WHERE ID_USER=".(int)$status_cur["FK_USER"]);
				// Add invoice

				$billingInvoiceManagement = BillingInvoiceManagement::getInstance($db);
				$billingBillableItemManagement = BillingBillableItemManagement::getInstance($db);

				$invoiceItems[] = array(
					'DESCRIPTION' => $status_cur["AD_NAME"] . "\n" . Translation::readTranslation('marketplace', 'invoice.period.performance', null, array(), 'Leistungszeitraum') . ": " . date("d.m.Y", strtotime($status_cur["STAMP_START"])) . " - " . date("d.m.Y", strtotime($status_cur["STAMP_END"])),
					'PRICE' => $status_cur["PRICE"] * $status_cur["DAYS"] / 1.19,
					'QUANTITY' => 1,
					'FK_TAX' => 1,
					'REF_TYPE' => BillingInvoiceItemManagement::REF_TYPE_ADVERTISEMENT,
					'REF_FK' => $id_ad_user
				);

				$billing_data = array(
					'FK_USER' => $status_cur["FK_USER"],
                    'FK_USER_SALES' => $id_user_sales,
					'__items' => $invoiceItems
				);

				if ($billingInvoiceManagement->shouldChargeAtOnceByUserId($status_cur["FK_USER"])) {
					$invoiceId = $billingInvoiceManagement->createInvoice($billing_data);
				} else {
					if ($ar_ad_user['FK_BILLING_BILLABLEITEM'] === null) {
						$billableItemId = $billingBillableItemManagement->createMultipleBillableItems($billing_data);
					}
				}

				if (!empty($billableItemId)) {
					
					$invoiceItemManagement = BillingInvoiceItemManagement::getInstance($this->database);
					$invoiceItemManagement->updateRefBillableItem($billableItemId[0], BillingInvoiceItemManagement::REF_TYPE_ADVERTISEMENT, $id_ad_user);
					
					$ar_result["changed"] = true;
					die(json_encode($ar_result));
				}

				if ($invoiceId > 0) {

					$db->querynow("
						UPDATE
							`advertisement_user`
						SET
							FK_INVOICE=".$invoiceId.",
							PAID=0
						WHERE
							ID_ADVERTISEMENT_USER=".$id_ad_user);
					
					$arInvoiceItems = $db->fetch_col("SELECT ID_BILLING_INVOICE_ITEM FROM `billing_invoice_item` WHERE FK_BILLING_INVOICE=".(int)$invoiceId);
					$invoiceItemManagement = BillingInvoiceItemManagement::getInstance($this->database);
					$invoiceItemManagement->updateRef($arInvoiceItems[0], BillingInvoiceItemManagement::REF_TYPE_ADVERTISEMENT, $id_ad_user);
					
					// Return result
					$ar_result["changed"] = true;
				} else {
					$ar_result["success"] = 0;
				}
			}
			die(json_encode($ar_result));
		}
		/**
		 * Set banner format
		 */
		if ($action == "banner_set") {
			if ($target > 0) {
				$banner_cur = $db->fetch_atom("SELECT FK_ADVERTISEMENT FROM `advertisement_user` WHERE ID_ADVERTISEMENT_USER=".$id_ad_user);
               	$res = $db->querynow("
					UPDATE
						`advertisement_user`
					SET
						FK_ADVERTISEMENT=".(int)$target."
					WHERE
						ID_ADVERTISEMENT_USER=".$id_ad_user);
				// Return result as json object
				header('Content-type: application/json');
				if ($res["rsrc"]) {
					// Get prices of the used advertisment
					$costs = $db->fetch_atom("
						SELECT
							COSTS
						FROM
							`advertisement`
						WHERE
							ID_ADVERTISEMENT=".(int)$target);
					$ar_costs = explode("|", $costs);
					// Update prices
					$db->querynow("
						UPDATE
							`advertisement_kat`
						SET
							PRICE=".(float)$ar_costs[0]."
						WHERE
							FK_ADVERTISEMENT_USER=".$id_ad_user);
					for ($level = 1; $level < count($ar_costs); $level++) {
						$db->querynow("
							UPDATE
								`advertisement_kat`
							SET
								PRICE=".(float)$ar_costs[$level]."
							WHERE
								FK_ADVERTISEMENT_USER=".$id_ad_user." AND
								LEVEL=".(int)$level);
					}
					// Return result
					die(json_encode(array(
						"success"	=> 1,
						"changed"	=> ($banner_cur != $target)
					)));
				} else {
					die(json_encode(array(
						"success"	=> 0,
						"changed"	=> ($banner_cur != $target),
						"previous"	=> $banner_cur
					)));
				}
			}
		}

		/**
		 * Set banner code
		 */
		if ($action == "code_set") {
			if (!empty($target)) {
				$code_cur = $db->fetch_atom("SELECT CODE FROM `advertisement_user` WHERE ID_ADVERTISEMENT_USER=".$id_ad_user);
				$res = $db->querynow("
					UPDATE
						`advertisement_user`
					SET
						CODE='".mysql_escape_string($target)."'
					WHERE
						ID_ADVERTISEMENT_USER=".$id_ad_user);
				// Return result as json object
				header('Content-type: application/json');
				if ($res["rsrc"]) {
					// Return result
					die(json_encode(array(
						"success"	=> 1,
						"changed"	=> ($code_cur != $target)
					)));
				} else {
					die(json_encode(array(
						"success"	=> 0,
						"changed"	=> ($code_cur != $target)
					)));
				}
			}
		}

		/**
		 * Set start date
		 */
		if ($action == "date_from_set") {
			if (preg_match("/([0-9]{1,2})\.([0-9]{1,2})\.([0-9]{2,4})/", $target, $matches)) {
				$date_db = $matches[3]."-".$matches[2]."-".$matches[1]." 00:00:00";
				$date_cur = $db->fetch_atom("SELECT STAMP_START FROM `advertisement_user` WHERE ID_ADVERTISEMENT_USER=".$id_ad_user);
				$res = $db->querynow("
					UPDATE
						`advertisement_user`
					SET
						STAMP_START='".mysql_escape_string($date_db)."'
					WHERE
						ID_ADVERTISEMENT_USER=".$id_ad_user);
				// Return result as json object
				header('Content-type: application/json');
				if ($res["rsrc"]) {
					// Return result
					die(json_encode(array(
						"success"	=> 1,
						"changed"	=> ($date_cur != $date_db),
						"old"		=> $date_cur,
						"new"		=> $date_db
					)));
				} else {
					die(json_encode(array(
						"success"	=> 0,
						"changed"	=> ($date_cur != $date_db)
					)));
				}
			}
		}

		/**
		 * Set end date
		 */
		if ($action == "date_to_set") {
			if (preg_match("/([0-9]{1,2})\.([0-9]{1,2})\.([0-9]{2,4})/", $target, $matches)) {
				$date_db = $matches[3]."-".$matches[2]."-".$matches[1]." 00:00:00";
				$date_cur = $db->fetch_atom("SELECT STAMP_END FROM `advertisement_user` WHERE ID_ADVERTISEMENT_USER=".$id_ad_user);
				$res = $db->querynow("
					UPDATE
						`advertisement_user`
					SET
						STAMP_END='".mysql_escape_string($date_db)."'
					WHERE
						ID_ADVERTISEMENT_USER=".$id_ad_user);
				// Return result as json object
				header('Content-type: application/json');
				if ($res["rsrc"]) {
					// Return result
					die(json_encode(array(
						"success"	=> 1,
						"changed"	=> ($date_cur != $date_db),
						"old"		=> $date_cur,
						"new"		=> $date_db
					)));
				} else {
					die(json_encode(array(
						"success"	=> 0,
						"changed"	=> ($date_cur != $date_db)
					)));
				}
			}
		}

		/**
		 * Add single category
		 */
		if ($action == "kat_add") {
			if ($target != $id_kat_root) {
				$id_kat = (int)$target;
				// Obtain category level
				$level = $db->fetch_atom("
					SELECT
						(SELECT count(*) FROM `kat` k2 WHERE k2.LFT<k1.LFT AND k2.RGT>k1.RGT AND k2.ROOT=k1.ROOT) as LEVEL
					FROM
						`kat` k1
					WHERE
						k1.ID_KAT=".$id_kat);
				// Get prices of the used advertisment
				$costs = $db->fetch_atom("
					SELECT
						COSTS
					FROM
						`advertisement`
					WHERE
						ID_ADVERTISEMENT=".$ar_ad_user["FK_ADVERTISEMENT"]);
				$ar_costs = explode("|", $costs);
				$price = ($ar_costs[$level] > 0 ? $ar_costs[$level] : $ar_costs[0]);
				// Add category to selection
				$res = $db->querynow("
					INSERT INTO `advertisement_kat`
						(`FK_ADVERTISEMENT_USER`, `FK_KAT`, `LEVEL`, `PRICE`)
					VALUES
						(".$id_ad_user.", ".$id_kat.", ".$level.", '".$price."')");
				// Return result as json object
				header('Content-type: application/json');
				die(json_encode(array(
					"success" => $res["rsrc"]
				)));
			} else {
				// Return result as json object
				header('Content-type: application/json');
				die(json_encode(array(
					"success" => 0
				)));
			}
		}

		/**
		 * Recursive add categorys
		 */
		if ($action == "kat_add_recursive") {
			if ($target != $id_kat_root) {
				$id_kat = (int)$target;
				// Get all related categorys
				$ar_kats = $db->fetch_nar("
					SELECT
						k1.ID_KAT,
						(SELECT count(*) FROM `kat` k2 WHERE k2.LFT<k1.LFT AND k2.RGT>k1.RGT AND k2.ROOT=k1.ROOT) as LEVEL
					FROM
						`kat` k1,
						`kat` k3
					WHERE
						k3.ID_KAT=".$id_kat." AND
						k1.LFT>=k3.LFT AND k1.RGT<=k3.RGT AND k1.ROOT=k3.ROOT");
				// Get prices of the used advertisment
				$costs = $db->fetch_atom("
					SELECT
						COSTS
					FROM
						`advertisement`
					WHERE
						ID_ADVERTISEMENT=".$ar_ad_user["FK_ADVERTISEMENT"]);
				$ar_costs = explode("|", $costs);
				$ar_checked_kats = array();
				// Add categorys to selection
				foreach ($ar_kats as $id_kat => $level) {
					$price = ($ar_costs[$level] > 0 ? $ar_costs[$level] : $ar_costs[0]);
					$res = $db->querynow("
						INSERT INTO `advertisement_kat`
							(`FK_ADVERTISEMENT_USER`, `FK_KAT`, `LEVEL`, `PRICE`)
						VALUES
							(".$id_ad_user.", ".$id_kat.", ".$level.", '".$price."')");
					if ($res["rsrc"] > 0) {
						$ar_checked_kats[] = $id_kat;
					}
				}
				// Return result as json object
				header('Content-type: application/json');
				die(json_encode(array(
					"success" => 1,
					"checked" => $ar_checked_kats
				)));
			} else {
				// Return result as json object
				header('Content-type: application/json');
				die(json_encode(array(
					"success" => 0
				)));
			}
		}

		/**
		 * Remove single category
		 */
		if ($action == "kat_rem") {
			if ($target != $id_kat_root) {
				$id_kat = (int)$target;
				// Remove category from selection
				$res = $db->querynow("
					DELETE FROM
						`advertisement_kat`
					WHERE
						FK_ADVERTISEMENT_USER=".$id_ad_user." AND FK_KAT=".$id_kat);
				// Return result as json object
				header('Content-type: application/json');
				die(json_encode(array(
					"success" => $res["rsrc"]
				)));
			} else {
				// Return result as json object
				header('Content-type: application/json');
				die(json_encode(array(
					"success" => 0
				)));
			}
		}

		/**
		 * Recursive remove categorys
		 */
		if ($action == "kat_rem_recursive") {
			if ($target != $id_kat_root) {
				$id_kat = (int)$target;
				// Get all related categorys
				$ar_kats = $db->fetch_nar("
					SELECT
						k1.ID_KAT,
						(SELECT count(*) FROM `kat` k2 WHERE k2.LFT<k1.LFT AND k2.RGT>k1.RGT AND k2.ROOT=k1.ROOT) as LEVEL
					FROM
						`kat` k1,
						`kat` k3
					WHERE
						k3.ID_KAT=".$id_kat." AND
						k1.LFT>=k3.LFT AND k1.RGT<=k3.RGT AND k1.ROOT=k3.ROOT");
				$ar_checked_kats = array_keys($ar_kats);
				// Remove categorys from selection
				$res = $db->querynow("
					DELETE FROM
						`advertisement_kat`
					WHERE
						FK_ADVERTISEMENT_USER=".$id_ad_user." AND
						FK_KAT IN (".implode(",", $ar_checked_kats).")");
				// Return result as json object
				header('Content-type: application/json');
				die(json_encode(array(
					"success" => $res["rsrc"],
					"checked" => $ar_checked_kats
				)));
			} else {
				// Return result as json object
				header('Content-type: application/json');
				die(json_encode(array(
					"success" => 0
				)));
			}
		}

		if ($action == "update") {
			// Update price and count of selected categorys
			$price = 0;
			$ar_selected = $db->fetch_nar("
				SELECT
					FK_KAT,
					LEVEL
				FROM
					`advertisement_kat`
				WHERE
					FK_ADVERTISEMENT_USER=".$id_ad_user);
			$ar_ids = array_keys($ar_selected);
			if (!empty($ar_selected)) {
				// Get category levels for pricing
				$price = $db->fetch_atom("
					SELECT
						SUM(PRICE)
					FROM
						`advertisement_kat`
					WHERE
						FK_ADVERTISEMENT_USER=".$id_ad_user." AND
						FK_KAT IN (".mysql_escape_string(implode(",", $ar_ids)).")
				");
			}
			// Return result as json object
			header('Content-type: application/json');
			die(json_encode(array(
				"count" => count($ar_ids),
				"price"	=> (float)$price
			)));
		}
	}
}

?>
