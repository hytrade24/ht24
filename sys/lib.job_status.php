<?php
/* ###VERSIONSBLOCKINLCUDE### */



/**
 * Klasse zur verwaltung der Job-Stati.
 * 
 * @package Jobs
 */
class job_status 
{
  /**
   * Datenbank-Objekt
   *
   * @var object ebiz_db
   */
  var $db;
  /**
   * User-ID des Benutzers
   *
   * @var int
   */
	var $uid;
	/**
	 * Letzte Fehlermeldung
	 *
	 * @var string
	 */
	var $err;
	
	/**
	 * Initialisieren der Klasse
	 *
	 * @return object job_status
	 */
	function job_status()
	{
		$this->db = &$GLOBALS['db'];
		$this->uid = &$GLOBALS['uid'];
		$this->err = array();
		
		return $this;
	}  
	
	/**
	 * Fehlermeldungen abfragen
	 *
	 * @return array
	 */
	function get_errors()
	{
	  return $this->err;
	}
	
	/**
	 * Prüfen ob Fehler aufgetreten sind
	 *
	 * @return bool
	 */
	function fail()
	{
	  return !empty($this->err);
	}
	
	/**
	 * Den Status aus einem Datensatz in ein für die Webseite passendes Format bringen.
	 *
	 * @param array $ar_status
	 * @return array
	 */
	function job_getstatus($ar_status) {
	  $result = array();
      if ((($ar_status['B_STATUS'] & $ar_status['STATUS']) & 14) == 14) {
	    $result['STATUS'] = "abgeschlossen!";		
        $result['AG'] = 1;
	    $result['AN'] = 1;
		return $result;
	  } // Beide abgeschlossen
      if ($ar_status['B_STATUS'] == 19) {
	    $result['STATUS'] = "Zuschlag entzogen!";		
        $result['AG'] = 1;
		return $result;
	  } // AG - Zuschlag enzogen - Sonderfall
      if ($ar_status['B_STATUS'] == 18) {
	    $result['STATUS'] = "Zuschlag abgelehnt!";		
	    $result['AN'] = 1;
		return $result;
	  } // AN - Zuschlag abgelehnt!
      if (($ar_status['B_STATUS'] & 16) == 16) {
	    $result['STATUS'] = "abgebrochen!";		
	    $result['AN'] = 1;
		return $result;
	  } // AN - abgebrochen!
      if (($ar_status['STATUS'] & 16) == 16) {
	    $result['STATUS'] = "abgebrochen!";		
        $result['AG'] = 1;
		return $result;
	  } // AG - abgebrochen!
      if (($ar_status['STATUS'] & 8) == 8) {
	    $result['STATUS'] = "abgeschlossen!";		
        $result['AG'] = 1;
		return $result;
	  } // AG - abgeschlossen!
      if (($ar_status['B_STATUS'] & 14) == 14) {
	    $result['STATUS'] = "abgeschlossen!";	
	    $result['AN'] = 1;
		return $result;
	  } // AN - abgeschlossen!
      if (($ar_status['B_STATUS'] & 7) == 7) {
	    $result['STATUS'] = "angenommen!";		
	    $result['AN'] = 1;
		return $result;
	  } // AN - angenommen!
	  if (($ar_status['B_STATUS'] & 3) == 3) {
	    $result['STATUS'] = "zuschlag!";		
	    $result['AN'] = 1;
		return $result;
	  } // AN - zuschlag!
	  return false;
	}
  
