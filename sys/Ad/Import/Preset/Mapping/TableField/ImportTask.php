<?php

class Ad_Import_Preset_Mapping_TableField_ImportTask extends Ad_Import_Preset_Mapping_TableField {


	public function postHook() {

		$this->setFieldName('IMPORT_TASK');
		$this->setDisplayName(Translation::readTranslation('marketplace', 'import.field.importask.displayname', null, array(), 'Import Anweisung'));
		$this->setType('TEXT');
		$this->setTableDef('artikel_master');
		$this->setIsMaster(true);
		$this->setIsSpecial(true);
		$this->setIsImport(true);
		$this->setIsRequired(0);
		$this->setAcceptedValues(array(
			'N' => 'NEW',
			'U' => 'UPDATE',
			'D' => 'DELETE',
			'PAUSE' => 'PAUSE',
			'START' => 'START'
 		));

		$this->setCheckAcceptedValues(true);
	}

}
