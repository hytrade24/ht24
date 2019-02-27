<?php

class Ad_Import_Preset_Mapping_TableField_Preis extends Ad_Import_Preset_Mapping_TableField {



	public function postHook() {
		parent::postHook();

		$this->setIsRequired(0);
	}


}
