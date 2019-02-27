<?php
/* ###VERSIONSBLOCKINLCUDE### */

$perpage = 25; // Elemente pro Seite
$page = ((int)$_REQUEST['npage'] ? (int)$_REQUEST['npage'] : 1);

require_once $ab_path."sys/lib.club.php";
$clubManagement = ClubManagement::getInstance($db);

$isSearch = false;
$parameters = array();
$order = array();
$order_allowed = array("STAMP", "COMMENTS", "MEMBERS");
$order_allowed_dir = array("ASC", "DESC");

if (!empty($_POST)) {
    // Search parameters
    if (!empty($_REQUEST["STATUS"])) {
        $parameters["STATUS"] = $_REQUEST["STATUS"];
        $tpl_content->addvar("SEARCH_STATUS_".$parameters["STATUS"], 1);
    }
    if (!empty($_REQUEST["SEARCHCLUB"])) {
        $parameters["SEARCHCLUB"] = $_REQUEST["SEARCHCLUB"];
    }
    if (!empty($_REQUEST["SEARCHCLUB_WHAT"]) && is_array($_REQUEST["SEARCHCLUB_WHAT"])) {
        $parameters["SEARCHCLUB_WHAT"] = $_REQUEST["SEARCHCLUB_WHAT"];
    }
    if (!empty($_REQUEST["FK_USER"])) {
        $parameters["FK_USER"] = (int)$_REQUEST["FK_USER"];
        $parameters["NAME_"] = $db->fetch_atom("SELECT NAME FROM `user` WHERE ID_USER=".(int)$_REQUEST['FK_USER']);
    } else if (!empty($_REQUEST["NAME_"])) {
        $parameters["NAME_"] = $_REQUEST["NAME_"];
    }
    if (!empty($_REQUEST["FK_USER_STATUS"])) {
        $parameters["FK_USER_STATUS"] = $_REQUEST["FK_USER_STATUS"];
    }
    // Sort order
    if (!empty($_REQUEST['SORT'])) {
        list($field, $direction) = explode(",", $_REQUEST['SORT']);
        $field = trim($field);
        $direction = trim($direction);
        if (!empty($field) && !empty($direction)) {
            $parameters["SORT"] = $field;
            $parameters["SORT_DIR"] = $direction;
        }
    }
    $_SESSION["SEARCH_CLUB"] = $parameters;
    $isSearch = true;
} elseif (($_REQUEST["search"] == 1) && (isset($_SESSION["SEARCH_CLUB"]))) {
    // Reuse previous search parameters
    $parameters = $_SESSION["SEARCH_CLUB"];
    $isSearch = true;
} else {
    // Forget previous search parameters
    unset($_SESSION["SEARCH_CLUB"]);
    // Select defaults
    $parameters["STATUS"] = "ALL";
    $tpl_content->addvar("SEARCH_STATUS_ALL", 1);
    $tpl_content->addvar("SEARCH_SEARCHCLUB_DEFAULT", 1);
}

if (!empty($parameters["FK_USER_STATUS"])) {
    $tpl_content->addvar("SEARCH_FK_USER_STATUS_".$parameters["FK_USER_STATUS"], 1);
}
if (!empty($parameters["SORT"])) {
    $tpl_content->addvar("SORT_".$parameters["SORT"]."_".$parameters["SORT_DIR"], 1);
}
if (is_array($parameters["SEARCHCLUB_WHAT"])) {
    foreach ($parameters["SEARCHCLUB_WHAT"] as $index => $what) {
        $tpl_content->addvar("SEARCH_SEARCHCLUB_WHAT_".$what, 1);
    }
}

$parameters["OFFSET"] = ($page-1) * $perpage;
$parameters["LIMIT"] = $perpage;

$all = $clubManagement->countClubsByParam($parameters, $langval);
$ar_clubs = $clubManagement->getClubsByParams($parameters, $langval);

$pagerUrl = "index.php?page=".$tpl_content->vars['curpage'].($isSearch ? "&search=1" : "")."&npage=";
$pager = htm_browse($all, $page, $pagerUrl, $perpage);

if ($isSearch) {
	$tpl_content->addvar("SEARCH_RESULT", 1);
}

$tpl_content->addvars($parameters, "SEARCH_");
$tpl_content->addlist('liste', $ar_clubs, 'tpl/de/clubs.row.htm');
$tpl_content->addvar('pager', $pager);

?>