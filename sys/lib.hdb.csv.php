<?php
require_once $ab_path.'sys/lib.hdb.php';

class ManufacturerDatabaseCSV {
    private static $instance = null;

    private $db;
    private $countAll;
    private $countNew;
    private $countUpdated;
    private $countDeleted;

    /**
     * Singleton
     *
     * @param ebiz_db $db
     * @return ManufacturerDatabaseCSV
     */
    public static function getInstance(ebiz_db $db) {
        if (self::$instance === null) {
            self::$instance = new self($db);
        }
        return self::$instance;
    }

    public function __construct(ebiz_db $db) {
        $this->db = $db;
        $this->countAll = 0;
        $this->countNew = 0;
        $this->countUpdated = 0;
        $this->countDeleted = 0;
    }

    public function exportByParam($hdbTable, $arParam, $skipFields = array(), $outfile = null) {
        // Disable caching/gzipping
        while (ob_end_clean()) {  
            // do nothing   
        }
        header('Content-Encoding: identity');
        // Stream hdb as csv
        $arParam["LIMIT"] = NULL;
        $hdbManagement = ManufacturerDatabaseManagement::getInstance($this->db);

        $query = $hdbManagement->fetchQueryByParam($hdbTable, $arParam);

        if ($outfile === null) {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="hdb_export_'.$hdbTable.'.csv"');
            $outfile = fopen('php://output', 'w');
        }

		$arColumns = array();
		$tableStructure = $this->db->fetch_table("SHOW COLUMNS FROM `".$hdbTable."`");
		foreach($tableStructure as $key => $field) {
			if (!in_array($field['Field'], $skipFields)) {
				$arColumns[] = $field['Field'];
			}
		}

		$arColumns[] = "MANUFACTURER_NAME";
		$arColumns[] = "_DELETE";


        $result = mysql_query($query, $this->db->rsrc_db);
        if ($result) {

			// Export column names
			fputcsv($outfile, $arColumns, ';');
			// Export rows


			while (($arRow = mysql_fetch_assoc($result)) !== FALSE) {
				foreach ($skipFields as $skipIndex => $skipField) {
					if (array_key_exists($skipField, $arRow)) {
						unset($arRow[$skipField]);
					}
				}
				// Add special fields
				$arRow["_DELETE"] = 0;
				fputcsv($outfile, array_values($arRow), ';');
			} ;

        }
        fclose($outfile);
    }

    public function exportById($hdbTable, $idList, $skipFields = array(), $outfile = null) {
        if (!is_array($idList)) {
            return $this->exportById($hdbTable, array($idList), $skipFields, $outfile);
        }
        return $this->exportByParam($hdbTable, array('ID_'.strtoupper($hdbTable) => $idList), $skipFields, $outfile);
    }

    public function importFromFile($file, $offset = 0, $maxLines = 100) {
        $infile = fopen($file, "r+");
        $arColumns = fgetcsv($infile, 0, ';');
        if ($arColumns === null) {
            return false;   // Invalid file handle
        }
        if ($arColumns === false) {
            return false;   // Invalid content / empty file
        }
        if (!preg_match("/^ID_(.+)$/i", $arColumns[0], $arMatches)) {
            return false;   // Invalid header row / could not find table
        }
        if ($offset > 0) {
            fseek($infile, $offset, SEEK_SET);
        }
        $arCacheManufacturer = array();
        $hdbManagement = ManufacturerDatabaseManagement::getInstance($this->db);
        $hdbTable = strtolower($arMatches[1]);
        while (($arRow = fgetcsv($infile, 0, ';')) !== false) {
            if (!empty($arRow)) {
		        $arRow[0] = preg_replace("/\xEF\xBB\xBF/", "", $arRow[0]);
            }
            $id = ((int)$arRow[0] > 0 ? (int)$arRow[0] : null);
            $arRowAssoc = array();
            foreach ($arRow as $colIndex => $colValue) {
                $arRowAssoc[ $arColumns[$colIndex] ] = ($colValue === "" ? null : $colValue);
            }

            $result = false;
            if ($arRowAssoc["_DELETE"] == 1 && $id != null) {
                // Delete
                $result = $hdbManagement->deleteProduct($id, $hdbTable);
                $this->countDeleted++;
            } else {
                // Insert / update
                if (!empty($arRowAssoc["MANUFACTURER_NAME"]) && !empty($arRowAssoc["PRODUKTNAME"])) {
                    if (array_key_exists($arRowAssoc["MANUFACTURER_NAME"], $arCacheManufacturer)) {
                        $id_man = $arCacheManufacturer[$arRowAssoc["MANUFACTURER_NAME"]];
                    } else {
                        $resMan = mysql_query("SELECT ID_MAN FROM `manufacturers` WHERE NAME='" . mysql_real_escape_string($arRowAssoc["MANUFACTURER_NAME"]) . "'");
                        $arMan = mysql_fetch_assoc($resMan);
                        $id_man = $arMan["ID_MAN"];
                        $arCacheManufacturer[$arRowAssoc["MANUFACTURER_NAME"]] = $id_man;
                    }
                    if ($id_man > 0) {
                        $arRowAssoc["FK_MAN"] = $id_man;
                    } else if (empty($arRowAssoc["FK_MAN"])) {
                        $arRowAssoc["FK_MAN"] = $this->db->update("manufacturers", array(
                                "NAME" => $arRowAssoc["MANUFACTURER_NAME"], "CONFIRMED" => 1
                        ));
                        $arCacheManufacturer[$arRowAssoc["MANUFACTURER_NAME"]] = $arRowAssoc["FK_MAN"];
                    }

                    $result = $hdbManagement->saveProduct($id, $hdbTable, $arRowAssoc);
                    if ($id === NULL) {
                        $this->countNew++;
                    } else {
                        $this->countUpdated++;
                    }
                }
            }
            $this->countAll++;
            if (--$maxLines <= 0) {
                $offsetCur = ftell($infile);
                fclose($infile);
                return $offsetCur;
            }
        }
        fclose($infile);
        return true;
    }

    /**
     * @return int
     */
    public function getCountAll() {
        return $this->countAll;
    }

    /**
     * @return int
     */
    public function getCountNew() {
        return $this->countNew;
    }

    /**
     * @return int
     */
    public function getCountUpdated() {
        return $this->countUpdated;
    }

    /**
     * @return int
     */
    public function getCountDeleted() {
        return $this->countDeleted;
    }

}