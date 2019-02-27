<?php
/* ###VERSIONSBLOCKINLCUDE### */

if (array_key_exists("reverse", $_REQUEST)) {
    header("Content-Type: application/json");
    die(json_encode(
        Geolocation_Generic::getGeolocationReverse($_REQUEST["lat"], $_REQUEST["lng"])
    ));
}

$street = $_POST['STREET'];
$zip = $_POST['ZIP'];
$city = $_POST['CITY'];
$country = $_POST['COUNTRY'];

/*
 * @TODO Referer Check
 */
$referer = $_SERVER['HTTP_REFERER'];
$mapsLanguage = $s_lang;

$geoCoordinates = Geolocation_Generic::getGeolocationCached($street, $zip, $city, $country, $mapsLanguage);

header("Content-Type: application/json");
if (($geoCoordinates !== false) && ($geoCoordinates !== null)) {
    echo json_encode(array("success" => true, 'result' => $geoCoordinates));
} else {
    echo json_encode(array("success" => false));
}
die();