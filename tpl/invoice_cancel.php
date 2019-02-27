<?php
if ( isset($ar_params[1]) && isset($ar_params[2]) ) {

	$id_item = $ar_params[1];
	$type = $ar_params[2];
	$result = null;

	if ( $type == "invoice" ) {

		if ( !empty($_POST) ) {//var_dump($_POST["INVOICE_CANCEL_ITEM_CHECK"]);die();

			$execute_invoice_item_delete = false;

			$invoice_cancel_item = $_POST["INVOICE_CANCEL_ITEM"];
			$billing_cancel_arr = array();

			$ids = array();

			foreach ($invoice_cancel_item as $index => $invoice_item) {

				if ( isset($_POST["INVOICE_CANCEL_ITEM_CHECK"]) ) {
					if ( $_POST["INVOICE_CANCEL_ITEM_CHECK"][$index] == "on" ) {

						$execute_invoice_item_delete = false;

						$sql = "SELECT a.ID_BILLING_CANCEL
						FROM billing_cancel a
						WHERE a.FK_BILLING_INVOICE_ITEM = ".$invoice_item;
						$id_ = $db->fetch_atom( $sql );

						$billing_cancel_arr["FK_USER"] = $uid;
						$billing_cancel_arr["FK_BILLING_INVOICE_ITEM"] = $invoice_item;
						$billing_cancel_arr["CUSTOMER_REMARKS"] = $_POST["CANCEL_REMARKS"];
						$reason_option = "CANCEL_INVOICE_ITEM_REASON_" . $invoice_item;
						$billing_cancel_arr["FK_LOOKUP"] = $_POST[$reason_option];
						$billing_cancel_arr["CREATED_AT"] = date("Y-m-d H:i:s");
						$billing_cancel_arr["LAST_MODIFIED"] = $billing_cancel_arr["CREATED_AT"];
						$billing_cancel_arr["ACTION"] = "none";

						if ( !is_null($id_) ) {
							$billing_cancel_arr["ID_BILLING_CANCEL"] = $id_;
						}

						$id = $db->update("billing_cancel",$billing_cancel_arr);
						array_push($ids, $id);
					}
					else {

						$execute_invoice_item_delete = true;
					}
				}
				else {
					$execute_invoice_item_delete = true;
				}

				if ( $execute_invoice_item_delete ) {
					$execute_invoice_item_delete = false;
					//delte query goes here
					$query = 'DELETE FROM `billing_cancel`
					WHERE ((`FK_BILLING_INVOICE_ITEM` = "'.$invoice_item.'") 
					AND (`FK_USER` = "'.$uid.'"));';

					$db->querynow( $query );

				}

			}

			forward(
				$tpl_content->tpl_uri_action(
					"invoices,1,cancel_requests,".implode("-",$ids)
				)
			);

		}

		$sql = 'SELECT a.STAMP_CREATE, t.TAX_VALUE, 
					SUM( b.PRICE * b.QUANTITY ) as SUM_PRICE,
					SUM((1 + (IFNULL(t.TAX_VALUE, 0)/100)) * b.PRICE) * b.QUANTITY as TOTAL_PRICE
					FROM billing_invoice a
					INNER JOIN billing_invoice_item b
					ON a.ID_BILLING_INVOICE = '.$id_item.'
					AND b.FK_BILLING_INVOICE = a.ID_BILLING_INVOICE
					LEFT JOIN tax t
					ON t.ID_TAX = b.FK_TAX';

		$result1 = $db->fetch1( $sql );

		$tpl_content->addvar("STAMP",$result1["STAMP_CREATE"]);
		$tpl_content->addvar("TAX_VALUE",$result1["TAX_VALUE"]);
		$tpl_content->addvar("INVOICE_SUM_PRICE",$result1["SUM_PRICE"]);
		$tpl_content->addvar("INVOICE_TOTAL_PRICE",$result1["TOTAL_PRICE"]);

		$sql = 'SELECT *, "1" as TYPE, t.TAX_VALUE,
					((1 + (IFNULL(t.TAX_VALUE, 0)/100)) * tab1.PRICE) * tab1.QUANTITY as TOTAL_PRICE
					FROM ( 
							SELECT *
								FROM billing_invoice_item a
								WHERE a.FK_BILLING_INVOICE = '.$id_item.'
						  ) tab1
					LEFT JOIN billing_cancel b
					ON b.FK_BILLING_INVOICE_ITEM = tab1.ID_BILLING_INVOICE_ITEM
					LEFT JOIN tax t
					ON t.ID_TAX = tab1.FK_TAX';

		$result = $db->fetch_table( $sql );

		foreach ( $result as $index => $row ) {
			if ( !is_null($row["FK_LOOKUP"]) ) {
				$result[$index]["CANCEL_INVOICE_ITEM_REASON_".$row["ID_BILLING_INVOICE_ITEM"]] = $row["FK_LOOKUP"];
			}
		}
		$tpl_content->addvar("CANCEL_REMARKS",$result[0]["CUSTOMER_REMARKS"]);

		//list_items
		$tpl_content->addlist("list_items",$result, 'tpl/'.$s_lang.'/invoices_cancel.row.htm');

	}
	else if ( $type == "billable" ) {

		if ( !empty($_POST) ) {

			$execute_billable_delete = false;

			$id_billing_billableitem_cancel = $_POST["BILLING_BILLABLEITEM_CANCEL"];

			if ( isset( $_POST["BILLING_BILLABLEITEM_CANCEL_CHECK"] ) ) {

				if ( $_POST["BILLING_BILLABLEITEM_CANCEL_CHECK"] == "on" ) {
					$sql = "SELECT a.ID_BILLING_CANCEL
						FROM billing_cancel a
						WHERE a.FK_BILLING_BILLABLEITEM = ".$_POST["BILLING_BILLABLEITEM_CANCEL"];
					$id_ = $db->fetch_atom( $sql );

					$reason_option = "CANCEL_INVOICE_ITEM_REASON_" . $id_item;
					$date = date("Y-m-d H:i:s");
					$billing_cancel_arr = array(
						"FK_USER"                   =>  $uid,
						"FK_BILLING_BILLABLEITEM"   =>  $_POST["BILLING_BILLABLEITEM_CANCEL"],
						"CUSTOMER_REMARKS"          =>  $_POST["CANCEL_REMARKS"],
						"FK_LOOKUP"                 =>  $_POST[$reason_option],
						"CREATED_AT"                =>  $date,
						"LAST_MODIFIED"             =>  $date,
						"ACTION"                    =>  "none"
					);
					if ( !is_null($id_) ) {
						$billing_cancel_arr["ID_BILLING_CANCEL"] = $id_;
					}

					$id = $db->update("billing_cancel",$billing_cancel_arr);

					forward($tpl_content->tpl_uri_action("invoices,1,cancel_requests,".$id));
				}
				else {
					$execute_billable_delete = true;
				}

			}
			else {
				$execute_billable_delete = true;
			}

			if ( $execute_billable_delete ) {
				//delte query goes here
				$query = 'DELETE FROM `billing_cancel`
				WHERE ((`FK_BILLING_BILLABLEITEM` = "'.$_POST["BILLING_BILLABLEITEM_CANCEL"].'") 
				AND (`FK_USER` = "'.$uid.'"));';

				$db->querynow( $query );
			}

		}

		$sql = 'SELECT a.STAMP_CREATE, a.PRICE, "2" as TYPE
					FROM billing_billableitem a
					WHERE a.ID_BILLING_BILLABLEITEM = '.$id_item;

		$result1 = $db->fetch1( $sql );

		$tpl_content->addvar("STAMP",$result1["STAMP_CREATE"]);
		$tpl_content->addvar("INVOICE_TOTAL_PRICE",$result1["PRICE"]);

		$sql = 'SELECT *, t.TAX_VALUE,
					((1 + (IFNULL(t.TAX_VALUE, 0)/100)) * tab1.PRICE) * tab1.QUANTITY as TOTAL_PRICE, 
					"2" as TYPE, b.CUSTOMER_REMARKS
					FROM (
							SELECT a.*, ads.FK_AD_ORDER
								FROM billing_billableitem a
								LEFT JOIN ad_sold ads 
								ON ads.ID_AD_SOLD = a.REF_FK
								AND a.REF_TYPE = 4
								WHERE a.ID_BILLING_BILLABLEITEM = '.$id_item.'
						  ) tab1
					LEFT JOIN billing_cancel b
					ON b.FK_BILLING_BILLABLEITEM = tab1.ID_BILLING_BILLABLEITEM
					LEFT JOIN tax t
					ON t.ID_TAX = tab1.FK_TAX';

		$result = $db->fetch_table( $sql );
		foreach ( $result as $index => $row ) {
			if ( !is_null($row["FK_LOOKUP"]) ) {
				$result[$index]["CANCEL_INVOICE_ITEM_REASON_".$row["ID_BILLING_BILLABLEITEM"]] = $row["FK_LOOKUP"];
			}
		}

		$tpl_content->addvar("CANCEL_REMARKS",$result[0]["CUSTOMER_REMARKS"]);
		$tpl_content->addlist("list_items",$result, 'tpl/'.$s_lang.'/invoices_cancel.row.htm');

	}

	$tpl_content->addvar("ID",$id_item);
	if ( $type == "invoice" ) {
		$type = 1;
	}
	else if ( $type == "billable" ) {
		$type = 2;
	}
	$tpl_content->addvar("TYPE",$type);
}