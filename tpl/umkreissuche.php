<?php
/* ###VERSIONSBLOCKINLCUDE### */


/* * * * * * * * * * * * * * * * * * * * * * * * *
    Klasse Umkreissuche
    (c) 2008 Philipp Mamat
    http://www.mamat-online.de/
    http://www.mamat-online.de/umkreissuche/opengeodb.php
 * * * * * * * * * * * * * * * * * * * * * * * * */
 
class Umkreissuche {
    // Erdradius in Kilometern
    private $Erdradius = 6371;
    // Datentabelle
    private $table = false;
    // Fehler zeigen?
    public $zeigeFehler = true;

    public function __construct($table = 'geodb_usercache') {
        $this->table = $table;

        // leere Koordinaten in Tabelle füllen
        $sql = 'SELECT `ID`, `PLZ`
                FROM `' . $this->table . '`
                WHERE
                    `KoordX` = "0"
                AND `KoordY` = "0"
                AND `KoordZ` = "0"
                ';
        $re = mysql_query($sql);
        while ($rd = @mysql_fetch_object($re)) {
            if (!$this->Plz2Koord($rd->PLZ, $lon, $lat)) {
                if ($this->zeigeFehler) {
                    trigger_error('Postleitzahl ' . $rd->PLZ . ' konnte nicht zugeordnet werden', E_USER_NOTICE);
                }
                continue;
            }
            $this->Kugel2Kartesisch($lon, $lat, $x, $y, $z);
            $sql = 'UPDATE `' . $this->table . '`
                    SET
                        `Longitude` = "' . $lon . '",
                        `Latitude` = "' . $lat . '",
                        `KoordX` = "' . $x . '",
                        `KoordY` = "' . $y . '",
                        `KoordZ` = "' . $z . '"
                    WHERE
                        `ID` = "' . (int)$rd->ID . '"
                    LIMIT 1
                    ';
            mysql_query($sql);
        }
    }
        
    public function Kugel2Kartesisch($lon, $lat, &$x, &$y, &$z) {
        $lambda = $lon * pi() / 180;
        $phi = $lat * pi() / 180; 
        $x = $this->Erdradius * cos($phi) * cos($lambda);
        $y = $this->Erdradius * cos($phi) * sin($lambda);
        $z = $this->Erdradius * sin($phi); 
        return true;
    }
    
    public function Plz2Koord($PLZ, &$lon, &$lat) {
        $sql = 'SELECT
                    coo.lon,
                    coo.lat
                FROM geodb_coordinates AS coo
                INNER JOIN geodb_textdata AS textdata
                ON textdata.loc_id = coo.loc_id
                WHERE
                    textdata.text_val = "' . $PLZ . '"
                AND textdata.text_type = "500300000"
                LIMIT 1';
        $re = mysql_query($sql);
        if (mysql_num_rows($re) != 1) {
            return false;
        }
        list($lon, $lat) = mysql_fetch_row($re);
        return true;
    }
   
    public function SucheJoin($PLZ, $Radius) {
		$result = array();
        if (!$this->Plz2Koord($PLZ, $lon, $lat)) {
            if ($this->zeigeFehler) {
                trigger_error('Postleitzahl ' . $PLZ . ' konnte nicht zugeordnet werden', E_USER_NOTICE);
            }
            return false;
        }
        $this->Kugel2Kartesisch($lon, $lat, $UrsprungX, $UrsprungY, $UrsprungZ);
        
		$result["var"] = '(POWER(' . $UrsprungX .' - KoordX, 2)
                  		+ POWER(' . $UrsprungY .' - KoordY, 2)
                  		+ POWER(' . $UrsprungZ .' - KoordZ, 2)) as DISTANCE';
		$result["join"] = 'LEFT JOIN `' . $this->table . '` ON ' . $this->table . '.PLZ = user.PLZ';
        $result["where"] = 'KoordX >= ' . ($UrsprungX - $Radius) . '
                AND KoordX <= ' . ($UrsprungX + $Radius) . '
                AND KoordY >= ' . ($UrsprungY - $Radius) . '
                AND KoordY <= ' . ($UrsprungY + $Radius) . '
                AND KoordZ >= ' . ($UrsprungZ - $Radius) . '
                AND KoordZ <= ' . ($UrsprungZ + $Radius) . '
                AND POWER(' . $UrsprungX .' - KoordX, 2)
                  + POWER(' . $UrsprungY .' - KoordY, 2)
                  + POWER(' . $UrsprungZ .' - KoordZ, 2)
                    <= ' . pow(2 * $this->Erdradius * sin($Radius / (2 * $this->Erdradius)), 2);

        return $result;
    }
	
    public function SucheEntfernung($PLZ_from, $PLZ_to) {
		$result = array();
        if (!$this->Plz2Koord($PLZ_from, $lon_from, $lat_from)) {
            if ($this->zeigeFehler) {
                trigger_error('Postleitzahl ' . $PLZ_from . ' konnte nicht zugeordnet werden', E_USER_NOTICE);
            }
            return false;
        }
        if (!$this->Plz2Koord($PLZ_to, $lon_to, $lat_to)) {
            if ($this->zeigeFehler) {
                trigger_error('Postleitzahl ' . $PLZ_to . ' konnte nicht zugeordnet werden', E_USER_NOTICE);
            }
            return false;
        }
        $this->Kugel2Kartesisch($lon_from, $lat_from, $UrsprungX, $UrsprungY, $UrsprungZ);
        $this->Kugel2Kartesisch($lon_to, $lat_to, $ZielX, $ZielY, $ZielZ);
		$dist = sqrt(pow($UrsprungX - $ZielX, 2) + pow($UrsprungY - $ZielY, 2) + pow($UrsprungZ - $ZielZ, 2));
		return $dist;
    }
	
	public function CachePLZ($PLZ) {		
		$sql_query = 'SELECT * FROM `' . $this->table . '` WHERE PLZ='.$PLZ;
		$sql_result = MYSQL_QUERY($sql_query);
		if (!@MYSQL_NUM_ROWS($sql_result)) {
			$lon = 0;
			$lat = 0;
			if ($this->Plz2Koord($PLZ, $lon, $lat)) {
				$this->Kugel2Kartesisch($lon, $lat, $x, $y, $z);
				$sql_query = 'INSERT INTO `' . $this->table . '` (PLZ, KoordX, KoordY, KoordZ) VALUES '.
							'('.$PLZ.', '.$x.', '.$y.', '.$z.')';
				$sql_result = MYSQL_QUERY($sql_query);
				return true;
			}
		}
		return false;
	}
}
?>