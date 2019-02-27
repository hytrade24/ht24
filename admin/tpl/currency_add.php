<?php
/**
 * ebiz-trader
 *
 * @copyright Copyright (c) 2012 ebiz-consult e.K.
 * @version 6.5.2
 */

require_once $ab_path."sys/lib.currency.php";
require_once $ab_path."sys/Currency/Fixer.php";
$currency = CurrencyManagement::getInstance($db);

$id_currency = (int)$_REQUEST["ID_CURRENCY"];
$ar_currency = ($id_currency > 0 ? $currency->getById($id_currency) : array());

if (!empty($_POST)) {
    $_POST["RATIO_FROM_DEFAULT"] =  str_replace(",", ".", $_POST["RATIO_FROM_DEFAULT"]);
    $id_currency = $currency->update($_POST);
    $curr = new Fixer();
    $ratio = $curr->get_and_convert("EUR",$_POST["ISO_CURRENCY_FORMAT"]);

	if ( !is_null($ratio) ) {
		$success = $curr->update_currency_ratio(
			$id_currency,
			$ratio
		);
	}
	else {
		$success = $curr->update_currency_auto_status(
			$id_currency
		);
	}

    die(forward( "index.php?page=currency&done=".(isset($_POST["ID_CURRENCY"]) ? "edit" : "add") ));
} else {
    $ar_currency["RATIO_FROM_DEFAULT"] = str_replace(".", ",", $ar_currency["RATIO_FROM_DEFAULT"]);
    $tpl_content->addvars($ar_currency);
    $tpl_content->addvar("AUTOMATICALLY_UPDATED_".$ar_currency["AUTOMATICALLY_UPDATED"],1);
}