<?php
/* ###VERSIONSBLOCKINLCUDE### */



class VendorManagement {
	private static $db;
    private static $langval = 128;
	private static $instance = null;
	protected $fieldsGroups;
	protected $fieldsSystemGroups;
	protected $vendorDataMaster;
	protected $vendorId;
	protected $vendorDataFull = null;

	/**
	 * Singleton
	 *
	 * @param ebiz_db $db
	 * @return VendorManagement
	 */
	public static function getInstance(ebiz_db $db) {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		self::setDb($db);

		return self::$instance;
	}

    public function adminAccept($vendorId) {
        $db = $this->getDb();
        $res = $db->querynow("UPDATE `vendor` SET MODERATED=1 WHERE ID_VENDOR=".(int)$vendorId);
        return $res["rsrc"];
    }

    public function adminAcceptUser($id_user) {
        global $db;
        $arEvents = $db->fetch_nar("SELECT ID_VENDOR, FK_USER FROM `vendor` WHERE FK_USER=".$id_user." AND MODERATED=0");
        foreach ($arEvents as $id_vendor => $fk_user) {
            $this->adminAccept($id_vendor);
        }
    }

    public function getData_VendorTableId(){
		global $db;
		$vendorTableId = (int)$db->fetch_atom(
			"SELECT ID_TABLE_DEF 
				FROM `table_def` 
			WHERE T_NAME='vendor_master'"
		);
		if ( $vendorTableId > 0 ) {
			return $vendorTableId;
		}
		return null;
    }

