<?php
/* ###VERSIONSBLOCKINLCUDE### */


require_once $ab_path.'sys/lib.ad_order.php';
require_once $ab_path.'sys/lib.payment.adapter.php';
require_once $ab_path.'sys/payment/PaymentFactory.php';


$tpl_content->addvar("use_prov", $nar_systemsettings['MARKTPLATZ']['USE_PROV']);

$npage = ($ar_params[1] ? (int)$ar_params[1] : 1);
$perpage = 25;
$limit = ($perpage*$npage)-$perpage;

$id = (int)$ar_params[2];
$act = $ar_params[3];
$show = $ar_params[4];

$adOrderManagement = AdOrderManagement::getInstance($db);
$paymentAdapterManagement = PaymentAdapterManagement::getInstance($db);

$param = array(
	'USER_SELLER' => $uid,
	'LIMIT' => $perpage,
	'OFFSET' => $limit
);

if (isset($_REQUEST['ID_AD_ORDER']) && $_REQUEST['ID_AD_ORDER'] != "") {
	$param['ID_AD_ORDER'] = $_REQUEST['ID_AD_ORDER'];
}
if (isset($_REQUEST['NAMEBUYER']) && $_REQUEST['NAMEBUYER'] != "") {
	$param['BUYER_NAME'] = $_REQUEST['NAMEBUYER'];
}
if (isset($_REQUEST['STAMP_CREATE_FROM']) && $_REQUEST['STAMP_CREATE_FROM'] != "") {
	$param['STAMP_CREATE_FROM'] = $_REQUEST['STAMP_CREATE_FROM'];
}
if (isset($_REQUEST['STAMP_CREATE_TO']) && $_REQUEST['STAMP_CREATE_TO'] != "") {
	$param['STAMP_CREATE_TO'] = $_REQUEST['STAMP_CREATE_TO'];
}
if (isset($_REQUEST['SHIPPING_PROVIDER']) && $_REQUEST['SHIPPING_PROVIDER'] != "") {
	$param['SHIPPING_PROVIDER'] = $_REQUEST['SHIPPING_PROVIDER'];
}
if (isset($_REQUEST['SEARCH_ORDER_CONFIRMATION_STATUS']) && $_REQUEST['SEARCH_ORDER_CONFIRMATION_STATUS'] != "-1") {
	$param['STATUS_CONFIRMATION'] = $_REQUEST['SEARCH_ORDER_CONFIRMATION_STATUS'];
} else {
	$_REQUEST['SEARCH_ORDER_CONFIRMATION_STATUS'] = -1;
}
if (isset($_REQUEST['SEARCH_ORDER_PAYMENT_STATUS']) && $_REQUEST['SEARCH_ORDER_PAYMENT_STATUS'] != "-1") {
	$param['STATUS_PAYMENT'] = $_REQUEST['SEARCH_ORDER_PAYMENT_STATUS'];
} else {
	$_REQUEST['SEARCH_ORDER_PAYMENT_STATUS'] = -1;
}
if (isset($_REQUEST['SEARCH_ORDER_SHIPPING_STATUS']) && $_REQUEST['SEARCH_ORDER_SHIPPING_STATUS'] != "-1") {
	$param['STATUS_SHIPPING'] = $_REQUEST['SEARCH_ORDER_SHIPPING_STATUS'];
} else {
	$_REQUEST['SEARCH_ORDER_SHIPPING_STATUS'] = -1;
}

if($show == 'show_done') {
	$param['ARCHIVE_SELLER'] = 1;
	$tpl_content->addvar("show_done", 1);
} else {
	$param['ARCHIVE_SELLER'] = 0;
	$tpl_content->addvar("show_open", 1);
}

