<?php
/**
 * ebiz-trader
 *
 * @copyright Copyright (c) 2012 ebiz-consult e.K.
 * @version 6.5.2
 */

require_once $ab_path."sys/Currency/Fixer.php";

require_once $ab_path."sys/lib.currency.php";
$currency = CurrencyManagement::getInstance($db);

if ( $_GET['type'] == "cur" ) {
	$returned_data = new stdClass();

	$cur = new Fixer();
	if ( $cur->update_all_currencies_ratios( true ) ) {
		$returned_data->success = true;
		$returned_data->msg = "Successfully updated all currencies ratios";
	}
	else {
		$returned_data->success = false;
		$returned_data->msg = "Failed to update all currencies ratios";
	}
	die(json_encode($returned_data));
}

if (!empty($_REQUEST["do"])) {
    switch ($_REQUEST["do"]) {
        case 'del':
            if ($currency->deleteById($_REQUEST['ID_CURRENCY'])) {
                die(forward("index.php?page=currency&done=del"));
            }
    }
}

$arList = $currency->getListByParams();
$tpl_content->addlist("liste", $arList, "tpl/de/currency.row.htm");

$tpl_content->addvar("CURRENCY_DEFAULT", $nar_systemsettings["MARKTPLATZ"]["CURRENCY"]);
