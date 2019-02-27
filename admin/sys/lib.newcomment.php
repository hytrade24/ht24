<?php
/* ###VERSIONSBLOCKINLCUDE### */



class comment 
{

  // Public
  public $err_out = array();
  public $ok_out = array();
  
  // Privat
  private $db = NULL;
  private $table = array();
  private $repostTime = 60; // Zeitsperre fÃ¼r Posts in Sekunden
  private $id_user = 0;
  private $err = false;
  private $err_message = array();
  private $uid = NULL;
  private $fk = 0; 
  private $currentTime = NULL;
  private $comment_ok = false;
  private $bbcode = NULL;
  private $kommentar = array();
  private $comment_minlength = 3;
  private $id_comment = NULL;
  private $user = array();
  
  // Public Functionen
  public function comment ( $fk = 0, $table, $id_comment = false )
  		{
		  $this->db = &$GLOBALS['db'];
		  $this->uid = &$GLOBALS['uid'];
		  $this->bbcode = &$GLOBALS['bbcode'];
		  $this->fk = $fk;
		  $this->table = $table; 
		  $this->id_comment = $id_comment;
		  $this->user = &$GLOBALS['user'];
		  $this->checkData(); // ÃberprÃ¼ft die eingaben
		}
  
  public function getComments()
  		{
		  // holt sich die kommentare aus der db
		}
		
  public function publish()
  		{
		  // Kommentar Freigeben oder Sperren
		}  
  
  public function editComment($comment)
  		{
		  if(strlen($comment) < $this->comment_minlength)
		    $this->err[] = "NOCOMMENT";
		  
		  $org_text = $comment;
		  
		  if($this->err == false)
		  {
		    $arr = array();
		    
			// alle codes erst mal filtern und zwischenspeichern ([CODE] und [PHP])
			preg_match_all("%(\[CODE\]|\[PHP\])(.*?)(\[/CODE\]|\[/PHP\])%si", $comment, $treffer, PREG_PATTERN_ORDER);
			
			foreach($treffer[0] as $key => $value)
			{
			  $arr[$key] = array("wert" => $value, "ident" => "/%/phpres id=".$key."/%/");
			  $comment = str_replace($arr[$key]['wert'], $arr[$key]['ident'], $comment);
			} // foreach ende
			
			// alle html tags werden entfernt
			$comment = strip_tags($comment);
			$comment = stdHtmlentities($comment);
			
			// die zwischengespeicherten codes werden wieder eingetragen
			for($i=0;$i<count($arr);$i++)
			{
			  $comment = str_replace($arr[$i]['ident'], $arr[$i]['wert'], $comment);
			} // foreach ende
			
			// bbcodes werden geparsed
			$this->bbcode->parseBB($comment);
			
			// kommentar array wird gefÃ¼llt
				$this->kommentar['ID_KOMMENTAR'] = $this->id_comment;
				$this->kommentar['KOMMENTAR'] = $org_text;							
				$this->kommentar['KOMMENTAR_PARSED'] = $this->bbcode->parsed_text."<p><i><small>edited by ".$this->user['NAME']."/".date("H:i:s d.m.Y")."</small></i></p>";
				
				$query = "UPDATE `kommentar_".strtolower($this->table)."`
						  SET `KOMMENTAR` = '".sqlString($this->kommentar['KOMMENTAR'])."', 
							  `KOMMENTAR_PARSED` = '".sqlString($this->kommentar['KOMMENTAR_PARSED'])."'
						  where `ID_KOMMENTAR_".strtoupper($this->table)."` = ".$this->kommentar['ID_KOMMENTAR'];
				// in db speichern
				//print_r ($query);
				if(!mysql_query($query))
				{
				  #echo $query."<hr>";
				  #echo mysql_error(); die();
				  $this->err[] = "SAVE_ERROR";
				  $this->comment_ok = false;
				} // konnte nicht speichern
				else
				{
				  $this->ok_out[] = "SAVE_OK";
				  $this->comment_ok = true;
				} // erfolgreich gespeichert
			}
		}
  		
