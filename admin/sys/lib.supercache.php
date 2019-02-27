<?php
/* ###VERSIONSBLOCKINLCUDE### */


class supercache {

	// Konstruktor
	function supercache()
	{
		$this->db = &$GLOBALS['db'];
		$this->datInsg = 0;
	}

	// Alle Inhalte Cachen
	function cacheAll() {
		$returnAllArray = array();
		$returnAllArray[] = $this->cacheUser();
		$returnAllArray[] = $this->cacheFirma();
		$returnAllArray[] = $this->cacheUmfrageBilder();
		$returnAllArray[] = $this->cacheTutorial();
		$returnAllArray[] = $this->cacheRezension();
		return $returnAllArray;
/*		foreach ($returnAllArray as $fAA)
		{
			$returnArray[0] += $fAA[0];
			$returnArray[1] += $fAA[1];
			$returnArray[2] .= $fAA[2].", ";
		}
		return $returnArray;*/
	}

	// Alle Tutorials cachen
	function cacheTutorial()
	{
        global $ab_path;
		require_once($ab_path.'sys/lib.tutorial.php');
		$myTut = new tutorial($uid);
		$allTutIds = $this->db->fetch_table("SELECT ID_TUTORIAL as id FROM tutorial WHERE STATUS=3");
		$counter = 0;
		$anzDateien = 0;
		foreach($allTutIds as $thisID)
		{
			$anzDateien += $myTut->createHTMLfile( $thisID["id"] );
			$counter++;
		}
		$returnArray[0] = $anzDateien;
		$returnArray[1] = $counter;
		$returnArray[2] = "Tutorials";
		return $returnArray;
	}

	// Alle Rezensionen Cachen
	function cacheRezension()
	{
        global $ab_path;
		require_once($ab_path.'sys/lib.rezension.php');
		$myRez = new rezension($uid);
		$allRezIds = $this->db->fetch_table("SELECT ID_REZENSION as id FROM rezension WHERE STATUS=3");
		$counter = 0;
		$anzDateien = 0;
		foreach($allRezIds as $thisID)
		{
			$anzDateien += $myRez->createHTMLfile( $thisID["id"] );
			$counter++;
		}
		$returnArray[0] = $anzDateien;
		$returnArray[1] = $counter;
		$returnArray[2] = "Rezensionen";
		return $returnArray;
	}

	// Die PNG fÃ¼r die Umfragen neu cachen
	function cacheUmfrageBilder()
	{
		// TODO: Komplett entfernen
		//require_once($ab_path.'sys/lib.poll.php');
		//$poll = new poll();
		//$ids = $this->db->fetch_table("SELECT ID_POLL_QUESTIONS as id FROM poll_questions WHERE AKTIV=1");
		$counter = 0;
		$anzDateien = 0;
		/*
		foreach($ids as $thisID)
		{
			$poll->remakePie($thisID["id"], "de");
			$poll->remakePie($thisID["id"], "en");
			$anzDateien += 2;
			$counter++;
		}
		*/
		$returnArray[0] = $anzDateien;
		$returnArray[1] = $counter;
		$returnArray[2] = "Umfragen";
		return $returnArray;
	}

	// Alle Seiten fÃ¼r User cachen; uBox und Profil
	function cacheUser()
	{
        global $ab_path;
		require_once($ab_path.'sys/lib.usercache.php');
		$ids = $this->db->fetch_table("SELECT ID_USER as id FROM user WHERE STAT=1");
		$counter = 0;
		$anzDateien = 0;
		foreach($ids as $thisID)
		{
			$usercache = new usercache($thisID["id"]);
			$usercache->create_all();
			$anzDateien += 11;
			$counter++;
		}
		$returnArray[0] = $anzDateien;
		$returnArray[1] = $counter;
		$returnArray[2] = "User";
		return $returnArray;
	}

	// Alles zu einem Firmenprofil cachen
	function cacheFirma( $onlyThisId=0 ) {
        global $ab_path;
		require_once($ab_path.'sys/lib.firmacache.php');
		$ids = $this->db->fetch_table("SELECT ID_FIRMA as id FROM firma");
		$counter = 0;
		$anzDateien = 0;
		foreach($ids as $thisID)
		{
			$firmacache = new firmacache($thisID["id"]);
			$firmacache->create_all();
			$anzDateien += 5;
			$counter++;
		}
		$returnArray[0] = $anzDateien;
		$returnArray[1] = $counter;
		$returnArray[2] = "Firmen";
		return $returnArray;
	}

	// FÃ¼r die Cache-Trommel einzeln die EintrÃ¤ge cachen.
	function cacheThisEntry( $art, $id ) {
        global $ab_path;
		switch ($art) {
			case "tutorial":
				require_once($ab_path.'sys/lib.tutorial.php');
				$myTut = new tutorial($uid);
				$myTut->createHTMLfile( $id );
				break;
			case "rezension":
				require_once($ab_path.'sys/lib.rezension.php');
				$myRez = new rezension($uid);
				$myRez->createHTMLfile( $id );
				break;
			case "umfrage":
				// TODO: Komplett entfernen
				/*
				require_once($ab_path.'sys/lib.poll.php');
				$poll = new poll();
				$poll->remakePie($id, "de");
				$poll->remakePie($id, "en");
				*/
				break;
			case "user":
				require_once($ab_path.'sys/lib.usercache.php');
				$usercache = new usercache($id);
				$usercache->create_all();
				break;
			case "firma":
				require_once($ab_path.'sys/lib.firmacache.php');
				$firmacache = new firmacache($id);
				$firmacache->create_all();
				break;
		}
	}

	function anzahlVonAllem() {
		  $anzahlArray["tutorial"] = (int)$this->db->fetch_atom("SELECT COUNT(*) FROM tutorial WHERE STATUS=3");
		  $anzahlArray["rezension"] = (int)$this->db->fetch_atom("SELECT COUNT(*) FROM rezension WHERE STATUS=3");
		  $anzahlArray["umfrage"] = (int)$this->db->fetch_atom("SELECT COUNT(*) FROM poll_questions WHERE AKTIV=1");
		  $anzahlArray["user"] = (int)$this->db->fetch_atom("SELECT COUNT(*) FROM user WHERE STAT=1");
		  $anzahlArray["firma"] = (int)$this->db->fetch_atom("SELECT COUNT(*) FROM firma");
		  $anzahlArray["all"] = array_sum($anzahlArray);
		  return $anzahlArray;
	}

	function getIds($art, $limit, $perpage) {
		switch ($art) {
			case "user":
				$ids = $this->db->fetch_table("SELECT ID_".$art." as id FROM ".$art." WHERE STAT=1 LIMIT ".$limit.",".$perpage);
				break;
			case "tutorial":
			case "rezension":
				$ids = $this->db->fetch_table("SELECT ID_".$art." as id FROM ".$art." WHERE STATUS=3 LIMIT ".$limit.",".$perpage);
				break;
			case "umfrage":
				$ids = $this->db->fetch_table("SELECT ID_POLL_QUESTIONS as id FROM poll_questions WHERE AKTIV=1 LIMIT ".$limit.",".$perpage);
				break;
			case "firma":
				$ids = $this->db->fetch_table("SELECT ID_".$art." as id FROM ".$art." WHERE LIMIT ".$limit.",".$perpage);
				break;
		}
		return $ids;
	}
}

?>