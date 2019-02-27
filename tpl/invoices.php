<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path . 'sys/lib.user.php';
require_once $ab_path . 'sys/lib.payment.adapter.php';
require_once $ab_path . 'sys/lib.billing.invoice.php';
require_once $ab_path . 'sys/lib.billing.creditnote.php';
require_once $ab_path . 'sys/lib.billing.billableitem.php';

if ($nar_systemsettings["MARKTPLATZ"]["FREE_ADS"]) {
	$tpl_content->addvar("FREE_ADS", 1);
}

if ($nar_systemsettings["MARKTPLATZ"]["COUPON_ENABLED"]) {
	$tpl_content->addvar("OPTION_COUPON_ENABLED", 1);
}


$npage = ((int)$ar_params[1] ? (int)$ar_params[1] : 1);
$perpage = 10;
$limit = ($perpage*$npage)-$perpage;

$show_open = ($ar_params[2] == '' ? true : false);
$show_paid = ($ar_params[2] == 'paid' ? true : false);
$show_storno = ($ar_params[2] == 'storno' ? true : false);
$show_credit = ($ar_params[2] == 'credit' ? true : false);
$show_coupon = ($ar_params[2] == 'coupon' ? true : false);
$show_cancel_requests = ($ar_params[2] == 'cancel_requests' ? true : false);

$tpl_content->addvar("show_open", $show_open);
$tpl_content->addvar("show_paid", $show_paid);
$tpl_content->addvar("show_storno", $show_storno);
$tpl_content->addvar("show_credit", $show_credit);
$tpl_content->addvar("show_coupon", $show_coupon);
$tpl_content->addvar("show_cancel_requests", $show_cancel_requests);


if(isset($_REQUEST['DO']) && $_REQUEST['DO'] == 'COUPON_SUBMIT') {
    $couponManagement = Coupon_CouponManagement::getInstance($db);
    $couponUsageManagement = Coupon_CouponUsageManagement::getInstance($db);

    $couponTargetType = ($_REQUEST['COUPON_WIDGET_TARGET_TYPE'] ? $_REQUEST['COUPON_WIDGET_TARGET_TYPE'] : null);
    $couponTargets = ($_REQUEST['COUPON_WIDGET_TARGETS'] ? json_decode(base64_decode($_REQUEST['COUPON_WIDGET_TARGETS'])) : null);

    $result = array();
    if(isset($_POST['COUPON_CODE']) && !empty($_POST['COUPON_CODE'])) {
        try {
            $couponUsage = $couponManagement->useCouponCode($_POST['COUPON_CODE'])->couponUsage;

            if($couponUsage['USAGE_STATE'] != Coupon_CouponUsageManagement::USAGE_STATE_ACTIVATED || !$couponUsageManagement->isCouponsUsageCompatible($couponUsage, $couponTargetType, $couponTargets)) {
                throw new Exception(Translation::readTranslation('marketplace', 'coupon.error.coupon.activated.but.not.compatible', NULL, array(), 'Der Gutschein wurde aktiviert, kann für die aktuelle Bestellung aber nicht verwendet werden. Sie können diesen in einer anderen Bestellung verwenden.'));
            }

            $result = array('success' => true, 'couponUsageId' => $couponUsage['ID_COUPON_CODE_USAGE']);


        } catch(Exception $e) {
            $result = array('success' => false, 'message' => $e->getMessage());
        }
    } else {
        $result = array('success' => false, 'message' => Translation::readTranslation('marketplace', 'coupon.error.coupon.no.code', NULL, array(), 'Es wurde kein Code eingegeben'));
    }


    die(json_encode($result));
}

