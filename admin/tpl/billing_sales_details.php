<?php
/* ###VERSIONSBLOCKINLCUDE### */

$perpage = 20;
$npage = ($_REQUEST["npage"] > 0 ? (int)$_REQUEST["npage"] : 1);
$id_user = (int)$_REQUEST["ID_USER"];

require_once $ab_path."sys/lib.sales.php";
$salesManagment = SalesManagement::getInstance();

if (is_array($_POST["CHECK"])) {
    $salesManagment->setSalesBilled(array_keys($_POST["CHECK"]), $_POST["SET_BILLED"], $id_user);
    die(forward("index.php?page=".$tpl_content->vars['curpage']."&ID_USER=".$id_user."&done=status"));
}
if (isset($_REQUEST["done"])) {
    $tpl_content->addvar("DONE", 1);
    $tpl_content->addvar("DONE_".strtoupper($_REQUEST["done"]), 1);
}

// Tax value
$taxId = $nar_systemsettings["MARKTPLATZ"]["TAX_DEFAULT"];
$tax = $db->fetch_atom("SELECT TAX_VALUE FROM `tax` WHERE ID_TAX=".(int)$taxId) / 100 + 1;
$tpl_content->addvar("TAX", $tax);

$searchParameters = array("LIMIT_COUNT" => $perpage, "LIMIT_OFFSET" => ($npage-1)*$perpage);
$searchParametersHttp = "";
if (is_array($_REQUEST["SEARCH"])) {
    $searchParameters = array_merge($searchParameters, $_REQUEST["SEARCH"]);
    $searchParametersTpl = $searchParameters;
    foreach ($searchParametersTpl as $param => $value) {
        $searchParametersTpl[$param."_".$value] = 1;
    }
    $tpl_content->addvars($searchParametersTpl, "SEARCH_");
    $tpl_content->addvar("SEARCH_SORT_".str_replace("+", "_", $searchParameters["SORT"]), 1);
    if (!empty($searchParameters["LAST_CONTACT_MIN"])
        && preg_match("/([0-9]{1,2})\.([0-9]{1,2})\.([0-9]{4})/", $searchParameters["LAST_CONTACT_MIN"], $arMatch)) {
        $searchParameters["LAST_CONTACT_MIN"] = $arMatch[3]."-".$arMatch[2]."-".$arMatch[1];
    }
    if (!empty($searchParameters["LAST_CONTACT_MAX"])
        && preg_match("/([0-9]{1,2})\.([0-9]{1,2})\.([0-9]{4})/", $searchParameters["LAST_CONTACT_MIN"], $arMatch)) {
        $searchParameters["LAST_CONTACT_MAX"] = $arMatch[3]."-".$arMatch[2]."-".$arMatch[1];
    }
    $searchParametersHttp = "&ID_USER=".$id_user."&".http_build_query(array("SEARCH" => $_REQUEST["SEARCH"]));
} else {
    $searchParametersHttp = "&ID_USER=".$id_user;
}
$arList = $salesManagment->getSalesUserTurnovers($id_user, $searchParameters);
$tpl_content->addvars($db->fetch1("SELECT * FROM `user` WHERE ID_USER=".$id_user), "SALES_");
$tpl_content->addvar("pager", htm_browse((int)$searchParameters["RESULT_COUNT"], $npage, "index.php?page=".$tpl_content->vars['curpage'].$searchParametersHttp."&npage=", $perpage));
$tpl_content->addlist("liste", $arList, "tpl/".$s_lang."/billing_sales_details.row.htm");

// Zur Historie hinzufügen
require_once 'sys/lib.worklist.php';
wklist_insert(2, $id_user, "billing_sales_details");

?>