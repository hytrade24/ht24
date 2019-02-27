<?php
/* ###VERSIONSBLOCKINLCUDE### */



class userfunctions {
	private $filterName;
	private $filterKats = array();

	public function __construct($filterName) {
		$this->filterName = $filterName;
	}

	public function kategorie_zuordnung($value) {
		global $db;
		if(!isset($this->filterKats[$this->filterName])) {
			$this->filterKats[$this->filterName] = array();
			$fieldName = "FK_".strtoupper($this->filterName);
			$res= $db->querynow($q = "
				select
					".mysql_real_escape_string($fieldName).",
					ID_KAT
				from
					kat
				where
					".mysql_real_escape_string($fieldName)." IS NOT NULL AND
					".mysql_real_escape_string($fieldName)." > 0");
			while($row = mysql_fetch_assoc($res['rsrc'])) {
				$this->filterKats[$this->filterName][$row[$fieldName]] = $row['ID_KAT'];
			}
			#echo ht(dump($this->ar_ebay_kats));
		}
		return ($this->filterKats[$this->filterName][$value] ? (int)$this->filterKats[$this->filterName][$value] : 0);
	}

	public function REV_kategorie_zuordnung($value) {
		if(!isset($this->filterKats[$this->filterName])) {
			$this->filterKats[$this->filterName] = array();
			$fieldName = "FK_".strtoupper($this->filterName);
			$res= $db->querynow("
				select
					".mysql_real_escape_string($fieldName).",
					ID_KAT
				from
					kat
				where
					".mysql_real_escape_string($fieldName)." IS NOT NULL AND
					".mysql_real_escape_string($fieldName)." > 0");
			while($row = mysql_fetch_assoc($res['rsrc'])) {
				$this->filterKats[$this->filterName][$row[$fieldName]] = $row['ID_KAT'];
			}
			#echo ht(dump($this->ar_ebay_kats));
		}
		foreach ($this->filterKats[$this->filterName] as $id_kat_filter => $id_kat_trader) {
			if ($id_kat_trader == $value) {
				return $id_kat_filter;
			}
		}
		return 0;
	}

	/**
	 * Funktion um anahnd der ebay Kategorie die trader-Kategorie zu finden
	 *
	 * @param string $value Value from CSV
	 * @return int ID_KAT
	 */
	public function kategorie_aus_ebay($value) {
		return $this->_get_kat_via_ebay($value);
	}

	public function REV_kategorie_aus_ebay($value) {
		return $this->get_kat_name($value);
	}

	public function zustand_aus_ebay($value) {
		switch($value) {
			case '1000':
			case '1500':
				$value = 1;		// Neu
				break;
			case '2000':
			case '2500':
				$value = 3;		// Generalüberholt
				break;
			case '3000':
				$value = 2;		// Gebraucht
				break;
			case '7000':
				$value = 38;	// Defekt
				break;
			default:
				$value = 1;		// Neu
		}
		return $value;
	}

	public function REV_zustand_aus_ebay($value) {
		switch($value) {
			case 1:
				$value = 1000;		// Neu
				break;
			case 2:
				$value = 3000;		// Gebraucht
				break;
			case 3:
				$value = 2000;		// Generalüberholt
				break;
			case 38:
				$value = 7000;		// Defekt
				break;
			default: $value = 1000; // neu
		}
		return $value;
	}

	/**
	 * Funktion um anahnd der ebay Laufzeit die trader-Kategorie zu finden
	 *
	 * @param string $value Value from CSV
	 * @return int ID_KAT
	 */
	public function laufzeit_aus_ebay($value) {
		global $db;
		return $db->fetch_atom("SELECT ID_LOOKUP FROM `lookup` WHERE VALUE='".(int)$value."'");
	}

	public function REV_laufzeit_aus_ebay($value) {
		global $db;
		return $db->fetch_atom("SELECT VALUE FROM `lookup` WHERE ID_LOOKUP='".(int)$value."'");
	}

    public function laender_zuordnung($value) {
        global $db;

        $res = $db->fetch_atom($a = "
            SELECT c.ID_COUNTRY FROM country c LEFT JOIN string s ON (s.S_TABLE = 'country' AND s.FK = c.ID_COUNTRY AND s.BF_LANG = if(c.BF_LANG & 128, 128, 1 << floor(log(c.BF_LANG+0.5)/log(2))))
            WHERE c.CODE = '".mysql_real_escape_string($value)."' OR s.V1 = '".mysql_real_escape_string($value)."'
        ");

        if($res != null && $res != "") {
            return $res;
        } else {
            return "";
        }
    }

	public function Zustand_ermitteln($value) {
		/*
		 * ebay werte (turbo-lister)
		 *  0. Keine Angabe - Wert 0
			1. Neu - Wert 1000
			2. Neu: Sonstige (siehe Artikelbeschreibung) - Wert 1500
			3. GeneralÃÂ¼berholt - Wert 2500
			4. Gebraucht - Wert 3000
			5. Als Ersatzteil / defekt - Wert 7000
		 */
		switch($value) {
			case '1':
			case '2':
				$value = 1;
				break;
			case '3':
				$value = 38;
				break;
			case 4:
				$value = 2;
				break;
			case 5:
				$value = 3;
				break;
			case '0':
			default: $value = 1; // neu
		}
		return $value;
	}

	public function komma_zu_punkt($value) {
		return str_replace(",", ".", $value);
	}

	public function REV_komma_zu_punkt($param) {
		$digits = 2;
      	return (!is_null($param) ? sprintf("%0.2f", round($param, $digits)) : $param);
	}

	public function default_1($value) {
		if(empty($value) || !is_numeric($value)) {
			$value = 1;
		}
		return $value;
	}

	public function bild_import($value) {
		return trim($value);
	}

	public function html_inhalte_verarbeiten($value) {
		$value = str_replace('""', '"', $value);
		$value = preg_replace("/%0d|%0a/si", " ", $value);
		#$value = preg_replace("/(style=\")([^\"]*)(\")/si", "", $value);
		return $value;
	}

	/**
	 * helpers (private -> do not touch)
	 */
	private $ar_ebay_kats;
	private function _get_kat_via_ebay($id) {
		global $db;
		if(is_null($this->ar_ebay_kats)) {
			$res= $db->querynow("
				select
					FK_EBAY,
					ID_KAT
				from
					kat
				where
					FK_EBAY IS NOT NULL AND FK_EBAY > 0");
			while($row = mysql_fetch_assoc($res['rsrc'])) {
				$this->ar_ebay_kats[$row['FK_EBAY']] = $row['ID_KAT'];
			}
			#echo ht(dump($this->ar_ebay_kats));
		}
		return ($this->ar_ebay_kats[$id] ? (int)$this->ar_ebay_kats[$id] : 420);
	}

	private $ar_kat_names;
	private function get_kat_name($value) {
		global $db, $langval;
		$value = (int)$value;
		if(!$value) {
			return '-';
		} else {
			if(!$this->ar_kat_names[$value]) {
				$name = $db->fetch_atom("SELECT V1 from string_kat where FK=".$value." and BF_LANG=".$langval);
				if(empty($name)) {
					$name = '-';
				}
				$this->ar_kat_names[$value] = $name;
				return $name;
			} else {
				return $this->ar_kat_names[$value];
			}
		}
	}
}

?>