if($show_open || $show_paid || $show_storno) {

    $billingInvoiceManagement = BillingInvoiceManagement::getInstance($db);
    $invoices = $billingInvoiceManagement->fetchAllByParam(array(
            'FK_USER' => $uid,
            'STATUS' => ($show_open ? 0 : ($show_paid ? 1 : 2)),
            'LIMIT' => $perpage,
            'OFFSET' => $limit,
	        'BILLING_CANCEL_CHECK'  =>  '1'
    ));

    $numberOfInvoices = $billingInvoiceManagement->countByParam(array(
            'FK_USER' => $uid, 'STATUS' => ($show_open ? 0 : ($show_paid ? 1 : 2))
    ));


    $tpl_content->addlist("liste", $invoices, 'tpl/' . $s_lang . '/invoices.row.htm');
    $tpl_content->addvar("pager", htm_browse_extended($numberOfInvoices, $npage, 'invoices,{PAGE},' . $ar_params[2], $perpage));

    #$tpl_content->addvar("OPEN", $acc->get_open());

}
if($show_credit) {
    $billingCreditnoteManagement = BillingCreditnoteManagement::getInstance($db);
    $billingBillableItemManagement = BillingBillableItemManagement::getInstance($db);

    // creditnotes
    $creditnotes = $billingCreditnoteManagement->fetchAllByParam(array(
        'FK_USER' => $uid,
        'STATUS' => BillingCreditnoteManagement::STATUS_ACTIVE
    ));


    $creditNoteTotalAmount = 0;
    foreach($creditnotes as $key => $creditnote) {
        $creditNoteTotalAmount += $creditnote['TOTAL_PRICE'];
    }


    // billable items
    $billableItems = $billingBillableItemManagement->fetchAllByParam(array(
        'FK_USER' => $uid,
        'SORT' => 'b.STAMP_CREATE',
        'SORT_DIR' => 'DESC'
    ));



    $tpl_content->addlist('liste_creditnotes', $creditnotes, 'tpl/'.$s_lang.'/invoices.creditnotes.row.htm');
    $tpl_content->addlist('liste_billableitems', $billableItems, 'tpl/'.$s_lang.'/invoices.billableitems.row.htm');

    $tpl_content->addvar('TOTAL_CREDITNOTE_SUM', $creditNoteTotalAmount);
}

if($show_coupon) {
    $couponManagement = Coupon_CouponManagement::getInstance($db);
    $couponUsageManagement = Coupon_CouponUsageManagement::getInstance($db);

    $tpl_content->addvars($_POST);

    if(isset($_POST['COUPON_CODE']) && !empty($_POST['COUPON_CODE'])) {
        try {
            $resultMessage = $couponManagement->useCouponCode($_POST['COUPON_CODE'])->message;
            $tpl_content->addvar('SUCCESS_COUPON', 1);
            $tpl_content->addvar('SUCCESS_COUPON_MSG', $resultMessage);

        } catch(Exception $e) {
            $tpl_content->addvar('ERR_COUPON', $e->getMessage());
        }
    }

    //list coupons
    $activatedCoupons = $couponUsageManagement->fetchAllByParam(array(
        'FK_USER' => $uid,
        'USAGE_STATE' => Coupon_CouponUsageManagement::USAGE_STATE_ACTIVATED,
        'SORT_BY' => 'ccu.STAMP_ACTIVATE',
        'SORT_DIR' => 'DESC'
    ));


    $tpl_content->addlist('liste_coupons', $activatedCoupons, 'tpl/'.$s_lang.'/invoices.coupons.row.htm');

}

if ($show_cancel_requests) {
	$sql = 'SELECT *
				FROM billing_cancel a
				WHERE FK_USER = ' . $uid.'
				ORDER BY a.ID_BILLING_CANCEL DESC';
	$result = $db->fetch_table( $sql );

	foreach ( $result as $index => $row ) {
		if ( !is_null($row["FK_BILLING_BILLABLEITEM"]) ) {
			$sql = 'SELECT a.DESCRIPTION, a.ID_BILLING_BILLABLEITEM as EDIT_LINK, "2" as TYPE, a.REF_TYPE, a.REF_FK, ads.FK_AD_ORDER
						FROM billing_billableitem a
						LEFT JOIN ad_sold ads
						ON ads.ID_AD_SOLD = a.REF_FK
						AND a.REF_TYPE = 4
						WHERE a.ID_BILLING_BILLABLEITEM = ' . $row["FK_BILLING_BILLABLEITEM"];
			$temp = $db->fetch1( $sql );
			$result[$index]["REF_FK"] = $temp["REF_FK"];
			$result[$index]["REF_TYPE"] = $temp["REF_TYPE"];
			$result[$index]["TYPE"] = $temp["TYPE"];
			$result[$index]["RELATED_TO"] = $temp["DESCRIPTION"];
			$result[$index]["EDIT_LINK"] = $temp["EDIT_LINK"].",billable";
			$result[$index]["FK_AD_ORDER"] = $temp["FK_AD_ORDER"];
		}
		else if ( !is_null($row["FK_BILLING_INVOICE_ITEM"]) ) {
			$sql = 'SELECT a.ID_BILLING_INVOICE_ITEM, a.DESCRIPTION, a.FK_BILLING_INVOICE as EDIT_LINK, "1" as TYPE, a.ID_BILLING_INVOICE_ITEM as LINK_ID
						FROM billing_invoice_item a
						WHERE a.ID_BILLING_INVOICE_ITEM = ' . $row["FK_BILLING_INVOICE_ITEM"];
			$temp = $db->fetch1( $sql );
			$result[$index]["TYPE"] = $temp["TYPE"];
			$result[$index]["LINK_ID"] = $temp["LINK_ID"];
			$result[$index]["RELATED_TO"] = $temp["DESCRIPTION"];
			$result[$index]["EDIT_LINK"] = $temp["EDIT_LINK"].",invoice";
			$result[$index]["ID_BILLING_INVOICE_ITEM"] = $temp["ID_BILLING_INVOICE_ITEM"];
		}
		$result[$index]["STATUS_".$row["STATUS"]] = 1;
	}

	if ( isset($ar_params[3]) ) {
		$tpl_content->addvar("newly_added",$ar_params[3]);
	}

	$tpl_content->addlist("liste_cancel_requests",$result, 'tpl/'.$s_lang.'/invoices.cancel.requests.row.htm');

}

