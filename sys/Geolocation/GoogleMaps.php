<?php
/**
 * Created by PhpStorm.
 * User: jens
 * Date: 27.07.15
 * Time: 17:23
 */

class Geolocation_GoogleMaps {

    static $defaultTypeHierarchy = array(
        "country", "administrative_area_level_1", "administrative_area_level_2", "administrative_area_level_3", "administrative_area_level_4", "administrative_area_level_5",
        "locality", "neighborhood", "sublocality_level_1", "sublocality_level_2", "sublocality_level_3", "sublocality_level_4", "sublocality_level_5"
    );
    
    static $googleApiKey = false;
    static $googleApiKeyJavascript = false;

    public static function setApiKey($key) {
        self::$googleApiKey = $key;
    }
    
    public static function setApiKeyJavascript($key) {
        self::$googleApiKeyJavascript = $key;
    }
        
    public static function getGeolocation($street = null, $zip = null, $city = null, $country = null, $language = null) {
        if ($language === null) {
            $language = $GLOBALS['s_lang'];
        }
        $query = Geolocation_Generic::getGeolocationQuery($street, $zip, $city, $country);
        if ($query === false) {
            return false;
        }
        $url = 'https://maps.googleapis.com/maps/api/geocode/json?sensor=false&language='.urlencode($language).'&address='.urlencode($query);
        if (self::$googleApiKey !== false) {
            $url .= "&key=".urlencode(self::$googleApiKey);
        }
        $response = file_get_contents($url);
        $hash = md5($language."|".$query);
        $arGeoResponse = json_decode($response, true);
        if (is_array($arGeoResponse) && array_key_exists("status", $arGeoResponse) && $arGeoResponse["status"] == "OK") {
            $idGeoRegion = null;
            if ($GLOBALS['nar_systemsettings']['SYS']['MAP_REGIONS']) {
                $idGeoRegion = self::writeGeolocationRegions($arGeoResponse["results"][0]);
            }
            $arGeoLocation = array(
                'STAMP_DATE'                    => date("Y-m-d H:i:s"),
                'HASH'                          => $hash,
                'FK_GEO_REGION'                 => $idGeoRegion,
                'STREET'                        => $street,
                'ZIP'                           => $zip,
                'CITY'                          => $city,
                'ADMINISTRATIVE_AREA_LEVEL_1'   => '',
                'COUNTRY'                       => $country,
                'LATITUDE'                      => 0,
                'LONGITUDE'                     => 0
            );
            $idGeoLocation = self::writeGeolocation($arGeoLocation, $arGeoResponse["results"][0], $idGeoRegion);
            return $arGeoLocation;
        } else {
            if ($arGeoResponse["status"] == "ZERO_RESULTS") {
                return null;
            } else {
                eventlog('error', 'GeoLocation lookup failed', var_export($arGeoResponse, true));
                return false;
            }
        }
    }
    
    public static function getGeolocationReverse($latitude, $longitude, $language = null) {
        if ($language === null) {
            $language = $GLOBALS['s_lang'];
        }
        $url = 'https://maps.googleapis.com/maps/api/geocode/json?sensor=false&language='.urlencode($language).'&latlng='.urlencode($latitude.",".$longitude);
        if (self::$googleApiKey !== false) {
            //$url .= "&key=".urlencode(self::$googleApiKey);
        }
        $response = file_get_contents($url);    
        $arGeoResponse = json_decode($response, true);
        if (is_array($arGeoResponse) && array_key_exists("status", $arGeoResponse) && $arGeoResponse["status"] == "OK") {
            $idGeoRegion = null;
            if ($GLOBALS['nar_systemsettings']['SYS']['MAP_REGIONS']) {
                $idGeoRegion = self::writeGeolocationRegions($arGeoResponse["results"][0]);
            }
            $arGeoLocation = array(
                "FK_GEO_REGION" => $idGeoRegion,
                "PLACE"         => $arGeoResponse["results"][0],
                "LATITUDE"      => $arGeoResponse["results"][0]["geometry"]["location"]["lat"],
                "LONGITUDE"     => $arGeoResponse["results"][0]["geometry"]["location"]["lng"]
            );
            return $arGeoLocation;
        } else {
            if ($arGeoResponse["status"] == "ZERO_RESULTS") {
                return null;
            } else {
                eventlog('error', 'GeoLocation lookup failed', var_export($arGeoResponse, true));
                return false;
            }
        }
    }
    
