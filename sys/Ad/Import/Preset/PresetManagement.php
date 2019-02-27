<?php

class Ad_Import_Preset_PresetManagement {

	private static $db;
	private static $instance = null;

	const STATUS_ENABLED = 1;
	const STATUS_DISABLED = 0;


	/**
	 * Singleton
	 *
	 * @param ebiz_db $db
	 * @return Ad_Import_Preset_PresetManagement
	 */
	public static function getInstance(ebiz_db $db) {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		self::setDb($db);

		return self::$instance;
	}


	/**
	 * @param $type
	 *
	 * @return Ad_Import_Preset_AbstractPreset
	 * @throws Exception
	 */
	public function createNewPreset($type) {
		if(class_exists($type) && is_subclass_of($type, 'Ad_Import_Preset_AbstractPreset')) {
			$presetClassName = $type;
		} else {
			$presetClassName = 'Ad_Import_Preset_Type_'.$type.'Preset';

			if(!class_exists($presetClassName) || !is_subclass_of($presetClassName, 'Ad_Import_Preset_AbstractPreset')) {
				throw new Exception("Could not load Preset Type Class or it does not implement Ad_Import_Preset_AbstractPreset");
			}
		}

		$preset = new $presetClassName();
		return $preset;
	}

	/**
	 * @param $importPresetId
	 *
	 * @return Ad_Import_Preset_AbstractPreset|null
	 */
	public function loadPresetById($importPresetId) {
		$preset = $this->getDb()->fetch1("SELECT * FROM import_preset WHERE ID_IMPORT_PRESET = '".(int)$importPresetId."'");
		if($preset != null) {
			return $this->loadPresetByDataset($preset);
		}

		return null;
	}

	/**
	 * @param $importPreset
	 *
	 * @return Ad_Import_Preset_AbstractPreset
	 */
	public function loadPresetByDataset($importPreset) {
		return unserialize($importPreset['PRESET_CONFIG']);
	}



	/**
	 * @param $presetId
	 * @param Ad_Import_Preset_AbstractPreset $preset
	 * @param string $presetType
	 */
	public function savePreset($presetId, $preset) {
		$preset->setIsStored(true);

		if($presetId == null) {
			$presetData['STAMP_CREATE'] = date("Y-m-d H:i:s");
			$presetId = $this->getDb()->update('import_preset', $presetData);
			$preset->setImportPresetId($presetId);
		}

		$presetData = array(
			'FK_USER' => $preset->getOwnerUser(),
			'STATUS' => $preset->getStatus(),
			'PRESET_NAME' => $preset->getPresetName(),
			'PRESET_TYPE' => $preset->getPresetType(),
			'STAMP_UPDATE' => date("Y-m-d H:i:s"),
			'IS_GLOBAL' => $preset->isIsGlobal(),
			'PRESET_CONFIG' => serialize($preset)
		);

		$presetData['ID_IMPORT_PRESET'] = $presetId;
		$result = $this->getDb()->update('import_preset', $presetData);


		return $result;
	}

	public function deletePreset($presetId) {
        // Delete sources
        $arSources = Ad_Import_Source_Source::getByPreset($this->getDb(), $presetId);
        /** @var    Ad_Import_Source_Source $importSource   */
        foreach ($arSources as $sourceIndex => $importSource) {
            $importSource->deleteFromDatabase();
        }
        // Delete preset
        $a = $this->getDb()->delete('import_preset', $presetId);
		return $a;
	}

	public function fetchGlobalImportPresets($status = 1) {
		if($status == null) { $statusQuery = ""; }
		if(is_array($status)) { $statusQuery = " AND STATUS IN (".implode(',', $status).") ";	}
		if(is_int($status)) { $statusQuery = " AND STATUS = ".(int)$status." ";	}

		return $this->getDb()->fetch_table("SELECT * FROM import_preset WHERE 1=1 ".$statusQuery." AND IS_GLOBAL = 1 ORDER BY PRESET_NAME");
	}

	public function fetchImportPresetsByUser($userId, $status = null) {
		if($status == null) { $statusQuery = ""; }
		if(is_array($status)) { $statusQuery = " AND STATUS IN (".implode(',', $status).") ";	}
		if(is_int($status)) { $statusQuery = " AND STATUS = ".(int)$status." ";	}

		return $this->getDb()->fetch_table("SELECT * FROM import_preset WHERE  1=1 ".$statusQuery." AND FK_USER = '".(int)$userId."' ORDER BY PRESET_NAME");
	}

	public function getTableFields() {
		global $langval;
		$ignoreFields = array("'FK_USER'","'STATUS'","'STAMP_START'","'STAMP_END'",	"'ADMIN_STAT'",	"'CRON_STAT'",	"'CRON_DONE'",
			"'AD_TABLE'","'AD_CLICKS'",	"'RUNTIME_DAYS'","'STAMP_DEACTIVATE'",	"'B_TOP'", "'DELETED'", "'LATITUDE'", '"LONGITUDE"', "'LIEFERUNG'",
				"'ONLY_COLLECT'", "'IS_VARIANT'", "'COMMENTS_ALLOWED'"
		);

		$query = "SELECT
			f.*,
			s.V1,
			s.T1,
			td.T_NAME as TABLE_DEF_TNAME,
			std.V1 as TABLE_DEF_NAME
		FROM
			`field_def` f
		JOIN table_def td ON td.ID_TABLE_DEF = f.FK_TABLE_DEF
		LEFT JOIN
			string_field_def s on s.S_TABLE='field_def' and s.FK=f.ID_FIELD_DEF	and s.BF_LANG=if(f.BF_LANG_FIELD_DEF & ".$langval.", ".$langval.", 1 << floor(log(f.BF_LANG_FIELD_DEF+0.5)/log(2)))
		LEFT JOIN
			string_app std on std.S_TABLE='table_def' and std.FK=td.ID_TABLE_DEF and std.BF_LANG=if(td.BF_LANG_APP & ".$langval.", ".$langval.", 1 << floor(log(td.BF_LANG_APP+0.5)/log(2)))
		WHERE
			f.F_NAME NOT LIKE 'ID_%' AND f.F_NAME NOT IN(".implode(",", $ignoreFields).") AND (f.B_ENABLED = 1 OR f.IS_SPECIAL != 0)
		ORDER BY
			f.F_ORDER ASC";

		return $this->getDb()->fetch_table($query);
	}

	/**
	 * @return assoc
	 */
	public function getRequiredCategoryFields($required = 1) {
		return $this->getDb()->fetch_nar("
			SELECT
				CONCAT(td.T_NAME, '-', fd.F_NAME),
				GROUP_CONCAT(k2f.FK_KAT SEPARATOR ',')
			FROM kat2field k2f
			JOIN field_def fd ON fd.ID_FIELD_DEF = k2f.FK_FIELD
			JOIN table_def td ON td.ID_TABLE_DEF = fd.FK_TABLE_DEF
			WHERE
				k2f.B_NEEDED = '".(int)$required."'
			GROUP BY k2f.FK_FIELD
		");
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