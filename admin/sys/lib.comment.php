<?php
/* ###VERSIONSBLOCKINLCUDE### */

/* Es gibt 2 Tabellen dazu: comment und comment_ipcheck

CREATE TABLE `comment_check` (
`ID_COMMENT_CHECK` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT ,
`DATUM` DATETIME NOT NULL ,
`FK_COMMENT_THREAD` BIGINT UNSIGNED NOT NULL ,
`IP` VARCHAR( 20 ) NOT NULL ,
`FK_USER` BIGINT UNSIGNED NOT NULL ,
PRIMARY KEY ( `ID_COMMENT_CHECK` )
);

*/
class comment {
	var $err = array();
	var $error_messages = array();
	var $type = "";
	var $fk = 0;
	var $id_comment = 0;
	var $db = NULL;
	var $langval = NULL;
	var $uid = NULL;
	var $tid = 0;
	var $repostSperre = 1; // Zeit zwischen zwei Kommentaren in Minuten
	
	// Konstruktor
	function comment( $s_table=1, $fk=1, $id_comment=1) 
	{
		$this->db = &$GLOBALS['db'];
		$this->uid = &$GLOBALS['uid'];
		$this->langval = &$GLOBALS['langval'];
		$this->now = date("m-d-Y G:i:s", mktime(date('H'), date('i'), date('s'), date('n'), date('d'), date('Y')));
		$this->type = $s_table;
		$this->fk = $fk;
		$this->id_comment = $id_comment;
		$this->error_messages = get_messages("KOMMENTAR");
		$this->err = "";
/*		if ( empty($this->fk) && empty($this->id_comment) )
			error401();
		elseif ( empty($this->type) )
			error401();		*/
	}	
  
