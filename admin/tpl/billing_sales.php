<?php
/* ###VERSIONSBLOCKINLCUDE### */

$perpage = 20;
$npage = ($_REQUEST["npage"] > 0 ? (int)$_REQUEST["npage"] : 1);

require_once $ab_path."sys/lib.sales.php";
$salesManagment = SalesManagement::getInstance();

$searchParameters = array("LIMIT_COUNT" => $perpage, "LIMIT_OFFSET" => ($npage-1)*$perpage);
$searchParametersHttp = "";
if (is_array($_REQUEST["SEARCH"])) {
    $searchParameters = array_merge($searchParameters, $_REQUEST["SEARCH"]);
    $tpl_content->addvars($searchParameters, "SEARCH_");
    $tpl_content->addvar("SEARCH_SORT_".str_replace("+", "_", $searchParameters["SORT"]), 1);
    if (!empty($searchParameters["LAST_CONTACT_MIN"])
        && preg_match("/([0-9]{1,2})\.([0-9]{1,2})\.([0-9]{4})/", $searchParameters["LAST_CONTACT_MIN"], $arMatch)) {
        $searchParameters["LAST_CONTACT_MIN"] = $arMatch[3]."-".$arMatch[2]."-".$arMatch[1];
    }
    if (!empty($searchParameters["LAST_CONTACT_MAX"])
        && preg_match("/([0-9]{1,2})\.([0-9]{1,2})\.([0-9]{4})/", $searchParameters["LAST_CONTACT_MIN"], $arMatch)) {
        $searchParameters["LAST_CONTACT_MAX"] = $arMatch[3]."-".$arMatch[2]."-".$arMatch[1];
    }
    $searchParametersHttp = "&".http_build_query(array("SEARCH" => $_REQUEST["SEARCH"]));
}
$arSalesUser = $salesManagment->getSalesUsers($searchParameters);
$tpl_content->addvar("pager", htm_browse((int)$searchParameters["RESULT_COUNT"], $npage, "index.php?page=".$tpl_content->vars['curpage'].$searchParametersHttp."&npage=", $perpage));
$tpl_content->addlist("liste", $arSalesUser, "tpl/".$s_lang."/billing_sales.row.htm");

?>