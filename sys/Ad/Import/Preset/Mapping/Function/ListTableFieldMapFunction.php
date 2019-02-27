<?php

class Ad_Import_Preset_Mapping_Function_ListTableFieldMapFunction extends Ad_Import_Preset_Mapping_Function_MapFunction {


	protected $functionName = 'List Map';

	function __construct($map = array(), $configuration = array()) {
		parent::__construct($map, $configuration);

		$this->functionName = Translation::readTranslation('marketplace', 'import.mapping.function.listtablefieldmapfunction.name', null, array(), 'Wertzuordnung aus Listenfeld');
	}



}