	// Kommentar einfÃ¼gen
	function insertComment( $_POST ) {

		foreach($_POST as $k=>$v)
			$_POST[$k] = trim($v);

		$_POST['STAMP'] = date('Y-m-d H:i:s');
		$_POST['FK_USER'] = (int)$this->uid;
		if (!$_POST['SUBJECT'])
			$this->err[] = $this->error_messages["NOSUBJECT"];
		if (!$_POST['BODY'])
			$this->err[] = $this->error_messages["NOCOMMENT"];
		$_POST['FK_COMMENT_THREAD'] = ( $this->id_comment 
			? $this->tid = $this->db->fetch_atom("select FK_COMMENT_THREAD 
										from comment where ID_COMMENT=". $this->id_comment)
			: $this->db->update('comment_thread', 
					array ('S_TABLE'=>$this->type,'FK'=>$this->fk, 'FK_LANG'=>$this->langval))	);	
		if (empty($this->err))
		{
			$this->id_comment = $this->db->update('comment', $_POST);
			return $this->id_comment;
		}
		else 
		{
//			echo ht(dump($_POST));
//			echo "<hr>".count($this->err)."__".$this->err."__<hr>";
			return false;
		}
	}

  function createBackLink() {
  	switch($this->type) {
		case "rezension":
			return "index.php?lang=de&page=modul_rezension_details&tutid=".$this->fk;
		case "tutorial":
			return "index.php?lang=de&page=modul_tutorial_details&tutid=".$this->fk;
	}
  }
  
  function deleteComment() {
  	if ($this->id_comment >= 1) 
	{
  		$parent = $this->db->fetch_atom("SELECT PARENT FROM comment WHERE ID_COMMENT='".$this->id_comment."'");
		$this->db->querynow("UPDATE comment SET PARENT='".$parent."' WHERE PARENT='".$this->id_comment."'");
		$this->db->querynow("DELETE FROM comment WHERE ID_COMMENT='".$this->id_comment."'");
		return true;
	}
	return false;
  }
  
  function updateComment( $subj, $msg ) {
		$res = $this->db->querynow("update comment set BODY='".mysql_escape_string($msg)."',
			    SUBJECT='".mysql_escape_string($subj)."' where ID_COMMENT=".$this->id_comment);
		if ($res)
			return true;
		return false;
  }
  
  function getSingleComment() {
		$array = $this->db->fetch1("select SUBJECT, BODY, ID_COMMENT, STAMP, u.NAME as USER   
									from comment c left join user u on c.FK_USER=u.ID_USER
									where ID_COMMENT=".$this->id_comment); 
		if(!is_array($array))
			$array = array();
		return $array;

  }

  function getMostCommented( $modul )
  {
		if ( $modul == "user" ) {
		
		}
		else 
		{
			$query = "SELECT t.FK, t.V1, count( c.ID_COMMENT ) AS comments
						FROM ".$modul." tu
						LEFT JOIN comment_thread ct ON ct.FK = tu.ID_".$modul." AND ct.S_TABLE = '".$modul."'
						LEFT JOIN COMMENT c ON ct.ID_COMMENT_THREAD = c.FK_COMMENT_THREAD
						LEFT JOIN string_".$modul." t ON tu.ID_".$modul." = t.FK
						GROUP BY tu.ID_".$modul."
						HAVING comments >0
						ORDER BY comments DESC
						LIMIT 5";
			return $this->db->fetch_table($query);
		}		
  }
  
  
  function getContent() {
  	if ( empty( $this->err ) ) {
		$result = $this->db->fetch1("SELECT * FROM ".$this->type." t
								left join string_".$this->type." s on s.FK=t.ID_".strtoupper($this->type)."
								WHERE  s.BF_LANG=if(t.BF_LANG_".strtoupper($this->type)." 
								& ".$this->langval.", ".$this->langval.", 
								1 << floor(log(t.BF_LANG_".strtoupper($this->type)."+0.5)/log(2)))
								and ID_".strtoupper($this->type)." = '".$this->fk."'");
		if ( empty($result))
		{
			//$this->err[] = $this->error_messages["NO_CONTENT"];
						echo ht(dump($GLOBALS['lastresult']));
			error401();
			return false;
		}
		else 
		{
			$result["s_table"] = $this->type;
			return $result;
		}
	}
  }
  
  function insertIPCheck() {
		$this->clearIPCheck();
		$newCheck["DATUM"] = date("m-d-Y G:i:s", mktime(date('H'), date('i')+$this->repostSperre, date('s'), date('n'), date('d'), date('Y')));
		$newCheck["FK_COMMENT"] = $this->id_comment;
		$newCheck["FK_CONTENT"] = $this->fk;
		$newCheck["FK_USER"] = $this->uid;
		$newCheck["IP"] = $_SERVER["REMOTE_ADDR"];
#		if ( $this-db->querynow("SELECT Count(*) FROM comment_ipcheck WHERE FK_COMMENT=".$this->id_comment."") )
#			return false;
#		else
			return $this->db->update("comment_ipcheck",$newCheck);
  }

  function clearIPCheck() {
	$this->now = date("m-d-Y G:i:s", mktime(date('H'), date('i'), date('s'), date('n'), date('d'), date('Y')));
	$this->db->querynow("DELETE FROM comment_ipcheck WHERE DATUM < ".$this->now);
	
  }
  
  function temp() {
			$query = "SELECT * FROM ".$this->type." t 
								left join string_".$this->type." s on s.FK=t.ID_".strtoupper($this->type)."
								WHERE  s.BF_LANG=if(t.BF_LANG_".strtoupper($this->type)." & ".$this->langval.", ".$this->langval.", 
								1 << floor(log(t.BF_LANG_".strtoupper($this->type)."+0.5)/log(2)))
								and ID_".strtoupper($this->type)." = '".$this->fk."'";
			return $query;
  }
    
  function deleteComplain() {
  	
  }
  
  function getLastVotes( $anfang=0, $anzahl=20, $where="", $uid="") 
  {
		$query = "SELECT concat( c.SUBJECT,  ' ', left( c.BODY, 300  )  )  AS VORSCHAU, c.STAMP, 
				c.ID_COMMENT, c.FK_USER, u.NAME AS USER, count( c.ID_COMMENT )  AS C_COM, ct.FK AS FK_CONTENT,
				ct.S_TABLE as modul, c.REPORTED
				FROM `comment` c
				LEFT  JOIN user u ON c.FK_USER = u.ID_USER
				LEFT  JOIN comment_thread ct ON c.FK_COMMENT_THREAD = ct.ID_COMMENT_THREAD
				"//hmm????by jura LEFT  JOIN  COMMENT cc ON u.ID_USER = cc.FK_USER
				.$where."
				GROUP  BY c.ID_COMMENT
				ORDER  BY c.REPORTED DESC, c.STAMP DESC
				Limit ".$anfang.",".$anzahl; 
		$return = $this->db->fetch_table($query);
		for($i = 0; $i<=count($return); $i++) 
		{
			switch ( $return[$i]["modul"] )
			{
				case "tutorial":
					$return[$i]["id_kind"] = "tutid";
					break;
				case "rezension":
					$return[$i]["id_kind"] = "rezid";
					break;
				case "script":
					$return[$i]["id_kind"] = "scriptid";
					break;
			 }
		}
//		echo ht(dump($return));
		return $return;
  }

  function unreportComment() {
	$new["ID_COMMENT"] = $this->id_comment;
	$new["REPORTED"] = 0;
	$this->db->update("comment", $new);
  }

  function shorten( $array ) {	
		if ( is_array($array) )
		{
			for($i=0;$i<=count($array);$i++) 
			{
				if ( count($array[$i][V1]) >= 30 )
				{
					$array[$i][V1] = substr( $array[$i][V1] , 0, 30);
					$array[$i][V1] .= "...";
				}
			}
			return $array;
		}				
		else 
			return false;
  }
}
?>