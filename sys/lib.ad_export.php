<?php
/* ###VERSIONSBLOCKINLCUDE### */



class AdExportManagement {
	private static $db;
	private static $instance = null;

	/**
	 * Singleton
	 *
	 * @param ebiz_db $db
	 * @return AdExportManagement
	 */
	public static function getInstance(ebiz_db $db) {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		self::setDb($db);

		return self::$instance;
	}


    public function getExportAsCsvByFilter($filter = array()) {
    	global $nar_systemsettings;
        $db = $this->getDb();

        if((!isset($filter['FK_KAT']) || $filter['FK_KAT'] == null) && (!isset($filter['KAT_TABLE']) || $filter['KAT_TABLE'] == null)) {
            throw new Exception("Export Category is missing");
        }
        if(!isset($filter['FK_USER']) ||$filter['FK_USER'] == null) {
            throw new Exception("Export User is missing");
        }

        $whereFilter = "";
        if(isset($filter['FK_USER']) && $filter['FK_USER'] != null) { $whereFilter .= " AND  FK_USER = '".mysql_real_escape_string($filter['FK_USER'])."' "; }
        if(isset($filter['FK_KAT']) && $filter['FK_KAT'] != null) { $whereFilter .= " AND  FK_KAT = '".mysql_real_escape_string($filter['FK_KAT'])."' "; }
        if(isset($filter['STATUS']) && $filter['STATUS'] != null) { $whereFilter .= " AND  STATUS = '".mysql_real_escape_string($filter['STATUS'])."' "; }

        if(isset($filter['FK_KAT'])){
            $categoryTable = $db->fetch_atom("SELECT KAT_TABLE FROM `kat` WHERE ID_KAT=".$filter['FK_KAT']);
            $categoryFieldId = "ID_".strtoupper($categoryTable);
        } else if(isset($filter['KAT_TABLE'])) {
            $categoryTable = $filter['KAT_TABLE'];
            $categoryFieldId = "ID_".strtoupper($categoryTable);
        } else {
            throw new Exception("Category missing");
        }

        $ads = $db->fetch_table("
            SELECT
                `".$categoryTable."`.*,
        		IF(i.SRC IS NOT NULL,
        			concat(\"".mysql_escape_string($nar_systemsettings["SITE"]["SITEURL"])."\", i.SRC),
        			'') as IMAGE_URL
            FROM `".$categoryTable."`
        	LEFT JOIN `ad_images` i ON i.FK_AD=".$categoryFieldId." AND i.IS_DEFAULT=1
            WHERE
                TRUE ".$whereFilter."
        ");

        $excludeColums = array($categoryFieldId, "STATUS", "FK_USER", "STAMP_START", "STAMP_END", "STAMP_END", "STAMP_DEACTIVATE");
        $columnNames = array();
        $csv = "";

        if(isset($filter['FILTER']) && $filter['FILTER'] != null) {
            require_once 'sys/lib.ad_export.filter.php';
            $adExportFilterManagement = AdExportFilterManagement::getInstance();
        } else {
            $adExportFilterManagement = null;
        }

        $csvFile = $ab_path.'cache/export/'.md5(time().serialize($filter)).'.csv';
        $fp = fopen($csvFile, "w");

        foreach($ads as $key=>$ad) {

            // IMPORT_IDENTIFIER setzen sofern nicht schon getan
            if($ad['IMPORT_IDENTIFIER'] == null || $ad['IMPORT_IDENTIFIER'] == "") {
                $ad['IMPORT_IDENTIFIER'] = md5($ad[$categoryFieldId].'_'.$ad['FK_KAT'].'_'.$ad['FK_USER'].'_'.$ad['STAMP_START']);
                $db->update($categoryTable, $ad);
            }

            if($adExportFilterManagement !== null) {
                $ad = $adExportFilterManagement->applyFilter($filter['FILTER'], $ad);
            }

            $tmp = array();
            foreach($excludeColums as $excludeKey=>$excludeColum) { unset($ad[$excludeColum]); }
            foreach($ad as $column => $value) {
                if($key == 0) { $columnNames[] = $column; }

                $ad[$column] = preg_replace("/\\n/", '', $ad[$column]);
                $ad[$column] = preg_replace("/\\r/", '', $ad[$column]);
                $tmp[] = $ad[$column];
                //$tmp[] = '"'.addslashes($ad[$column]).'"';
            }

			fputcsv($fp, $tmp, ';');
        }
        fclose($fp);
        chmod($csvFile, 0777);
        $tmpContent = file_get_contents($csvFile);

        if($filter['FIRST_LINE_COLUMN_NAMES'] == true) {
        	$fl = fopen($csvFile, "w+");
        	fputcsv($fl, $columnNames, ';');
        	fclose($fl);

        	file_put_contents($csvFile, $tmpContent, FILE_APPEND);
            //$csv = implode(";", $columnNames)."\r\n".$csv;
        }


		$csvFileContent = file_get_contents($csvFile);

		return $csvFileContent;
        //return $csv;
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