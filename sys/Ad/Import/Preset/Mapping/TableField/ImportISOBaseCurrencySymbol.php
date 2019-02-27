<?php

class Ad_Import_Preset_Mapping_TableField_ImportISOBaseCurrencySymbol extends Ad_Import_Preset_Mapping_TableField {

	public function postHook() {

		parent::postHook();

		$this->setFieldName('FK_CURRENCY');
		$this->setDisplayName(
			Translation::readTranslation(
			'marketplace',
			'import.field.import.ISO.BASE.CURRENCY',
			null,
			array(),
			'ISO Base Currency String'
			)
		);
		$this->loadAcceptedValues();
		$this->setType('LIST');

		$this->setTableDef('artikel_master');
		$this->setIsMaster(true);
		$this->setIsSpecial(true);
		$this->setIsImport(true);
		$this->setIsRequired(0);

	}

	protected function loadAcceptedValues() {
		global $db;

		$query = 'SELECT c.ID_CURRENCY, c.ISO_CURRENCY_FORMAT
					FROM currency c';

		$list = $db->fetch_nar($query);
		$this->setAcceptedValues($list);


	}

}