<?php

class Ad_Import_PresetEditor_FormResult {

	protected $success = true;
	protected $errors = array();


	public function setFailed() {
		$this->success = false;

		return $this;
	}

	public function isSuccess() {
		return $this->success;
	}

	public function addError($msg) {
		$this->errors[] = $msg;
	}

	/**
	 * @return array
	 */
	public function getErrors() {
		return $this->errors;
	}


}