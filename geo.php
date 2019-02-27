<?php
/* ###VERSIONSBLOCKINLCUDE### */

 
/**
 * @deprecated
 */

$url = 'http://maps.google.com/maps/geo?q='.urlencode($_REQUEST['q']).'&output=json&key='.urlencode($_REQUEST['key']);
$response = file_get_contents($url);
header('Content-Type:application/json');
die($response);

?>