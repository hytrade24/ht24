<?php
/* ###VERSIONSBLOCKINLCUDE### */

$action = "";
$perpage = 20;
$npage = ($ar_params[2] > 0 ? (int)$ar_params[2] : 1);

require_once $ab_path."sys/lib.sales.php";
$salesManagment = SalesManagement::getInstance();

if (!empty($_POST["action"])) {
    $arResult = array("success" => false);
    switch ($_POST["action"]) {
        case 'updateUser':
            $idUser = (int)$_POST["idUser"];
            $arResult["success"] = $salesManagment->updateLastContact($uid, $idUser);
            $arResult["today"] = date("d.m.Y");
            break;
    }
    header("Content-Type: application/json");
    die(json_encode($arResult));
}

$searchActive = false;
$searchParameters = array("LIMIT_COUNT" => $perpage, "LIMIT_OFFSET" => ($npage-1)*$perpage);
$searchParametersHttp = "";
if (is_array($_REQUEST["SEARCH"])) {
    $searchActive = true;
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
    $searchParametersHttp = "?".http_build_query(array("SEARCH" => $_REQUEST["SEARCH"]));
}
$arSalesUser = $salesManagment->getUsersBySaleUser($uid, $searchParameters);
$tpl_content->addvar("pager", htm_browse_extended((int)$searchParameters["RESULT_COUNT"], $npage, "my-sales,".$action.",{PAGE}", $perpage, 5, $searchParametersHttp));
$tpl_content->addlist("liste", $arSalesUser, "tpl/".$s_lang."/my-sales.row.htm");

if (empty($arSalesUser) && !$searchActive) {
    $provPercent = 0;
    $idUsergroup = (int)$user["FK_USERGROUP"];
    if ($idUsergroup > 0) {
        $provPercent = (float)$db->fetch_atom("SELECT SALES_PROV FROM `usergroup` WHERE ID_USERGROUP=".$idUsergroup);
    }
    // Load intro template
    $tpl_content->LoadText("tpl/".$s_lang."/my-sales.intro.htm");
    $tpl_content->addvar("PROVISION_PERCENT", $provPercent);
}

require_once $ab_path."sys/packet_management.php";
$packets = PacketManagement::getInstance($db);
$ar_packets = $packets->getList(1, 100, $all, array("TYPE='MEMBERSHIP'"), array("F_ORDER ASC", "TYPE ASC", "V1 ASC"));
$tpl_content->addlist("options_membership", $ar_packets, "tpl/".$s_lang."/my-sales.row_membership.htm");

?>