<?php
/* ###VERSIONSBLOCKINLCUDE### */


require_once $ab_path.'admin/sys/lib.import_export_filter.php';
require_once $ab_path.'admin/sys/lib.import.php';


class AdImportManagement {
	private static $db;
	private static $instance = null;

	const IMPORT_FILE_STATUS_UPLOAD = 0;
	const IMPORT_FILE_STATUS_READY = 4;
	const IMPORT_FILE_STATUS_VALIDATE = 1;
	const IMPORT_FILE_STATUS_IMPORT = 2;
	const IMPORT_FILE_STATUS_IMPORTED = 3;

	const IMPORT_STATUS_VALIDATION = 2;
	const IMPORT_STATUS_VALIDATION_SUCCESS = 3;
	const IMPORT_STATUS_VALIDATION_FAILED = 4;
	const IMPORT_STATUS_IMPORTED = 1;

	/**
	 * Singleton
	 *
	 * @param ebiz_db $db
	 * @return AdImportManagement
	 */
	public static function getInstance(ebiz_db $db) {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		self::setDb($db);

		return self::$instance;
	}


	/**
	 * Erzeugt eine neues ImportFile
	 * @param int $userId	UserID des Besitzers
	 * @param string $name	Bezeichnung des ImportFiles
	 * @param string $filterIdent	Filter Ident
	 * @param string $data	Import Daten
	 */
	public function createImportFile($userId, $name, $filterId, $data) {
		$db = $this->getDb();

		$result = $db->querynow("
			INSERT INTO
				`import_file` (FK_USER, STAMP_CREATED, STATUS, NAME, FK_IMPORT_FILTER, DATA)
			VALUES (
				'".mysql_real_escape_string($userId)."',
				NOW(),
				".AdImportManagement::IMPORT_FILE_STATUS_UPLOAD.",
				'".mysql_real_escape_string($name)."',
				'".mysql_real_escape_string($filterId)."',
				'".mysql_real_escape_string($data)."'
			)
		");

		$importFileId = $result['int_result'];

		//$this->loadImportFileToTmpTable($importFileId);

        return $importFileId;
	}

	public function loadImportFileToTmpTable($importFileId, $startAtCsvLine = 0, $readLimit = null) {
		$db = $this->getDb();

		$importFile = $db->fetch1("SELECT * FROM `import_file` WHERE ID_IMPORT_FILE = '".mysql_real_escape_string($importFileId)."'");
		if($importFile != null) {
			$import = new import();

			$import->set_filter($importFile['FK_IMPORT_FILTER']);

			$importResult = $import->move_to_temp_table($importFile['DATA'], $importFile['ID_IMPORT_FILE'], $startAtCsvLine, $readLimit);

			if($importResult == false) {
				throw new Exception("Load CSV to Tmp Table failed");
			} elseif(is_array($importResult) && $importResult['result'] == true && $importResult['isFinished'] == true) {
				// CSV Upload beendet, import_file Status setzen
				$db->querynow("UPDATE `import_file` SET STATUS = '".AdImportManagement::IMPORT_FILE_STATUS_VALIDATE."' WHERE ID_IMPORT_FILE = '".mysql_real_escape_string($importFileId)."'");

                return array('result' => true, 'isFinished' => true);
            } else {
                $newLine = ($readLimit != null)?($startAtCsvLine + $readLimit):null;

                return array('result' => true, 'isFinished' => false, 'line' => $newLine);
            }
		} else {
			throw new Exception("Import File could not be found.");
		}
	}

	public function existsImportFileByUserId($importFileId, $userId) {
		$c = $this->getDb()->fetch_atom("SELECT COUNT(*) FROM import_file WHERE ID_IMPORT_FILE = '".mysql_real_escape_string($importFileId)."' AND FK_USER = '".mysql_real_escape_string($userId)."'");
		return ($c > 0);
	}


	public function validateImportFile($importFileId) {
		$db = $this->getDb();

		$categoryTable = $this->getCategoryTableByImportFileId($importFileId);
		$imports = $this->fetchAllImportsByImportFileId($importFileId, array("status" => AdImportManagement::IMPORT_STATUS_VALIDATION, "limit" => 100));

		foreach ($imports as $key=>$import) {
			$this->validateImport($importFileId, $import['ID_'.strtoupper($categoryTable)]);
		}
	}

	/**
	 * Validiert einen temporären Import Datensatz
	 *
	 * @param int $importFileId
	 * @param int $importId
	 */
	public function validateImport($importFileId, $importId) {
		$db = $this->getDb();

		$importClass = new import();
		$importFilterId = $db->fetch_atom("SELECT FK_IMPORT_FILTER FROM `import_file` WHERE ID_IMPORT_FILE = '".mysql_real_escape_string($importFileId)."'");
		$importFilerData = $importClass->get_filter_data($importFilterId);

		$import = $this->fetchImportById($importFileId, $importId);

		$validationResult = $importClass->pre_check($import);

		if($validationResult == false) {
			return false;
		} else {
			return true;
		}
	}

	public function import($importFileId, $importId) {
		$db = $this->getDb();

		$importClass = new import();
		$importFilterId = $db->fetch_atom("SELECT FK_IMPORT_FILTER FROM `import_file` WHERE ID_IMPORT_FILE = '".mysql_real_escape_string($importFileId)."'");
		$importFilerData = $importClass->get_filter_data($importFilterId);

		$import = $this->fetchImportById($importFileId, $importId);

		$importResult = $importClass->import_one($import);

		if($importResult == false) {
			return false;
		} else {
			return true;
		}
	}

	/**
	 * Holt einen Import Datensatz aus der Tmp Tabelle anhand der ImportFile Id und der Id des Datensatzes
	 *
	 * @param int $importFileId
	 * @param int $importId
	 *
	 * @return array
	 */
	public function fetchImportById($importFileId, $importId) {
		$db = $this->getDb();

		$importClass = new import();
		$importFilterId = $db->fetch_atom("SELECT FK_IMPORT_FILTER FROM `import_file` WHERE ID_IMPORT_FILE = '".mysql_real_escape_string($importFileId)."'");
		$importFilerData = $importClass->get_filter_data($importFilterId);

		$import = $db->fetch1("
			SELECT * FROM
				import_tmp_".strtolower($importFilerData['IDENT'])."
			WHERE
				ID_".strtoupper($importFilerData['T_NAME'])." = '".mysql_real_escape_string($importId)."'
				AND FK_IMPORT_FILE = '".mysql_real_escape_string($importFileId)."'
			LIMIT 1
		");

		return $import;
	}



	public function fetchAllImportFilesByStatus($status = 1, $param = array()) {
		$db = $this->getDb();

		$where = "";
		$limit = "";

		if(isset($param['limit']) && $param['limit'] != null) { $limit .= " LIMIT '".$param['limit']."' "; }

		$importFiles = $db->fetch_table("
			SELECT * FROM
				import_file
			WHERE
				STATUS = '".mysql_real_escape_string($status)."'
				".$where." ".$limit."
		");

		return $importFiles;
	}

	public function fetchAllImportFilesByUserId($userId, $param = array()) {
		$db = $this->getDb();

		$where = "";
		$limit = "";

		if(isset($param['limit']) && $param['limit'] != null) { $limit .= " LIMIT '".$param['limit']."' "; }

		$importFiles = $db->fetch_table("
			SELECT * FROM
				import_file
			WHERE
				FK_USER = '".mysql_real_escape_string($userId)."'
				".$where."
		");

		return $importFiles;
	}

	public function fetchAllImportsByImportFileIdAndUserId($importFileId, $userId, $param = array()) {
		$db = $this->getDb();

		$importClass = new import();
		$importFilterId = $db->fetch_atom("SELECT FK_IMPORT_FILTER FROM `import_file` WHERE ID_IMPORT_FILE = '".mysql_real_escape_string($importFileId)."' AND FK_USER = '".mysql_real_escape_string($userId)."' ");
		$importFilerData = $importClass->get_filter_data($importFilterId);


		$where = "";
		$limit = "";
		$order = "";
		if(isset($param['status']) && $param['status'] != null) { $where .= " AND IMPORT_STATUS = '".$param['status']."' "; }
        if(isset($param['importable']) && $param['importable'] == true) { $where .= " AND (FK_PACKET_ORDER != '-1' OR FK_PACKET_ORDER IS NULL) "; }
		if(isset($param['limit']) && $param['limit'] != null && !isset($param['offset'])) { $limit .= " LIMIT ".$param['limit']." "; }
		if(isset($param['limit']) && $param['limit'] != null && isset($param['offset'])) { $limit .= " LIMIT ".$param['offset'].",".$param['limit']." "; }
		if(isset($param['sort']) && $param['sort'] != null) { $order .= " ORDER BY ".$param['sort']." "; }

		$import = $db->fetch_table("
			SELECT * FROM
				import_tmp_".strtolower($importFilerData['IDENT'])."
			WHERE
				FK_IMPORT_FILE = '".mysql_real_escape_string($importFileId)."'
				".$where." ".$order." ".$limit."
		");

		return $import;
	}

	public function countImportsByImportFileIdAndUserId($importFileId, $userId, $param = array()) {
		$db = $this->getDb();

		$importClass = new import();
		$importFilterId = $db->fetch_atom("SELECT FK_IMPORT_FILTER FROM `import_file` WHERE ID_IMPORT_FILE = '".mysql_real_escape_string($importFileId)."' AND FK_USER = '".mysql_real_escape_string($userId)."' ");

        $importFilerData = $importClass->get_filter_data($importFilterId);

        if($importFilerData === null) {
            return 0;
        }

		$where = "";
		$limit = "";
		$order = "";
		if(isset($param['status']) && $param['status'] != null) { $where .= " AND IMPORT_STATUS = '".$param['status']."' "; }
        if(isset($param['importable']) && $param['importable'] == true) { $where .= " AND (FK_PACKET_ORDER != '-1' OR FK_PACKET_ORDER IS NULL) "; }

		$c = $db->fetch_atom("
			SELECT COUNT(*) FROM
				import_tmp_".strtolower($importFilerData['IDENT'])."
			WHERE
				FK_IMPORT_FILE = '".mysql_real_escape_string($importFileId)."'
				".$where."
		");

		return $c;
	}

	public function updateImportStatus($importFileId, $importId, $status) {
		$db = $this->getDb();

		$importClass = new import();
		$importFilterId = $db->fetch_atom("SELECT FK_IMPORT_FILTER FROM `import_file` WHERE ID_IMPORT_FILE = '".mysql_real_escape_string($importFileId)."'");
		$importFilerData = $importClass->get_filter_data($importFilterId);

		$import = $db->querynow("
			UPDATE
				import_tmp_".strtolower($importFilerData['IDENT'])."
			SET
				IMPORT_STATUS = '".mysql_real_escape_string($status)."'
			WHERE
				ID_".strtoupper($importFilerData['T_NAME'])." = '".mysql_real_escape_string($importId)."'
				AND FK_IMPORT_FILE = '".mysql_real_escape_string($importFileId)."'
		");
	}

    /**
     * Aktualisiert die Importdatensätze für einen Benutzer bei Auswahl eines Anzeigenpaketes
     * Datensätze die im Kontingent liegen erhalten in FK_PACKET_ORDER die Vertragsnummer oder NULL
     * Datensätze die vermutlich nicht im Kontingent liegen erhalten -1
     *
     * @param $importFileId
     * @param $packetId
     */
    public function updateImportPacket($importFileId, $userId, $packetId = null) {
        global $nar_systemsettings, $ab_path;
        include_once "sys/lib.shop_kategorien.php";
		require_once $ab_path."sys/packet_management.php";

        $db = $this->getDb();
        $kat = new TreeCategories("kat", 1);
		$packets = PacketManagement::getInstance($db);
		$order = $packets->order_get($packetId);

        $importClass = new import();
        $importFilterId = $db->fetch_atom("SELECT FK_IMPORT_FILTER FROM `import_file` WHERE ID_IMPORT_FILE = '".mysql_real_escape_string($importFileId)."'");
        $importFilerData = $importClass->get_filter_data($importFilterId);

        $packetOk = false;
        if ($nar_systemsettings["MARKTPLATZ"]["FREE_ADS"]) {
        	$packetOk = true;
        } else {
	        if($packetId != null) {
	        	if ((($order->getPacketId() == PacketManagement::getType("ad_abo"))
	        		|| ($order->getPacketId() == PacketManagement::getType("ad_abo")))
	        		&& ($order->getCountUsed() < $order->getCountMax())) {
        			$packetOk = true;
	        	} else if (($order->getType() == "COLLECTION") || ($order->getType() == "MEMBERSHIP")) {
	        		$packetOk = $order->isAvailable("ad", 1);
	        	}
	        }
        }

        if ($packetOk) {
            $imports = $this->fetchAllImportsByImportFileIdAndUserId($importFileId, $userId, array(
                'status' => self::IMPORT_STATUS_VALIDATION_SUCCESS
            ));
            $packetImportIds = array();
            $resetImportIds = array();
			$wherePacketImport = array();

            $i = 0;
			if($nar_systemsettings["MARKTPLATZ"]["FREE_ADS"]) {

            } else {
                $arImportIdents = array();
                $arImportExistingAds = array();
                $countImportAds = count($imports);
                foreach ($imports as $import) {
                    if (!empty($import["IMPORT_IDENTIFIER"])) {
                        $arImportIdents[] = mysql_real_escape_string($import["IMPORT_IDENTIFIER"]);
                    } else {
                        $arImportIdents[] = "";
                    }
                }
                if (!empty($arImportIdents)) {
                    $queryExisting = "SELECT IMPORT_IDENTIFIER, ID_".strtoupper($importFilerData['T_NAME'])."
                        FROM `".$importFilerData['T_NAME']."`
                        WHERE IMPORT_IDENTIFIER IN ('".implode("', '", $arImportIdents)."')";
                    $arImportExistingAds = $db->fetch_nar($queryExisting);
                }

                $ar_available = $order->getPacketUsage(array_keys($arImportExistingAds));
                $countAvailable = $ar_available['ads_available'] + $ar_available['ads_required'];
                foreach($imports as $key => $import) {
                    $adImportIdent = $import['IMPORT_IDENTIFIER'];
                    $adId = (int)$import['ID_'.strtoupper($importFilerData['T_NAME'])];
                    if (array_key_exists($adImportIdent, $arImportExistingAds)) {
                        // Ad already exists
                        $adIdExisting = $arImportExistingAds[$adImportIdent];
                        if (in_array($adIdExisting, $ar_available["ads_used"]) || in_array($adIdExisting, $ar_available["ads_free"])) {
                            // Ad was already withdrawn from this packet or ad is free, continue with next ad
                            $packetImportIds[] = $adId;
                            continue;
                        } else if ($countAvailable-- > 0) {
                            // Ad already exists but uses another packet or none (disabled)
                            $packetImportIds[] = $adId;
                            continue;
                        } else {
                            // Failed!
                            $resetImportIds[] = $adId;
                        }
                    } else {
                        // Ad does not exist yet
                        if ($countAvailable-- > 0) {
                            $packetImportIds[] = $adId;
                        } else {
                            $resetImportIds[] = $adId;
                        }
                    }
                }

				if(count($packetImportIds) > 0) {
					$wherePacketImport[] = "ID_".strtoupper($importFilerData['T_NAME'])." IN (".implode(",", $packetImportIds).")";
				}
            }


            if ($nar_systemsettings["MARKTPLATZ"]["FREE_ADS"] || (count($packetImportIds) > 0)) {
            	$wherePacketImport[] = "FK_IMPORT_FILE = '".mysql_real_escape_string($importFileId)."'";
            	$wherePacketImport[] = "IMPORT_STATUS = '".self::IMPORT_STATUS_VALIDATION_SUCCESS."'";

                $db->querynow("
                    UPDATE
                        import_tmp_".strtolower($importFilerData['IDENT'])."
                    SET
                        FK_PACKET_ORDER = '".mysql_real_escape_string($packetId)."'
                    WHERE
                        ".implode(" AND ", $wherePacketImport)."

                ");
            }

            if(count($resetImportIds) > 0) {
                $a = $db->querynow("
                    UPDATE
                        import_tmp_".strtolower($importFilerData['IDENT'])."
                    SET
                        FK_PACKET_ORDER = '-1'
                    WHERE
                        ID_".strtoupper($importFilerData['T_NAME'])." IN (".implode(",", $resetImportIds).")
                        AND FK_IMPORT_FILE = '".mysql_real_escape_string($importFileId)."'
                ");
            }

        } else {
            $db->querynow("
                UPDATE
                    import_tmp_".strtolower($importFilerData['IDENT'])."
                SET
                    FK_PACKET_ORDER = '-1'
                WHERE
                    AND FK_IMPORT_FILE = '".mysql_real_escape_string($importFileId)."'
            ");
        }

    }

    public function removeImportFile($importFileId, $userId) {
        $db = $this->getDb();

        $importClass = new import();
        $importFilterId = $db->fetch_atom("SELECT FK_IMPORT_FILTER FROM `import_file` WHERE ID_IMPORT_FILE = '" . mysql_real_escape_string($importFileId) . "'");
        $importFilerData = $importClass->get_filter_data($importFilterId);

        $removeImport = $db->querynow("
            DELETE FROM
                import_tmp_".strtolower($importFilerData['IDENT'])."
            WHERE
                FK_IMPORT_FILE = '".mysql_real_escape_string($importFileId)."'
                AND FK_USER = '".mysql_real_escape_string($userId)."'
        ");

        $removeImportFile = $db->querynow("
            DELETE FROM
                import_file
            WHERE
                ID_IMPORT_FILE = '".mysql_real_escape_string($importFileId)."'
                AND FK_USER = '".mysql_real_escape_string($userId)."'
        ");

        return true;
    }

	public function removeImport($importFileId, $importId, $userId) {
		$db = $this->getDb();

		$importClass = new import();
		$importFilterId = $db->fetch_atom("SELECT FK_IMPORT_FILTER FROM `import_file` WHERE ID_IMPORT_FILE = '".mysql_real_escape_string($importFileId)."'");
		$importFilerData = $importClass->get_filter_data($importFilterId);

		$import = $db->querynow("
			DELETE FROM
				import_tmp_".strtolower($importFilerData['IDENT'])."
			WHERE
				ID_".strtoupper($importFilerData['T_NAME'])." = '".mysql_real_escape_string($importId)."'
				AND FK_IMPORT_FILE = '".mysql_real_escape_string($importFileId)."'
				AND FK_USER = '".mysql_real_escape_string($userId)."'
		");
	}


	public function updateImportFileStatus($importFileId, $status) {
		$db = $this->getDb();

		$import = $db->querynow("
			UPDATE
				import_file
			SET
				STATUS = '".mysql_real_escape_string($status)."'
			WHERE
				ID_IMPORT_FILE = '".mysql_real_escape_string($importFileId)."'
		");
	}



	/**
	 * Gibt die Kategorie Tabelle von einem Import File zurück
	 *
	 * @param int $importFileId
	 */
	public function getCategoryTableByImportFileId($importFileId) {
		$db = $this->getDb();
		$importClass = new import();
		$importFilterId = $db->fetch_atom("SELECT FK_IMPORT_FILTER FROM `import_file` WHERE ID_IMPORT_FILE = '".mysql_real_escape_string($importFileId)."'");
		$importFilerData = $importClass->get_filter_data($importFilterId);

		return $importFilerData['T_NAME'];
	}

	public function getImportIdentByImportFileId($importFileId) {
		$db = $this->getDb();
		$importClass = new import();
		$importFilterId = $db->fetch_atom("SELECT FK_IMPORT_FILTER FROM `import_file` WHERE ID_IMPORT_FILE = '".mysql_real_escape_string($importFileId)."'");
		$importFilerData = $importClass->get_filter_data($importFilterId);

		return $importFilerData['IDENT'];
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