<?php


class Ad_Import_Preset_Mapping_DataField {

	protected $identifier;
	protected $name;
	protected $description;
	protected $type;
	protected $exampleData = array();

	function __construct($identifier, $name, $description = '') {
		$this->identifier = $identifier;
		$this->name = $name;
		$this->description = $description;
	}


	/**
	 * @return mixed
	 */
	public function getIdentifier() {
		return $this->identifier;
	}

	/**
	 * @param mixed $identifier
	 *
	 * @return Ad_Import_Preset_Mapping_DataField
	 */
	public function setIdentifier($identifier) {
		$this->identifier = $identifier;

		return $this;
	}

	/**
	 * @return mixed
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * @param mixed $name
	 *
	 * @return Ad_Import_Preset_Mapping_DataField
	 */
	public function setName($name) {
		$this->name = $name;

		return $this;
	}

	/**
	 * @return mixed
	 */
	public function getType() {
		return $this->type;
	}

	/**
	 * @param mixed $type
	 *
	 * @return Ad_Import_Preset_Mapping_DataField
	 */
	public function setType($type) {
		$this->type = $type;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getDescription() {
		return $this->description;
	}

	/**
	 * @param string $description
	 *
	 * @return Ad_Import_Preset_Mapping_DataField
	 */
	public function setDescription($description) {
		$this->description = $description;

		return $this;
	}



	public function addExampleData($data) {
		$this->exampleData[] = $data;

		return $this;
	}

	/**
	 * @return array
	 */
	public function getExampleData() {
		return $this->exampleData;
	}

	/**
	 * @param array $exampleData
	 *
	 * @return Ad_Import_Preset_Mapping_DataField
	 */
	public function setExampleData($exampleData) {
		$this->exampleData = $exampleData;

		return $this;
	}


	public function toArray() {
		return array(
			'identifier' => $this->identifier,
			'name' => $this->name,
			'type' => $this->type,
			'exampleDataAsString' => implode(', ', $this->exampleData)
		);
	}
}