    /**
     * Get markers for google map by text search
     * @param string        $query
     * @param float|null    $locationLat
     * @param float|null    $locationLon
     * @param int           $radius         Radius for search in meters
     * @return array
     */
    public static function getTextSearchResults($query, $locationLat = null, $locationLon = null, $radius = 20000) {
        // Most basic url
        $url = 'https://maps.googleapis.com/maps/api/place/textsearch/json?query='.urlencode($query);
        // Get fallback parameters
        if (self::$googleApiKey !== false) {
            $url .= "&key=".urlencode(self::$googleApiKey);
        }

        // Location based
        if (($locationLat !== null) && ($locationLon !== null)) {
            $url .= '&location='.urlencode($locationLat).','.urlencode($locationLon).'&radius='.urlencode($radius);
        }

        // Get result
        //put the results on the cache
        $cacheDir = $GLOBALS["ab_path"]."cache/map/";
        $cacheFile = $cacheDir."cache_".sha1($query."|".$locationLat."|".$locationLon."|".$radius).".json";
        $cacheFileLifetimeHours = 24 * 14;
        $cacheFileLifetime = time() - (3600 * $cacheFileLifetimeHours);
        $cacheUpdated = false;
        if (file_exists($cacheFile) && (filemtime($cacheFile) > $cacheFileLifetime)) {
            // Read from local cache
            $json = file_get_contents($cacheFile);
        } else {
            // Query result from google
            $json = file_get_contents($url);
            $cacheUpdated = true;
        }

        $data = json_decode($json,true);

        if (is_array($data) && array_key_exists("status", $data) && ($data["status"] == "OK")) {
            // Success!
            if ($cacheUpdated) {
                // Write result to cache
                file_put_contents($cacheFile, $json);
            }
            return $data["results"];
        } else {
            // Failed!
            if (file_exists($cacheFile)) {
                // Failed, but got result in cache file. Use that instead
                $json = file_get_contents($cacheFile);
                $data = json_decode($json,true);
                return $data["results"];
            }
            if ($data["status"] != "ZERO_RESULTS") {
                eventlog("error", "Problem in GoogleMaps:getTextSearchResults", var_export($data, true));
            }
            // No results found
            return array();
        }
    }

    public static function getDistanceMatrix($origin,$destination=null,$transport_mode=null) {
        if(empty($destination)){
            // Failed!
            return array();
        }

        if (!is_array($origin) || !array_key_exists("lat", $origin) || !array_key_exists("lng", $origin)) {
            // No origin given
            return array();
        }
        $urlOrigins = $origin["lat"].",".$origin["lng"];

        //basic url
        $url = 'https://maps.googleapis.com/maps/api/distancematrix/json?origins='.$urlOrigins.'&destinations='. urlencode(implode("|", $destination)).'&mode=' .urldecode($transport_mode);
        //check whether the api key is valid.


        if($transport_mode = null){
            $transport_mode = "driving";
        }
        //store the data on cache
        $cacheFile = "cache_".sha1($url)."json";
        $cacheFileLifetime = time() - 3600;
        if (file_exists($cacheFile) && (filemtime($cacheFile) > $cacheFileLifetime)) {
            // Read from local cache
            $json = file_get_contents($cacheFile);
        } else {
            // Query result from google and write to cache
            $json = file_get_contents($url);
            file_put_contents($cacheFile, $json);
        }

        $data = json_decode($json,true);

        if (is_array($data) && array_key_exists("status", $data) && ($data["status"] == "OK")) {
            // Success
            return $data["rows"][0]["elements"];
        } else {
            // Failed!
            if ($data["status"] != "ZERO_RESULTS") {
                eventlog("error", "Problem in GoogleMaps:getTextSearchResults", var_export($data, true));
            }
            // No results found
            return array();
        }
    }
    
    protected static function writeGeolocation(&$arGeoLocation, &$arGeoResult, $idGeoRegion = null, ebiz_db $db = null) {
        if ($db === null) {
            $db = $GLOBALS["db"];
        }
        $idGeoLocation = $db->fetch_atom("SELECT ID_GEOLOCATION FROM `geolocation` WHERE HASH='".mysql_real_escape_string($arGeoLocation['HASH'])."'");
        if ($idGeoLocation > 0) {
            $arGeoLocation["ID_GEOLOCATION"] = $idGeoLocation;
        }
        $arGeoLocation["STAMP_DATE"] = date("Y-m-d H:i:s");
        $arGeoLocation["LATITUDE"] = $arGeoResult["geometry"]["location"]["lat"];
        $arGeoLocation["LONGITUDE"] = $arGeoResult["geometry"]["location"]["lng"];
        foreach ($arGeoResult["address_components"] as $addressCompIndex => $arAddressComponent) {
            if (array_key_exists("types", $arAddressComponent)) {
                if (in_array("administrative_area_level_1", $arAddressComponent["types"])) {
                    $arGeoLocation["ADMINISTRATIVE_AREA_LEVEL_1"] = $arAddressComponent["long_name"];
                }
            }
        }
        if ($idGeoLocation > 0) {
            // Update
            $db->update("geolocation", $arGeoLocation);
        } else {
            // Insert
            $idGeoLocation = $arGeoLocation["ID_GEOLOCATION"] = $db->update("geolocation", $arGeoLocation);
        }
        return $idGeoLocation;
    }

