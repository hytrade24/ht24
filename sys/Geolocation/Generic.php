<?php
/**
 * Created by PhpStorm.
 * User: jens
 * Date: 03.08.15
 * Time: 11:35
 */

class Geolocation_Generic {

    public static function getGeolocationQuery($street = null, $zip = null, $city = null, $country = null) {
        $queryParts = array();
        if ($street !== null) {
            $queryParts[] = $street;
        }
        $cityFull = null;
        if ($zip !== null) {
            $cityFull = $zip;
        }
        if ($city !== null) {
            $cityFull = ($cityFull === null ? $city : $cityFull." ".$city);
        }
        if ($cityFull !== null) {
            $queryParts[] = $cityFull;
        }
        if ($country !== null) {
            $queryParts[] = $country;
        }
        if (empty($queryParts)) {
            return false;
        }
        return implode(", ", $queryParts);
    }
    
    public static function getGeolocationReverse($latitude, $longitude, $language = null) {
        if ($language === null) {
            $language = $GLOBALS['s_lang'];
        }
        // Query google maps
        $result = Geolocation_GoogleMaps::getGeolocationReverse($latitude, $longitude, $language);
        if (($result !== false) && ($result !== null)) {
            return $result;
        }
        // Query open street map
        $result = Geolocation_OpenStreetMap::getGeolocationReverse($latitude, $longitude, $language);
        if (($result !== false) && ($result !== null)) {
            return $result;
        }
        return $result;
    }
        
    public static function getGeolocation($street = null, $zip = null, $city = null, $country = null, $language = null) {
        if ($language === null) {
            $language = $GLOBALS['s_lang'];
        }
        if (empty($street) && empty($zip) && empty($city) && empty($country)) {
            return false;
        }
        // Query google maps
        $result = Geolocation_GoogleMaps::getGeolocation($street, $zip, $city, $country, $language);
        if (($result !== false) && ($result !== null)) {
            return $result;
        }
        // Query open street map
        $result = Geolocation_OpenStreetMap::getGeolocation($street, $zip, $city, $country, $language);
        if (($result !== false) && ($result !== null)) {
            return $result;
        }
        return $result;
    }

    public static function getGeolocationCached($street = null, $zip = null, $city = null, $country = null, $language = null) {
        if ($language === null) {
            $language = $GLOBALS['s_lang'];
        }
        if (empty($street) && empty($zip) && empty($city) && empty($country)) {
            return false;
        }
        $query = self::getGeolocationQuery($street, $zip, $city, $country);
        if ($query === false) {
            return false;
        }
        $hash = md5($language."|".$query);
        $arGeolocation = $GLOBALS['db']->fetch1("SELECT * FROM `geolocation` WHERE HASH='".mysql_real_escape_string($hash)."'");
        $refreshGeolocation = false;
        if (is_array($arGeolocation)) {
            if (($arGeolocation["FK_GEO_REGION"] === null) && $GLOBALS['nar_systemsettings']['SYS']['MAP_REGIONS']) {
                $refreshGeolocation = true;
            }
        }
        if (!is_array($arGeolocation) || $refreshGeolocation) {
            $arGeolocation = self::getGeolocation($street, $zip, $city, $country, $language);
        }
        return $arGeolocation;
    }
    
    public static function getGeolocationRegions($idGeoRegion, ebiz_db $db = null) {
        if ($db === null) {
            $db = $GLOBALS["db"];
        }
        if ($idGeoRegion === null) {
            return null;
        }
        $serPathIds = $db->fetch_atom("SELECT SER_PATH FROM `geo_region` WHERE ID_GEO_REGION=".(int)$idGeoRegion);
        if (($serPathIds === null) || ($serPathIds === false)) {
            return null;
        }
        $arPathIds = unserialize($serPathIds);
        if ($arPathIds === false) {
            return null;
        }
        $arPath = $db->fetch_table("SELECT * FROM `geo_region` WHERE ID_GEO_REGION IN (".implode(", ", $arPathIds).")");
        if (!is_array($arPath)) {
            return null;
        }
        return $arPath;
    }
    
}