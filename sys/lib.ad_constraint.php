<?php
/* ###VERSIONSBLOCKINLCUDE### */


class AdConstraintManagement {
	static public $mappingDefinition = array(
		'1' => 'B2B'
	);
	
	static function addTemplateConstraintMapping(Template $tpl, $name = "BF_CONSTRAINTS") {
		$isAdConstraintEnabled = (bool)$GLOBALS['nar_systemsettings']['MARKTPLATZ']['AD_CONSTRAINTS'];
		foreach (self::$mappingDefinition as $key => $value) {
			$ad[$name . '_' . $value] = ($isAdConstraintEnabled && ($adConstraint & $key) == $key) ? 1 : 0;
		}
	}

	static function appendAdContraintMapping($ad, $name = "BF_CONSTRAINTS") {
		global $nar_systemsettings;

		$adConstraint = $ad[$name];
		$isAdConstraintEnabled = (bool)$nar_systemsettings['MARKTPLATZ']['AD_CONSTRAINTS'];

        foreach(self::$mappingDefinition as $key => $value) {
            $ad[$name.'_'.$value] = ($isAdConstraintEnabled && ($adConstraint & $key) == $key)?1:0;
        }

		return $ad;
	}

	static function appendAdContraintMappingToList($ads, $name = "BF_CONSTRAINTS") {
		foreach($ads as $key => $value) {
			$ads[$key] = self::appendAdContraintMapping($value, $name = "BF_CONSTRAINTS");
		}

		return $ads;
	}

}
?>
