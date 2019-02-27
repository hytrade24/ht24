<?php
/* ###VERSIONSBLOCKINLCUDE### */


/**
 *
 */
class GeolocationManagement {
    private static $db;
   	private static $instance = null;

   	/**
   	 * Singleton
   	 *
   	 * @param ebiz_db $db
   	 * @return GeolocationManagement
   	 */
   	public static function getInstance(ebiz_db $db) {
   		if (self::$instance === null) {
   			self::$instance = new self();
   		}
   		self::setDb($db);

   		return self::$instance;
   	}

	public function getCoordinatesFromAddress($street = "", $zip = "", $city = "", $country = 'Deutschland', $language = 'de') {

        $dataset = $this->getGeolocationDataset($street, $zip, $city, $country);
        if($dataset != null) {
            return $dataset;
        } else {
            $geoCoordinates = $this->fetchCoordinatesFromGeolocationService($street, $zip, $city, $country, $language);
            if($geoCoordinates != null) {
                $this->updateGeolocationDataset($street, $zip, $city, $geoCoordinates['ADMINISTRATIVE_AREA_LEVEL_1'], $country, $geoCoordinates['longitude'], $geoCoordinates['latitude']);

                return $this->getGeolocationDataset($street, $zip, $city, $country);
            } else {
            	return null;
            }
        }
	}

    /**
     * Läd den Geolocation Datensatz anhand von Straße, PLZ, Stadt, Land aus der Datenbank
     *
     * @param $street
     * @param $zip
     * @param $city
     * @param $country
     *
     * @return null|array
     */
    private function getGeolocationDataset($street, $zip, $city, $country) {
        $db = $this->getDb();
        $hash = $this->getHash($street, $zip, $city, $country);

        $dataset = $db->fetch1("SELECT * FROM geolocation WHERE hash = '".mysql_real_escape_string($hash)."'");
        return $dataset;
    }

    /**
     * Aktualisiert einen Geolocation Datensatz in der Datenbank
     *
     * @param $street
     * @param $zip
     * @param $city
     * @param $country
     * @param $longitude
     * @param $latitude
     *
     * @return boolean
     */
    private function updateGeolocationDataset($street, $zip, $city, $administrative_area_level_1, $country, $longitude, $latitude) {
        $db = $this->getDb();

        $dataset = $this->getGeolocationDataset($street, $zip, $city, $country);
        $currentDateTime = new DateTime();
        $hash = $this->getHash($street, $zip, $city, $country);

        if($dataset == null) {
            // Neuen Datensatz anlegen
            $data = array();
        } else {
            // Datensatz aktualisieren
            $data = array('ID_GEOLOCATION' => $dataset['ID_GEOLOCATION']);
        }

        $data = array_merge($data, array(
            'STAMP_DATE' => $currentDateTime->format("Y-m-d H:i:s"),
            'HASH' => $hash,
            'STREET' => $street,
            'ZIP' => $zip,
            'CITY' => $city,
            'ADMINISTRATIVE_AREA_LEVEL_1' => $administrative_area_level_1,
            'COUNTRY' => $country,
            'LATITUDE' => $latitude,
            'LONGITUDE' => $longitude
        ));
        $db->update("geolocation", $data);
    }

    /**
     * Liest die Geokoordinaten einer Adresse aus Google Maps aus und gibt sie als Array zurück
     *
     * @param string $street
     * @param string $zip
     * @param string $city
     * @param string $country
     *
     * @return array|null
     */
    private function fetchCoordinatesFromGeolocationService($street = "", $zip = "", $city = "", $country = 'Deutschland', $language = 'de') {
    	$result = $this->fetchCoordinatesFromGeolocationServiceGoogle($street, $zip, $city, $country, $language);
		if($result === NULL) {
			$result = $this->fetchCoordinatesFromGeolocationServiceOpenStreetMap($street, $zip, $city, $country, $language);
		}
		if($result === NULL) {
			$result = $this->fetchCoordinatesFromGeolocationServiceYahoo($street, $zip, $city, $country, $language);
		}

		return $result;
    }

