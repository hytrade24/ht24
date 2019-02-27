<?php

class Ad_Import_Preset_Mapping_TableField_Versandoptionen extends Ad_Import_Preset_Mapping_TableField {


	public function postHook() {
		parent::postHook();

		$this->setAcceptedValues(array(
			'0' => 'Versandkostenfrei',
			'1' => 'Nur Selbstabholung',
			'2' => 'Versandkosten auf Anfrage',
			'3' => 'Versandkosten',
		));

		$this->setCheckAcceptedValues(true);

	}


}
