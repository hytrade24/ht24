<?php

require_once dirname(__FILE__)."/../sys/lib.datatable.php";

$marketplaceHost = rtrim(str_replace(array("http://www.", "http://"), "", $GLOBALS["nar_systemsettings"]['SITE']['SITEURL']), "/");

$dataTable = new Api_DataTable($db, "vendor_homepage", "h");
// Define joins
$dataTable->addTableJoin("user", "u", "LEFT JOIN", "u.ID_USER=h.FK_USER");
// Define fields
$dataTable->addField(NULL, NULL, "COUNT(*)", "RESULT_COUNT", true);
$dataTable->addField("h", "ID_VENDOR_HOMEPAGE", NULL, "ID_VENDOR_HOMEPAGE", true);
$dataTable->addField("h", "FK_USER", NULL, "FK_USER", true);
$dataTable->addField("h", "ACTIVE", NULL, "ACTIVE", true);
$dataTable->addField("h", "STAMP_START", NULL, "STAMP_START", true);
$dataTable->addField("h", "STAMP_END", NULL, "STAMP_END", true);
$dataTable->addField("h", "DOMAIN_SUB", NULL, "DOMAIN_SUB");
$dataTable->addField("h", "DOMAIN_FULL", NULL, "DOMAIN_FULL");
$dataTable->addField("h", NULL, "IFNULL(CONCAT(h.DOMAIN_SUB,'.".mysql_real_escape_string($marketplaceHost)."'),h.DOMAIN_FULL)", "DOMAIN_ANY", true);
$dataTable->addField("h", "SER_DETAILS", NULL, "SER_DETAILS");
$dataTable->addField(NULL, NULL, "(SELECT COUNT(*) FROM ad_master a WHERE a.FK_USER = h.FK_USER AND STATUS IN (1,3,5,7,9,11,13,15) AND DELETED=0)", "USER_AD_COUNT");
$dataTable->addField("u", "NAME", NULL, "USER_NAME");
// Define special fields
$dataTable->addFieldSpecial("ACTIONS", array("USER_NAME", "FK_USER"), "USER_NAME", "vendor-homepages.action.htm");
$dataTable->addFieldSpecial("DOMAIN_SPECIAL", array("DOMAIN_ANY"), "DOMAIN_ANY", "vendor-homepage.col.domain.htm");
$dataTable->addFieldSpecial("ACTIVE_SPECIAL", array("ACTIVE"), "ACTIVE", "vendor-homepage.col.active.htm");
$dataTable->addFieldSpecial("USER_SPECIAL", array("USER_NAME", "FK_USER"), "USER_NAME", "data_table.col.user.htm");
// Define conditions
$dataTable->addWhereCondition("ACTIVE", "h.ACTIVE=$1$");
$dataTable->addWhereCondition("TOP", "IF(a.B_TOP>0, 1, 0)=$1$");
$dataTable->addWhereCondition("USER_NAME", "u.NAME LIKE '%$1$%'", array("u"));
$dataTable->addWhereCondition("DOMAIN_ANY", "(h.DOMAIN_SUB LIKE '%$1$%' OR h.DOMAIN_FULL LIKE '%$1$%')");
$dataTable->addWhereCondition("DOMAIN_TYPE", array(
    "SUBDOMAIN"     => "h.DOMAIN_SUB IS NOT NULL",
    "FULLDOMAIN"    => "h.DOMAIN_FULL IS NOT NULL"
));
$dataTable->addWhereCondition("DOMAIN_NAME", "(h.DOMAIN_SUB LIKE '%$1$%' OR h.DOMAIN_FULL LIKE '%$1$%')");

