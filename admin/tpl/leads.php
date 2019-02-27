<?php

/** @var Api_Plugins_Leads_Plugin $pluginLeads */
$pluginLeads = Api_TraderApiHandler::getInstance()->getPlugin("Leads");

$page = (array_key_exists("npage", $_REQUEST) ? (int)$_REQUEST["npage"] : 1);
$limit = 20;

$params = array();
$url = "index.php?page=leads";

if (array_key_exists("id", $_REQUEST) && !empty($_REQUEST["id"])) {
    $params["id"] = (int)$_REQUEST["id"];
    $url .= "&id=".urlencode($_REQUEST["id"]);
}
if (array_key_exists("moderated", $_REQUEST) && ($_REQUEST["moderated"] != "")) {
    if ($_REQUEST["moderated"] == 2) {
        $_REQUEST["moderated"] = 0;
        $params["declined"] = 1;
    } else {
        $params["declined"] = 0;
    }
    $params["moderated"] = (int)$_REQUEST["moderated"];
    $url .= "&moderated=".urlencode($_REQUEST["moderated"]);
    $tpl_content->addvar("search_moderated_".$params["moderated"], 1);
}
if (array_key_exists("user", $_REQUEST) && !empty($_REQUEST["user"])) {
    if (preg_match("/^[0-9]+$/", $_REQUEST["user"])) {
        $userLead = $pluginLeads->getUserById((int)$_REQUEST["user"]);
        $params["user_id"] = $userLead->id;
    } else {
        $userLead = $pluginLeads->getUserByName($_REQUEST["user"]);
        $params["user_id"] = $userLead->id;
    }
    $url .= "&user=".urlencode($_REQUEST["user"]);
    $tpl_content->addvar("search_user", $_REQUEST["user"]);
}

$tpl_content->addvars($params, "search_");

if (!empty($_REQUEST["action"])) {
    $leadId = (int)$_REQUEST["actionId"];
    $lead = $pluginLeads->getLead($leadId);
    $notes = (array_key_exists("NOTES", $_POST) ? $_POST["NOTES"] : null);
    $declineReason = (array_key_exists("REASON", $_POST) ? $_POST["REASON"] : null);
    $lead->setAdminNotes($notes);
    switch ($_REQUEST["action"]) {
        case "confirm":
            $lead->setModerated(true, false, $declineReason);
            die(forward($url."&npage=".$page."&done=confirm"));
        case "unconfirm":
            $lead->setModerated(false, true, $declineReason);
            die(forward($url."&npage=".$page."&done=unconfirm"));
    }
}

$countLeads = $pluginLeads->queryLeadsCount($params);
$listLeads = $pluginLeads->getLeads($params, $limit, $page);
$listLeadsArray = array();
/**
 * @var int $leadIndex
 * @var \Plugins\Hydromot\Lead $lead
 */
foreach ($listLeads as $leadIndex => $lead) {
    $listLeadsArray[$leadIndex] = array_flatten($lead->toArray(), "both");
    $listLeadsArray[$leadIndex]["preview"] = view("Hydromot::snippets/lead_info", [ "lead" => $lead, "isAdmin" => true ])->render();
}

#dd($countLeads, $listLeadsArray);

$tpl_content->addlist("liste", $listLeadsArray, "tpl/de/leads.row.htm");
$tpl_content->addvar("pager", htm_browse($countLeads, $page, $url."&npage=", $limit));
$tpl_content->addvar("searchUrl", $url."&npage=".$page);

if (!empty($_REQUEST["done"])) {
    $tpl_content->addvar("done", 1);
    $tpl_content->addvar("done_".$_REQUEST["done"], 1);
}