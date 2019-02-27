<?php

class Ad_Import_Preset_Mapping_TableField_ImportPriceInBaseCurrency extends Ad_Import_Preset_Mapping_TableField {

	public function postHook() {

		parent::postHook();

		$this->setFieldName('PREIS_IN_BASE_CURRENCY');
		$this->setDisplayName(
			Translation::readTranslation(
				'marketplace',
				'import.field.import.price.in.base.currency',
				null,
				array(),
				'Price in above selected currency'
			)
		);
		$this->setType('TEXT');
		$this->setTableDef('artikel_master');
		$this->setIsMaster(true);
		$this->setIsSpecial(true);
		$this->setIsImport(true);
		$this->setIsRequired(1);

	}

}