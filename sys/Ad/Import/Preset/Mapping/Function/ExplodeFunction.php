<?php

class Ad_Import_Preset_Mapping_Function_ExplodeFunction implements Ad_Import_Preset_Mapping_Function_MappingFunctionInterface {

	protected $delimiter = ';';
	protected $configuration = array();

	protected $functionName = 'Explode';

	function __construct($delimiter = ';', $configuration = array()) {
		$this->functionName = Translation::readTranslation('marketplace', 'import.mapping.function.explode.name', null, array(), 'Zerteilen');

		$this->configuration = $configuration;
		$this->delimiter = $delimiter;
	}


	public function execute($input) {

		if(strpos($input, $this->delimiter) !== FALSE) {
			return explode($this->delimiter, $input);
		}

		return $input;
	}


	/**
	 * @return string
	 */
	public function getFunctionName() {
		return $this->functionName;
	}


	public function setConfiguration($config) {
		if(isset($config['DELIMITER'])) {
			$this->delimiter = $config['DELIMITER'];
		}
	}

	/**
	 * @return string
	 */
	public function getDelimiter() {
		return $this->delimiter;
	}




}