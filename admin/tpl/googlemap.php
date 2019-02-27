<?php

$arMarkers = array();

if (array_key_exists("show", $_REQUEST)) {
  switch ($_REQUEST["show"]) {
    case "calendar_event":
      $searchParameter = $_POST["SEARCH"];
      // Pagination
      if (isset($_POST['search_'])) {
        // New search, reset page
        unset($_POST["npage"]);
      } else {
        $npage = (int)$_POST["npage"];
        $searchParameter['OFFSET'] = $perpage * ($npage-1);
      }
      $searchParameter['LIMIT'] = $perpage;
      // Sort order
      list($searchParameter["SORT_BY"], $searchParameter["SORT_DIR"]) = explode(",", $searchParameter["SORT"]);
      // User search
      if ($_POST["FK_USER"] > 0) {
        $searchParameter["FK_USER"] = (int)$_POST["FK_USER"];
        $searchParameter["NAME_"] = $db->fetch_atom("
          SELECT NAME FROM `user`
            WHERE ID_USER=".(int)$_POST["FK_USER"]);
      } else if (!empty($_POST["NAME_"])) {
        $searchParameter["FK_USER"] = array_keys($db->fetch_nar("
          SELECT ID_USER, NAME FROM `user`
            WHERE NAME LIKE '%".mysql_real_escape_string($_POST["NAME_"])."%'"));
        $searchParameter["NAME_"] = $_POST["NAME_"];
      }
      // Date range (from)
      if (preg_match("/([0-3][0-9])\.([0-1][0-9])\.([0-9]{2,4})/", $searchParameter["STAMP_START_GT"], $matches)) {
        $searchParameter["STAMP_START_GT"] = $matches[3]."-".$matches[2]."-".$matches[1];
      } else {
        unset($searchParameter["STAMP_START_GT"]);
      }
      // Date range (from)
      if (preg_match("/([0-3][0-9])\.([0-1][0-9])\.([0-9]{2,4})/", $searchParameter["STAMP_START_LT"], $matches)) {
        $searchParameter["STAMP_START_LT"] = $matches[3]."-".$matches[2]."-".$matches[1];
      } else {
        unset($searchParameter["STAMP_START_LT"]);
      }
      // Read events from db
      require_once $ab_path."sys/lib.calendar_event.php";
      $calendarEventManagement = CalendarEventManagement::getInstance($db);
      $eventliste = $calendarEventManagement->fetchAllByParam($searchParameter, $all);
      foreach ($eventliste as $eventIndex => $eventDetails) {
        $arMarkers[] = array(
          "URL"         => "index.php?page=veranstaltung_edit&id=".$eventDetails["ID_CALENDAR_EVENT"],
          "NAME"        => $eventDetails["TITLE"]." (#".$eventDetails["ID_CALENDAR_EVENT"].")",
          "STREET"      => $eventDetails["STREET"],
          "ZIP"         => $eventDetails["ZIP"],
          "CITY"        => $eventDetails["CITY"],
          "FK_COUNTRY"  => $eventDetails["FK_COUNTRY"],
          "LATITUDE"    => $eventDetails["LATITUDE"],
          "LONGITUDE"   => $eventDetails["LONGITUDE"]
        );
      }
      break;
  }
}

if (array_key_exists("ID_USER", $_REQUEST)) {
  $user = $db->fetch1("SELECT * FROM `user` WHERE ID_USER=".(int)$_REQUEST["ID_USER"]);
  $arMarkers[] = array(
    "NAME"        => $user["NAME"]." (#".$user["ID_USER"].")",
    "STREET"      => $user["STRASSE"],
    "ZIP"         => $user["PLZ"],
    "CITY"        => $user["ORT"],
    "FK_COUNTRY"  => $user["FK_COUNTRY"],
    "LATITUDE"    => null,
    "LONGITUDE"   => null
  );
}

if (array_key_exists("ID_VENDOR", $_REQUEST)) {
  $vendor = $db->fetch1("SELECT * FROM `vendor` WHERE ID_VENDOR=".(int)$_REQUEST["ID_VENDOR"]);
  $arMarkers[] = array(
    "NAME"        => $vendor["NAME"]." (#".$user["ID_USER"].")",
    "STREET"      => $vendor["STRASSE"],
    "ZIP"         => $vendor["PLZ"],
    "CITY"        => $vendor["ORT"],
    "FK_COUNTRY"  => $vendor["FK_COUNTRY"],
    "LATITUDE"    => $vendor["LATITUDE"],
    "LONGITUDE"   => $vendor["LONGITUDE"]
  );
}

foreach ($arMarkers as $markerIndex => $markerDetails) {
  // Resolve country
  $addressCountryStr = Api_StringManagement::getInstance($db)->readById("country", $markerDetails["FK_COUNTRY"]);
  $arMarkers[$markerIndex]["COUNTRY"] = $addressCountryStr["V1"];
  // Resolve location
  if (($markerDetails["LATITUDE"] === null) || ($markerDetails["LONGITUDE"] === null)) {
    $arLocation = Geolocation_Generic::getGeolocationCached($markerDetails["STREET"], $markerDetails["ZIP"], $markerDetails["CITY"], $markerDetails["COUNTRY"]);
    $arMarkers[$markerIndex]["LATITUDE"] = (float)$arLocation["LATITUDE"];
    $arMarkers[$markerIndex]["LONGITUDE"] = (float)$arLocation["LONGITUDE"];
  }
}

if (!empty($GLOBALS["nar_systemsettings"]["SYS"]["MAP_API"])) {
  $tpl_content->addvar("API_KEY", $GLOBALS["nar_systemsettings"]["SYS"]["MAP_API"]);
}

$tpl_content->addvar("JSON_MARKERS", json_encode($arMarkers));

/*
$tpl_content->addvar("STREET", $addressStreet);
$tpl_content->addvar("ZIP", $addressZip);
$tpl_content->addvar("CITY", $addressCity);
$tpl_content->addvar("COUNTRY", $addressCountry);

$tpl_content->addvar("LATITUDE", $addressLatitude);
$tpl_content->addvar("LONGITUDE", $addressLongitude);
*/