	/**
	 * Returns the vendor dataset as assoc array
	 * @return array
	 */
    public function getData_FieldsGroups() {
    	global $db;
		if ($this->fieldsGroups === null) {
			$vendorTableId = $this->getData_VendorTableId();
			if ( $vendorTableId !== null ) {
				$this->fieldsGroups = $db->fetch_col($q="
					SELECT ID_FIELD_GROUP 
						FROM `field_group` 
						WHERE FK_TABLE_DEF=".$vendorTableId);
			} else {
				$this->fieldsGroups = array();
			}
			$this->fieldsGroups[] = null;
		}
		return $this->fieldsGroups;
    }

	/**
	 * Returns a list of all fields with name, type and whether its required.
	 * @param   int $idFieldGroup   Field group to be read
	 * @return array    List of all fields with name, type and whether its required.
	 */
	public function getFields($idFieldGroup = null, $categories,$s_lang) {
		global $db;
		$langval = $GLOBALS["lang_list"][$s_lang]["BITVAL"];
		// Regular group, read from database
		$arFields = $db->fetch_table($q="
                SELECT
                    F_NAME, f.F_TYP, f.FK_FIELD_GROUP, f.FK_LISTE, f.IS_SPECIAL, IFNULL(kf.B_NEEDED,f.B_NEEDED) AS B_NEEDED, s.*
                FROM `kat` k
                LEFT JOIN `table_def` t ON t.T_NAME=k.KAT_TABLE
                LEFT JOIN `field_def` f ON f.FK_TABLE_DEF=t.ID_TABLE_DEF
                LEFT JOIN `kat2field` kf ON kf.FK_KAT=k.ID_KAT AND kf.FK_FIELD=f.ID_FIELD_DEF
                        LEFT JOIN `string_field_def` s ON s.S_TABLE='field_def' AND s.FK=f.ID_FIELD_DEF
                  AND s.BF_LANG=if(f.BF_LANG_FIELD_DEF & ".$langval.", ".$langval.", 1 << floor(log(f.BF_LANG_FIELD_DEF+0.5)/log(2)))
                WHERE k.ID_KAT IN ('".implode("','",$categories)."') AND kf.B_ENABLED=1 AND f.B_ENABLED=1
                    AND f.FK_FIELD_GROUP".($idFieldGroup === null ? " IS NULL" : "=".(int)$idFieldGroup)."
                GROUP BY f.ID_FIELD_DEF
                ORDER BY f.FK_FIELD_GROUP ASC, f.F_ORDER ASC");
		return $arFields;
	}

	/**
	 * Returns the article dataset as assoc array
	 * @param string|null $fieldName
	 * @return array|string|null
	 */
	public function getData_Vendor($fieldName = null,$vendorId) {
		$this->vendorId = $vendorId;
		if ($fieldName !== null) {
			$result = $this->getData_VendorMaster($fieldName);
			if ($result !== null) {
				return $result;
			}
			$result = $this->getData_VendorFull($fieldName);
			if ($result !== null) {
				return $result;
			}
			return null;
		} else {
			$result = array();
			if ($this->getData_VendorMaster() !== null) {
				$result = array_merge($result, $this->vendorDataMaster);
			}
			if ($this->getData_VendorMaster() !== null) {
				$result = array_merge($result, $this->vendorDataFull);
			}
			return $result;
		}
	}

	/**
	 * Returns the article dataset as assoc array
	 * @param string|null   $fieldName
	 * @return array|string|null
	 */
	public function getData_VendorFull($fieldName = null) {
		global $db;
		if (($this->vendorDataFull === null) && ($this->vendorId !== null)) {
			$vendorTable = $this->getData_VendorMaster("AD_TABLE");
			$arVendorFull = $db->fetch1("SELECT * FROM `vendor_master` WHERE ID_VENDOR_MASTER=".(int)$this->vendorId);
			if (is_array($arVendorFull)) {
				$this->vendorDataFull = $arVendorFull;
			}
		}
		if (is_array($this->vendorDataFull)) {
			if ($fieldName !== null) {
				// Get single field data
				return (array_key_exists($fieldName, $this->vendorDataFull) ? $this->vendorDataFull[$fieldName] : null);
			} else {
				return $this->vendorDataFull;
			}
		} else {
			return null;
		}
	}

	/**
	 * Returns the article dataset as assoc array
	 * @param string|null   $fieldName
	 * @return array|string|null
	 */
	public function getData_VendorMaster($fieldName = null) {
		global $db;
		if (($this->vendorDataMaster === null) && ($this->vendorId !== null)) {
			$arVendorMaster = $db->fetch1("SELECT * FROM `vendor_master` WHERE ID_VENDOR_MASTER=".(int)$this->vendorId);
			if (is_array($arVendorMaster)) {
				$this->vendorDataMaster = $arVendorMaster;
			}
		}
		if (is_array($this->vendorDataMaster)) {
			if ($fieldName !== null) {
				// Get single field data
				return (array_key_exists($fieldName, $this->vendorDataMaster) ? $this->vendorDataMaster[$fieldName] : null);
			} else {
				return $this->vendorDataMaster;
			}
		} else {
			return null;
		}
	}

    public function adminDecline($vendorId, $reason, $mail = true) {
        $db = $this->getDb();
        $res = $db->querynow("UPDATE `vendor` SET MODERATED=2, DECLINE_REASON='".mysql_real_escape_string($reason)."' WHERE ID_VENDOR=".(int)$vendorId);
        if ($mail) {
            // Notify user by email
            $arMailVendor = $this->fetchByVendorId($vendorId);
            $arMailVendor["REASON"] = (empty($reason) ? false : $reason);
            $arMailUser = $db->fetch1("SELECT * FROM `user` WHERE ID_USER=".$arMailVendor["FK_USER"]);
            sendMailTemplateToUser(0, $arMailUser["ID_USER"], "MODERATE_VENDOR_DECLINED", array_merge($arMailVendor, $arMailUser));
        }
        return $res["rsrc"];
    }

    public function adminDeclineUser($id_user, $reason) {
        global $db;
        $arEvents = $db->fetch_nar("SELECT ID_VENDOR, FK_USER FROM `vendor` WHERE FK_USER=".$id_user." AND MODERATED=0");
        foreach ($arEvents as $id_vendor => $fk_user) {
            $this->adminDecline($id_vendor, $reason, false);
        }
    }

    /**
     * @param $userId
     * @return bool
     */
	public function isUserVendorByUserId($userId) {
        $db = $this->getDb();

        $r = $db->fetch_atom("SELECT COUNT(*) FROM vendor WHERE FK_USER = '".mysql_real_escape_string($userId)."' AND STATUS = 1 AND MODERATED = 1");
        return ($r > 0);
    }


	public static function getDataTable($categoryId = null, ebiz_db $db = null, $langval = null) {        // Default settings
		if ( $db === null ) {
			$db = $GLOBALS["db"];
		}
		if ( $langval === null ) {
			$langval = $GLOBALS["langval"];
		}
		$s_lang = "de";
		foreach ( $GLOBALS['lang_list'] as $langIndex => $langCurrent ) {
			if ( $langCurrent["BITVAL"] == $langval ) {
				$s_lang = $langCurrent["ABBR"];
			}
		}
		/*
		 * Get target article table by category
		 */
		$vendorTable   = "vendor";
		$vendorTableId = null;
		if ( $categoryId > 0 ) {
			$arTable        = $db->fetch1( $q = "
                    SELECT k.ID_KAT, k.KAT_TABLE, t.ID_TABLE_DEF
                    FROM kat k
                    LEFT JOIN table_def t ON k.KAT_TABLE=t.T_NAME
                    WHERE ID_KAT=" . (int) $categoryId );
			$vendorTable   = $arTable['KAT_TABLE'];
			$vendorTableId = $arTable['ID_TABLE_DEF'];
		} else {
			$categoryId = $db->fetch_atom( "SELECT ID_KAT FROM `kat` WHERE LFT=1 AND ROOT=1" );
		}
		if ( $vendorTableId === null ) {
			$vendorTableId = $db->fetch_atom( "SELECT ID_TABLE_DEF FROM table_def WHERE T_NAME = 'vendor_master'" );
		}
		$masterTableShortcut = "v";
		//$masterTableShortcut = ( $vendorTable == "vendor" ? "v" : "vm" );

		/*
		 * Create data table
		 */
		$dataTable = new Api_DataTable($db, "vendor", $masterTableShortcut);

		/*
		 * Define joins
		 */
		if ($vendorTable != "vendor") {
			$dataTable->addTableJoin($vendorTable, "vt", "LEFT JOIN", $masterTableShortcut . ".ID_VENDOR = vt.`ID_" . strtoupper($vendorTable) . "`");
		}
		$dataTable->addTableJoin("country", "c", "LEFT JOIN", $masterTableShortcut . ".FK_COUNTRY = c.ID_COUNTRY");
		$dataTable->addTableJoinString("country", "c", "string", "sc", "LEFT JOIN", $langval);
		$dataTable->addTableJoin("vendor_place", "vp", "LEFT JOIN", $masterTableShortcut . ".ID_VENDOR = vp.FK_VENDOR");
		$dataTable->addTableJoin("vendor_category", "vc", "LEFT JOIN", $masterTableShortcut . ".ID_VENDOR = vc.FK_VENDOR");
		$dataTable->addTableJoin("kat", "k", "LEFT JOIN", "vc.FK_KAT = k.ID_KAT", array("vc"));
		$dataTable->addTableJoinString("kat", "k", "string_kat", "sk");
		$dataTable->addTableJoin("user", "u", "LEFT JOIN", $masterTableShortcut . ".FK_USER = u.ID_USER");
		$dataTable->addTableJoin("searchdb_index_".$s_lang, "sdbi", "LEFT JOIN", "sdbi.S_TABLE='vendor' AND ".$masterTableShortcut.".ID_VENDOR = sdbi.FK_ID");
		$dataTable->addTableJoin("searchdb_words_".$s_lang, "sdbw", "LEFT JOIN", "sdbw.ID_WORDS = sdbi.FK_WORDS", array("sdbi"));

		/*
		 * Define fields
		 */
		// Field for count queries
		$dataTable->addField(null, null, "COUNT(DISTINCT " . $masterTableShortcut . ".ID_VENDOR)", "RESULT_COUNT");
		// Vendor fields with user fallback
		$dataTable->addField(null, null, "IF(v.NAME != '', v.NAME, u.FIRMA)", "VENDOR_FIRMA");
		$dataTable->addField(null, null, "IF(v.PLZ != '', v.PLZ, u.PLZ)", "VENDOR_PLZ");
		$dataTable->addField(null, null, "IF(v.ORT != '', v.ORT, u.ORT)", "VENDOR_ORT");
		$dataTable->addField(null, null, "(SELECT V1 FROM string WHERE S_TABLE = 'country' AND FK = IF(v.FK_COUNTRY != '',v.FK_COUNTRY, u.FK_COUNTRY) AND BF_LANG = '".$langval."')", "VENDOR_COUNTRY");
		$dataTable->addField(null, null, "IF(v.TEL != '', v.TEL, u.TEL)", "VENDOR_TEL");
		$dataTable->addField(null, null, "IF(v.FAX != '', v.FAX, u.FAX)", "VENDOR_FAX");
		$dataTable->addField(null, null, "IF(v.URL != '', v.URL, u.URL)", "VENDOR_URL");
		// User fields with prefix "USER_"
		$dataTable->addField("u", "ID_USER", null, "USER_ID_USER");
		$dataTable->addField("u", "NAME", null, "USER_NAME");
		$dataTable->addField("u", "CACHE", null, "USER_CACHE");
		$dataTable->addField("u", "TOP_USER", null, "USER_TOP_USER", true);
		$dataTable->addField("u", "TOP_SELLER", null, "USER_TOP_SELLER");
		$dataTable->addField("u", "PROOFED", null, "USER_PROOFED");
		$dataTable->addField("u", "RATING");
		// Country fields with prefix "COUNTRY_
		$dataTable->addField("c", "CODE", null, "COUNTRY_CODE");
		// Field for random sorting
		$dataTable->addField(null, null, "RAND()", "RANDOM", true);
		// Field for sorting by number of comments
		$dataTable->addField(null, null, "(SELECT count(*) FROM `vendor_gallery` where FK_VENDOR = v.ID_VENDOR)", "COUNT_GALLERY");
		$dataTable->addField(null, null, "(SELECT count(*) FROM `calendar_event` where FK_REF = v.ID_VENDOR AND FK_REF_TYPE = 'user' AND PRIVACY = 1 AND IS_CONFIRMED = 1 AND STAMP_END >= now())", "COUNT_EVENTS");
		$dataTable->addField(null, null, "(SELECT AMOUNT FROM comment_stats WHERE FK = v.ID_VENDOR AND `TABLE` = 'vendor')", "COUNT_COMMENTS");
		$dataTable->addField(null, null, "(SELECT T1 FROM string_vendor WHERE S_TABLE = 'vendor' AND FK = v.ID_VENDOR AND BF_LANG = if(v.BF_LANG_VENDOR & ".$langval.", ".$langval.", 1 << floor(log(v.BF_LANG_VENDOR+0.5)/log(2))))", "VENDOR_DESCRIPTION");
		$dataTable->addField(null, null, "(SELECT substring(T1, 1, 200) FROM string_vendor WHERE S_TABLE = 'vendor' AND FK = v.ID_VENDOR AND BF_LANG = if(v.BF_LANG_VENDOR & ".$langval.", ".$langval.", 1 << floor(log(v.BF_LANG_VENDOR+0.5)/log(2))))", "VENDOR_SHORT_DESCRIPTION");
		// Master fields
		$dataTable->addFieldsFromDb($masterTableShortcut);
		// Sort fields

		$dataTable->setFieldSortable($masterTableShortcut, "CHANGED", true);
		$dataTable->setFieldSortable(null, "VENDOR_FIRMA", true);
		$dataTable->setFieldSortable(null, "COUNT_COMMENTS", true);
		$dataTable->setFieldSortable(null, "COUNT_EVENTS", true);
		$dataTable->setFieldSortable(null, "COUNT_GALLERY", true);


		// Define article fields
		$variantIndex = 0;
		$arSearchFields = array_merge(
			self::getSearchFields($vendorTableId, $categoryId, $db, $langval)
		);
		foreach ($arSearchFields as $searchFieldIndex => $searchField) {
			$searchFieldName = $searchField["F_NAME"];
			$searchFieldRequire = ($vendorTable == "vendor" ? array() : array("vt"));
			// Add field
			//$dataTable->addField("a", $searchFieldName, NULL, $searchFieldName);
			// Do not add where clauses for special (search)fields
			if ($searchField["IS_SPECIAL"]) {
				continue;
			}
			// Add where clause(s)
			switch ($searchField["F_TYP"]) {
				case 'TEXT':
					$dataTable->addWhereCondition("_EQUAL_" . $searchFieldName, 'vt.`' . mysql_real_escape_string($searchFieldName) . '` LIKE "%$1$%"', $searchFieldRequire);
					break;
				case 'DATE':
				case 'INT':
				case 'FLOAT':
					if ($searchField["B_SEARCH"] == 1) {
						$dataTable->addWhereCondition("_EQUAL_" . $searchFieldName, 'vt.`' . mysql_real_escape_string($searchFieldName) . '`="$1$"', $searchFieldRequire);
					} else if ($searchField["B_SEARCH"] == 2) {
						$dataTable->addWhereCondition("_RANGE_" . $searchFieldName, 'vt.`' . mysql_real_escape_string($searchFieldName) . '` BETWEEN "$1$" AND "$2$"', $searchFieldRequire);
						$dataTable->addWhereCondition("_GT_EQ_" . $searchFieldName, 'vt.`' . mysql_real_escape_string($searchFieldName) . '` >= "$1$"', $searchFieldRequire);
						$dataTable->addWhereCondition("_LT_EQ_" . $searchFieldName, 'vt.`' . mysql_real_escape_string($searchFieldName) . '` <= "$1$"', $searchFieldRequire);
					}
					break;
				case 'LIST':
					$dataTable->addWhereCondition("_EQUAL_" . $searchFieldName, 'vt.`' . mysql_real_escape_string($searchFieldName) . '`="$1$"', $searchFieldRequire);
					$dataTable->addWhereCondition("_IN_" . $searchFieldName, 'vt.`' . mysql_real_escape_string($searchFieldName) . '` IN ($1$)', $searchFieldRequire);
					break;
				case 'MULTICHECKBOX':
					$dataTable->addWhereCondition("_MULTI_" . $searchFieldName, 'vt.`' . mysql_real_escape_string($searchFieldName) . '` LIKE "%x$1$x%"', $searchFieldRequire, "OR");
					break;
				case 'MULTICHECKBOX_AND':
					$dataTable->addWhereCondition("_MULTI_" . $searchFieldName, 'vt.`' . mysql_real_escape_string($searchFieldName) . '` LIKE "%x$1$x%"', $searchFieldRequire, "AND");
					break;
			}
		}

		/*
		 * Define core conditions
		 */
		$dataTable->addWhereCondition("ID_VENDOR", $masterTableShortcut.".ID_VENDOR='$1$'");
        $dataTable->addWhereCondition("SEARCHVENDOR", "((sdbw.wort LIKE '%$1$%') OR (v.NAME LIKE '%$1$%') OR (u.FIRMA LIKE '%$1$%'))", array("sdbw", "u"));

		$dataTable->addWhereCondition("ORT", "(vp.ORT LIKE '$1$%' OR vp.PLZ LIKE '$1$%' OR ".$masterTableShortcut.".ORT LIKE '$1$%' OR ".$masterTableShortcut.".PLZ LIKE '$1$%')", array("vp"));
		$dataTable->addWhereCondition("PLZ", "(vp.PLZ LIKE '$1$%' OR ".$masterTableShortcut.".PLZ LIKE '$1$%')", array("vp"));
		$dataTable->addWhereCondition("FK_COUNTRY", $masterTableShortcut . ".FK_COUNTRY='$1$'");
		$dataTable->addWhereCondition("FK_USER", $masterTableShortcut . ".FK_USER='$1$'");
		$dataTable->addWhereCondition("NAME_", "u.NAME='$1$'", array("u"));
		$dataTable->addWhereCondition("FK_KAT_IN", "vc.FK_KAT IN $1$", array("vc"));
		$dataTable->addWhereCondition("GEO_RECT", "
				vp.LATITUDE BETWEEN '$1$' AND '$2$' AND vp.LONGITUDE BETWEEN '$3$' AND '$4$'
				OR
				".$masterTableShortcut . ".LATITUDE BETWEEN '$1$' AND '$2$' AND " . $masterTableShortcut . ".LONGITUDE BETWEEN '$3$' AND '$4$'", array("vp"));
		$dataTable->addWhereCondition("GEO_CIRCLE", "
                (
                    6368 * SQRT(ABS(2*(1-cos(RADIANS(" . $masterTableShortcut . ".LATITUDE)) *
              	    cos($1$) * (sin(RADIANS(" . $masterTableShortcut . ".LONGITUDE)) *
              	    sin($2$) + cos(RADIANS(" . $masterTableShortcut . ".LONGITUDE)) *
              	    cos($2$)) - sin(RADIANS(" . $masterTableShortcut . ".LATITUDE)) * sin($1$))))
              	) <= $3$
              	OR
                (
                    6368 * SQRT(ABS(2*(1-cos(RADIANS(vp.LATITUDE)) *
              	    cos($1$) * (sin(RADIANS(vp.LONGITUDE)) *
              	    sin($2$) + cos(RADIANS(vp.LONGITUDE)) *
              	    cos($2$)) - sin(RADIANS(vp.LATITUDE)) * sin($1$))))
              	) <= $3$", array("vp"));
		$dataTable->addWhereCondition("TOP", "u.TOP_USER='$1$'", array("u"));
		$dataTable->addWhereCondition("STATUS", $masterTableShortcut . ".STATUS='$1$'");
		$dataTable->addWhereCondition("MODERATED", $masterTableShortcut . ".MODERATED='$1$'");

		// Plugin event
		$eventMarketViewParams = new Api_Entities_EventParamContainer(array(
			"vendorTable"           => $vendorTable,
			"vendorMasterShortcut"  => $masterTableShortcut,
			"categoryId"            => $categoryId,
			"dataTable"		        => $dataTable
		));
		Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::VENDOR_AD_GET_DATATABLE, $eventMarketViewParams);

		return $dataTable;

	}

	/**
	 * Get all search fields for the given table/category
	 * @param int       $tableDefId
	 * @param int       $categoryId
	 * @param ebiz_db   $db
	 * @param int|null  $langval
	 * @return array
	 */
	public static function getSearchFields($tableDefId, $categoryId, ebiz_db $db = null, $langval = null) {
		// Default settings
		if ($db === null) {
			$db = $GLOBALS["db"];
		}
		if ($langval === null) {
			$langval = $GLOBALS["langval"];
		}
		// Query result
		return $db->fetch_table("
      			SELECT f.ID_FIELD_DEF, f.F_NAME, f.IS_SPECIAL, f.F_TYP, f.B_SEARCH, f.FK_LISTE, s.V1, s.V2, s.T1
      			FROM field_def f
      			LEFT JOIN `string_field_def` s on s.S_TABLE='field_def' and s.FK=f.ID_FIELD_DEF
      			  and s.BF_LANG=if(f.BF_LANG_FIELD_DEF & ".$langval.", ".$langval.", 1 << floor(log(f.BF_LANG_FIELD_DEF+0.5)/log(2)))
      			LEFT JOIN `kat2field` kf ON kf.FK_FIELD=f.ID_FIELD_DEF AND kf.FK_KAT=" . $categoryId . "
      			WHERE f.FK_TABLE_DEF=" . $tableDefId . " AND f.B_SEARCH IN(1,2) AND (f.IS_MASTER=1 OR kf.B_ENABLED=1)"
		);
	}

    /**
     * Baut den WHERE Teil und liefert auch die notwendigen joins.
     * Return ist ein Array mit 0 => sqlWhere und 1 => sqlJoin
     *
     * @param  array  $param
     * @param  int $status
     * @return array
     */
    public function buildWhereQueryWithJoins($param, $status = 1)
    {
        $db = $this->getDb();

        $sqlWhere = "";
        $sqlJoin = "";

        if(isset($param['ID_VENDOR']) && $param['ID_VENDOR'] != null ) { $sqlWhere .= " AND v.ID_VENDOR = '".mysql_real_escape_string($param['ID_VENDOR'])."' "; }
        if(isset($param['SEARCHVENDOR']) && $param['SEARCHVENDOR'] != null) { $sqlWhere .= " AND ((sw.wort LIKE '%".mysql_real_escape_string($param['SEARCHVENDOR'])."%') OR (v.NAME LIKE '%".mysql_real_escape_string($param['SEARCHVENDOR'])."%') OR (u.FIRMA LIKE '%".mysql_real_escape_string($param['SEARCHVENDOR'])."%')) "; }
        if(isset($param['ORT']) && $param['ORT'] != null && (!isset($param['LATITUDE']) || $param['LATITUDE'] == "")) { $sqlWhere .= " AND (p.ORT LIKE '".mysql_real_escape_string($param['ORT'])."%' OR p.PLZ LIKE '".mysql_real_escape_string($param['ORT'])."%' OR v.ORT LIKE '".mysql_real_escape_string($param['ORT'])."%' OR v.PLZ LIKE '".mysql_real_escape_string($param['ORT'])."%') "; }
        if(isset($param['PLZ']) && $param['PLZ'] != null && (!isset($param['LATITUDE']) || $param['LATITUDE'] == "")) { $sqlWhere .= " AND (p.PLZ LIKE '".mysql_real_escape_string($param['PLZ'])."%' OR v.PLZ LIKE '".mysql_real_escape_string($param['PLZ'])."%') "; }
        if(isset($param['FK_COUNTRY']) && $param['FK_COUNTRY'] != null && (!isset($param['LATITUDE']) || $param['LATITUDE'] == "")) { $sqlWhere .= " AND v.FK_COUNTRY = '".mysql_real_escape_string($param['FK_COUNTRY'])."' "; }
        if(isset($param['FK_USER']) && $param['FK_USER'] != null && (!isset($param['LATITUDE']) || $param['LATITUDE'] == "")) { $sqlWhere .= " AND v.FK_USER = '".mysql_real_escape_string($param['FK_USER'])."' "; }
        if(!isset($param['FK_USER']) && isset($param['NAME_']) && $param['NAME_'] != null && (!isset($param['LATITUDE']) || $param['LATITUDE'] == "")) { $sqlWhere .= " AND u.NAME = '".mysql_real_escape_string($param['NAME_'])."' "; }
        if(isset($param['CATEGORY']) && $param['CATEGORY'] != null) {
            $row_kat = $db->fetch1("SELECT LFT,RGT,ROOT FROM `kat` WHERE ID_KAT=" . $param['CATEGORY']);
            $ids_kats = $db->fetch_nar("
              SELECT ID_KAT
                FROM `kat`
              WHERE
                (LFT >= " . $row_kat["LFT"] . ") AND
                (RGT <= " . $row_kat["RGT"] . ") AND
                (ROOT = " . $row_kat["ROOT"] . ")
            ");

            $sqlWhere .= " AND c.FK_KAT IN (".mysql_real_escape_string(implode(',', array_keys($ids_kats))).") ";
        }
        if(isset($param['LATITUDE']) && $param['LATITUDE'] != "" && isset($param['LONGITUDE']) && $param['LONGITUDE'] != "" && isset($param['LU_UMKREIS']) && $param['LU_UMKREIS'] != "" ) {
            $radius = 6368;

            $rad_b = $param['LATITUDE'];
            $rad_l = $param['LONGITUDE'];

            $rad_l = $rad_l / 180 * M_PI;
            $rad_b = $rad_b / 180 * M_PI;

            $sqlWhere .= " AND ((
                    " . $radius . " * SQRT(ABS(2*(1-cos(RADIANS(p.LATITUDE)) *
                     cos(" . $rad_b . ") * (sin(RADIANS(p.LONGITUDE)) *
                     sin(" . $rad_l . ") + cos(RADIANS(p.LONGITUDE)) *
                     cos(" . $rad_l . ")) - sin(RADIANS(p.LATITUDE)) * sin(" . $rad_b . "))))
                ) <= " . $db->fetch_atom("select `value` from lookup where ID_LOOKUP =" . $param['LU_UMKREIS'])."
                OR (
                    " . $radius . " * SQRT(ABS(2*(1-cos(RADIANS(v.LATITUDE)) *
                     cos(" . $rad_b . ") * (sin(RADIANS(v.LONGITUDE)) *
                     sin(" . $rad_l . ") + cos(RADIANS(v.LONGITUDE)) *
                     cos(" . $rad_l . ")) - sin(RADIANS(v.LATITUDE)) * sin(" . $rad_b . "))))
                ) <= " . $db->fetch_atom("select `value` from lookup where ID_LOOKUP =" . $param['LU_UMKREIS']).")";
        }

        if(isset($param['BF_LANG']) && $param['BF_LANG'] != null) { $langval = $param['BF_LANG']; } else { $langval = $this->getLangval(); }

        if(isset($param['AD']) && is_array($param['AD']) && ((int)$param['AD']['FK_KAT'] > 0)) {
            $articleTable = $db->fetch_atom("SELECT k.KAT_TABLE FROM kat k LEFT JOIN table_def t ON k.KAT_TABLE=t.T_NAME WHERE ID_KAT=" . (int)$param['AD']['FK_KAT']);

            // ID's der Unterkategorien holen
            $row_kat = $db->fetch1("SELECT LFT,RGT,ROOT FROM `kat` WHERE ID_KAT=".(int)$param['AD']['FK_KAT']);
            $ids_kats = $db->fetch_nar("
              SELECT ID_KAT
                FROM `kat`
              WHERE
                (LFT >= ".$row_kat["LFT"].") AND
                (RGT <= ".$row_kat["RGT"].") AND
                (ROOT = ".$row_kat["ROOT"].")
              ");
            $ids_kats = "(".implode(",",array_keys($ids_kats)).")";
            // Nur in aktueller Kategorie und deren Unterkategorien suchen
            $sqlWhere .= " AND a.FK_KAT IN ".$ids_kats." AND a.STATUS&3=1 AND (a.DELETED=0) ";
            if(is_array($param['AD']['SEARCH'])) {
                foreach($param['AD']['SEARCH'] as $key=>$sval) {
                    $sqlWhere .= " AND ".$sval." ";
                }
            }

            $sqlJoin .= " JOIN `".$articleTable."` a ON a.FK_USER = u.ID_USER ";
        }
        if(isset($param['TOP'])) {
            // Nur Top-User
            $sqlWhere .= " AND u.TOP_USER=1 ";
        }

        if ($status != null) {
            $sqlWhere .= "AND v.STATUS = " . $status;
            if ($status == 1) {
                $sqlWhere .= " AND v.MODERATED=1";
            }
        } else {
            if (isset($param['STATUS']) && ($param['STATUS'] != "")) {
                $sqlWhere .= " AND v.STATUS=".(int)$param['STATUS'];
            }
            if (isset($param['MODERATED']) && ($param['MODERATED'] != "")) {
                $sqlWhere .= " AND v.MODERATED=".(int)$param['MODERATED'];
            }
        }

        return array($sqlWhere, $sqlJoin);
    }


    public function fetchAllByParam($param, $status = 1) {
        $db = $this->getDb();
        $langval = $this->getLangval();
        $query = self::getQueryByParams($param, $status, $db, $langval);
        $query->addFields(array(
	        "v.CHANGED" => "CHANGED",
	        "v.ID_VENDOR" => "ID_VENDOR",
	        "v.LOGO" => "VENDOR_LOGO",
	        "v.STATUS" => "STATUS",
	        "v.MODERATED" => "MODERATED",
	        "v.ALLOW_COMMENTS" => "ALLOW_COMMENTS",
	        "VENDOR_FIRMA" => "VENDOR_FIRMA",
	        "VENDOR_PLZ" => "VENDOR_PLZ",
	        "VENDOR_ORT" => "VENDOR_ORT",
	        "VENDOR_COUNTRY" => "VENDOR_COUNTRY",
	        "VENDOR_TEL" => "VENDOR_TEL",
	        "VENDOR_FAX" => "VENDOR_FAX",
	        "VENDOR_URL" => "VENDOR_URL",
	        "USER_ID_USER" => "USER_ID_USER",
	        "USER_NAME" => "USER_NAME",
	        "USER_CACHE" => "USER_CACHE",
	        "USER_TOP_USER" => "USER_TOP_USER",
	        "USER_TOP_SELLER" => "USER_TOP_SELLER",
	        "USER_PROOFED" => "USER_PROOFED",
			"COUNTRY_CODE" => "COUNTRY_CODE",
	        "COUNT_GALLERY" => "COUNT_GALLERY",
	        "COUNT_EVENTS" => "COUNT_EVENTS",
	        "COUNT_COMMENTS" => "COUNT_COMMENTS",
	        "VENDOR_DESCRIPTION" => "VENDOR_DESCRIPTION",
	        "VENDOR_SHORT_DESCRIPTION" => "VENDOR_SHORT_DESCRIPTION",
	        "RATING" => "RATING"
        ));
	    $query->addFields("vt.*");
	    if(isset($param['LIMIT']) && $param['LIMIT'] != null) {
		    if(isset($param['OFFSET']) && $param['OFFSET'] != null) {
		    	$query->setLimit($param["LIMIT"], $param["OFFSET"]);
		    } else {
			    $query->setLimit($param["LIMIT"]);
		    }
	    }
	    if(isset($param['SORT']) && isset($param['SORT_DIR'])) {
		    if ($param['SORT'] == "STANDARD") {
			    $query->addSortField("u.TOP_USER", "DESC");
			    $query->addSortField("v.CHANGED", "DESC");
		    } else {
			    $query->addSortField($param['SORT'], $param['SORT_DIR']);
		    }
	    }
	    if (in_array("sdbi", $query->getQueryJoins())) {
		    $query->addGroupField("v.ID_VENDOR");
	    }
        return $query->fetchTable();
    }

	/**
	 * @param $searchData
	 * @param int $status
	 * @param ebiz_db|null $db
	 * @param null $langval
	 *
	 * @return Api_DataTableQuery
	 */
	public static function getQueryByParams($searchData, $status = 1, ebiz_db $db = null, $langval = null) {
		// Default settings
		if ($db === null) {
			$db = $GLOBALS["db"];
		}
		if ($langval === null) {
			$langval = $GLOBALS["langval"];
		}
		$s_lang = $GLOBALS["s_lang"];
		foreach ($GLOBALS["lang_list"] as $langAbbr => $arLang) {
			if ($arLang["BITVAL"] == $langval) {
				$s_lang = $langAbbr;
				break;
			}
		}
		$idCategory = null;
		if ( isset( $searchData['CATEGORY'] ) && $searchData['CATEGORY'] != null ) {
			$idCategory = (int)$searchData['CATEGORY'];
		}

	    $dataTable      = self::getDataTable($idCategory, $db, $langval);
	    $dataTableQuery = $dataTable->createQuery();
	    if ( ! empty( $searchData["ID_VENDOR"] ) ) {
		    $dataTableQuery->addWhereCondition( "ID_VENDOR", $searchData["ID_VENDOR"] );
	    }
	    if ( ! empty( $searchData["SEARCHVENDOR"] ) ) {
		    $dataTableQuery->addWhereCondition( "SEARCHVENDOR", $searchData["SEARCHVENDOR"] );
	        unset( $searchData["SEARCHVENDOR"] );}
	    if ( ! isset( $searchData['LATITUDE'] ) || $searchData['LATITUDE'] == "" ) {
		    if ( ! empty( $searchData["ORT"] ) ) {
			    $dataTableQuery->addWhereCondition( "ORT", $searchData["ORT"] );
		    }
		    if ( ! empty( $searchData["PLZ"] ) ) {
			    $dataTableQuery->addWhereCondition( "PLZ", $searchData["PLZ"] );
		    }
		    if ( ! empty( $searchData["FK_COUNTRY"] ) ) {
			    $dataTableQuery->addWhereCondition( "FK_COUNTRY", $searchData["FK_COUNTRY"] );
		    }
	    }
	    if ( ! empty( $searchData["FK_USER"] ) ) {
		    $dataTableQuery->addWhereCondition( "FK_USER", $searchData["FK_USER"] );
	    }
	    if ( $idCategory !== null && $idCategory != 676 ) {//676 is root category of all vendors
		    $row_kat  = $db->fetch1( "SELECT LFT,RGT,ROOT FROM `kat` WHERE ID_KAT=" . $idCategory );
		    $ids_kats = $db->fetch_nar( "
                  SELECT ID_KAT
                    FROM `kat`
                  WHERE
                    (LFT >= " . $row_kat["LFT"] . ") AND
                    (RGT <= " . $row_kat["RGT"] . ") AND
                    (ROOT = " . $row_kat["ROOT"] . ")
                " );
		    $ids_kats = "(" . implode( ",", array_keys( $ids_kats ) ) . ")";
		    $dataTableQuery->addWhereCondition( "FK_KAT_IN", $ids_kats );
	    }
	    if ( ! empty( $searchData['LU_UMKREIS'] ) && ! empty( $searchData['LONGITUDE'] ) && ! empty( $searchData['LATITUDE'] ) ) {
		    if ( is_array( $searchData['LONGITUDE'] ) && is_array( $searchData['LATITUDE'] ) ) {
			    // Umkreissuche (ausschnitt/rechteck)
			    $latMin = min( $searchData['LATITUDE'][0], $searchData['LATITUDE'][1] );
			    $latMax = max( $searchData['LATITUDE'][0], $searchData['LATITUDE'][1] );
			    $lngMin = min( $searchData['LONGITUDE'][0], $searchData['LONGITUDE'][1] );
			    $lngMax = max( $searchData['LONGITUDE'][0], $searchData['LONGITUDE'][1] );
			    $dataTableQuery->addWhereCondition( "GEO_RECT", array( $latMin, $latMax, $lngMin, $lngMax ) );
		    } else {
			    // Umkreissuche (klassisch/kreis)
			    $dataTableQuery->addWhereCondition( "GEO_CIRCLE", array(
				    ( $searchData['LATITUDE'] / 180 * M_PI ),
				    ( $searchData['LONGITUDE'] / 180 * M_PI ),
				    $db->fetch_atom( "select `value` from lookup where ID_LOOKUP =" . $searchData['LU_UMKREIS'] )
			    ) );
		    }
		    unset( $searchData["LATITUDE"] );
		    unset( $searchData["LONGITUDE"] );
		    unset( $searchData["LU_UMKREIS"] );
		    unset( $searchData["FK_COUNTRY"] );
	    } else if ( ! empty( $searchData['LU_UMKREIS'] ) && ( ! empty( $searchData["ZIP"] ) || ! empty( $searchData["CITY"] ) ) ) {
		    // Umkreissuche (klassisch/kreis)
		    $countryAsName = $db->fetch_atom( "SELECT V1 FROM `string` WHERE S_TABLE='country' AND BF_LANG=" . $langval . " AND FK=" . (int) $searchData["FK_COUNTRY"] );
		    $geoResult     = Geolocation_Generic::getGeolocationCached( $searchData["STREET"], $searchData["ZIP"], $searchData["CITY"], $countryAsName );
		    if ( is_array( $geoResult ) ) {
			    $dataTableQuery->addWhereCondition( "GEO_CIRCLE", array(
				    ( $geoResult['LATITUDE'] / 180 * M_PI ),
				    ( $geoResult['LONGITUDE'] / 180 * M_PI ),
				    $db->fetch_atom( "select `value` from lookup where ID_LOOKUP =" . $searchData['LU_UMKREIS'] )
			    ) );
		    }
	    }
	    if ( ! empty( $searchData["TOP"] ) ) {
		    $dataTableQuery->addWhereCondition( "TOP", 1 );
	    }
	    if ( $status != null ) {
		    $dataTableQuery->addWhereCondition( "STATUS", $status );
		    if ( $status == 1 ) {
			    $dataTableQuery->addWhereCondition( "MODERATED", 1 );
		    }
	    } else {
		    if ( isset( $searchData['STATUS'] ) && ( $searchData['STATUS'] != "" ) ) {
			    $dataTableQuery->addWhereCondition( "STATUS", $searchData['STATUS'] );
		    }
		    if ( isset( $searchData['MODERATED'] ) && ( $searchData['MODERATED'] != "" ) ) {
			    $dataTableQuery->addWhereCondition( "MODERATED", $searchData['MODERATED'] );
		    }
	    }
	    // Add where conditions (default)
	    foreach ( $searchData as $searchDataKey => $searchDataValue ) {
		    if ( ( $searchDataValue === null ) || ( $searchDataValue == "" ) ) {
			    // Skip empty search fields
			    continue;
		    }
		    $whereAdded = $dataTableQuery->addWhereCondition( $searchDataKey, ( ! is_array( $searchDataValue ) ? array( $searchDataValue ) : $searchDataValue ) );
		    if ( ! $whereAdded ) {
			    // No matching condition found, try article field conditions
			    if ( is_array( $searchDataValue ) ) {
				    // Search value is array, do range search
				    $searchFrom = "";
				    $searchTo   = "";
				    if ( array_key_exists( "VON", $searchDataValue ) && array_key_exists( "BIS", $searchDataValue ) ) {
					    $searchFrom = $searchDataValue["VON"];
					    $searchTo   = $searchDataValue["BIS"];
				    } else if ( array_key_exists( 0, $searchDataValue ) && array_key_exists( 1, $searchDataValue ) ) {
					    $searchFrom = $searchDataValue[0];
					    $searchTo   = $searchDataValue[1];
				    } else if ( array_key_exists( "VON", $searchDataValue ) ) {
					    $searchFrom = $searchDataValue["VON"];
				    } else if ( array_key_exists( 0, $searchDataValue ) ) {
					    $searchFrom = $searchDataValue[0];
				    } else if ( array_key_exists( "BIS", $searchDataValue ) ) {
					    $searchTo = $searchDataValue["BIS"];
				    }
				    if ( ( $searchFrom != "" ) && ( $searchTo != "" ) ) {
					    $whereAdded = $dataTableQuery->addWhereCondition( "_RANGE_" . $searchDataKey, array(
						    $searchFrom,
						    $searchTo
					    ) );
				    } else if ( $searchFrom != "" ) {
					    $whereAdded = $dataTableQuery->addWhereCondition( "_GT_EQ_" . $searchDataKey, array( $searchFrom ) );
				    } else if ( $searchTo != "" ) {
					    $whereAdded = $dataTableQuery->addWhereCondition( "_LT_EQ_" . $searchDataKey, array( $searchTo ) );
				    }
				    if ( ! $whereAdded ) {
					    // Try to use an "in" condition
					    $valueListEscaped = array();
					    foreach ( $searchDataValue as $searchDataValueIndex => $searchDataValueContent ) {
						    $valueListEscaped[] = '"' . mysql_real_escape_string( $searchDataValueContent ) . '"';
					    }
					    $whereAdded = $dataTableQuery->addWhereCondition( "_IN_" . $searchDataKey, array( implode( ", ", $valueListEscaped ) ) );
				    }
				    if ( ! $whereAdded ) {
					    // Try to add as multiple where conditions
					    $searchValueMulti = array();
					    foreach ( $searchDataValue as $searchDataValueIdx => $searchDataValueCur ) {
						    $searchValueMulti[] = array( $searchDataValueCur );
					    }
					    $whereAdded = $dataTableQuery->addWhereCondition( "_MULTI_" . $searchDataKey, $searchValueMulti );
				    }
			    } else {
				    // Search value is string/number
				    $whereAdded = $dataTableQuery->addWhereCondition( "_EQUAL_" . $searchDataKey, array( $searchDataValue ) );
			    }
		    }
	    }
	    return $dataTableQuery;
    }

    public function countByParam($param, $status = 1) {
	    $db = $this->getDb();
	    $langval = $this->getLangval();
	    $query = self::getQueryByParams($param, $status, $db, $langval);
	    return $query->fetchCount();
	}

    /**
     * Holt einen Anbieter anhand einer Benutzer Id
     *
     * @throws Exception
     * @param $userId
     * @return assoc
     */
    public function fetchByUserId($userId) {
        $db = $this->getDb();
        $langval = $this->getLangval();
        $userExists = 0;
        if ($userId > 0) {
            $userExists = $db->fetch_atom("SELECT COUNT(*) FROM `user` WHERE ID_USER=".$userId);
        }
        
        if ($userExists) {
            $this->createVendorDbTableIfNotExistByUserId($userId);
    
            $vendor = $db->fetch1("
                SELECT
                    v.*,
                    sc.V1 AS COUNTRY,
                    vm.*
                FROM vendor v
                JOIN user u ON u.ID_USER = v.FK_USER
                LEFT JOIN string sc ON sc.FK = IF(v.FK_COUNTRY != '', v.FK_COUNTRY, u.FK_COUNTRY)
                    AND sc.S_TABLE = 'country' AND sc.BF_LANG = '".$langval."'
                LEFT JOIN vendor_master vm ON vm.ID_VENDOR_MASTER = v.ID_VENDOR
                WHERE FK_USER = '".mysql_real_escape_string($userId)."'"
            );
    
            return $vendor;
        } else {
            return null;
        }
    }

    /**
     * Holt einen Anbieter anhand einer Vendor Id
     *
     * @throws Exception
     * @param $userId
     * @return assoc
     */
    public function fetchByVendorId($vendorId) {
        $db = $this->getDb();

        $langval = $this->getLangval();

        $vendor = $db->fetch1($x = "
            SELECT v.CHANGED,
                v.ID_VENDOR,
                v.FK_USER,
                v.LATITUDE,
                v.LONGITUDE,
                v.STATUS,
                v.LOGO,
                v.BUSINESS_HOURS,
                v.ALLOW_COMMENTS,
                u.NAME AS USER_NAME,
                u.ID_USER AS USER_ID,
                IF(v.STRASSE != '', v.STRASSE, u.STRASSE) AS STRASSE,
                IF(v.PLZ != '', v.PLZ, u.PLZ) AS PLZ,
                IF(v.ORT != '', v.ORT, u.ORT) AS ORT,
                IF(v.PLZ != '', v.PLZ, u.PLZ) AS PLZ,
                IF(v.TEL != '', v.TEL, u.TEL) AS TEL,
                IF(v.FAX != '', v.FAX, u.FAX) AS FAX,
                IF(v.URL != '', v.URL, u.URL) AS URL,
                IF(v.NAME != '', v.NAME, u.FIRMA) AS FIRMA,
                sc.V1 AS COUNTRY,
                (SELECT count(*) FROM `vendor_gallery` where FK_VENDOR = v.ID_VENDOR) as COUNT_GALLERY,
                (SELECT count(*) FROM `calendar_event` where FK_REF = v.ID_VENDOR AND FK_REF_TYPE = 'user' AND PRIVACY = 1 AND IS_CONFIRMED = 1 AND STAMP_END >= now()) as COUNT_EVENTS,
                (SELECT AMOUNT FROM comment_stats WHERE FK = v.ID_VENDOR AND `TABLE` = 'vendor') as COUNT_COMMENTS,
                vm.*
            FROM
                vendor v
            JOIN user u ON u.ID_USER = v.FK_USER
            LEFT JOIN string sc ON sc.FK = IF(v.FK_COUNTRY != '', v.FK_COUNTRY, u.FK_COUNTRY)
                AND sc.S_TABLE = 'country' AND sc.BF_LANG = '".$langval."'
            LEFT JOIN vendor_master vm ON vm.ID_VENDOR_MASTER = v.ID_VENDOR
            WHERE
                v.ID_VENDOR = '".mysql_real_escape_string($vendorId)."'
            ");

        return $vendor;
    }

    /**
     * @param $vendor
     * @return assoc
     */
    public function extendSingle(&$vendor) {
        $business_hours = @json_decode($vendor['BUSINESS_HOURS'], true);
        if (!is_array($business_hours)) {
            $business_hours = [];
        }
        $weekday_today = date('N')-1;
        foreach ($business_hours as $weekday => $weekdayHours) {
            $vendor['BUSINESS_HOURS_'.$weekday] = $weekdayHours;
            if($weekday == $weekday_today) {
                $vendor['BUSINESS_HOURS_'.$weekday.'_ACTIVE'] = 1;
                $vendor['BUSINESS_HOURS_TODAY'] = $weekdayHours;
            } else {
                $vendor['BUSINESS_HOURS_'.$weekday.'_ACTIVE'] = 0;
            }
        }
    }

    /**
     * @param $vendorList
     * @return assoc
     */
    public function extendList(&$vendorList) {
        foreach ($vendorList as $vendorIndex => $vendorDetails) {
            self::extendSingle($vendorList[$vendorIndex]);
        }
        return $vendorList;
    }

    /**
     * Speichert einen Vendor Datensatz anhand der UserId
     *
     * @param array $vendor
     * @param int $userId
     * @return bool
     */
    public function saveVendorByUserId($vendor, $userId) {
    	global $ab_path, $nar_systemsettings;
        //eventlog("info", "Vendor debug: ", "saveVendorByUserId(".$vendor.", ".$userId.")");
        $db = $this->getDb();
        $sqlSet = array();
        $validation = true;

        $tmp = $this->fetchByUserId($userId);

		$land = $db->fetch_atom("SELECT V1 FROM `string` WHERE S_TABLE='country' AND FK=".(int)$vendor["FK_COUNTRY"]." AND BF_LANG=128");
        $geoCoordinates = Geolocation_Generic::getGeolocationCached($vendor["STRASSE"], $vendor["PLZ"], $vendor["ORT"], $land);

        if (($geoCoordinates !== null) && ($geoCoordinates !== false)) {
        	// Erfolg! Geo-Koordinaten übernehmen
        	$vendor["LATITUDE"] = $geoCoordinates["LATITUDE"];
        	$vendor["LONGITUDE"] = $geoCoordinates["LONGITUDE"];
        } else {
        	eventlog("error", "Anbieter: Fehler beim Auflösen einer Adresse!", $vendor["STRASSE"]." ".$vendor["PLZ"]." ".$vendor["ORT"].", ".$land);
        }

        $vendor['CHANGED']=date("Y-m-d H:i:s");
        if ($nar_systemsettings["MARKTPLATZ"]["MODERATE_VENDORS"]) {
            $userAutoConfirm = $db->fetch_atom("SELECT AUTOCONFIRM_VENDORS FROM `user` WHERE ID_USER=".$userId);
            $vendor['MODERATED'] = ($userAutoConfirm ? 1 : 0);
        } else {
            $vendor['MODERATED'] = 1;
        }
        if(isset($vendor['STATUS']) && $vendor['STATUS'] == '1') { $vendor['STATUS'] = 1; } else { $vendor['STATUS'] = 0; }
        if(isset($vendor['URL']) && $vendor['URL'] != "" && !preg_match("/^https?\:\/\//", $vendor['URL'])) { $vendor['URL'] = 'http://'.$vendor['URL']; }
        $vendor['ID_VENDOR'] = $tmp['ID_VENDOR'];
        $vendor['FK_USER'] = $userId;
        // Strip invalid html

        $vendorLanguages = array();
        if(is_array($vendor['T1'])) {
             foreach($vendor['T1'] as $lang => $value) {
                 $vendorLanguages[$lang] = $vendor;
                 // Remove invalid HTML before saving
                 $vendorLanguages[$lang]['T1'] = strip_tags($value, $nar_systemsettings["MARKTPLATZ"]["HTML_ALLOWED_TAGS_VENDOR"]);
                 $vendorLanguages[$lang]['BF_LANG_VENDOR'] = $lang;
             }
        }  else {
			if (!empty($vendor['T1'])) {
				// Remove invalid HTML before saving
				$vendor["T1"] = strip_tags($vendor["T1"], $nar_systemsettings["MARKTPLATZ"]["HTML_ALLOWED_TAGS_VENDOR"]);
			}
			$vendorLanguages[$this->getLangval()] = $vendor;
		}

        if($validation === true) {
            //file_put_contents($ab_path."dev/log/vendor_debug_1.log", var_export($vendorLanguages, true));
        	//eventlog("info", "Vendor debug: ", var_export($vendorLanguages, true));
            foreach($vendorLanguages as $lang => $vendorLanguage) {
                $vendorLanguage['BF_LANG_VENDOR'] = $lang;
                $db->update("vendor", $vendorLanguage);
            }
            //file_put_contents($ab_path."dev/log/vendor_debug_2.log", var_export($this->fetchByUserId($userId), true));
            // Save vendor master
            $db->querynow("INSERT IGNORE INTO `vendor_master` (ID_VENDOR_MASTER) VALUES (".(int)$vendor["ID_VENDOR"].")");
            $vendor["ID_VENDOR_MASTER"] = $vendor["ID_VENDOR"];
            unset($vendor["BF_LANG_VENDOR"]);
            $db->update("vendor_master", $vendor);
            return true;
        } else {
            return false;
        }

        return true;
    }



    /**
     * existiert ein Eintrag in der Vendor Tabelle für ein User
     * @param $userId
     * @return bool
     */
    private function existVendorDbTableByUserId($userId) {
        $db = $this->getDb();

        $exist = $db->fetch_atom("SELECT COUNT(*) FROM vendor  WHERE FK_USER = '".mysql_real_escape_string($userId)."'");
        return ($exist > 0);
    }

    /**
     * Legt einen neuen Eintrag in der Vendor Tabelle für einen User an sofern dieser noch nicht existiert
     *
     * @param $userId
     * @return void
     */
    public function createVendorDbTableIfNotExistByUserId($userId) {
        $db = $this->getDb();

        if(!$this->existVendorDbTableByUserId($userId)) {
            $db->querynow("INSERT INTO vendor (FK_USER, BF_LANG_VENDOR) VALUES ('".$userId."', 128)");
        }
    }

    public function copyUserToVendor($userId, $status = null) {
        //eventlog("info", "Vendor debug: ", "copyUserToVendor(".$userId.", ".$status.")");
        $db = $this->getDb();
        $this->existVendorDbTableByUserId($userId);

        $user = $db->fetch1("SELECT u.* FROM user u WHERE ID_USER ='".mysql_real_escape_string($userId)."'");
        $this->saveVendorByUserId(array(
        	'NAME'			=> $user['FIRMA'],
            'STRASSE' 		=> $user['STRASSE'],
            'PLZ' 			=> $user['PLZ'],
            'ORT' 			=> $user['ORT'],
            'TEL' 			=> $user['TEL'],
            'FK_COUNTRY' 	=> $user['FK_COUNTRY'],
            'FAX' 			=> $user['FAX'],
            'URL' 			=> $user['URL'],
        	// TODO: Besser lösen als zweite Datenbank-Query
        	'STATUS'		=> ($status == null ? $db->fetch_atom("SELECT STATUS FROM `vendor` WHERE FK_USER='".(int)$userId."'") : $status)

        ), $userId);
    }

    public function fetchVendorDescriptionByLanguage($vendorId, $langval = null) {
        $db = $this->getDb();
        if($langval == null) { $langval = $this->getLangval(); }

        return $db->fetch_atom("
            SELECT
                T1
            FROM
                string_vendor s
            JOIN
                vendor p ON p.ID_VENDOR = s.FK
            WHERE
                p.ID_VENDOR = '".$vendorId."'
                AND s.BF_LANG = if(p.BF_LANG_VENDOR & ".$langval.", ".$langval.", 1 << floor(log(p.BF_LANG_VENDOR+0.5)/log(2)))
            ");

    }

    public function fetchAllSearchWordsOfAllUsersWithLanguage($language) {
        $db = $this->getDb();

        $query = "SELECT
                    w.wort, count(*) as count_keywords
                FROM
                    searchdb_index_".$language." i
                JOIN searchdb_words_".$language." w ON w.ID_WORDS = i.FK_WORDS
                WHERE
                    S_TABLE = 'vendor'
                GROUP BY w.wort
                ORDER BY count_keywords DESC
                LIMIT 30";

        return $db->fetch_table( $query );
    }

    public function fetchAllSearchWordsByUserIdAndLanguage($userId, $language) {
        $db = $this->getDb();

        $vendor = $this->fetchByUserId($userId);

        if($vendor !== null) {

            return $db->fetch_table("
                SELECT
                    w.ID_WORDS, w.wort
                FROM
                    searchdb_index_".$language." i
                JOIN searchdb_words_".$language." w ON w.ID_WORDS = i.FK_WORDS
                WHERE
                    S_TABLE = 'vendor' AND FK_ID = '".$vendor['ID_VENDOR']."'
                ORDER BY w.wort
            ");

        } else {
            return null;
        }
    }

    /**
     * Fügt ein neues Schlagwort zu einem Anbieter hinzu
     *
     * @param $searchWord
     * @param $userId
     * @param string $language
     * @return bool
     */
    public function addVendorSearchWordByUserId($searchWord, $userId, $language = "de") {
        require_once ("admin/sys/lib.search.php");

        $vendor = $this->fetchByUserId($userId);

        if($vendor !== null) {
            $doSearch = new do_search($language, true);
            $doSearch->add_new_word($searchWord, $vendor['ID_VENDOR'], "vendor");

            return true;
        }
    }

    /**
     * Löscht ein Schlagwort eines Anbieters
     *
     * @param $searchWord
     * @param $userId
     * @param string $language
     * @return bool
     */
    public function deleteVendorSearchWordByUserId($searchWord, $userId, $language = "de") {
        require_once ("admin/sys/lib.search.php");

        $vendor = $this->fetchByUserId($userId);

        if($vendor !== null) {
            $doSearch = new do_search($language, true);
            $doSearch->delete_word_from_searchindex($searchWord, $vendor['ID_VENDOR'], "vendor");

            return true;
        }
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


    public function getLangval() {
        return self::$langval;
    }
    public function setLangval($langval) {
        self::$langval = $langval;
    }

    public function enableById($id) {
        $db = $this->getDb();

        $result = $db->querynow("UPDATE vendor SET STATUS = '1' WHERE ID_VENDOR='".mysql_real_escape_string($id)."'");

        if ($result['rsrc']) {

            return true;
        }

        return false;
    }

    public function disableById($id) {
        $db = $this->getDb();

        $result = $db->querynow("UPDATE vendor SET STATUS = '0' WHERE ID_VENDOR='".mysql_real_escape_string($id)."'");

        if ($result['rsrc']) {
            return true;
        }

        return false;
    }

    /**
     * Eingaben auf Fehler überprüfen.
     *
     * @param assoc     $ar_vendor
     * @param array     $errors
     * @return bool
     */
    public function updateCheckFields($ar_vendor, &$errors) {
        if (!is_array($errors)) { $errors = array(); }
        if (empty($ar_vendor["NAME"])) {
            $errors[] = "MISSING_NAME";
        }
        return empty($errors);
    }

	private function __construct() {
	}
	private function __clone() {
	}
}