  public function delComment($id, $delTyp)
  		{
		  if($id && $delTyp == 1)
		    $query = "DELETE FROM `kommentar_".strtolower($this->table)."` WHERE `ID_KOMMENTAR_".strtoupper($this->table)."` = ".$id." AND `FK` = '".$this->fk."' LIMIT 1";
		  if($id && $delTyp == "all")
		    $query = "DELETE FROM `kommentar_".strtolower($this->table)."` WHERE `FK` = '".$id."'";
		  if($query)
		  {
		    if($this->db->querynow($query))
			{
			  $this->updateComment();
			  //$this->updateRating();
			  global $db;
			  $last = "'".$db->fetch_atom("select STAMP from kommentar_".$this->table." where FK=".$this->fk)."'";
			  if(!$last || $last = "''")
			    $last = 'NULL';
			  $res=$db->querynow("update ".$this->table." set LAST_COMMENT=".$last." where ID_".strtoupper($this->table)."=".$this->fk);
			  #die(ht(dump($res)));
			  $this->ok_out[] = "DEL_OK";
			  $this->comment_ok = true;
			  return "success";
			}
			else
			{
			  $this->err[] = "DEL_ERROR";
			  $this->comment_ok = false;
			  return false;
			}
		  }
		}// Kommentar LÃ¶schen
  
  public function preview($comment)
  {
			// alle codes erst mal filtern und zwischenspeichern ([CODE] und [PHP])
			preg_match_all("%(\[CODE\]|\[PHP\])(.*?)(\[/CODE\]|\[/PHP\])%si", $comment, $treffer, PREG_PATTERN_ORDER);
			
			foreach($treffer[0] as $key => $value)
			{
			  $arr[$key] = array("wert" => $value, "ident" => "/%/phpres id=".$key."/%/");
			  $comment = str_replace($arr[$key]['wert'], $arr[$key]['ident'], $comment);
			} // foreach ende
			
			// alle html tags werden entfernt
			$comment = strip_tags($comment);
			$comment = stdHtmlentities($comment);
			
			// die zwischengespeicherten codes werden wieder eingetragen
			for($i=0;$i<count($arr);$i++)
			{
			  $comment = str_replace($arr[$i]['ident'], $arr[$i]['wert'], $comment);
			} // foreach ende
			
			// bbcodes werden geparsed
			$this->bbcode->parseBB($comment);
			$this->preview_text = $this->bbcode->parsed_text;  
  } // preview
  
  		
  public function addComment($comment)
  		{
		  
		  if(strlen($comment) < $this->comment_minlength)
		    $this->err[] = "NOCOMMENT";
		  
		  $org_text = $comment;
		  
		  if($this->err == false)
		  {
		    $arr = array();
		    
			// alle codes erst mal filtern und zwischenspeichern ([CODE] und [PHP])
			preg_match_all("%(\[CODE\]|\[PHP\])(.*?)(\[/CODE\]|\[/PHP\])%si", $comment, $treffer, PREG_PATTERN_ORDER);
			
			foreach($treffer[0] as $key => $value)
			{
			  $arr[$key] = array("wert" => $value, "ident" => "/%/phpres id=".$key."/%/");
			  $comment = str_replace($arr[$key]['wert'], $arr[$key]['ident'], $comment);
			} // foreach ende
			
			// alle html tags werden entfernt
			$comment = strip_tags($comment);
			$comment = stdHtmlentities($comment);
			
			// die zwischengespeicherten codes werden wieder eingetragen
			for($i=0;$i<count($arr);$i++)
			{
			  $comment = str_replace($arr[$i]['ident'], $arr[$i]['wert'], $comment);
			} // foreach ende
			
			// bbcodes werden geparsed
			$this->bbcode->parseBB($comment);			
			
			if($this->repost())
			{	
				// kommentar array wird gefÃ¼llt
				$this->kommentar['FK'] = ($this->string_fk ? $this->string_fk : $this->fk);
				$this->kommentar['FK_USER'] = $this->uid;
				$this->kommentar['PUBLISH'] = 1;
				$this->kommentar['KOMMENTAR'] = $org_text;
				/*if($this->checkRating())
				  $this->kommentar['BEWERTUNG'] = $rating;
				else
				  $this->kommentar['BEWERTUNG'] = NULL;		*/						
				
				$query = "INSERT INTO `kommentar_".strtolower($this->table)."`
						  ( `FK`, `FK_USER`,  `REPORT`, `PUBLISH`, `KOMMENTAR`, `KOMMENTAR_PARSED` ) VALUE  
						  ( '".$this->kommentar['FK']."', '".$this->kommentar['FK_USER']."', 
						    NULL, '".$this->kommentar['PUBLISH']."', '".sqlString($this->kommentar['KOMMENTAR'])."', '".sqlString($this->bbcode->parsed_text)."')";
							
				/*$query = "INSERT INTO `kommentar_".strtolower($this->table)."`
						  ( `FK`, `FK_USER`, `STAMP`, `REPORT`, `PUBLISH`, `KOMMENTAR`, `KOMMENTAR_PARSED`, `BEWERTUNG` ) VALUE  
						  ( '".$this->kommentar['FK']."', '".$this->kommentar['FK_USER']."', '".$this->kommentar['STAMP']."', 
						    NULL, '".$this->kommentar['PUBLISH']."', '".sqlString($this->kommentar['KOMMENTAR'])."', '".sqlString($this->bbcode->parsed_text)."',
							".(is_null($this->kommentar['BEWERTUNG']) ? "NULL" : "'".$this->kommentar['BEWERTUNG']."'")." )";*/
				// in db speichern
				//print_r ($query);
				if(!mysql_query($query))
				{
				  #echo $query."<hr>";
				  #echo mysql_error(); die();
				  $this->err[] = "SAVE_ERROR";
				  $this->comment_ok = false;
				} // konnte nicht speichern
				else
				{
				  //$this->updateComment();
				  //$this->updateRating();
				  
				  $this->ok_out[] = "SAVE_OK";
				  $this->comment_ok = true;
				} // erfolgreich gespeichert
			} // Die Zeitsperre ist aufgehoben er darf speichern
			
			//print_r ($this->bbcode->parsed_text);
			if(!$this->comment_ok)
		      setcookie("comment", "com", time() - 1, "/");
		  } // $this->err = false
		}// speicher den kommentar
		
