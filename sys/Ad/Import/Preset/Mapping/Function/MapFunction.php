<?php

class Ad_Import_Preset_Mapping_Function_MapFunction implements Ad_Import_Preset_Mapping_Function_MappingFunctionInterface {

	protected $map = array();
	protected $configuration = array();

	protected $functionName = 'Value Map';

	function __construct($map = array(), $configuration = array()) {
		$this->functionName = Translation::readTranslation('marketplace', 'import.mapping.function.mapfunction.name', null, array(), 'Wertzuordnung');

		$this->configuration = $configuration;
		$this->map = $map;
	}


	public function execute($input) {

		if(is_array($input)) {
			foreach($input as $key => $value) {
				$input[$key] = $this->execute($value);
			}

			return $input;
		} else {
			if (array_key_exists($input, $this->map)) {
				return $this->map[$input];
			} else {
				return $input;
			}
		}
	}

	/**
	 * @return string
	 */
	public function getFunctionName() {
		return $this->functionName;
	}

	/**
	 * @return array
	 */
	public function getMap() {
		return $this->map;
	}



	public function setConfiguration($config) {
		if(isset($config['MAP']) && is_array($config['MAP'])) {
			$map = array();
			for($i=0; $i < count($config['MAP']['KEY']);$i++) {
				if(trim($config['MAP']['KEY'][$i] != '')) {
					$map[$config['MAP']['KEY'][$i]] = $config['MAP']['VALUE'][$i];
				}
			}

			$this->map = $map;
		}

	}


}