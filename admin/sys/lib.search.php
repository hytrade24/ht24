<?php

/* ###VERSIONSBLOCKINLCUDE### */

/*
add_new_text ($text,$id,$table) // fÃ¼gt einen Text zur Wortliste
$id 	= ID aus der ursprÃ¼nglichen tabelle z.b. ID_NEWS
$table	= Name der ursprÃ¼nglichen tabelle z.b. news
*/

class do_search
{
	var $minwordlenght=2; //minimum Wordlenght

	var $text; //text to index
	var $id;// ID of owner Table
	var $table;// Name of Owner Table
	var $s_dir, $s_file = false;
	var $lang;// language
	var $allwords = array(); // all Words in Text INDEX=WORD / KEY
	var $allwords_withID= array(); // all Words from searchwords_
	var $badwort= array(); // all Words from searchwords_
	var $new_word_added= 0; // new word added to searchwords_
	var $spider_dirs = array();
	var $new_dirs = array();


	function do_search($lang='de',$lbw=true)
	{
	  global $db, $ab_path;
	  $this->lang=$lang;
	  if ($lbw) //badword laden
	    $this->get_badwort();
	  ### DIRS
	  include_once $ab_path."cache/spider_dirs.php";
	  $this->spider_dirs = $ar_spider_dirs;
	  unset($ar_spider_dirs);
	  ### merken was gespidert werden soll
	  if($_REQUEST['what'])
	  {
	    $_SESSION['what'] = $_REQUEST['what'];
		$_SESSION['start'] = ($_REQUEST['start'] ? $_REQUEST['start'] : NULL);
		$_SESSION['last_dir'] = NULL;
		unset($_SESSION['c_files']);
		$_SESSION['scanned'] = array();
	  } // request what
	  elseif(!$_SESSION['what'])
	  {
	    $_SESSION['what'] = 'all';
		$_SESSION['start'] = NULL;
	  }

	}

	function get_badwort()
	{
		global $db;
		$query = "SELECT badword FROM searchdb_badword_".$this->lang;
		$res = $db->fetch_table( $query );

		foreach ( $res as $word_ ) {
			$this->badwort[$word_['badword']]=1;
		}
	}

	function add_new_text ($text,$id,$table,$dir=NULL,$file=NULL)
	{
	    $this->_prepare_new_text($text, $id, $table, $dir, $file);

		$this->build_wordlist_from_text();
		$this->save_new_words(); // speichert neue WÃ¶rter falls vorhanden

		$this->store_searchindex();
	}

    /**
     * FÃ¼gt ein einzelnes Suchwort hinzu
     *
     * @author Danny Rosifka
     * @param $word
     * @param $id
     * @param $table
     * @return void
     */
    function add_new_word($word,$id,$table) {
    	global $db;
        $this->_prepare_new_text($word, $id, $table);
        $this->allwords[$this->text] = 1;
        $this->save_new_words();

        $query = "SELECT ID_WORDS, wort FROM searchdb_words_".$this->lang." WHERE wort = '".$this->text."' LIMIT 1";
        $fetch = $db->fetch1( $query );

	    $que = "insert into searchdb_index_".$this->lang." (FK_WORDS,FK_ID,S_TABLE,SCORE,`DIR`, `FILE`)
				 values (".$fetch['ID_WORDS'].",".$this->id.",'".$this->table."', 1,'".$this->s_dir."','".$this->s_file."')";
        $db->querynow( $que );

    }

    function _prepare_new_text($text,$id,$table,$dir=NULL,$file=NULL) {
        unset ($this->allwords);
		(int)$this->id = (int)$id;
		$this->table = $table;
		$this->s_dir = $dir;
		$this->s_file = $file;

		$find = array(
			'#[()"\'!\#{};<>]|\\\\|:(?!//)#s',			// allow through +- for boolean operators and strip colons that are not part of URLs
			"#([.,?&/_]+)( |\.|\r|\n|\t)#s",			// \?\&\,
			'#\s+(-|\+)+([^\s]+)#si',					// remove leading +/- characters
			'#(\s?\w*\*\w*)#s',							// remove words containing asterisks
			'#\s+#s',									// whitespace to space
		);
		$replace = array(
			'',		// allow through +- for boolean operators and strip colons that are not part of URLs
			' ',	// \?\&\,
			' \2',	// remove leading +/- characters
			'',		// remove words containing asterisks
			' ',	// whitespace to space
		);
		$this->text = strip_tags($text); // clean out HTML
		$this->text = preg_replace($find, $replace, $this->text); // use regular expressions above

		// HTML ENTITIES umdrehen
		$this->text = html_entity_decode($this->text);

		if (strlen($this->text) < 1)
			return false;

    }


