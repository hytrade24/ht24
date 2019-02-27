<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once('sys/lib.parserbb.php');


// Stati von Tutorials
// Status 0: In bearbeitung durch User
// Status 1: Fertig
// Status 2: Neue
// Status 3: Unsichtbare

class tutorial {
	function tutorial() {
		$bericht;
		$this->db = &$GLOBALS['db'];
		$this->langval = &$GLOBALS['langval'];
		$this->s_lang = &$GLOBALS['s_lang'];
		$this->uid = &$GLOBALS['uid'];
	}

	function delete($tutid) { // NOT TESTED YET
		$db->querynow("DELETE FROM img WHERE MODUL='Tutorial' and FK_ANZEIGE='".$tutid."'");

		// Bilder aus DB lÃ¶schen
//		$db->querynow("DELETE FROM img WHERE MODUL='Tutorial' and FK_ANZEIGE='".$tutid."'");
		if($bilder = $db->fetch1("SELECT * from img WHERE MODUL='Tutorial' and FK_ANZEIGE='".$tutid."'")) {
			foreach($bilder as $bild) {
			//	$bild = $db->fetch1("select * from img where ID_IMG = ".$userbild);
				$del = $db->querynow("delete from img where ID_IMG = ".$bild["ID_IMG"]);
				@unlink("../".$bild['SRC']);
				if(!empty($bild['SRC_T']))
				{
					@unlink("../".$bild['SRC_T']);
				}
				$bericht[] = "Datei ".$bild['SRC']." und thumb gelÃ¶scht.";
			}
		}
		return $bericht;

	}

	function translate($text, $tutid) {

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
		// SchriftgrÃ¶Ãe
		$bbcode->addCode ('size', 'callback_replace', 'do_bbcode_size', array ('usecontent_param' => 'default'),
						  'inline', array ('listitem', 'block', 'inline', 'link','font','color'), array ());
		// Code
		$bbcode->addCode ('code', 'usecontent?', 'do_bbcode_code', array ('usecontent_param' => 'default'),
						  'code', array ('listitem', 'block', 'inline'), array ('code'));

		$bbcode->addParser (array ('block', 'inline', 'link', 'listitem'), 'htmlspecialchars');
		$bbcode->addParser (array ('block', 'inline', 'link', 'listitem'), 'nl2br');

		if ($text != "") {
			$text = $bbcode->parse($text);
		}

		$alleBilder = $this->getTutBilder($tutid);
		$k=0;
		foreach($alleBilder as $aB) {
			$k++;
			$index = str_pad($k, 2, "0", STR_PAD_LEFT);
			$suchmuster = '.Bild_'.$index.".";
			$ersetzung = "<img src='/".$aB["SRC"]."' alt='".$aB["ALT"]."' title='".$aB["ALT"]."'>";
			$text = preg_replace("/".$suchmuster."/", $ersetzung, $text);
		}
		return $text;
	}

	function getAll($status, $limit, $perpage)	{
		$query = "select t.*, s.*, u.name as uname from `tutorial` t
							left join string_tutorial s on s.FK=t.ID_TUTORIAL
							left join user u on u.ID_USER=t.FK_UID
							WHERE  s.BF_LANG=if(t.BF_LANG_TUTORIAL & ".$this->langval.", ".$this->langval.",
							1 << floor(log(t.BF_LANG_TUTORIAL+0.5)/log(2)))
							and status = '$status' and s.S_TABLE='TUTORIAL'
							order by ID_TUTORIAL desc LIMIT ".$limit.",".$perpage;
		return $this->db->fetch_table($query);
	}

	function insertStep1($topic, $descr) {
		$now = date("Y-m-d H:i:s");
		$startTutorialInsertArray["FK_UID"] = $this->uid;
		$startTutorialInsertArray["V1"] = $topic;
		$startTutorialInsertArray["V2"] = $descr;
		$startTutorialInsertArray["DATUM"] = $now;
		return $this->db->update("tutorial", $startTutorialInsertArray);
	}

	function insertStep2($tutid, $topic, $descr, $tutText, $inform) {
		$now = date("Y-m-d H:i:s");
		if ($inform == true) { $inform = "1"; }
		$updateTutorialArray["ID_TUTORIAL"] = $tutid;
		$updateTutorialArray["STATUS"] = "2";
		$updateTutorialArray["DATUM"] = $now;
		$updateTutorialArray["INFORM"] = $inform;
		$updateTutorialArray["V1"] = $topic;
		$updateTutorialArray["V2"] = $descr;
		$updateTutorialArray["T1"] = $tutText;
		return $this->db->update("tutorial", $updateTutorialArray);
	}

	function getTutBilder( $tutid ) {
		$tmpArray = $this->db->fetch_table("SELECT SRC, ALT FROM img WHERE modul='Tutorial' and FK_ANZEIGE='$tutid' ORDER BY alt");
		return $tmpArray;
	}

	function changeStatus( $tutid, $newStatus ) {
		$aktiveChange["ID_TUTORIAL"]= $tutid;
		$aktiveChange["STATUS"]= $newStatus;
		$this->db->update('tutorial', $aktiveChange);
		if( $newStatus == 1) {
			echo $this->createHTMLfile($tutid);
		}
	}

	function getTut( $tutid ) {
		$tmpArray = $this->db->fetch1("select t.*, t.datum as datum, s.V1, s.V2, s.T1, u.*,u.name as uname from `tutorial` t
					left join string_tutorial s on s.S_TABLE='tutorial'
					and s.FK=t.ID_TUTORIAL
					and s.BF_LANG=if(t.BF_LANG_TUTORIAL & ".$this->langval.",
					".$this->langval.", 1 << floor(log(t.BF_LANG_TUTORIAL+0.5)/log(2)))
					LEFT  JOIN user u ON u.ID_USER = t.FK_UID
					WHERE ID_TUTORIAL='".$tutid."'");
		return $tmpArray;
	}

	function createHTMLfile( $tutid ) {
		$filename = "../cache/tutorials/tutorial".$tutid.".htm";
		if (!$myFile = fopen($filename, "w+")) {
			die("<br>Kann $filename nicht Ã¶ffnen!");
		}

		$thisTut = $this->getTut( $tutid );
		$tpl_tmp = new Template(CacheTemplate::getHeadFile("tpl/".$this->s_lang."/tutorial_details.htm"));
		$tpl_tmp->addvar("topic", $thisTut["V1"]);
		$tpl_tmp->addvar("descr", $thisTut["V2"]);
		$tpl_tmp->addvar("text", $this->translate($thisTut["T1"],$tutid));
		$tpl_tmp->addvar("uname", $thisTut["uname"]);
		$tpl_tmp->addvar("DATUM", $thisTut["datum"]);
		$content = $tpl_tmp->process();

		if (!fwrite($myFile, $content)) {
	    	die("<br>Kann in die Datei $filename nicht schreiben.");
		}
		fclose($myFile);
		chmod($filename, 0777);
		return;
	}
}

?>