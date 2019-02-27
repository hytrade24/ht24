<?php
/**
 * ebiz-trader
 *
 * @copyright Copyright (c) 2012 ebiz-consult e.K.
 * @version 6.5.2
 */

require_once $ab_path."sys/lib.currency.php";
$currency = CurrencyManagement::getInstance($db);

$arCurrencies = $currency->getListByParams();

if (!empty($arCurrencies)) {
    $arCurrencyDefault = $arCurrencies[0];
    if (isset($_COOKIE['currencyConversion'])) {
        // Restore last selection from cookie
        foreach ($arCurrencies as &$arCurrency) {
            if ($arCurrency["ID_CURRENCY"] == $_COOKIE['currencyConversion']) {
                $arCurrency["ACTIVE"] = 1;
                $arCurrencyDefault = $arCurrency;
                break;
            }
        }
    } else {
        $arCurrencies[0]["ACTIVE"] = 1;
    }
    $valueConverted = (float)$tpl_content->getval("PRICE") * (float)$arCurrencyDefault["RATIO_FROM_DEFAULT"];
    $tpl_content->addlist("liste", $arCurrencies, "tpl/".$s_lang."/currency_conversion.row.htm");
    $tpl_content->addvar("CONVERTED_VALUE", $valueConverted);
    $tpl_content->addvar("CONVERTED_SYMBOL", $arCurrencyDefault["V2"]);
}