	protected function fetchCoordinatesFromGeolocationServiceGoogle($street = "", $zip = "", $city = "", $country = 'Deutschland', $language = 'de') {
		global $nar_systemsettings;

		$api_key = $nar_systemsettings['SITE']['GOOGLE_API'];
		$url = 'https://maps.googleapis.com/maps/api/geocode/json?sensor=false&language='.urlencode($language).'&address='.urlencode($street." ".$zip." ".$city." ".$country);

		$response = file_get_contents($url);
		$ar_response = json_decode($response);

		if(!empty($ar_response->results['0']->geometry) && !empty($ar_response->results['0']->geometry->location->lat)) {
			$ar_result = array(
				"latitude"		=> $ar_response->results['0']->geometry->location->lat,
				"longitude"		=> $ar_response->results['0']->geometry->location->lng,
			);

            foreach ($ar_response->results[0]->address_components as $address_component) {
                $ar_result[strtoupper($address_component->types[0])] = $address_component->long_name;
            }

			return $ar_result;
		} else {
			#eventlog("error", "Geocodierung Google fehlgeschlagen", print_r($ar_response, true));

			return null;
		}
	}

	protected function fetchCoordinatesFromGeolocationServiceOpenStreetMap($street = "", $zip = "", $city = "", $country = 'Deutschland', $language = 'de') {
		global $nar_systemsettings;

		// Hack: remove ranged house numbers
		if (preg_match("/^(.+)\s([0-9]+[a-z]?)\-([0-9]+[a-z]?)$/i", $street, $arMatches)) {
			$street = $arMatches[1]." ".$arMatches[2];
		}
		
		$url = 'http://nominatim.openstreetmap.org/search?format=json&q='.urlencode(trim($street." ".$zip." ".$city." ".$country));

		$response = file_get_contents($url);
		$ar_response = json_decode($response);

		if(!empty($ar_response) && !empty($ar_response['0']->lat)) {
			$ar_result = array(
				"latitude"		=> $ar_response['0']->lat,
				"longitude"		=> $ar_response['0']->lon
			);
			return $ar_result;
		} else {
			eventlog("error", "Geocodierung OpenstreetMap fehlgeschlagen", print_r($ar_response, true));

			return null;
		}
		return null;
	}

	protected function fetchCoordinatesFromGeolocationServiceYahoo($street = "", $zip = "", $city = "", $country = 'Deutschland', $language = 'de') {
		global $nar_systemsettings;

		/*$url = 'http://where.yahooapis.com/geocode?street='.urlencode($street)."&postal=".urlencode($zip)."&city=".urlencode($city)."&country=".urlencode($country)."&flags=CJ";

		$response = file_get_contents($url);
		$ar_response = json_decode($response);

		if(!empty($ar_response->ResultSet->Results['0']) && !empty($ar_response->ResultSet->Results['0']->latitude)) {
			$ar_result = array(
				"latitude"		=> $ar_response->ResultSet->Results['0']->latitude,
				"longitude"		=> $ar_response->ResultSet->Results['0']->longitude
			);
			return $ar_result;
		} else {
			eventlog("error", "Geocodierung Yahoo fehlgeschlagen", print_r($ar_response, true));

			return null;
		}*/
		return null;
	}

    private function getHash($street, $zip, $city, $country) {
        return md5(implode(";", array($street, $zip, $city, $country)));
    }

    /**
   	 * @return ebiz_db $db
   	 */
   	public function getDb() {
   		return self::$db;
   	}

   	/**
   	 * @param ebiz_db $db
   	 */
   	public function setDb(ebiz_db $db) {
   		self::$db = $db;
   	}

   	private function __construct() {
   	}
   	private function __clone() {
   	}
}

?>