    protected static function writeGeolocationRegions(&$arGeoResult, ebiz_db $db = null, $regionLevel = 0, $regionParent = null, $regionParentList = null, &$typeHierarchy = null) {
        if (!array_key_exists("address_components", $arGeoResult)) {
            return false;
        }
        if ($db === null) {
            $db = $GLOBALS["db"];
        }
        if ($typeHierarchy === null) {
            $typeHierarchy = self::$defaultTypeHierarchy;
        }
        if ($regionLevel >= count($typeHierarchy)) {
            // Reached end of type hierarchy
            return ($regionParent === null ? false : $regionParent);
        }
        $idGeoRegion = $regionParent;
        $typeCurrent = $typeHierarchy[$regionLevel];
        foreach ($arGeoResult["address_components"] as $addressCompIndex => $arAddressComponent) {
            if (!array_key_exists("types", $arAddressComponent) || !in_array($typeCurrent, $arAddressComponent["types"])) {
                continue;
            }
            $regionChanged = false;
            $arGeoRegion = array(
                "FK_PARENT"     => $regionParent,
                "NAME"          => $arAddressComponent["long_name"],
                "TYPE"          => $typeCurrent,
                "TYPE_EX"       => (count($arAddressComponent["types"]) > 1 ? $arAddressComponent["types"][1] : "Unknown"),
                "SER_PATH"      => ($regionParentList === null ? null : serialize($regionParentList)),
                "LATITUDE_MIN"  => (float)$arGeoResult["geometry"]["location"]["lat"],
                "LONGITUDE_MIN" => (float)$arGeoResult["geometry"]["location"]["lng"],
                "LATITUDE_MAX"  => (float)$arGeoResult["geometry"]["location"]["lat"],
                "LONGITUDE_MAX" => (float)$arGeoResult["geometry"]["location"]["lng"]
            );
            $arGeoRegionCur = $db->fetch1($q="
              SELECT * FROM `geo_region` 
              WHERE NAME='".mysql_real_escape_string($arGeoRegion['NAME'])."' AND ".($regionParent > 0 ? "FK_PARENT=".(int)$regionParent : "FK_PARENT IS NULL"));
            if ($arGeoRegionCur === null) {
                $regionChanged = true;
            } else {
                $idGeoRegion = $arGeoRegion["ID_GEO_REGION"] = $arGeoRegionCur["ID_GEO_REGION"];
                if (($arGeoRegion["LATITUDE_MIN"] !== 0) && ($arGeoRegion["LATITUDE_MIN"] < $arGeoRegionCur["LATITUDE_MIN"])) {
                    $regionChanged = true;
                } else if ($arGeoRegionCur["LATITUDE_MIN"] !== null) {
                    $arGeoRegion["LATITUDE_MIN"] = $arGeoRegionCur["LATITUDE_MIN"];
                }
                if (($arGeoRegion["LONGITUDE_MIN"] !== 0) && ($arGeoRegion["LONGITUDE_MIN"] < $arGeoRegionCur["LONGITUDE_MIN"])) {
                    $regionChanged = true;
                } else if ($arGeoRegionCur["LONGITUDE_MIN"] !== null) {
                    $arGeoRegion["LONGITUDE_MIN"] = $arGeoRegionCur["LONGITUDE_MIN"];
                }
                if (($arGeoRegion["LATITUDE_MAX"] !== 0) && ($arGeoRegion["LATITUDE_MAX"] > $arGeoRegionCur["LATITUDE_MAX"])) {
                    $regionChanged = true;
                } else if ($arGeoRegionCur["LATITUDE_MAX"] !== null) {
                    $arGeoRegion["LATITUDE_MAX"] = $arGeoRegionCur["LATITUDE_MAX"];
                }
                if (($arGeoRegion["LONGITUDE_MAX"] !== 0) && ($arGeoRegion["LONGITUDE_MAX"] > $arGeoRegionCur["LONGITUDE_MAX"])) {
                    $regionChanged = true;
                } else if ($arGeoRegionCur["LONGITUDE_MAX"] !== null) {
                    $arGeoRegion["LONGITUDE_MAX"] = $arGeoRegionCur["LONGITUDE_MAX"];
                }
            }
            if ($regionChanged) {
                $idGeoRegion = $db->update("geo_region", $arGeoRegion);
            }
            break;
        }
        if ($regionParentList === null) {
            $regionParentList = array($idGeoRegion);
        } else if (!in_array($idGeoRegion, $regionParentList)) {
            $regionParentList[] = $idGeoRegion;
        }
        return self::writeGeolocationRegions($arGeoResult, $db, $regionLevel+1, $idGeoRegion, $regionParentList, $typeHierarchy);
    }
}

if (!empty($GLOBALS["nar_systemsettings"]["SYS"]["MAP_API"])) {
    Geolocation_GoogleMaps::setApiKey($GLOBALS["nar_systemsettings"]["SYS"]["MAP_API"]);
}

if (!empty($GLOBALS["nar_systemsettings"]["SYS"]["MAP_API_JS"])) {
    Geolocation_GoogleMaps::setApiKeyJavascript($GLOBALS["nar_systemsettings"]["SYS"]["MAP_API_JS"]);
}