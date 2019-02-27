<?php
/* ###VERSIONSBLOCKINLCUDE### */



class kat extends nestedsets
{
	## private vars
	
	## protected vars
			
	## public vars
	public $ar_tree = array();
	public $node = 0;
	public $error = array();
	public $ar_node = array();
	
	## magic functions
	/*
	 * Constructor braucht natÃ¼rlich die Vars fÃ¼r die nestedset Klasse
	 */
	public function __construct($s_table, $n_root, $b_lock = false, $db = NULL)
	{
		// nested Sets aufbauen
		$this->nestedsets($s_table, $n_root, $b_lock, $db);
		
	}	// __construct()
	
	public function __destruct()
	{
		echo ht(dump($this));
	}	// __destruct()
	
	## public functions
	/*
	 * getTree() liest den nestedsets baum ein, und legt in in $ar_tree ab
	 */
	public function getTree()
	{
		$res = $this->nestSelect('', '', ((int)!$this->tableLock). ' as let_move,', true);
		while($row = mysql_fetch_assoc($res))
		{
			$this->ar_tree[] = $row;
		}
	}	// getTree()
	
	public function changeTree($what, $id)
	{
		if((int)$id > 0 || count($_POST))
		{
			global $db;
			$this->node = $id;
			$reset_perms = false;
			
			switch($what)
			{
				case "lt": 
					$this->nestMoveLeft($id);
					break;
				case "rt": 
					$this->nestMoveRight($id);
					break;
				case "up": 
					$this->nestMoveUp($id);
					break;
				case "dn": 
					$this->nestMoveDown($id);
					break;
				case "v0": 
					$db->querynow("
						UPDATE
							kat
						SET
							B_VIS=0
						WHERE
							ID_KAT=".$id);
					break;
				case "v1": 
					$db->querynow("
						UPDATE
							kat
						SET
							B_VIS=1
						WHERE
							ID_KAT=".$id);
					break;
				case "mod": 
					$reset_perms = true;
					$this->setPerms();
					break;
				default: die("No or unknown action for changeTree()");
			}	// swicth what
			
			// kick the cache
			$this->kickCache($what, $id, $reset_perms);
		}	// id > 0		
	}	// chacgeTree()
	
	public function getKat($id)
	{
		global $db, $langval;
		$this->node = (int)$id;
		
		$this->ar_node = $db->fetch1("
			SELECT 
				t.*, 
				s.V1,
				s.V2,
				s.T1 
			FROM
				`kat` t 
			LEFT JOIN
				string_kat s ON s.S_TABLE='kat' 
					AND s.FK=t.ID_KAT AND s.BF_LANG=if(t.BF_LANG_KAT & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_KAT+0.5)/log(2)))
			WHERE
				t.ID_KAT=".$this->node);
	}	// getKat
	
	public function saveNode($ar)
	{
		global $db;
		$this->node = $ar['ID_KAT'];
		$db->update("kat", $ar);
		$this->kickCache("node", $this->node);
	}
	
	## private functions
	
	private function kickCache($action = NULL, $id = NULL, $reset_perms = false)
	{
		
	}	// kickCache()
	
	private function setPerms()
	{
		#echo ht(dump($_POST));
		require_once 'sys/lib.perm_admin.php';
    	katperm2role_set(-1, $_POST['mod']);
	}	// setPerms()
	
}	// class kat

?>