if (!empty($_REQUEST["doExport"])) {
    // Only allow to export confirmed sales
    $param['STATUS_CONFIRMATION'] = 1;
    // Do not limit/offset for export
    $param["LIMIT"] = NULL;
    $param["OFFSET"] = NULL;

    switch ($_REQUEST["doExport"]) {
        case 'xml_jtl':
            $exportXml = new Export_Xml_Sales_Jtl_JtlExportAdapter($db);
            $exportXml->process($param);
            die($exportXml->streamXml());
        case 'csv':
        default:
            // Export as CSV (continue below)
            break;
    }

    // Export as CSV
    $filename = "export_sales.csv";
    $csvSeperator = ';';
    $arFieldTitles = array();
    $arUserOrders = $adOrderManagement->fetchAllByParam($param);
    if (!empty($arUserOrders)) {
        $output = fopen('php://output', 'w');
        ob_start();
        foreach ($arUserOrders as $index => $arOrder) {
            // Minimize order content
            $arOrderMin = array(
                "ID_ORDER"          => $arOrder["ID_AD_ORDER"],
                "TRANSACTION_ID"    => $arOrder["TRANSACTION_ID"],
                "STATUS_PAYMENT"    => $arOrder["STATUS_PAYMENT"],
                "STATUS_SHIPPING"   => $arOrder["STATUS_SHIPPING"],
                "STAMP"             => $arOrder["STAMP_CREATE"],
                "REMARKS"           => $arOrder["REMARKS"],
                "PAYMENT_ADAPTER"   => $arOrder["PAYMENT_ADAPTER_NAME"],
                "PRICE_SHIPPING"    => $arOrder["SHIPPING_PRICE"],
                "PRICE_TOTAL"       => $arOrder["TOTAL_PRICE"],
                "USER_ID"           => $arOrder["FK_USER"],
                "USER_NAME"         => $arOrder["USER_EK_NAME"]
            );
            // Add items
            foreach ($arOrder["items"] as $itemIndex => $arItem) {
                // Minimize item content
                $arItemMin = array(
                    "ID_AD"                 => $arItem["FK_AD"],
                    "ID_AD_VARIANT"         => $arItem["FK_AD_VARIANT"],
                    "NAME"                  => $arItem["PRODUKTNAME"],
                    "NOTIZ"                 => $arItem["NOTIZ"],
                    "MENGE"                 => $arItem["MENGE"],
                    "MWST"                  => $arItem["MWST"],
                    "PREIS"                 => $arItem["PREIS"],
                    "VERSANDKOSTEN"         => $arItem["VERSANDKOSTEN"],
                    "PROVISION"             => $arItem["PROV"]
                );
                // Add invoice address to order
                $arOrderMin["INVOICE_FIRMA"] = $arItem["INVOICE_FIRMA"];
                $arOrderMin["INVOICE_VORNAME"] = $arItem["INVOICE_VORNAME"];
                $arOrderMin["INVOICE_NACHNAME"] = $arItem["INVOICE_NACHNAME"];
                $arOrderMin["INVOICE_STRASSE"] = $arItem["INVOICE_STRASSE"];
                $arOrderMin["INVOICE_PLZ"] = $arItem["INVOICE_PLZ"];
                $arOrderMin["INVOICE_ORT"] = $arItem["INVOICE_ORT"];
                $arOrderMin["INVOICE_LAND"] = $db->fetch_atom("SELECT V1 FROM string
                                                  WHERE S_TABLE='country' AND BF_LANG=".$langval." AND
                                                    FK=".(int)$arItem["INVOICE_FK_COUNTRY"]);
                // Add shipping address to order
                $arOrderMin["VERSAND_FIRMA"] = $arItem["VERSAND_FIRMA"];
                $arOrderMin["VERSAND_VORNAME"] = $arItem["VERSAND_VORNAME"];
                $arOrderMin["VERSAND_NACHNAME"] = $arItem["VERSAND_NACHNAME"];
                $arOrderMin["VERSAND_STRASSE"] = $arItem["VERSAND_STRASSE"];
                $arOrderMin["VERSAND_PLZ"] = $arItem["VERSAND_PLZ"];
                $arOrderMin["VERSAND_ORT"] = $arItem["VERSAND_ORT"];
                $arOrderMin["VERSAND_LAND"] = $db->fetch_atom("SELECT V1 FROM string
                                                  WHERE S_TABLE='country' AND BF_LANG=".$langval." AND
                                                    FK=".(int)$arItem["VERSAND_FK_COUNTRY"]);
                // Add item to order
                foreach ($arItemMin as $itemField => $itemValue) {
                    $arOrderMin["ITEM".$itemIndex."_".$itemField] = $itemValue;
                }
            }
            // Check if this line contains more lines than previous
            if (count($arFieldTitles) < count($arOrderMin)) {
                // Get field titles
                $arFieldTitles = array_keys($arOrderMin);
            }
            // Put csv line
            fputcsv($output, $arOrderMin, $csvSeperator);
        }
        fclose($output);
        $csv = implode($csvSeperator, $arFieldTitles)."\n".ob_get_clean();
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename='.basename($filename));
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . strlen($csv));
        die($csv);
    }
}

$userOrders = $adOrderManagement->fetchAllByParam($param);
$countOrder = $adOrderManagement->countByParam($param);

$countUnconfirmedOrders =  $adOrderManagement->countByParam(array(
	'STATUS_CONFIRMATION' => 0,
	'USER_SELLER' => $uid
));

$additionalParams = $_GET;
unset($additionalParams['page']);


// Plugin event
$eventOrderSalesSearchForm = new Api_Entities_EventParamContainer(array(
    "fields"        => array(),
    "params"        => $param            
));
Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::MARKETPLACE_ORDER_SALES_SEARCH_FORM, $eventOrderSalesSearchForm);
if ($eventOrderSalesSearchForm->isDirty()) {
    $arFields = $eventOrderSalesSearchForm->getParam("fields");
	  $tpl_content->addvar("PLUGIN_HTML", implode("\n", $arFields));
}


$tpl_content->addlist("orders", $userOrders, "tpl/".$s_lang."/my-marktplatz-verkaeufe.row.htm", "callback_order_addOrderItems");
$tpl_content->addvar("CURRENCY_DEFAULT", $nar_systemsettings['MARKTPLATZ']['CURRENCY']);
$tpl_content->addvar("pager", htm_browse_extended($countOrder, $npage, "my-marktplatz-verkaeufe,{PAGE},".$id.",".$act.",".$show, $perpage, 5, '?'.http_build_query($additionalParams)));
$tpl_content->addvar('COUNT_UNCONFIRMED_ORDERS', $countUnconfirmedOrders);
$tpl_content->addvars($_REQUEST);

?>