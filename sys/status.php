<?php
/* ###VERSIONSBLOCKINLCUDE### */



class Status {
	
	public static $file_ignore = array(
		// Ignored directories
		"cache", 
		"updater",
		"uploads",
		// Ignored files
		"inc.server.php"
	);

	/**
	 * Loads an trader installation status from a running installation.
	 * 
	 * @param string $url	The URL of the trader installation
	 */
	public static function getFromURL($url) {
		$status = file_get_contents($url."update_check.php?key=ebiz-secret");
		return new Status(json_decode($status, true));
	}
		
	public static function getFromDir_File($dir, &$ar_result = array(), $dir_rel = "") {
		if (!is_array($ar_result)) $ar_result = array();
		if ($handle = @opendir($dir.$dir_rel)) {
	    	while (false !== ($file = readdir($handle))) {
	    		$file_full = $dir.$dir_rel.$file;
	    		if (!in_array($file, Status::$file_ignore)) {
		    		if (($file != ".") && ($file != "..") && is_dir($file_full)) {
		    			Status::getFromDir_File($dir, $ar_result, $file."/");
		    		} else if (preg_match("/^.+\.(php|htm|html|js)$/i", $file)) {
		        		$ar_result[$dir_rel.$file] = @md5_file($file_full);
		    		}
	    		}
	    	}
		}
		return $ar_result;
	}	
	
	/**
	 * Loads an trader installation status from the given installation directory on the local server.
	 * 
	 * @param string $dir	Absolute path to the tader directory.
	 */
	public static function getFromDir($dir, $lang = 128) {
		if (!file_exists($dir."inc.server.php")) return null;
		// MySQL connection
		include $dir."inc.server.php";
		if (!mysql_connect($db_host, $db_user, $db_pass) || !mysql_select_db($db_name)) {
			return null;
		}
		// Setup
		$languages = array("de");
		// Temporary variables
		$ar_status = array(
			"database"		=> array(),
			"content"		=> array(),
			"file"			=> Status::getFromDir_File($dir),
			"nav" 			=> array(),		// DEPRICATED
			"nav_ident" 	=> array(),		// DEPRICATED
			"templates" 	=> array(),		// DEPRICATED
			"libraries"		=> array()		// DEPRICATED
		);
		
		for ($root = 1; $root < 3; $root++) {
			// Nested set trees
			$ar_status["nav"][$root] = array();
			$ar_status["nav_ident"][$root] = array();
			$query = "
				SELECT 
					n.*,
					s.V1, s.V2, s.T1
				FROM `nav` n
				LEFT JOIN `string` s ON
					s.S_TABLE='nav' AND s.FK=n.ID_NAV AND s.BF_LANG=".(int)$lang."
				WHERE 
					ROOT=".$root."
				ORDER BY LFT";
			$res = @mysql_query($query);
			while ($row = @mysql_fetch_assoc($res)) {
				$row["V1"] = $row["V1"];
				$row["V2"] = $row["V2"];
				$row["T1"] = $row["T1"];
				$ident = $row["IDENT"];
				$query = "
					SELECT 
						n.*,
						s.V1, s.V2, s.T1
					FROM `nav` n
					LEFT JOIN `string` s ON
						s.S_TABLE='nav' AND s.FK=n.ID_NAV AND s.BF_LANG=".(int)$lang."
					WHERE ROOT=".(int)$row["ROOT"]." AND LFT<".(int)$row["LFT"]." AND RGT>".(int)$row["RGT"]."
					ORDER BY LFT DESC
					LIMIT 1";
				$row["PARENT"] = mysql_fetch_assoc(mysql_query($query));
				if (!empty($row["PARENT"])) { 
					$row["PARENT"]["V1"] = $row["PARENT"]["V1"];
					$row["PARENT"]["V2"] = $row["PARENT"]["V2"];
					$row["PARENT"]["T1"] = $row["PARENT"]["T1"];
				}
				$ar_status["nav"][$root][] = $row;
				$ar_status["nav_ident"][$root][$ident] = count($ar_status["nav"][$root]);
			}
			// Templates
			$ar_status["templates"][$root] = array();
			$tpl_path = ($root == 1 ? $dir : $dir."admin/");
			if ($handle = @opendir($tpl_path.'tpl')) {
			    while (false !== ($file = readdir($handle))) {
			    	if (preg_match("/^.+\.php$/i", $file)) {
			        	$ar_status["templates"][$root][$file] = @md5_file($tpl_path."tpl/".$file);
			    	}
			    }
			}
			foreach ($languages as $lang) {
				if ($handle = @opendir($tpl_path.'tpl/'.$lang)) {
				    while (false !== ($file = readdir($handle))) {
				    	if (preg_match("/^.+\.html?$/i", $file)) {
				        	$ar_status["templates"][$root][$lang."/".$file] = @md5_file($tpl_path."tpl/".$lang."/".$file);
				    	}
				    }
				}
			}
			// Libaries
			$ar_status["libraries"][$root] = array();
			if ($handle = @opendir($tpl_path.'sys')) {
			    while (false !== ($file = readdir($handle))) {
			    	if (preg_match("/^.+\.(php|js)$/i", $file)) {
			        	$ar_status["libraries"][$root]["sys/".$file] = @md5_file($tpl_path."sys/".$file);
			    	}
			    }
			}
			if ($handle = @opendir($tpl_path.'lib')) {
			    while (false !== ($file = readdir($handle))) {
			    	if (preg_match("/^.+\.(php|js)$/i", $file)) {
			        	$ar_status["libraries"][$root]["lib/".$file] = @md5_file($tpl_path."lib/".$file);
			    	}
			    }
			}
			if ($handle = @opendir($tpl_path.'js')) {
			    while (false !== ($file = readdir($handle))) {
			    	if (preg_match("/^.+\.js$/i", $file)) {
			        	$ar_status["libraries"][$root]["js/".$file] = @md5_file($tpl_path."js/".$file);
			    	}
			    }
			}
		}
		// Database tables
		$ar_status["tables"] = array();
		$ar_status["tables_create"] = array();
		$res = @mysql_query("SHOW TABLES");
		while ($row_table = @mysql_fetch_row($res)) {
			$table_name = $row_table[0];
			$row_create = @mysql_fetch_row(@mysql_query("SHOW CREATE TABLE `".mysql_escape_string($table_name)."`"));
			$table_create = preg_replace("/(\s?AUTO_INCREMENT=[0-9]+)/i", "", $row_create[1]);
			$table_create = preg_replace("/(\s?DEFAULT CHARSET=[a-z0-9_-]+)/i", "", $table_create);
			$ar_status["tables"][] = $table_name;
			$ar_status["tables_create"][$table_name] = $table_create;
		}
		return new Status($ar_status);
	}
	
	/**
	 * Loads an trader installation status from file.
	 * 
	 * @param string $file	File that contains the installation status.
	 */
	public static function getFromFile($file) {
		if (file_exists($file)) {
			$status = file_get_contents($file);
			return new Status(json_decode($status, true));
		}
		return null;
	}
	
	private $ar_status = null;
	
	public function Status($ar_status) {
		$this->ar_status = $ar_status;
	}
	
	public function get() {
		return $this->ar_status;
	}
	
	/**
	 * Saves the current trader status to file for later use
	 * 
	 * @param string $file	The file to be written.
	 */
	public function saveToFile($file) {
		$status = json_encode($this->status);
		file_put_contents($cache_file, $status);
	}
	
}

?>