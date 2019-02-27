<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once($ab_path.'sys/lib.parserbb.php');
// Stati von Rezensionen
// Status 0: Inaktiv
// Status 1: Vom User aktiviert
// Status 2: Vom Admin aktiviert
// Status 3: Aktiv


class rezension {
	function rezension() {
//		$bericht;
		$this->db = &$GLOBALS['db'];
		$this->langval = &$GLOBALS['langval'];
		$this->s_lang = &$GLOBALS['s_lang'];
		$this->uid = &$GLOBALS['uid'];
	}

	// Rezension löschen
	function delete($rezid) { // NOT TESTED YET
		$db->querynow("DELETE FROM img
					WHERE MODUL='Rezension' and
					FK_ANZEIGE='".$rezid."'");

	// Bilder aus DB löschen
		if($bilder = $db->fetch1("SELECT * from img WHERE MODUL='Rezension' and FK_ANZEIGE='".$rezid."'"))
		{
			foreach($bilder as $bild)
			{
				$del = $db->querynow("delete from img where ID_IMG = ".$bild["ID_IMG"]);
				@unlink("../".$bild['SRC']);
				if(!empty($bild['SRC_T']))
				{
					@unlink("../".$bild['SRC_T']);
				}
				$bericht[] = "Datei ".$bild['SRC']." und thumb gelöscht.";
			}
		}
		return $bericht;
	}

	// Der eigentliche Parser
	function translate($text, $rezid) {
		$bbcode = new StringParser_BBCode();
		// Fett
		$bbcode->addCode ('b', 'simple_replace', null, array ('start_tag' => '<b>', 'end_tag' => '</b>'),
						  'inline', array ('block', 'inline'), array ());
		// Kursiv
		$bbcode->addCode ('i', 'simple_replace', null, array ('start_tag' => '<i>', 'end_tag' => '</i>'),
						  'inline', array ('listitem', 'block', 'inline', 'link'), array ());
		// Listenelement
		$bbcode->addCode ('list', 'simple_replace', null, array ('start_tag' => '<li>', 'end_tag' => '</li>'),
						  'inline', array ('listitem', 'block', 'inline', 'link'), array ());
		// Quote
		$bbcode->addCode ('quote', 'simple_replace', null, array ('start_tag' => '<p class="quote">
					<b>Zitat:</b><br>', 'end_tag' => '</p>'), 'inline', array ('block', 'inline'), array ());
		// Farbe
		$bbcode->addCode ('color', 'callback_replace', 'do_bbcode_color', array ('usecontent_param' => 'default'),
						  'inline', array ('listitem', 'block', 'inline', 'link','font','size'), array ());
		// Schriftgröße
		$bbcode->addCode ('size', 'callback_replace', 'do_bbcode_size', array ('usecontent_param' => 'default'),
						  'inline', array ('listitem', 'block', 'inline', 'link','font','color'), array ());
		// Code
		$bbcode->addCode ('code', 'usecontent?', 'do_bbcode_code', array ('usecontent_param' => 'default'),
						  'code', array ('listitem', 'block', 'inline'), array ('code'));
		$bbcode->addParser (array ('block', 'inline', 'link', 'listitem'), 'htmlspecialchars');
		$bbcode->addParser (array ('block', 'inline', 'link', 'listitem'), 'nl2br');
		if ($text != "")
			$text = $bbcode->parse($text);
		return $text;
	}

// Rezensionen holen
	function getAllOld($status, $limit="0", $show="10")
	{
		$query = "select r.*, s.*, u.name as uname
							from `rezension` r
							left join string_rezension s on s.FK=r.ID_REZENSION
							left join user u on u.ID_USER=r.FK_UID
							WHERE s.BF_LANG=if(r.BF_LANG_REZENSION
							& ".$this->langval.", ".$this->langval.",
							1 << floor(log(r.BF_LANG_REZENSION+0.5)/log(2)))
							and status = '$status' and s.S_TABLE='REZENSION'
							order by ID_REZENSION desc
							limit ".$limit.", ".$show;
		return $this->db->fetch_table($query);
	}


	function getAll($status, $limit="0", $show="10")	{
		$query = "select t.*, s.*, u.name as uname, count( c.ID_COMMENT ) AS anzCom, str.V1 as katname
							FROM `rezension` t
							LEFT JOIN string_rezension s ON s.FK = t.ID_REZENSION
							LEFT JOIN user u ON u.ID_USER = t.FK_UID
							LEFT JOIN COMMENT_THREAD ct ON t.ID_REZENSION = ct.FK
							LEFT JOIN COMMENT c ON ct.ID_COMMENT_THREAD = c.FK_COMMENT_THREAD
							LEFT JOIN string_tree_rezension str ON t.FK_CAT = str.FK
							WHERE  s.BF_LANG=if(t.BF_LANG_REZENSION & ".$this->langval.", ".$this->langval.",
							1 << floor(log(t.BF_LANG_REZENSION+0.5)/log(2)))
							and t.STATUS = '$status'
							and s.S_TABLE='REZENSION'
							GROUP BY ID_REZENSION
							ORDER BY t.ID_REZENSION DESC
							limit ".$limit.", ".$show."";
		return $this->db->fetch_table($query);
	}


// Rezension rudimentär einfügen
	function insertStep1($topic, $descr, $asin="")
	{
		$now = date("Y-m-d H:i:s");
		$startRezensionInsertArray["FK_UID"] = $this->uid;
		$startRezensionInsertArray["V1"] = $topic;
		$startRezensionInsertArray["V2"] = $descr;
		$startRezensionInsertArray["DATUM"] = $now;
		$startRezensionInsertArray["ASIN"] = $asin;
		return $this->db->update("rezension", $startRezensionInsertArray);
	}

// Rezension bearbeiten
	function insertStep2($rezid, $topic, $descr, $tutText, $inform, $asin) {
		$now = date("Y-m-d H:i:s");
		if ($inform == true)
			$inform = "1";
		$updateRezArray["ID_REZENSION"] = $rezid;
		$updateRezArray["STATUS"] = "1";
		$updateRezArray["DATUM"] = $now;
		$updateRezArray["INFORM"] = $inform;
		$updateRezArray["V1"] = $topic;
		$updateRezArray["V2"] = $descr;
		$updateRezArray["T1"] = $tutText;
		$updateRezArray["ASIN"] = $asin;
		return $this->db->update("rezension", $updateRezArray);
	}

// Bilder für den Editor holen
	function getRezBilder( $rezid )
	{
		$tmpArray = $this->db->fetch_table("SELECT SRC, ALT FROM img
								WHERE modul='Rezension' and FK_ANZEIGE='$rezid' ORDER BY alt");
		return $tmpArray;
	}

	function changeStatus( $id, $wer, $was, $admintext="")
	{
        global $ab_path;
	/* Admin aktivert		1=>	3
		Admin aktivert		0=>	2
		Admin deaktivert	2=>	0
		Admin deaktivert	3=>	1
		User aktiviert		2=>	3
		User aktiviert		0=>	1
		User deaktiviert	3=>	2
		User deaktiviert	1=>	0
		*/
		if ( $was == "0" )
			$was = "deaktivieren";
		if ( $was == "1" )
			$was = "aktivieren";
		if ( empty( $wer ) || empty( $was ) || empty( $id ) )
			return false;
		$oldStatus = $this->db->fetch_atom("SELECT STATUS FROM rezension WHERE ID_REZENSION='".$id."'");
		if ( !is_numeric($wer) )
		{
			if( $wer == "admin")
			{
					if ( $was == "aktivieren")
					{
							if ( $oldStatus == 1 )
								$newStatus = 3;
							else
								$newStatus = 2;
					}
					elseif  ( $was == "deaktivieren")
					{
							if ( $oldStatus == 2 )
								$newStatus = 0;
							else
								$newStatus = 1;
					}
					else
						return false;
			}
		}
		else
		{
			$checkId = $this->db->fetch_atom("SELECT ID_REZENSION FROM rezension
								WHERE ID_REZENSION='".$id."' and FK_UID='".$wer."'");
			if ( count($checkId) )
			{
				if ( $was == "aktivieren")
				{
						if ( $oldStatus == 2 )
							$newStatus = 3;
						else
							$newStatus = 1;
				}
				elseif  ( $was == "deaktivieren")
				{
						if ( $oldStatus == 3 )
							$newStatus = 2;
						else
							$newStatus = 0;
				}
				else
					return false;
			}
			else
				return false;
		}

		// Speichern
		$aktiveChange["ID_REZENSION"]= $id;
		$aktiveChange["STATUS"]= $newStatus;
		$this->db->update('rezension', $aktiveChange);
		require_once($ab_path."sys/lib.usernotice.php");
		$UN = new usernotice();
		$urheber = $this->db->fetch_atom("SELECT FK_UID FROM rezension WHERE ID_REZENSION = '".$id."'");
		if( $newStatus == 3) {
				$UN->addNotice($urheber, "REZENSION_ACK", $id);
				$this->createHTMLfile($id);
		}
		if( ($newStatus == 1) && ($oldStatus == 0)) {
				$UN->addNotice($urheber, "REZENSION_CHECK", $id);
		}
		if( ($newStatus <= 1) && ($oldStatus >= 2)) {
				$UN->addNotice($urheber, "REZENSION_DENY", $id, $admintext);
		}
	}


/*/ Status einer Rezensionen im Backend ändern
	function changeStatus( $rezid, $newStatus )
	{
		$aktiveChange["ID_REZENSION"] = $rezid;
		$aktiveChange["STATUS"] = $newStatus;
		$this->db->update('rezension', $aktiveChange);
		if( $newStatus == 3) {
				$urheber = $this->db->fetch_atom("SELECT FK_UID FROM rezension WHERE ID_REZENSION = '".$rezid."'");
				require_once($ab_path."sys/lib.usernotice.php");
				$UN = new usernotice();
				$UN->addNotice($urheber, "REZENSION_ACK", $rezid);
				$this->createHTMLfile($rezid);
		}
		if( $newStatus == 1) {
				$urheber = $this->db->fetch_atom("SELECT FK_UID FROM rezension WHERE ID_REZENSION = '".$rezid."'");
				require_once($ab_path."sys/lib.usernotice.php");
				$UN = new usernotice();
				$UN->addNotice($urheber, "REZENSION_CHECK", $rezid);
		}
	}
*/
	// Alle Rezensionen von einem Nutzer finden
	function getUsersRezs() {
		$query = "select r.*, s.V1, s.V2
							from `rezension` r
							left join string_rezension s on s.FK=r.ID_REZENSION
							left join user u on u.ID_USER=r.FK_UID
							WHERE s.BF_LANG=if(r.BF_LANG_REZENSION & ".$this->langval.", ".$this->langval.",
							1 << floor(log(r.BF_LANG_REZENSION+0.5)/log(2)))
							and s.S_TABLE='REZENSION'
							and r.FK_UID ='".$this->uid."'
							order by r.ID_REZENSION desc
							limit 0,10";
		return $this->db->fetch_table($query);

	}

// Daten zu aktuellem Rezension holen
	function getRez( $rezid ) {
		$tmpArray = $this->db->fetch1("select r.*, r.datum as datum, s.V1, s.V2, s.T1, u.*,u.name as uname from `rezension` r
					left join string_rezension s on s.S_TABLE='rezension'
					and s.FK=r.ID_REZENSION
					and s.BF_LANG=if(r.BF_LANG_REZENSION & ".$this->langval.",
					".$this->langval.", 1 << floor(log(r.BF_LANG_REZENSION+0.5)/log(2)))
					LEFT  JOIN user u ON u.ID_USER = r.FK_UID
					WHERE ID_REZENSION='".$rezid."'");
		return $tmpArray;
	}

// Generiere HTML-Chaches
	function createHTMLfile( $rezid ) {
		$thisRez = $this->getRez( $rezid );
		$thisRez["T1"] = $this->translate($thisRez["T1"],$rezid);
		$filename = "../cache/rezensionen/rezension,".$rezid.".htm";
			if (!$myFile = fopen($filename, "w+")) {
				die("<br>Kann $filename nicht öffnen!");
			}
			$tpl_tmp = new Template(CacheTemplate::getHeadFile("tpl/".$this->s_lang."/rezension_template.htm"));
			$tpl_tmp->addvars($thisRez);
			$content = $tpl_tmp->process();
			if (!fwrite($myFile, $content)) {
				die("Kann in die Datei $filename nicht schreiben.");
			}
			fclose($myFile);
			chmod($filename, 0777);
		return 1;
	}

	function getAllCategories( $aktuelleIDKat = 0 ) {
		$selectNo1 = $this->db->fetch_table("SELECT t.ID_TREE_REZENSION as id, s.V1 as txt FROM tree_rezension t
									left join string_tree_rezension s on s.FK=t.ID_TREE_REZENSION
									WHERE  s.BF_LANG=if(t.BF_LANG_TREE_REZENSION & ".$this->langval.", ".$this->langval.",
									1 << floor(log(t.BF_LANG_TREE_REZENSION+0.5)/log(2)))");
		foreach( $selectNo1 as $s) {
			if ( $s["id"] == $aktuelleIDKat )
				$options[] = "<option value='".$s["id"]."' selected>".$s["txt"]."</option>";
			else
				$options[] = "<option value='".$s["id"]."'>".$s["txt"]."</option>";
		}
		return implode("\n", $options);
	}

}

?>