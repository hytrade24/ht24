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
  private $repostTime = 60; // Zeitsperre für Posts in Sekunden
  private $id_user = 0;
  private $err = false;
  private $err_message = array();
  private $uid = NULL;
  private $fk = 0; 
  private $ip = 0;
  private $string_fk = false;
  private $currentTime = NULL;
  private $comment_ok = false;
  private $bbcode = NULL;
  private $kommentar = array();
  private $comment_minlength = 3;
  
  // Public Functionen
  // neue variable string_fk, für fk der keine id ist z.b. handbuch
  // jürgen 02.02.09
  public function comment ( $fk = 0, $table, $string_fk = false )
  		{
		  $this->db = &$GLOBALS['db'];
		  $this->uid = &$GLOBALS['uid'];
		  $this->bbcode = &$GLOBALS['bbcode'];
		  $this->fk = (int)$fk;
		  $this->string_fk = $string_fk;
		  $this->table = $table; 
		  $this->ip = $_SERVER['REMOTE_ADDR'];
		  
		  $this->checkData(); // Überprüft die eingaben
		}
  
  public function getComments()
  		{
		  // holt sich die kommentare aus der db
		}
		
  public function publish()
  		{
		  // Kommentar Freigeben oder Sperren
		}  
		
  public function delComment()
  		{
		  // Kommentar Löschen
		}
  
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
				// kommentar array wird gefüllt
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
		  // prüfen obs den kommentar gibt
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
			elseif(!mysql_query("INSERT INTO `kommentar_".strtolower($this->table)."` ( `FK`, `FK_USER`, `SUBJEKT`, `BODY` ) VALUE ( ".(int)$fk.", ".$this->uid.", '".mysql_escape_string($subjekt)."', '".mysql_escape_string($body)."' )"))
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
		  $comment = $this->db->fetch1("select count(*) as com_anzahl from kommentar_".strtolower($this->table)." where FK = ".$this->fk);
		  //print_r($comment);
		  mysql_query("UPDATE `".strtolower($this->table)."` SET `PCOUNT` = '".$comment['com_anzahl']."' WHERE `ID_".strtoupper($this->table)."` = ".$this->fk." LIMIT 1 ;");
		} // speichert die anzahl der kommentar in der entsprechenden tabelle
		
  private function updateRating()
  		{
		  // holt die anzahl der bewertungen und die summe davon
		  $rating = $this->db->fetch1("select count(*) as com_anzahl, SUM(RATING) as com_summe from rating_".strtolower($this->table)." 
		  								where FK = ".$this->fk."
										group by FK");
		  if($rating['com_anzahl'] > 0)
		  {
		    $final_rating = $rating['com_summe'] / $rating['com_anzahl'];
		    //print_r ($rating);
		  }
		  else
		    $final_rating = 0;
        mysql_query("UPDATE `".strtolower($this->table)."` SET `RATING` = '".$final_rating."' WHERE `ID_".strtoupper($this->table)."` = ".$this->fk." LIMIT 1 ;");
        switch(strtolower($this->table)) {
          case "script":
            mysql_query("UPDATE `script_work` SET `RATING` = '".$final_rating."' WHERE `ID_SCRIPT_WORK` = ".$this->fk." LIMIT 1 ;");
          default:
            mysql_query("UPDATE `".strtolower($this->table)."_live` SET `RATING` = '".$final_rating."' WHERE `ID_".strtoupper($this->table)."_LIVE` = ".$this->fk." LIMIT 1 ;");
        }
		} // speichert die bewertung in der entsprechenden tabelle	
		
  public function checkRating()
  		{
		  if(!$row = mysql_fetch_assoc(mysql_query("select DATE_ADD(STAMP, INTERVAL 6 HOUR) as last from rating_".strtolower($this->table)." where FK = ".$this->fk." and ".($this->uid ? "FK_USER = ".(int)$this->uid : "IP = '".$this->ip."'")." order by STAMP DESC LIMIT 1")))
		    return false;
		  else
		    return $row['last']; // keine bewertung von diesem User in diesem Bereich gefunden
		  // überprüft ob user schon mal gevotet hat
		}
		
  public function addRating($rate, $comment = NULL, $RATE_TYP = NULL, $REF = NULL, $AR_KOMPETENZ = NULL)
  		{
		  if($row = mysql_fetch_assoc(mysql_query("select NAME from user where ID_USER = ".$this->uid)))
		    $USER = $row['NAME'];
		  else
		    $USER = "GAST";
		  
		  if(!is_null($AR_KOMPETENZ) && is_array($AR_KOMPETENZ)) {
		    $fields = ", `".implode("`, `", array_keys($AR_KOMPETENZ))."` ";
			$val = ", ".implode(", ", array_values($AR_KOMPETENZ));
		  }
		  
		  $query = "insert into `rating_".strtolower($this->table)."` 
		  		( `FK_USER`, `FK`, ".($REF != NULL ? "`FK2`," : "")." `USERNAME`, ".($RATE_TYP != NULL ? "`RATE_TYP`," : "")." `RATING`, `COMMENT`, `IP`".$fields." ) 
				VALUE ( ".$this->uid.", ".(int)$this->fk.", ".($REF != NULL ? (int)$REF."," : "")." '".$USER."', ".($RATE_TYP != NULL ? "'".$RATE_TYP."'," : "")." ".(int)$rate.", '".mysql_escape_string($comment)."', '".$this->ip."'".$val." )";
			#die(ht(dump($query)));
		  if(mysql_query($query)) {
		    $this->updateRating();
			return true;
		  }
		  else
		    return mysql_error();
		}
		
  private function checkData()
  		{
		  // überprüft die daten ob es sie auch gibt
		  if($this->fk == 0)
		  {
		    $this->err_message[] = "ebiz-berni";
			$this->err = true;
		  } // FK ist ungültig
		  
		  if($this->table == '' || preg_replace("/[a-zA-Z0-9_]/", "", $this->table))
		  {
		    $this->err_message[] = "Variable <b>\$this-&gt;table</b> ist leer oder enthält ungültige Zeichen";
			$this->err = true;
		  } // leere $this->table
		  
		  /*global $SILENCE;
		  if($SILENCE == false)
		  {
		    if(!mysql_num_rows( mysql_query("SHOW TABLES LIKE 'kommentar_".strtolower($this->table)."'")))
		    {
		      $this->err_message[] = "Tabelle \"<b>kommentar_".strtolower($this->table)."</b>\" existiert nicht";
			  $this->err = true;
		    } // Tabelle exisitert nicht
		  } // nur wenn Debug Modus*/
		  
		  if($this->err)
		  {
		    die(implode("<br />", $this->err_message));
			//die(print_r($this->err_message));
		  }
		  
		}
		
  private function repost()
  		{
		  // setzt ein cookie für eine zeitsperre
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