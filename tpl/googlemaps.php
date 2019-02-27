<?php

include_once $ab_path . 'sys/lib.map.php';

if (isset($tpl_content->vars['type']) && isset($tpl_content->vars['ident'])) {
    $type = $tpl_content->vars['type'];
    $ident = $tpl_content->vars['ident'];
}
elseif (!isset($tpl_content->vars['type']) && isset($tpl_content->vars['ident'])) {
    $type = null;
    $ident = 'all';
}
else {
    return false;
}

$googleMaps = GoogleMaps::getInstance();

// set options for map
$googleMaps->setMapOptions(array());

// Add Markers to map
$googleMaps->addMarkerList($type, $ident);

// generate Map (intern)
$googleMaps->generateMap();

$tpl_content->addvar('MARKER_INCLUDE_LIST', $googleMaps->getMarkerIncludeList());
$tpl_content->addvar('MAP', $googleMaps->getMap());
$tpl_content->addvar('CURRENCY_DEFAULT', $nar_systemsettings['MARKTPLATZ']['CURRENCY']);
$tpl_content->addlist('MARKERS', $googleMaps->getMarkers(), $ab_path . 'tpl/de/googlemaps.markers.htm');