if ($show_open) {
	$billingBillableItemManagement = BillingBillableItemManagement::getInstance($db);
	$userManagement = UserManagement::getInstance($db);

	$billableItems = $billingBillableItemManagement->fetchAllByParam(
		array(
			"FK_USER" => $uid,
			"JOIN"  =>  " LEFT JOIN ad_sold ads ON ads.ID_AD_SOLD = b.REF_FK AND b.REF_TYPE = 4 LEFT JOIN billing_cancel bc ON bc.FK_BILLING_BILLABLEITEM = b.ID_BILLING_BILLABLEITEM",
			"SELECT"    =>  " bc.ID_BILLING_CANCEL, ads.FK_AD_ORDER "
		)
	);

	$tplBillableItems = array();

	foreach($billableItems as $key => $billableItem) {
		//$invoiceUser = $userManagement->fetchById($billableItem['FK_USER']);

		$billableItem['USER_NAME'] = $invoiceUser['NAME'];

		$tplBillableItems[] = $billableItem;
	}

	$tpl_content->addlist('liste_rechnungslauf', $tplBillableItems, 'tpl/'.$s_lang.'/invoices.lauf.row.htm');
	$tpl_content->addvar('INVOICE_DAYS_AUTOMATIC_BILLING', $nar_systemsettings['MARKTPLATZ']['INVOICE_DAYS_AUTOMATIC_BILLING']);

	$paymentAdapterManagement = PaymentAdapterManagement::getInstance($db);
	$paymentAdapters = $paymentAdapterManagement->fetchAllByParam(array('STATUS' => PaymentAdapterManagement::STATUS_ENABLED));

	$userPaymentAdapter = $paymentAdapterManagement->fetchById($user['FK_PAYMENT_ADAPTER']);

	if ( $userPaymentAdapter != false ) {
		$tpl_content->addvar('USER_SELECTED_PAYMENT_ADAPTER_NAME', $userPaymentAdapter["NAME"]);

		$paymentAdapterConfiguration = array(
			'CONFIG' => $paymentAdapterManagement->fetchConfigurationById(
				$userPaymentAdapter['ID_PAYMENT_ADAPTER']
			)
		);
		/** @var Payment_Adapter_PaymentAdapterInterface $paymentAdapter  */
		$paymentAdapter = Payment_PaymentFactory::factory($userPaymentAdapter['ADAPTER_NAME'], $paymentAdapterConfiguration);
		$paymentAdapter->init(array(
			'FK_USER' => $uid
		));

		$userPaymentDetails = $paymentAdapter->getUserConfiguration(
			strtolower($userPaymentAdapter["ADAPTER_NAME"])
		);

		$tpl_content->addvar('USER_SELECTED_PAYMENT_ADAPTER_RECIPIENT', $userPaymentDetails["Recipient"]);
		$tpl_content->addvar('USER_SELECTED_PAYMENT_ADAPTER_IBAN', $userPaymentDetails["iban"]);
		$tpl_content->addvar('USER_SELECTED_PAYMENT_ADAPTER_BIC', $userPaymentDetails["bic"]);
		$tpl_content->addvar('USER_SELECTED_PAYMENT_ADAPTER_BANK', $userPaymentDetails["Bank"]);
	}
}

?>