	/**
	 * Dem User ($id_user) den Zuschlag für den Job ($id_job) erteilen.
	 *
	 * @param int $id_job
	 * @param int $id_user
	 * @return bool
	 */ 
  function job_zuschlag($id_job, $id_user) {
	  if (!is_numeric($id_user) || !is_numeric($id_job)) {
	    // Parameter fehlerhaft / nicht vollständig
		$this->err[] = "MISSING_PARAMS";
	    return false;
	  }
	
	  $job_users = $this->db->fetch1("SELECT FK_USER, FK_USER2, STATUS FROM job_live WHERE ID_JOB_LIVE=".$id_job);
	  $user_status = $this->db->fetch_atom("SELECT B_STATUS FROM job2user WHERE FK_JOB=".$id_job." AND FK_USER=".$id_user);
	  if (!$job_users) {
		$this->err[] = "NO_RIGHTS";
		return false;
	  }
	  if (($job_users["STATUS"] == 1) && (($user_status&13) == 1) && (!$job_users["FK_USER2"])) {
        $job_status = $job_users["STATUS"];
	    // Der Zuschlag ist noch nicht vergeben worden.
	    if ($job_users["FK_USER"] == $this->uid) {
		  /* Dem aktiven User gehört der Job.
		     Setze FK_USER2 (User mit Zuschlag) und STAMP_AUFTRAG (Auftrag erteilt am ...) in 'job_live'
			 Setze von B_STATUS (Bewerberstatus in 'job2user') und STATUS (Jobstatus in 'job_live') das 
			   2. Bit (Zuschlag, Wert: 2) und lösche das 5. Bit (Abbruch, Wert: 16) beim User */
	      $job_status = ($job_status | 2);
	      $user_status = ($user_status | 2) - ($user_status & 16);
		  
	
		  $this->db->querynow("UPDATE job_live SET FK_USER2=".$id_user.", STAMP_AUFTRAG=NOW(), STATUS=".$job_status."
		  					WHERE ID_JOB_LIVE=".$id_job);
		  $this->db->querynow("UPDATE job SET FK_USER2=".$id_user.", STAMP_AUFTRAG=NOW(), STATUS=".$job_status."
		  					WHERE ID_JOB=".$id_job);
		  $this->db->querynow("UPDATE job2user SET B_STATUS=".$user_status." 
		  					WHERE FK_USER=".$id_user." AND FK_JOB=".$id_job);
          return true;
		} else {
		  // User ist nicht der Eigentümer des Jobs
		  $this->err[] = "NO_RIGHTS";
		  return false;
		}
	  }
	  // Der Zuschlag wurde bereits vergeben oder der Job existiert nicht, Fehlschlag.
	  $this->err[] = "ALREADY_ASSIGNED";
	  return false;
	}
	
	/**
	 * Als User ($id_user) den Zuschlag für den Job ($id_job) akzeptieren.
	 *
	 * @param unknown_type $id_job
	 * @param unknown_type $id_user
	 * @return unknown
	 */
  function user_annehmen($id_job, $id_user) {
	  if (!is_numeric($id_user) || !is_numeric($id_job)) {
	    // Parameter fehlerhaft / nicht vollständig
		$this->err[] = "MISSING_PARAMS";
	    return false;
	  }

	  $job_users = $this->db->fetch1("SELECT FK_USER, FK_USER2, STATUS FROM job_live WHERE ID_JOB_LIVE=".$id_job);
	  $user_status = $this->db->fetch_atom("SELECT B_STATUS FROM job2user WHERE FK_JOB=".$id_job." AND FK_USER=".$id_user);
	  if (!$job_users) {
		$this->err[] = "NO_RIGHTS";
		return false;
	  }
	  if (($job_users["STATUS"] == 3) && ($user_status == 3)) {
	    // Das Angebot wurde noch nicht angenommen.
        $job_status = $job_users["STATUS"];
	    if ($job_users["FK_USER2"] == $this->uid) {
		  /* Der aktive User hat den Zuschlag erhalten.
			 Setze von B_STATUS (Bewerberstatus in 'job2user') und STATUS (Jobstatus in 'job_live') das 
			   3. Bit (Angenommen, Wert: 4) */
	      $job_status = ($job_status | 4);
	      $user_status = ($user_status | 4);
	
		  $this->db->querynow("UPDATE job_live SET STATUS=".$job_status." WHERE ID_JOB_LIVE=".$id_job);
		  $this->db->querynow("UPDATE job SET STATUS=".$job_status." WHERE ID_JOB=".$id_job);
		  $this->db->querynow("UPDATE job2user SET B_STATUS=".$user_status." 
		  					WHERE FK_USER=".$id_user." AND FK_JOB=".$id_job);
          return true;
		} else {
		  // User hat den Zuschlag nicht erhalten.
		  $this->err[] = "NO_RIGHTS";
		  return false;
		}
	  }
	  // Das Angebot wurde bereits angenommen.
	  $this->err[] = "ALREADY_ACCEPTED";
	  return false;
	}
	
	/**
	 * Als User ($id_user) den Job ($id_job) abbrechen / ablehnen.
	 *
	 * @param int $id_job
	 * @param int $id_user
	 * @return bool
	 */
  function user_abbruch($id_job, $id_user) {
	  if (!is_numeric($id_user) || !is_numeric($id_job)) {
	    // Parameter fehlerhaft / nicht vollständig
		$this->err[] = "MISSING_PARAMS";
	    return false;
	  }

	  $job_users = $this->db->fetch1("SELECT FK_USER, FK_USER2, STATUS FROM job_live WHERE ID_JOB_LIVE=".$id_job);
	  $user_status = $this->db->fetch_atom("SELECT B_STATUS FROM job2user WHERE FK_JOB=".$id_job." AND FK_USER=".$id_user);
	  if (!$job_users) {
		$this->err[] = "NO_RIGHTS";
		return false;
	  }
	  if ((($job_users["STATUS"]&17) == 1) && (($user_status&17) == 1)) {
        $job_status = $job_users["STATUS"];
	    // Der Zuschlag ist noch nicht vergeben worden.
	    if ($job_users["FK_USER2"] == $this->uid) {
		  /* Der aktiven User hat den Zuschlag erhalten.
			 Setze von B_STATUS (Bewerberstatus in 'job2user') und STATUS (Jobstatus in 'job_live') das 
			   5. Bit (Abbruch, Wert: 16) und lösche das 1. Bit (Aktiv, Wert: 1) */
		  if (($user_status & 6) == 6) {
		    // Der User hat den Zuschlag erhalten und angenommen, Job schließen.
	        $job_status -= ($job_status & 1);
      	    $free = "";
		  } else {
		    $job_status -= ($job_status & 2);
      	    $free = ", FK_USER2=NULL, STAMP_AUFTRAG=NULL";
		  }
		  
	      $user_status = ($user_status | 16) - ($user_status & 1);
		  $this->db->querynow("UPDATE job2user SET B_STATUS=".$user_status." 
							WHERE FK_USER=".$id_user." AND FK_JOB=".$id_job);
		  $this->db->querynow("UPDATE job_live SET STATUS=".$job_status.$free." WHERE ID_JOB_LIVE=".$id_job);
		  $this->db->querynow("UPDATE job SET STATUS=".$job_status.$free." WHERE ID_JOB=".$id_job);
          return true;
		} else {
		  // User hat den Zuschlag nicht erhalten.
	      $user_status -= ($user_status & 1);
		  $this->db->querynow("UPDATE job2user SET B_STATUS=".$user_status." 
		  					WHERE FK_USER=".$id_user." AND FK_JOB=".$id_job);
          return true;
		}
	  }
	  // Die Bewerbung wurde bereits abgebrochen / der Zuschlag abgelehnt.
      $this->err[] = "ALREADY_ABORTED";
	  return false;
	}	
	
    /* ------------------------------------------------------------------
	   Als User ($id_user) den Job ($id_job) reaktivieren.
       ------------------------------------------------------------------ */
	/**
	 * Als User ($id_user) den Job ($id_job) reaktivieren.
	 *
	 * @param int $id_job
	 * @param int $id_user
	 * @return bool
	 */
  function user_activate($id_job, $id_user) {
	  if (!is_numeric($id_user) || !is_numeric($id_job)) {
	    // Parameter fehlerhaft / nicht vollständig
		$this->err[] = "MISSING_PARAMS";
	    return false;
	  }

	  $job_users = $this->db->fetch1("SELECT FK_USER, FK_USER2, STATUS FROM job_live WHERE ID_JOB_LIVE=".$id_job);
	  $user_status = $this->db->fetch_atom("SELECT B_STATUS FROM job2user WHERE FK_JOB=".$id_job." AND FK_USER=".$id_user);
	  if (!$job_users) {
		$this->err[] = "NO_RIGHTS";
		return false;
	  }
	  if ((($job_users["STATUS"]&1) == 1) && (($user_status&1) == 0)) {
        $job_status = $job_users["STATUS"];
	    // Der Zuschlag ist noch nicht vergeben worden.
	    $user_status = ($user_status | 1);
		$this->db->querynow("UPDATE job2user SET B_STATUS=".$user_status." 
		  					WHERE FK_USER=".$id_user." AND FK_JOB=".$id_job);
        return true;
	  }
	  // Die Bewerbung wurde bereits abgebrochen / der Zuschlag abgelehnt.
	  $this->err[] = "ALREADY_ABORTED";
	  return false;
	}	
	
	
    /* ------------------------------------------------------------------
	   Als Arbeitgeber ($id_user) den Job ($id_job) reaktivieren.
       ------------------------------------------------------------------ */
	/**
	 * Als Arbeitgeber ($id_user) den Job ($id_job) reaktivieren.
	 *
	 * @param unknown_type $id_job
	 * @param unknown_type $id_user
	 * @return unknown
	 */
  function job_activate($id_job, $id_user) {
	  if (!is_numeric($id_user) || !is_numeric($id_job)) {
	    // Parameter fehlerhaft / nicht vollständig
		$this->err[] = "MISSING_PARAMS";
	    return false;
	  }

	  $job_users = $this->db->fetch1("SELECT FK_USER, FK_USER2, STATUS FROM job_live WHERE ID_JOB_LIVE=".$id_job);
	  $user_status = $this->db->fetch_atom("SELECT B_STATUS FROM job2user WHERE FK_JOB=".$id_job." AND FK_USER=".$id_user);
	  if ((!$job_users) || ($job_users["FK_USER"] != $this->uid)) {
		$this->err[] = "NO_RIGHTS";
		return false;
	  }
	  if ((($job_users["STATUS"]&15) == 0)) {
        $job_status = $job_users["STATUS"];
	    // Der Zuschlag ist noch nicht vergeben worden.
	    $job_status = ($job_status | 1) - ($job_status & 16);
		$this->db->querynow("UPDATE job_live SET STATUS=".$job_status." WHERE ID_JOB_LIVE=".$id_job);
		$this->db->querynow("UPDATE job SET STATUS=".$job_status." WHERE ID_JOB=".$id_job);
        return true;
	  }
	  // Die Bewerbung wurde bereits abgebrochen / der Zuschlag abgelehnt.
	  $this->err[] = "ALREADY_ABORTED";
	  return false;
	}
	
    function job_restart($id_job, $id_user) {
	  if (!is_numeric($id_user) || !is_numeric($id_job)) {
	    // Parameter fehlerhaft / nicht vollständig
		$this->err[] = "MISSING_PARAMS";
	    return false;
	  }

	  $job_users = $this->db->fetch1("SELECT FK_USER, FK_USER2, STATUS, FK_JOB_NEW FROM job_live WHERE ID_JOB_LIVE=".$id_job);
	  $user_status = $this->db->fetch_atom("SELECT B_STATUS FROM job2user WHERE FK_JOB=".$id_job." AND FK_USER=".$id_user);
	  if ((!$job_users) || ($job_users["FK_USER"] != $this->uid) || ($job_users["FK_JOB_NEW"])) {
		$this->err[] = "NO_RIGHTS";
		return false;
	  }
	  if ((($job_users["STATUS"]&8) == 0)) {
        $job_status = $job_users["STATUS"];
	    // Der Zuschlag ist noch nicht vergeben worden.
		$oJob = $this->db->fetch1("SELECT *,DATEDIFF(STAMP_END, STAMP) as runtime FROM job WHERE ID_JOB=".$id_job);
		$oJobLive = $this->db->fetch1("SELECT * FROM job_live WHERE ID_JOB_LIVE=".$id_job);
		
		unset($oJob["ID_JOB"], $oJob["FK_USER2"], $oJob["STAMP_AUFTRAG"], $oJob["STAMP_ABSCHLUSS"], $oJob["ENDPREIS"]);
		unset($oJobLive["FK_USER2"], $oJobLive["STAMP_AUFTRAG"], $oJobLive["STAMP_ABSCHLUSS"], $oJobLive["ENDPREIS"]);

        foreach ($oJob as $key => $value) {
  		  if (!is_numeric($value)) { $oJob[$key] = "'".$value."'"; }
  		  if (is_null($value)) { $oJob[$key] = "NULL"; }
  		  if (is_numeric($value)) { $oJob[$key] = $value; }
		}
		
		foreach ($oJob as $key => $value) {
  		  if (!is_numeric($value)) { $oJobLive[$key] = "'".$value."'"; }
  		  if (is_null($value)) { $oJobLive[$key] = "NULL"; }
  		  if (is_numeric($value)) { $oJobLive[$key] = $value; }
		}
		
		$oJob["STATUS"] = $oJobLive["STATUS"] = 1;
		$oJob["STAMP"] = $oJobLive["STAMP"] = "NOW()";
		$oJob["STAMP_END"] = $oJobLive["STAMP_END"] = "'".date('Y-m-d h:i:s', strtotime('+'.$oJob['runtime'].' days'))."'";
		unset($oJob['runtime']);
		$keys = "`".join("`,`", array_keys($oJob))."`";
		$values = join(",", array_values($oJob));
		$result = $this->db->querynow("INSERT INTO job ($keys) VALUES ($values)");
		//die(ht(dump("INSERT INTO job ($keys) VALUES ($values)")));
		$oJobLive["ID_JOB_LIVE"] = $result["int_result"];
		$keys = "`".join("`,`", array_keys($oJobLive))."`";
		$values = join(",", array_values($oJobLive));
		$this->db->querynow("INSERT INTO job_live ($keys) VALUES ($values)");
		
		$this->db->querynow("UPDATE job_live SET FK_JOB_NEW=".$result["int_result"]." WHERE ID_JOB_LIVE=".$id_job);
		$this->db->querynow("UPDATE job SET FK_JOB_NEW=".$result["int_result"]." WHERE ID_JOB=".$id_job);
        return true;
	  }
	  // Die Bewerbung wurde bereits abgebrochen / der Zuschlag abgelehnt.
	  $this->err[] = "ALREADY_DONE";
	  return false;
	}
	
	/**
	 * Als Auftraggeber den Job ($id_job) abbrechen / den User ($id_user) ablehnen.
	 *
	 * @param int $id_job
	 * @param int $id_user
	 * @return unknown
	 */
  function job_abbruch($id_job, $id_user) {
	  if (!is_numeric($id_user) || !is_numeric($id_job)) {
	    // Parameter fehlerhaft / nicht vollständig
		$this->err[] = "MISSING_PARAMS";
	    return false;
	  }

	  $job_users = $this->db->fetch1("SELECT FK_USER, FK_USER2, STATUS FROM job_live WHERE ID_JOB_LIVE=".$id_job);
	  if (!$job_users) {
		$this->err[] = "NO_RIGHTS";
		return false;
	  }
	  if ($job_users["STATUS"]&1) {
	    // Hole User-Status falls vergeben
	    if ($id_user = $job_users["FK_USER2"])
          $user_status = $this->db->fetch_atom("SELECT B_STATUS FROM job2user WHERE FK_JOB=".$id_job." AND FK_USER=".$id_user);
        $job_status = $job_users["STATUS"];
	    // Der Job ist noch aktiv.
	    if ($job_users["FK_USER"] == $this->uid) {
		  /* Dem aktiven User gehört der Job.
			 Setze von B_STATUS (Bewerberstatus in 'job2user') und STATUS (Jobstatus in 'job_live') das 
			   5. Bit (Abbruch, Wert: 16) und lösche das 1. Bit (Aktiv, Wert: 1) */
      	  $free = "";
          $job_status = ($job_status | 16) - ($job_status & 1);
	
		  $this->db->querynow("UPDATE job_live SET STATUS=".$job_status.$free." WHERE ID_JOB_LIVE=".$id_job);
		  $this->db->querynow("UPDATE job SET STATUS=".$job_status.$free." WHERE ID_JOB=".$id_job);
		  if ($user_status) {
		      $user_status -= ($user_status & 1);
        	  $this->db->querynow("UPDATE job2user SET B_STATUS=".$user_status." 
		  					WHERE FK_USER=".$id_user." AND FK_JOB=".$id_job);
		  }
          return true;
		} else {
		  // User hat den Zuschlag nicht erhalten.
		  $this->err[] = "NO_RIGHTS";
		  return false;
		}
	  }
	  // Die Bewerbung wurde bereits abgebrochen / der Zuschlag abgelehnt.
	  $this->err[] = "ALREADY_ABORTED";
	  return false;
	}	
	
	/**
	 * Als Auftraggeber den Job ($id_job) abbrechen / den User ($id_user) ablehnen.
	 *
	 * @param int $id_job
	 * @param int $id_user
	 * @return bool
	 */
  function job_zuschlag_rem($id_job, $id_user) {
	  if (!is_numeric($id_user) || !is_numeric($id_job)) {
	    // Parameter fehlerhaft / nicht vollständig
		$this->err[] = "MISSING_PARAMS";
	    return false;
	  }

	  $job_users = $this->db->fetch1("SELECT FK_USER, FK_USER2, STATUS FROM job_live WHERE ID_JOB_LIVE=".$id_job);
	  $user_status = $this->db->fetch_atom("SELECT B_STATUS FROM job2user WHERE FK_JOB=".$id_job." AND FK_USER=".$id_user);
	  if (!$job_users) {
		$this->err[] = "NO_RIGHTS";
		return false;
	  }
	  if ($job_users["STATUS"]&1) {
        $job_status = $job_users["STATUS"];
	    // Der Job ist noch aktiv.
	    if ($job_users["FK_USER"] == $this->uid) {
		  /* Dem aktiven User gehört der Job.
			 Setze von B_STATUS (Bewerberstatus in 'job2user') und STATUS (Jobstatus in 'job_live') das 
			   5. Bit (Abbruch, Wert: 16) und lösche das 1. Bit (Aktiv, Wert: 1) */
		  if (($job_status & 6) == 6) {
		    // Zuschlag erhalten und angenommen
      	    $free = "";
            $job_status = ($job_status | 16) - ($job_status & 1);
	        $user_status = ($user_status | 16) - ($user_status & 1);
		  } else {
		    // Zuschlag erhalten, aber noch nicht angenommen
      	    $free = ", FK_USER2=NULL, STAMP_AUFTRAG=NULL";
			// Zuschlag zurückziehen
            $job_status -= ($job_status & 2);
	        $user_status = ($user_status | 16);
		  }
	
		  $this->db->querynow("UPDATE job_live SET STATUS=".$job_status.$free." WHERE ID_JOB_LIVE=".$id_job);
		  $this->db->querynow("UPDATE job SET STATUS=".$job_status.$free." WHERE ID_JOB=".$id_job);
		  $this->db->querynow("UPDATE job2user SET B_STATUS=".$user_status." 
		  					WHERE FK_USER=".$id_user." AND FK_JOB=".$id_job);
          return true;
		} else {
		  // User hat den Zuschlag nicht erhalten.
		  $this->err[] = "NO_RIGHTS";
		  return false;
		}
	  }
	  // Die Bewerbung wurde bereits abgebrochen / der Zuschlag abgelehnt.
	  $this->err[] = "ALREADY_ABORTED";
	  return false;
	}	
	
	/**
	 * Als Auftraggeber den Job ($id_job) abschließen.
	 *
	 * @param int $id_job
	 * @param int $id_user
	 * @param array $postdata
	 * @return bool
	 */
  function job_abschluss($id_job, $id_user, $postdata) {
	  if (!is_numeric($id_user) || !is_numeric($id_job)) {
	    // Parameter fehlerhaft / nicht vollständig
		$this->err[] = "MISSING_PARAMS";
	    return false;
	  }

	  $job_users = $this->db->fetch1("SELECT FK_USER, FK_USER2, STATUS FROM job_live WHERE ID_JOB_LIVE=".$id_job);
	  $user_status = $this->db->fetch_atom("SELECT B_STATUS FROM job2user WHERE FK_JOB=".$id_job." AND FK_USER=".$id_user);
	  if ((!$job_users) || (($job_users["STATUS"] & 24) > 0))
	  {
	    // Die Bewerbung wurde bereits abgebrochen oder abgeschlossen.
		$this->err[] = "ALREADY_DONE";
	    return false;
	  } else {
        $job_status = $job_users["STATUS"];
	    // Der Job ist noch aktiv.
	    if ($job_users["FK_USER"] == $this->uid) {
		  /* Dem aktiven User gehört der Job.
			 Setze von STATUS (Jobstatus in 'job_live') das 
			   4. Bit (Abschluss, Wert: 8) und lösche das 1. Bit (Aktiv, Wert: 1) */
          $job_status = ($job_status | 8) - ($job_status & 1);
		  $user_status -= ($job_status & 1);
	
		  $this->db->querynow("UPDATE job2user SET B_STATUS=".$user_status." WHERE FK_USER=".$id_user." AND FK_JOB=".$id_job);
		  $this->db->querynow("UPDATE job_live SET STATUS=".$job_status.", STAMP_ABSCHLUSS='".$postdata["STAMP_ABSCHLUSS"]."', 
		  	ENDPREIS=".$postdata["ENDPREIS"]." WHERE ID_JOB_LIVE=".$id_job);
		  $this->db->querynow("UPDATE job SET STATUS=".$job_status.", STAMP_ABSCHLUSS='".$postdata["STAMP_ABSCHLUSS"]."', 
		  	ENDPREIS=".$postdata["ENDPREIS"]." WHERE ID_JOB=".$id_job);
			
	      include_once("sys/lib.newcomment.php");
		  $ar_ratings = array();
		  $ar_ratings["AN_FACHKOMPETENZ"] = $postdata['RATING_FACHLICH'];
		  $ar_ratings["AN_PUENKTLICHKEIT"] = $postdata['RATING_PUENKTLICH'];
		  $ar_ratings["AN_SOZIALKOMPETENZ"] = $postdata['RATING_SOZIAL'];
		  $ar_ratings["AN_INNOVATIV"] = $postdata['RATING_INNOVATIV'];
          $comment = new comment( $id_user, "user" );
          $comment->addRating($postdata["RATING"], $postdata["COMMENT"], "AG_JOB", $id_job, $ar_ratings);
		  
		  global $ab_path, $db;
		  $cachedir = $db->fetch_atom("select CACHE from user where ID_USER=". $id_user);
		  $lang = $db->fetch_table("select ABBR from lang where B_PUBLIC = 1");
		  
		  for($i=0; $i<count($lang); $i++) {
			if (file_exists($ab_path."cache/users/".$cachedir."/".$id_user."/box.".$lang[$i]['ABBR'].".htm"))
			  unlink($ab_path."cache/users/".$cachedir."/".$id_user."/box.".$lang[$i]['ABBR'].".htm");
			//die(ht(dump($lang[$i]['ABBR'])));
		  }
		  
          return true;
		} else {
		  // User hat den Zuschlag nicht erhalten.
		  $this->err[] = "NO_RIGHTS";
		  return false;
		}
	  }
	}

	/**
	 * Als Auftragnehmer den Job abschließen.
	 *
	 * @param int $id_job
	 * @param int $id_user
	 * @param array $postdata
	 * @return bool
	 */
  function user_abschluss($id_job, $id_user, $postdata) {
	  if (!is_numeric($id_user) || !is_numeric($id_job)) {
	    // Parameter fehlerhaft / nicht vollständig
		$this->err[] = "MISSING_PARAMS";
	    return false;
	  }

	  $job_users = $this->db->fetch1("SELECT FK_USER, FK_USER2, STATUS FROM job_live WHERE ID_JOB_LIVE=".$id_job);
	  $user_status = $this->db->fetch_atom("SELECT B_STATUS FROM job2user WHERE FK_JOB=".$id_job." AND FK_USER=".$id_user);
	  if ((!$user_status) || (($user_status & 24) > 0))
	  {
	    // Die Bewerbung wurde bereits abgebrochen oder abgeschlossen.
		$this->err[] = "ALREADY_DONE";
	    return false;
	  } else {
        $job_status = $job_users["STATUS"];
	    // Der Job ist noch aktiv.
	    if ($job_users["FK_USER2"] == $this->uid) {
		  /* Dem aktiven User wurde der Zuschlag erteilt.
			 Setze von STATUS (Jobstatus in 'job_live') das 
			   4. Bit (Abschluss, Wert: 8) und lösche das 1. Bit (Aktiv, Wert: 1) */
          $user_status = ($user_status | 8) - ($user_status & 1);
	
		  $this->db->querynow("UPDATE job2user SET B_STATUS=".$user_status." 
		  					WHERE FK_USER=".$id_user." AND FK_JOB=".$id_job);
			
	      include_once("sys/lib.newcomment.php");
		  $ar_ratings = array();
		  $ar_ratings["AG_ZAHLUNG"] = $postdata['RATING_ZAHLUNG'];
		  $ar_ratings["AG_PUENKTLICHKEIT"] = $postdata['RATING_PUENKTLICH'];
		  $ar_ratings["AG_SOZIALKOMPETENZ"] = $postdata['RATING_SOZIAL'];
		  $ar_ratings["AG_INNOVATIV"] = $postdata['RATING_INNOVATIV'];
          $comment = new comment( $job_users["FK_USER"], "user" );
          $comment->addRating($postdata["RATING"], $postdata["COMMENT"], "AN_JOB", $id_job, $ar_ratings);
		  
		  global $ab_path, $db;
		  $cachedir = $db->fetch_atom("select CACHE from user where ID_USER=". $job_users["FK_USER"]);
		  $lang = $db->fetch_table("select ABBR from lang where B_PUBLIC = 1");
		  
		  for($i=0; $i<count($lang); $i++) {
			if (file_exists($ab_path."cache/users/".$cachedir."/".$job_users["FK_USER"]."/box.".$lang[$i]['ABBR'].".htm"))
			  unlink($ab_path."cache/users/".$cachedir."/".$job_users["FK_USER"]."/box.".$lang[$i]['ABBR'].".htm");
			//die(ht(dump($lang[$i]['ABBR'])));
		  }		  
		  
          return true;
		} else {
		  // User hat den Zuschlag nicht erhalten.
		  $this->err[] = "NO_RIGHTS";
		  return false;
		}
	  }
	}

	
  }
?>
