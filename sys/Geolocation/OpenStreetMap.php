<?php
/**
 * Created by PhpStorm.
 * User: jens
 * Date: 27.07.15
 * Time: 17:23
 */

class Geolocation_OpenStreetMap {   
    
    static $defaultTypeHierarchy = array(
        "country", "administrative_area_level_1", "administrative_area_level_2", "administrative_area_level_3", "administrative_area_level_4", "administrative_area_level_5",
        "locality", "neighborhood", "sublocality_level_1", "sublocality_level_2", "sublocality_level_3", "sublocality_level_4", "sublocality_level_5"
    );

    public static function getGoogleMapsPlace($arGeolocationResultOSM) {
        // Create google maps compatible place
        $arPlace = array(
            'geometry' => array(
                'location'  => array(
                    'lat' => $arGeolocationResultOSM['lat'],
                    'lng' => $arGeolocationResultOSM['lon']
                )
            ),
            'address_components' => array()
        );
        foreach ($arGeolocationResultOSM["address"] as $addressType => $addressValue) {
            switch ($addressType) {
                case 'house_number':
                    $arPlace['address_components'][] = array(
                        'long_name' => $addressValue, 'short_name' => $addressValue, 'types' => array('street_number')
                    );
                    break;
                case 'road':
                    $arPlace['address_components'][] = array(
                        'long_name' => $addressValue, 'short_name' => $addressValue, 'types' => array('route')
                    );
                    break;
                case 'postcode':
                    $arPlace['address_components'][] = array(
                        'long_name' => $addressValue, 'short_name' => $addressValue, 'types' => array('postal_code')
                    );
                    break;
                case 'town':
                    $arPlace['address_components'][] = array(
                        'long_name' => $addressValue, 'short_name' => $addressValue, 'types' => array('locality', 'political')
                    );
                    break;
                case 'state':
                    $arPlace['address_components'][] = array(
                        'long_name' => $addressValue, 'short_name' => $addressValue, 'types' => array('administrative_area_level_1', 'political')
                    );
                    break;
                case 'country':
                    $arPlace['address_components'][] = array(
                        'long_name' => $addressValue, 'short_name' => $addressValue, 'types' => array('country', 'political')
                    );
                    break;
            }
        }
        return $arPlace;
    }
    
    public static function getGeolocation($street = null, $zip = null, $city = null, $country = null, $language = null) {
        if ($language === null) {
            $language = $GLOBALS['s_lang'];
        }
        $query = Geolocation_Generic::getGeolocationQuery($street, $zip, $city, $country);
        if ($query === false) {
            return false;
        }
        $url = 'http://nominatim.openstreetmap.org/search?format=json&q='.urlencode($query).'&accept-language='.urlencode($language).'&addressdetails=1';
      
        $response = @file_get_contents($url);
        $hash = md5($language."|".$query);
        $arGeoResponse = json_decode($response, true);
        if (is_array($arGeoResponse) && (count($arGeoResponse) > 0)) {
            $idGeoRegion = null;
            if ($GLOBALS['nar_systemsettings']['SYS']['MAP_REGIONS']) {
                // TODO: $idGeoRegion = Geolocation_Generic::writeGeolocationRegions($arGeoResponse["results"][0]);
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
            $idGeoLocation = self::writeGeolocation($arGeoLocation, $arGeoResponse[0], $idGeoRegion);
            return $arGeoLocation;
        } else {
            if (!is_array($arGeoResponse)) {
                eventlog('error', 'GeoLocation lookup failed', var_export($arGeoResponse, true));
                return false;
            } else {
                return null;
            }
        }
    }
    
    public static function getGeolocationReverse($latitude, $longitude, $language = null) {
        if ($language === null) {
            $language = $GLOBALS['s_lang'];
        }
        $url = 'http://nominatim.openstreetmap.org/reverse?format=json&language='.urlencode($language).'&lat='.urlencode($latitude).'&lon='.urlencode($longitude);
        $response = @file_get_contents($url);    
        $arGeoResponse = json_decode($response, true);
        if (is_array($arGeoResponse) && (count($arGeoResponse) > 0)) {
            $idGeoRegion = null;
            if ($GLOBALS['nar_systemsettings']['SYS']['MAP_REGIONS']) {
                // TODO: $idGeoRegion = Geolocation_Generic::writeGeolocationRegions($arGeoResponse["results"][0]);
            }
            // Build result object
            $arGeoLocation = array(
                "FK_GEO_REGION" => $idGeoRegion,
                "PLACE"         => self::getGoogleMapsPlace($arGeoResponse),
                "LATITUDE"      => $arGeoResponse["lat"],
                "LONGITUDE"     => $arGeoResponse["lon"]
            );
            return $arGeoLocation;
        } else {
            if (!is_array($arGeoResponse)) {
                eventlog('error', 'GeoLocation reverse-lookup failed', var_export($arGeoResponse, true));
                return false;
            } else {
                return null;
            }
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
        $arGeoLocation["LATITUDE"] = $arGeoResult["lat"];
        $arGeoLocation["LONGITUDE"] = $arGeoResult["lon"];
        if (is_array($arGeoResult["address"]) && array_key_exists("state", $arGeoResult["address"])) {
            $arGeoLocation["ADMINISTRATIVE_AREA_LEVEL_1"] = $arGeoResult["address"]["state"];
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
    
}