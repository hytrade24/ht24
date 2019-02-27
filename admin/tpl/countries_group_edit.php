<?php

/* ###VERSIONSBLOCKINLCUDE### */

$countryGroups = Api_CountryGroupManagement::getInstance($db);

/*
$countryGroups->debug();
die();
*/

$nodeId = ($_REQUEST["id"] > 0 ? (int)$_REQUEST["id"] : null);
$parentId = ($_REQUEST["parent"] > 0 ? (int)$_REQUEST["parent"] : null);

if ($nodeId !== null) {
  $arNode = $countryGroups->fetchById($nodeId);
  $parentId = $arNode["FK_PARENT"];
  $tpl_content->addvars($arNode);
  $tpl_content->addvar("HAS_CHILDS", ($arNode["NS_RIGHT"] - $arNode["NS_LEFT"] > 1 ? 1 : 0));
}
if ($parentId !== null) {
  $arParent = $countryGroups->fetchById($parentId);
  $tpl_content->addvar("FK_PARENT", $parentId);
  $tpl_content->addvar("PARENT_NAME", $arParent["V1"]);
}

$arCountryList = $countryGroups->fetchCountryList($nodeId);
$tpl_content->addlist("liste", $arCountryList, "tpl/de/countries_group_edit.row.htm");

if (!empty($_POST)) {
  if (array_key_exists("ID_COUNTRY_GROUP", $_POST)) {
    // Update existing
	  $countries = array();
	  $force = false;
	  if ( isset($_POST["COUNTRIES"]) ) {
		  $countries = $_POST["COUNTRIES"];
	  }
	  else {
		  $force = true;
	  }

    $result = $countryGroups->update(
    	$_POST["ID_COUNTRY_GROUP"],
	    $_POST["V1"],
	    $countries,
	    $force
    );
    die(forward("index.php?page=countries_group&done=update"));
  } else {
    $idNode = $countryGroups->create($_POST["V1"], $_POST["FK_PARENT"], $_POST["COUNTRIES"]);
    die(forward("index.php?page=countries_group&done=create"));
  }
}
  
?>