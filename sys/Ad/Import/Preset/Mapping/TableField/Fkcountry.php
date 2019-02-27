<?php

class Ad_Import_Preset_Mapping_TableField_Fkcountry extends Ad_Import_Preset_Mapping_TableField {


	public function postHook() {
		parent::postHook();

		$this->loadAcceptedValues();
		$this->setType("LIST");
	}

	protected function loadAcceptedValues() {
		global $db, $langval;

		$query = "
			SELECT
				l.ID_COUNTRY, s.V1
			FROM country l
    		LEFT JOIN `string` s ON
    			s.S_TABLE='country' AND s.FK=l.ID_COUNTRY AND
    			s.BF_LANG=if(l.BF_LANG & ".$langval.", ".$langval.", 1 << floor(log(l.BF_LANG+0.5)/log(2)))
			ORDER BY l.ID_COUNTRY
		";

		$list = $db->fetch_nar($query);
		$this->setAcceptedValues($list);
	}

}