	function store_searchindex ()
	{
		global $db;
	    /* speichert die Daten in searchindex_*/
		$this->delete_from_searchindex($this->id,$this->table,$this->s_dir,$this->s_file);
		if (count($this->allwords))
		{
			foreach ($this->allwords AS $word => $score)
			{
				#echo $word.' '.$score.' '.$this->allwords_withID[$word].'<br> ';
				$que = "insert into searchdb_index_".$this->lang." (FK_WORDS,FK_ID,S_TABLE,SCORE,`DIR`, `FILE`)
				 values (".$this->allwords_withID[$word].",".$this->id.",'".$this->table."',".$score.",'".$this->s_dir."','".$this->s_file."')";
				$db->querynow( $que );
				//mysql_query();
					#echo $que."<br />";
				#echo mysql_error();
			}
		}
	}

    function delete_word_from_searchindex($word, $id_to_delete, $fromtable) {
    	global $db;
    	$query = "SELECT ID_WORDS FROM searchdb_words_".$this->lang." WHERE wort = '".mysql_real_escape_string($word)."'";
    	$fetch = $db->fetch1( $query );
        //$res = mysql_query();
        //$fetch = mysql_fetch_assoc($res);

        if($fetch != null) {
        	$query = "delete from searchdb_index_".$this->lang." where FK_ID= ".$id_to_delete." and S_TABLE='".$fromtable."' and FK_WORDS = '".$fetch['ID_WORDS']."' ";
            return $db->querynow( $query );
        }
    }

	function delete_from_searchindex ($id_to_delete,$fromtable,$dir=false,$file=false)
	{
		global $db;
	  	/*LÃ¶scht einen Eintrag aus dem Searchindex*/
		if(!$dir) {
			$query = "delete from searchdb_index_".$this->lang." where FK_ID= ".$id_to_delete." and S_TABLE='".$fromtable."'";
			return $db->querynow( $query );
		}
	    else
		{
			$query = "delete from from searchdb_index_".$this->lang." where `DIR`= ".$dir." and `FILE`='".$file."'";
			$db->querynow( $query );
		} // dir Ã¼bergeben
	}

	function delete_article_from_searchindex ($id_to_delete,$fromtable)
	{
		global $db;
		/*LÃ¶scht einen Eintrag aus dem Suchdatenbank*/
		$language=array();
		$res = $db->fetch_table("select ABBR from lang");//mysql_query();

		foreach ( $res as $word_ ) {
			$language[]=$word_['ABBR'];
		}

		foreach ($language AS $sprache) {
			$query = "delete from searchdb_index_".$sprache." where FK_ID= ".$id_to_delete." and S_TABLE='".$fromtable."'";
			$db->querynow( $query );
		}
	}

	function build_wordlist_from_text()
	{
	    /*
			erstellt ein Array allwords
			1. mit allen Woerter die groesser  minwordlenght sind
			2. zaehlt das vorkommen der WÃ¶rter (Score)
		*/
		$wordarray = explode(' ', strtolower($this->text)); // Build array with words (lowercase)

		//echo print_r ($wordarray);
		foreach ($wordarray AS $word)
			if (strlen($word)>$this->minwordlenght)
				if (!$this->badwort[$word]==1)
					$this->allwords[$word]++; //only Words bigger 2 AND count Word in array



	}

	function save_new_words()
	{
		global $db;
		/* alle WÃ¶rter (allwords) bzw. ID lesen die in der Datenbank bereits vorhanden sind*/
		$b_has_new_words=false;

		$this->db_get_wordIDs(); // Liste der bereits vorhanden WÃ¶rter erstellen

		$newwords = array();

		if (count($this->allwords))
		{
			foreach ($this->allwords AS $word => $score) {
				#echo $word."<br />";
				if (!isset($this->allwords_withID[$word]))
				{
					$newwords[] = $word; // No so add it to the word table
					$query = "insert into searchdb_words_".$this->lang." (wort) values ('".$word."')";
					$db->querynow( $query );

					$this->new_word_added++;
					$b_has_new_words=true;
				}
			}
		}

		if ($b_has_new_words) //wenn ein neues Wort hinzugefÃ¼gt wurde, dann liste allwords_withID neu laden
			$this->db_get_wordIDs();

		return $this->new_word_added;
	}

