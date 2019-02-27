<?php
/* ###VERSIONSBLOCKINLCUDE### */



function list_category(&$row, $i) {
	global $db, $langval;
	$ar_kats = $db->fetch_nar("SELECT sk.V1, sk.V1 FROM `kat` k\n".
	"	LEFT JOIN `string_kat` sk ON sk.S_TABLE='kat' AND sk.FK=k.ID_KAT \n".
	" AND sk.BF_LANG=if(k.BF_LANG_KAT & ".$langval.", ".$langval.", 1 << floor(log(k.BF_LANG_KAT+0.5)/log(2)))\n".
	"	LEFT JOIN `advertisement_kat` a ON k.ID_KAT=a.FK_KAT ".
	"WHERE a.FK_ADVERTISEMENT_USER=".$row["ID_ADVERTISEMENT_USER"]);
	$row['LIST_KATS'] = implode(", ", $ar_kats);
}

//$SILENCE = false;

$mode = ($_REQUEST["mode"] ? $_REQUEST["mode"] : "unconfirmed");

if (!empty($_REQUEST['confirm'])) {
	$id_ad_user = (int)$_REQUEST['confirm'];
	//$ar_ad_user = $db->fetch1("SELECT * FROM `advertiesment_user` WHERE ID_ADVERTISEMENT_USER=".$id_ad_user);
	$ar_ad_user = $db->fetch1("
		SELECT
			u.*,
			(DATEDIFF(STAMP_END,STAMP_START)+1) as DAYS,
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
	if (empty($_REQUEST['skip_booking'])) {
        $id_user_sales = $db->fetch_atom("SELECT FK_USER_SALES FROM `user` WHERE ID_USER=".(int)$id_user);
		// Add invoice
		require_once($ab_path."sys/lib.billing.invoice.php");
		require_once($ab_path."sys/lib.billing.billableitem.php");
		$billingInvoiceManagement = BillingInvoiceManagement::getInstance($db);
		$billingBillableItemManagement = BillingBillableItemManagement::getInstance($db);

        $invoiceItems[] = array(
            'DESCRIPTION' => $ar_ad_user["AD_NAME"]."\n".Translation::readTranslation('marketplace', 'invoice.period.performance', null, array(), 'Leistungszeitraum').": ".date("d.m.Y", strtotime($ar_ad_user["STAMP_START"]))." - ".date("d.m.Y", strtotime($ar_ad_user["STAMP_END"])),
            'PRICE' => $ar_ad_user["PRICE"] * $ar_ad_user["DAYS"] / 1.19,
            'QUANTITY' => 1,
			'FK_TAX' => 1,
			'REF_TYPE' => BillingInvoiceItemManagement::REF_TYPE_ADVERTISEMENT,
			'REF_FK' => $id_ad_user,

        );

		$billing_data = array(
			'FK_USER'       => $id_user,
            'FK_USER_SALES' => $id_user_sales,
			'__items'       => $invoiceItems
		);

		if($billingInvoiceManagement->shouldChargeAtOnceByUserId($id_user)) {
			$invoiceId = $billingInvoiceManagement->createInvoice($billing_data);
		} else {
			$billableItemId = $billingBillableItemManagement->createMultipleBillableItems($billing_data);
		}


        if ($invoiceId > 0) {
            $db->querynow("
                UPDATE
                    `advertisement_user`
                SET
                    FK_INVOICE=".$invoiceId.",
                    CONFIRMED=1
                WHERE
                    ID_ADVERTISEMENT_USER=".$id_ad_user);
            die(forward("index.php?page=market_advertisement_orders&mode=unconfirmed"));
        } else {
            die(forward("index.php?page=market_advertisement_orders&mode=unconfirmed"));
        }
	} else {
		$db->querynow("
			UPDATE
				`advertisement_user`
			SET
				FK_INVOICE=NULL,
				CONFIRMED=1
			WHERE
				ID_ADVERTISEMENT_USER=".$id_ad_user);
	}
}
if (!empty($_REQUEST['delete'])) {
	$id_ad_user = (int)$_REQUEST['delete'];
	$db->querynow("DELETE FROM `advertisement_user` WHERE ID_ADVERTISEMENT_USER=".$id_ad_user);
	die(forward("index.php?page=market_advertisement_orders&mode=".rawurlencode($mode)));
}

$ar_orders = array();

$query_base = "
		SELECT
			u.*,
			(SELECT count(*) FROM `advertisement_kat` k WHERE k.FK_ADVERTISEMENT_USER=u.ID_ADVERTISEMENT_USER)
				AS NUM_KATS,
			(DATEDIFF(STAMP_END,STAMP_START)+1) as DAYS,
			((DATEDIFF(STAMP_END,STAMP_START)+1) * u.PRICE) as PRICE_SUM,
			s.V1 as AD_NAME, 
            if (u.ENABLED=1 AND (CURDATE() BETWEEN u.STAMP_START AND u.STAMP_END),1,0) as ISONLINE
		FROM
			`advertisement_user` u
		LEFT JOIN
			`advertisement` a ON
			a.ID_ADVERTISEMENT=u.FK_ADVERTISEMENT
		LEFT JOIN
			`string_advertisement` s ON
			s.S_TABLE='advertisement' AND s.FK=a.ID_ADVERTISEMENT AND
			s.BF_LANG=if(a.BF_LANG_ADVERTISEMENT & ".$langval.", ".$langval.", 1 << floor(log(a.BF_LANG_ADVERTISEMENT+0.5)/log(2)))
		WHERE";

if ($mode == "unconfirmed") {
	$ar_orders = $db->fetch_table($query_base."
			u.DONE=1 AND u.CONFIRMED=0
		ORDER BY
			u.STAMP_START");
}
if ($mode == "confirmed") {
	$ar_orders = $db->fetch_table($query_base."
			u.DONE=1 AND u.CONFIRMED=1 AND u.PAID=0
		ORDER BY
			u.STAMP_START");
}
if ($mode == "paid") {
	$ar_orders = $db->fetch_table($query_base."
			u.DONE=1 AND u.CONFIRMED=1 AND u.PAID=1
		ORDER BY
			u.STAMP_START");
}
$tpl_content->addvar("mode_".$mode, 1);
$tpl_content->addvar("mode", rawurlencode($mode));
$tpl_content->addlist("liste", $ar_orders, "tpl/de/market_advertisement_orders.row_".$mode.".htm", "list_category");

?>