  //report comment 
  //wird noch nicht verwendet muss evt. noch neu angepasst werden		
  public function report_comment($fk, $subjekt, $body)
  		{
		  // prÃ¼fen obs den kommentar gibt
		  if(mysql_query("select FK from `kommentar_".strtolower($this->table)."` WHERE `ID_KOMMENTAR_".strtoupper($this->table)."` = ".(int)$fk." LIMIT 1 ;"))
		    $fk = $fk;
		  else
		    $fk = false;
			
		  // alle html tags entfernen
		  $subjekt = strip_tags($subjekt);
		  $body = strip_tags($body);
		  
		  if($fk)  
		  {
		    // kommentar spalte REPORT updaten status 1
			if(!mysql_query("UPDATE `kommentar_".strtolower($this->table)."` SET `REPORT` = '1' WHERE `ID_KOMMENTAR_".strtoupper($this->table)."` = ".(int)$fk." LIMIT 1 ;"))
		      $this->err_out[] = "REPORT_ERROR";
			// die meldung in die tabelle report speichern
			elseif(!mysql_query("INSERT INTO `kommentar_".strtolower($this->table)."` ( `FK`, `FK_USER`, `SUBJEKT`, `BODY` ) VALUE ( '".$fk."', ".$this->uid.", '".mysql_escape_string($subjekt)."', '".mysql_escape_string($body)."' )"))
		      $this->err_out[] = "REPORT_ERROR";
			else
			  $this->ok_out[] = "REPORT_OK";
		  } // Kommentar ist vorhanden
		  else
		    $this->err_out[] = "REPORT_FK_ERROR";
			// Kommentar wurde nicht gefunden
		} // meldet ein kommentar
  
