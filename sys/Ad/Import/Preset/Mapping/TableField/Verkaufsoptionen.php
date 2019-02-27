<?php

class Ad_Import_Preset_Mapping_TableField_Verkaufsoptionen extends Ad_Import_Preset_Mapping_TableField {


	public function postHook() {
		parent::postHook();

		$this->setAcceptedValues(array(
			'0' => 'Verkauf',
			'1' => 'auf Anfrage',
			'2' => 'auf Anfrage mit Preis'
		));

		$this->setCheckAcceptedValues(true);
		$this->setDefaultValue(0);
	}


}
