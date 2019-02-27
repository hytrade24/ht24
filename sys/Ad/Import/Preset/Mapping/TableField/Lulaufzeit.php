<?php

class Ad_Import_Preset_Mapping_TableField_Lulaufzeit extends Ad_Import_Preset_Mapping_TableField {

	public function postHook() {
		parent::postHook();

		$this->loadAcceptedValues();
		$this->setType("LIST");
	}


	protected function loadAcceptedValues() {
		global $db, $langval;

		$translationDays = Translation::readTranslation("general", "date.days", $langval, array(), "Tage");
		$query = "
			SELECT
				l.ID_LOOKUP, CONCAT(l.VALUE,' ".$translationDays."')
			FROM lookup l
    		LEFT JOIN `string` s ON
    			s.S_TABLE='lookup' AND s.FK=l.ID_LOOKUP AND
    			s.BF_LANG=if(l.BF_LANG & ".$langval.", ".$langval.", 1 << floor(log(l.BF_LANG+0.5)/log(2)))
			WHERE l.ART = 'LAUFZEIT'
			ORDER BY l.F_ORDER
		";

		$list = $db->fetch_nar($query);
		$this->setAcceptedValues($list);
	}

}
