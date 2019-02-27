<?php


$perpage = 20;
$npage = ($_REQUEST["npage"] > 0 ? (int)$_REQUEST["npage"] : 1);
$id_user = (int)$_REQUEST["ID_USER"];
$searchParameters = array("LIMIT_COUNT" => $perpage, "LIMIT_OFFSET" => ($npage-1)*$perpage);

require_once $ab_path."sys/lib.sales.php";
$salesManagment = SalesManagement::getInstance();

if (isset($_POST["SUBMIT_CHECK_ALIGN"])) {
    $arUsers = array_keys($_POST["CHECK"]);
    $id_user_sale = (int)$_POST["FK_AUTOR"];
    if ($salesManagment->updateSalesUser($arUsers, $id_user_sale)) {
        // Success
        die(forward("index.php?page=".$tpl_content->vars['curpage']."&ID_USER=".$id_user."&done=assign"));
    } else {
        // Failed
        die(forward("index.php?page=".$tpl_content->vars['curpage']."&ID_USER=".$id_user."&fail=assign"));
    }
}
if (isset($_POST["SUBMIT_CHECK_ALIGN_CLEAR"])) {
    $arUsers = array_keys($_POST["CHECK"]);
    if ($salesManagment->updateSalesUser($arUsers, null)) {
        // Success
        die(forward("index.php?page=".$tpl_content->vars['curpage']."&ID_USER=".$id_user."&done=assign"));
    } else {
        // Failed
        die(forward("index.php?page=".$tpl_content->vars['curpage']."&ID_USER=".$id_user."&fail=assign"));
    }
}

if (!empty($_REQUEST["done"])) {
    switch ($_REQUEST["done"]) {
        case 'assign':
            $tpl_content->addvar("msg", "Die Zuordnung der gewählten Kunden wurde erfolgreich geändert.");
            break;
    }
} elseif (!empty($_REQUEST["fail"])) {
    switch ($_REQUEST["done"]) {
        case 'assign':
            $tpl_content->addvar("err", "Fehler beim Ändern der Zuordnung für die gewählten Kunden.");
            break;
    }
}
if (is_array($_REQUEST["SEARCH"])) {
    $searchParameters = array_merge($searchParameters, $_REQUEST["SEARCH"]);
    $searchParametersTpl = $searchParameters;
    foreach ($searchParametersTpl as $param => $value) {
        $searchParametersTpl[$param."_".$value] = 1;
    }
    $tpl_content->addvars($searchParametersTpl, "SEARCH_");
    $tpl_content->addvar("SEARCH_SORT_".str_replace("+", "_", $searchParameters["SORT"]), 1);

    $searchParametersHttp = "&ID_USER=".$id_user."&".http_build_query(array("SEARCH" => $_REQUEST["SEARCH"]));
} else {
    $searchParametersHttp = "&ID_USER=".$id_user;
}

$liste = $salesManagment->getUsersBySaleUser($id_user, $searchParameters);
$tpl_content->addvar("pager", htm_browse((int)$searchParameters["RESULT_COUNT"], $npage, "index.php?page=".$tpl_content->vars['curpage'].$searchParametersHttp."&npage=", $perpage));
$tpl_content->addlist("liste", $liste, "tpl/".$s_lang."/user_billing_sales.row.htm");
$tpl_content->addvars($db->fetch1("SELECT ID_USER, CACHE, NAME FROM `user` WHERE ID_USER=".$id_user));