	function db_get_wordIDs()
	{
		global $db;
		/* alle WÃ¶rter (allwords) bzw. ID lesen die in der Datenbank bereits vorhanden sind*/
		unset ($this->allwords_found_in_wordlist);
		if (count($this->allwords))
		{
			$query = "SELECT ID_WORDS, wort FROM searchdb_words_".$this->lang." WHERE wort in ('" . implode ("','",array_keys($this->allwords))  . "')";
		    $res = $db->fetch_table( $query );

		    foreach ( $res as $word_ ) {
			    $this->allwords_withID[$word_["wort"]] = $word_['ID_WORDS'];
		    }
		}
	}

	function spiderDirs()
	{
	  global $ab_path;
	  $ar_dirs = array();

	  $d = dir($ab_path."files");
      while (false !== ($entry = $d->read()))
	  {
        if($entry == '.' || $entry == '..')
		  continue;
        else
		  $ar_dirs[] = $entry;
	  }
      $d->close();

	  foreach($ar_dirs as $key => $dir)
	  {
	    if(!in_array($dir, $this->spider_dirs))
		  $this->new_dirs[] = $dir;
	  } // foreach new-dirs
	  #echo ht(dump($this->spider_dirs));
	  #echo ht(dump($this->new_dirs));
	} // spiderdirs

	function currentDir()
	{
	  if(!$_SESSION['last_dir'])
	  {
	    if(!$_SESSION['start'])
		  $_SESSION['last_dir'] = $this->spider_dirs[0];
	    else
		  $_SESSION['last_dir'] = $_SESSION['start'];
	  } // noch keine DIR
	  if(!in_array($_SESSION['last_dir'], $_SESSION['scanned']))
	    $_SESSION['scanned'][] = $_SESSION['last_dir'];
	  #die(ht(dump($_SESSION)));
	} // <-- currentDir()

	function countFiles()
	{
	  global $ab_path;
	  if($_SESSION['c_files'] === NULL || !isset($_SESSION['c_files']))
	  {
	    $d = dir($ab_path."files/".$_SESSION['last_dir']."/".$this->lang);
        $c=0;
		while (false !== ($entry = $d->read()))
	    {
          if($entry == '.' || $entry == '..')
		    continue;
          else
		    $c++;
	    }
        $d->close();
		return $_SESSION['c_files'] = $c;
	  } // noch nicht gezÃ¤hlt
	  else
	    return $_SESSION['c_files'];
	} // countFiles()

	function getNextFiles($limit, $perpage)
	{
	    global $ab_path;
		$ar = array();
		$d = dir($p=$ab_path."files/".$_SESSION['last_dir']."/".$this->lang);
        #die($p);
	    $c = -1;
		$counted = 0;
		while (false !== ($entry = $d->read()))
	    {
          if($entry == '.' || $entry == '..')
		    continue;
          else
		  {
			$c++;
			if($c < $limit)
			  continue;
			if($perpage == $counted)
			  break;

			$ar[] = $ab_path."files/".$_SESSION['last_dir']."/".$this->lang."/".$entry;
			$counted++;
	      }
		}
        $d->close();
		return $ar;
	} // getNextFiles()

	function writeScanned()
	{
	  global $ab_path;
	  if(!empty($_SESSION['scanned']))
	  {
	    for($i=0; $i<count($_SESSION['scanned']); $i++)
		{
		  if(!in_array($_SESSION['scanned'][$i], $this->spider_dirs))
		    $this->spider_dirs[] = $_SESSION['scanned'][$i];
		}
		$code = $codes = array();
		$code[] = "<?php
";
		$code[] = "\$ar_spider_dirs = array (";
		foreach($this->spider_dirs as $key => $value)
		  $codes[] = "'".$value."'";
		$code[] = implode(",", $codes);
		$code[] = ");";
		$code[] = "?>";
		$code = implode("\n", $code);
		$fp = fopen($filename = $ab_path."cache/spider_dirs.php", "w");
		fwrite($fp, $code);
		fclose($fp);
		chmod($filename, 0777);
	  } // array nicht leer
	}

}// end Class do_search

 function tagFilter($str)
 {
   $return = array( 'TITLE' => '', 'TEXT' => '');

   ### titel finden
   preg_match_all("|(<title)([^>]*)(>)([^<]*)|si", $str, $find);
   $return['TITLE'] = $find[4][0];

   $return['TEXT'] = strip_tags(preg_replace("|(>)|si", "> ", preg_replace("|(<!DOCTYPE)(.*?)(/head)([^>]*)(>)|si", '', $str)));

   return $return;
 } // tagFilter()



?>