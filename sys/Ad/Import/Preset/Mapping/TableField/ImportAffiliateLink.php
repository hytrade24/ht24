<?php

class Ad_Import_Preset_Mapping_TableField_ImportAffiliateLink extends Ad_Import_Preset_Mapping_TableField {


	public function postHook() {

		$this->setFieldName('AFFILIATE_LINK');
		$this->setDisplayName(Translation::readTranslation('marketplace', 'import.field.import.affiliate.deeplink', null, array(), 'Affiliate-Deeplink'));
		$this->setType('TEXT');
		$this->setTableDef('artikel_master');
		$this->setIsMaster(true);
		$this->setIsSpecial(true);
		$this->setIsImport(true);
		$this->setIsRequired(1);
	}

}
