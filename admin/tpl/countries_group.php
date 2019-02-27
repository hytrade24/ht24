<?php

/* ###VERSIONSBLOCKINLCUDE### */

$countryGroups = Api_CountryGroupManagement::getInstance($db);

if (array_key_exists("do", $_REQUEST)) {
  switch ($_REQUEST["do"]) {
    case "delete":
      $countryGroups->delete($_REQUEST["id"]);
      die(forward("index.php?page=countries_group&done=delete"));
  }
}

if (array_key_exists("done", $_REQUEST)) {
  $tpl_content->addvar("done_".$_REQUEST["done"], 1);
}

$arTree = $countryGroups->fetchTree();
foreach ($arTree as $nodeIndex => $nodeDetails) {
  $arTree[$nodeIndex]["COUNTRY_LIST"] = $countryGroups->fetchCountryListAssignedAsText($nodeDetails["ID_COUNTRY_GROUP"]);
}


$tpl_content->addlist("liste", $arTree, "tpl/de/countries_group.row.htm");
  
?>