  // Private Funktionen		
  private function updateComment()
  		{
		  // holt die anzahl der kommentare
		  $comment = $this->db->fetch1("select count(*) as com_anzahl from kommentar_".strtolower($this->table)." where FK = '".$this->fk."'");
		  //print_r($comment);
		  mysql_query("UPDATE `".strtolower($this->table)."` SET `PCOUNT` = '".$comment['com_anzahl']."' WHERE `ID_".strtoupper($this->table)."` = ".$this->fk." LIMIT 1 ;");
		} // speichert die anzahl der kommentar in der entsprechenden tabelle
		
  private function updateRating()
  		{
		  // holt die anzahl der bewertungen und die summe davon
		  $rating = $this->db->fetch1("select count(*) as com_anzahl, SUM(RATING) as com_summe from rating_".strtolower($this->table)." 
		  								where FK = '".$this->fk."'
										group by FK");
		  if($rating['com_anzahl'] > 0)
		  {
		    $final_rating = $rating['com_summe'] / $rating['com_anzahl'];
		    //print_r ($rating);
		  }
		  else
		    $final_rating = 0;
		    mysql_query("UPDATE `".strtolower($this->table)."` SET `RATING` = '".$final_rating."' WHERE `ID_".strtoupper($this->table)."` = '".$this->fk."' LIMIT 1 ;");
		} // speichert die bewertung in der entsprechenden tabelle	
		
  public function checkRating()
  		{
		  if(!$row = mysql_fetch_assoc(mysql_query("select DATE_ADD(STAMP, INTERVAL 6 HOUR) as last from rating_".strtolower($this->table)." where FK = '".$this->fk."' and ".($this->uid ? "FK_USER = ".(int)$this->uid : "IP = '".$this->ip."'")." order by STAMP DESC LIMIT 1")))
		    return false;
		  else
		    return $row['last']; // keine bewertung von diesem User in diesem Bereich gefunden
		  // Ã¼berprÃ¼ft ob user schon mal gevotet hat
		}
		
  private function checkData()
  		{
		  // Ã¼berprÃ¼ft die daten ob es sie auch gibt
		  if($this->fk <= 0 && is_numeric($this->fk))
		  {
		    $this->err_message[] = "UngÃ¼ltige Zuordnung: ".$this->fk;
			$this->err = true;
		  } // FK ist ungÃ¼ltig
		  
		  if($this->table == '' || preg_replace("/[a-zA-Z0-9_]/", "", $this->table))
		  {
		    $this->err_message[] = "Variable <b>\$this-&gt;table</b> ist leer oder enthÃ¤lt ungÃ¼ltige Zeichen";
			$this->err = true;
		  } // leere $this->table
		  
		  global $SILENCE;
		  if($SILENCE == false)
		  {
		    if(!mysql_num_rows( mysql_query("SHOW TABLES LIKE 'kommentar_".strtolower($this->table)."'")))
		    {
		      $this->err_message[] = "Tabelle \"<b>kommentar_".strtolower($this->table)."</b>\" existiert nicht";
			  $this->err = true;
		    } // Tabelle exisitert nicht
		  } // nur wenn Debug Modus
		  
		  if($this->err)
		  {
		    die(implode("<br />", $this->err_message));
			//die(print_r($this->err_message));
		  }
		  
		}
		
  private function repost()
  		{
		  // setzt ein cookie fÃ¼r eine zeitsperre
		  if(!$_COOKIE['comment'])
		  {
		    setcookie("comment", "com", time() + $this->repostTime, "/");
			return true;
			//echo "hahaha";
		  } // cookie wird gesetzt (kommentar erfolgreich gespeichert)
		  else
		  {
		    $this->err[] = "REPOST_ERROR";	
			return false;   
			//echo "hahaha fehler -.-";
		  } // Fehler die Zeit ist noch nicht um (60s)
		}

   public function checkErrors()
   {
      #echo ht(dump($this->err));
	  if($this->err && !empty($this->err))
	  {
	    $this->err_out = get_messages("KOMMENTAR", implode(",", $this->err));
	  } // error
   } // checkerror();
}
?>