if (array_key_exists("ajax", $_REQUEST) && ($_REQUEST["ajax"] == "dataTable")) {
    $jsonResult = array("success" => false, "error" => "Unknown ajax action!");
    switch ($_POST["action"]) {
        case "getResults":
            $options = json_decode($_POST["queryOptions"], true);
            $calcFoundRows = (array_key_exists("calcFoundRows", $_POST) ? $_POST["calcFoundRows"] : false);
            if (is_array($options)) {
                // Build query
                $dataTableQuery = $dataTable->createQuery();
                $dataTableQuery->addFields($options["fields"]);
                $dataTableQuery->addWhereConditions($options["where"]);
                $dataTableQuery->addGroupFields($options["group"]);
                $dataTableQuery->addHavingConditions($options["having"]);
                $dataTableQuery->addSortFields($options["sorting"]);
                $dataTableQuery->setLimit($options["limit"], $options["offset"]);
                $resultCount = ($calcFoundRows ? 0 : NULL);
                $resultBody = AdminDataTable::renderDataTableBody($dataTableQuery, $resultCount);
                $jsonResult = array("success" => true, "body" => $resultBody, "count" => $resultCount);
            } else {
                $jsonResult["error"] = "Invalid / missing query options!";
            }
            break;
        case "getSelectKeys":
            $options = json_decode($_POST["queryOptions"], true);
            $calcFoundRows = (array_key_exists("calcFoundRows", $_POST) ? $_POST["calcFoundRows"] : false);
            if (is_array($options)) {
                $arSelectKeyFields = $dataTable->getSelectKeys();
                // Build query
                $dataTableQuery = $dataTable->createQuery();
                foreach ($arSelectKeyFields as $keyIndex => $keyName) {
                    $dataTableQuery->addField($keyName);
                }
                $dataTableQuery->addWhereConditions($options["where"]);
                $dataTableQuery->addGroupFields($options["group"]);
                $dataTableQuery->addHavingConditions($options["having"]);
                $dataTableQuery->addSortFields($options["sorting"]);
                $jsonResult = array("success" => true, "keys" => (count($arSelectKeyFields) == 1 ? array_keys($dataTableQuery->fetchNar()) : $dataTableQuery->fetchTable(MYSQL_NUM)));
            } else {
                $jsonResult["error"] = "Invalid / missing query options!";
            }
            break;
        case "executeAction":
            $arTargets = json_decode($_POST["targetKeys"]);
            if (is_array($arTargets)) {
                switch ($_POST["targetAction"]) {
                    case "confirm":
                        Api_VendorHomepageManagement::getInstance($db)->batchSetStatus($arTargets, Api_Entities_VendorHomepage::STATUS_ACTIVE);
                        break;
                    case "decline":
                        $arTargetsObj = Api_VendorHomepageManagement::getInstance($db)->fetchAllAsObject(array("ID_VENDOR_HOMEPAGE" => $arTargets));
                        /**
                         * @var Api_Entities_VendorHomepage $targetHomepage
                         */
                        foreach ($arTargetsObj as $targetIndex => $targetHomepage) {
                            $targetHomepage->setActive(Api_Entities_VendorHomepage::STATUS_DECLINED);
                            $targetHomepage->setDetails(array_merge($targetHomepage->getDetails(), array(
                                "DECLINE_REASON"    => $_POST["targetParameters"]
                            )));
                            if (!$targetHomepage->updateDatabase()) {
                                $jsonResult = array("success" => false, "error" => "Failed to update dataset!");
                                break 2;
                            } else {
                                // Inform user
                                require_once $ab_path.'sys/lib.user.php';
                                $userManagement = UserManagement::getInstance($db);
                                $recipientUser = $userManagement->fetchById($targetHomepage->getUser());
                                $arMailParams = array_merge(array_flatten($recipientUser, true, "_", "USER_"), array(
                                    "REASON" => $_POST["targetParameters"]
                                ));
                                sendMailTemplateToUser(0, $targetHomepage->getUser(), "VENDOR_HOMEPAGE_DECLINED", $arMailParams);
                            }
                        }
                        // Api_VendorHomepageManagement::getInstance($db)->batchSetStatus($arTargets, Api_Entities_VendorHomepage::STATUS_DECLINED);
                        break;
                    case "delete":
                        Api_VendorHomepageManagement::getInstance($db)->batchDelete($arTargets);
                        break;
                    default:
                        // Do not rewrite cache for unknown actions
                        break 2;
                }
                Api_TraderApiHandler::getInstance()->triggerEvent("VENDOR_HOMEPAGE_PLUGIN_CACHE");
                $jsonResult = array("success" => true);
            }
            break;
    }
    header("Content-Type: application/json");
    die(json_encode($jsonResult));
}

// Build query
$dataTableQuery = $dataTable->createQuery();
$dataTableQuery->addFields(array(
    "ACTIONS"               => "Aktionen",
    "ID_VENDOR_HOMEPAGE"    => "Id",
    "USER_SPECIAL"          => "Benutzer",
    "DOMAIN_SPECIAL"        => "(Sub-)Domain",
    "ACTIVE_SPECIAL"        => "Status",
    "STAMP_START"           => "Eingetragen seit",
    "STAMP_END"             => "Eingetragen bis"
));
if (array_key_exists("ACTIVE", $_REQUEST)) {
    $dataTableQuery->addWhereCondition("ACTIVE", (int)$_REQUEST["ACTIVE"]);
}
$dataTableQuery->addSortField("ID_VENDOR_HOMEPAGE", "DESC");
$dataTableQuery->setLimit(20);

$tpl_content->addvar("dataTableHash", $dataTable->getHash());
$tpl_content->addvar("dataTable", AdminDataTable::renderDataTable(
    $dataTableQuery,                                                        // Query
    "Anzeigen-Liste",                                                       // Default title (translatable)
    false,                                                                   // Action template
    new Template("tpl/".$s_lang."/vendor-homepages.filter.htm"),            // Filter template
    "index.php?lang=".$s_lang."&page=vendor-homepages&ajax=dataTable"       // Ajax URL
));


// Breite für linkes menü
$tpl_main->addvar("size_left", 240);