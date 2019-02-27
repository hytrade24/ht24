<?php
require_once "sys/lib.shop_kategorien.php";
require_once $ab_path.'sys/lib.pub_kategorien.php';

class Ad_Import_PresetEditor_Step_CategoryMappingStep extends Ad_Import_PresetEditor_Step_AbstractPresetEditorStep {



	public function save($data) {
		$dataPost = $data['POST'];
		$dataFiles = $data['FILES'];

		$validationResult = $this->validate($data);
		if(!$validationResult->isSuccess()) {
			return $validationResult;
		}

		$categoryMap = array();
		if(is_array($dataPost['CATEGORYMAPPING']['KEY'])) {
			for($i = 0; $i < count($dataPost['CATEGORYMAPPING']['KEY']); $i++) {
				$categoryKey = $dataPost['CATEGORYMAPPING']['KEY'][$i];
				$categoryValue = $dataPost['CATEGORYMAPPING']['FK_KAT'][$i];

				if(trim($categoryKey) != '') {
					$this->presetEditor->getPreset()->addCategoryMapping($categoryKey, $categoryValue);
				}
			}
		}

		return $validationResult;
	}

	public function remove_category_mapping_value($categoryMappingKey) {
		return $this->presetEditor->getPreset()->removeCategoryMapping($categoryMappingKey);
	}

	public function load() {
		global $s_lang;

		$kat = new TreeCategories("kat", 1);
		$categoriesBase = new CategoriesBase();

		$flashMessages = $this->presetEditor->getFlashMessages();

		$tpl = new Template('tpl/'.$s_lang.'/my-import-presets-edit.step.categorymapping.htm');

		if($flashMessages) {
			$tpl->addvar("FLASHMESSAGES", implode('<br>', $flashMessages));
			$this->presetEditor->resetFlashMessages();
		}

		$categoryTableField = $this->presetEditor->getPreset()->getTableFieldByName('FK_KAT');
		$categoryMappingField = $this->presetEditor->getPreset()->getCategoryField();
		$categoriesInPreset = $this->presetEditor->getPreset()->getCategoryDataValues();
		$categoryMapping = $this->presetEditor->getPreset()->getCategoryMapping();
		$categoryHashMap = $categoriesBase->getCategoryPathHashMap();


		$categoryDefaultValue = $categoryTableField->getDefaultValue();
		if($categoryDefaultValue) {
			$categoryDefaultValueElement = $kat->element_read($categoryDefaultValue);
			if($categoryDefaultValueElement != null) {
				$categoryDefaultValueName = $categoryDefaultValueElement['V1'];
			}
		}


		if($categoryMappingField != null) {
			$tplCategoryMapOutput = '';

			foreach ($categoriesInPreset as $key => $categoryInPreset) {
				$tmpTplCategoryMap = new Template('tpl/' . $s_lang . '/my-import-presets-edit.step.categorymapping.maprow.htm');
				$tmpTplCategoryMap->addvar('CATEGORY_FROM_PRESET', 1);
				$tmpTplCategoryMap->addvar('CATEGORY_NAME', $categoryInPreset);

				if (isset($categoryMapping[$categoryInPreset])) {
					$tmpTplCategoryMap->addvar('CATEGORY_MAPPED_ID', $categoryMapping[$categoryInPreset]);
					$tmpTplCategoryMap->addvar('CATEGORY_MAPPED_NAME', $categoryHashMap['ID'][$categoryMapping[$categoryInPreset]]['V1']);

				} elseif(isset($categoryHashMap['ID'][$categoryInPreset])) {
					// try Automapping
					$tmpTplCategoryMap->addvar('CATEGORY_MAPPED_ID', $categoryInPreset);
					$tmpTplCategoryMap->addvar('CATEGORY_MAPPED_NAME', $categoryHashMap['ID'][$categoryInPreset]['V1']);
				}

				$tplCategoryMapOutput .= $tmpTplCategoryMap->process();
			}



			foreach ($categoryMapping as $key => $categoryMappingElement) {
				if (!in_array($key, $categoriesInPreset)) {
					$tmpTplCategoryMap = new Template('tpl/' . $s_lang . '/my-import-presets-edit.step.categorymapping.maprow.htm');
					$tmpTplCategoryMap->addvar('CATEGORY_FROM_PRESET', 0);
					$tmpTplCategoryMap->addvar('CATEGORY_NAME', $key);

					$tmpTplCategoryMap->addvar('CATEGORY_MAPPED_ID', $categoryMappingElement);
					$tmpTplCategoryMap->addvar('CATEGORY_MAPPED_NAME', $categoryHashMap['ID'][$categoryMappingElement]['V1']);

					$tplCategoryMapOutput .= $tmpTplCategoryMap->process();
				}
			}

			$tpl->addvar('CATEGORY_MAP', $tplCategoryMapOutput);
		} else {
			$tpl->addvar('NO_CATEGORY_FIELD', 1);
		}

		$tpl->addvar('CATEGORY_DEFAULT_VALUE', $categoryDefaultValue);
		$tpl->addvar('CATEGORY_DEFAULT_VALUE_NAME', $categoryDefaultValueName);

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
		$dataPost = $data['POST'];

		return $formResult;
	}
}