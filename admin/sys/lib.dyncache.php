<?php
/* ###VERSIONSBLOCKINLCUDE### */

/*
	Parameter:
		TUTORIAL
		REZENSION
		NEWUSER
		NEWCOMPANY
		NEWUMFRAGE
*/


class dyncache {

	var $err = array();
	var $error_messages = array();
	
	// Konstruktor
	function dyncache($kind, $fk) 
	{
		$this->db = &$GLOBALS['db'];
		$this->uid = &$GLOBALS['uid'];
		$this->langval = &$GLOBALS['langval'];
		$this->fk = $fk;
		$this->kind = $kind;
		$this->doSwitch();
	}

	function doSwitch() {
		switch ($this->kind) {
			case "TUTORIAL":
				$this->cacheTutorial();
				break;
			case "REZENSION":
				$this->cacheRezension();
				break;
			case "NEWCOMPANY":
				$this->cacheUser();
				break;
			case "NEWUSER":
				$this->cacheCompany();
				break;
		}
	}
	
	function cacheTutorial() {
	
	}
	
	function cacheRezension() {
	
	}
	
	function cacheUser() {
	
	}
	
	function cacheCompany() {
	
	}
}
?>