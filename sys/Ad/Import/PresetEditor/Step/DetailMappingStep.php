<?php
require_once "sys/lib.pub_kategorien.php";

class Ad_Import_PresetEditor_Step_DetailMappingStep extends Ad_Import_PresetEditor_Step_AbstractMappingStep {


	protected $cachedCategoryHashmapById;

	function __construct($presetEditor) {
		parent::__construct($presetEditor);

		$categoriesBase = new CategoriesBase();
		$categoryHashMap = $categoriesBase->getCategoryPathHashMap();

		$this->cachedCategoryHashmapById = $categoryHashMap['ID'];
	}


	public function save($data) {
		$dataPost = $data['POST'];
		$dataFiles = $data['FILES'];

		$validationResult = $this->validate($data);
		if(!$validationResult->isSuccess()) {
			return $validationResult;
		}

		return $validationResult;
	}


	public function load() {
		global $s_lang;


		$tpl = new Template('tpl/'.$s_lang.'/my-import-presets-edit.step.detailmapping.htm');

		$flashMessages = $this->presetEditor->getFlashMessages();
		if($flashMessages) {
			$tpl->addvar("FLASHMESSAGES", implode('<br>', $flashMessages));
			$this->presetEditor->resetFlashMessages();
		}



		$preset = $this->presetEditor->getPreset();
		if (!$preset->isTableFieldsUpToDate()) {
		    $preset->loadTableFields();
        }
		$tableFields = $preset->getTableFieldsByTableDef();
		$tplTableDefNav = array();

		#die(var_dump($preset->getCategoryMapping(), $this->cachedCategoryHashmapById));
		// read uses category tables
		$usedCategoryTables = array();
		foreach($this->cachedCategoryHashmapById as $key => $value) {
			if((is_array($preset->getCategoryMapping()) && in_array($key, $preset->getCategoryMapping())) || $preset->getTableFieldByName('FK_KAT')->getDefaultValue() == $key) {
				$usedCategoryTables[$value['KAT_TABLE']] = $value['KAT_TABLE'];
			}
		}


		$i = 0;
		foreach($tableFields as $tableDef => $tableFieldsByTableDef) {
			if($tableDef != 'artikel_master') {
				$tableDefSort = $i;
				if(!in_array($tableDef,$usedCategoryTables)) {
					continue;	// Do not display unused tables
					$tableDefSort += 1000;
				}

				$tplTableFieldsOutput = '';
				$tableField = null;
				/** @var Ad_Import_Preset_Mapping_TableField $tableField	 */
				foreach($tableFieldsByTableDef as $key => $tableField) {
					$tplTableFieldsOutput .= $this->renderTableFieldMapping($tableField->getFieldName(), $tableDef, $preset, true);
				}

				if($tableField != null) {
					$tplTableDefNav[$tableDefSort] = array(
							'TABLE_DEF' => $tableDef, 'TABLE_DEF_NAME' => $tableField->getTableDefName(),
							'IS_USED_BY_KAT' => in_array($tableDef, $usedCategoryTables) ? 1 : 0,
							'TABLE_FIELD_OUTPUT' => $tplTableFieldsOutput
					);
				} else {
					$tplTableDefNav[$tableDefSort] = array(
							'TABLE_DEF' => $tableDef,
							'TABLE_DEF_NAME' =>$tableDef,
							'IS_USED_BY_KAT' => in_array($tableDef, $usedCategoryTables) ? 1 : 0,
							'TABLE_FIELD_OUTPUT' => $tplTableFieldsOutput
					);
				}

				$i++;
			}
		}
		ksort($tplTableDefNav);
		$tplTableDefNav = array_values($tplTableDefNav);

		$tpl->addvar('DETAILMAPPING_SPECIAL', $preset->getStepSpecial("DetailMapping"));
		$tpl->addlist_fast('TABLE_DEF_CONTENT', $tplTableDefNav, 'tpl/'.$s_lang.'/my-import-presets-edit.step.detailmapping.tabledefcontentrow.htm');

		$tpl->addvars($_POST);
		$tpl->addvar('ID_IMPORT_PRESET', $this->presetEditor->getPreset()->getImportPresetId());


		return $tpl->process();
	}


	/**
	 * @param $data
	 *
	 * @return Ad_Import_PresetEditor_FormResult
	 */
	protected function validate($data) {
		$formResult = new Ad_Import_PresetEditor_FormResult();
		$preset = $this->presetEditor->getPreset();

		return $formResult;
	}
}