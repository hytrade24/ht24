<?php
/* ###VERSIONSBLOCKINLCUDE### */


require_once $ab_path.'admin/sys/lib.nestedsets.php';

 ### CACHE Lib

function cache_nav_all($root)
{
global $db,$langval;

	  // Nav-Cache aktualisieren
	  $nest = new nestedsets('nav', $root, true);
	  $langs = $db->fetch_table("select * from lang ");
	  $langval_bak = $langval;
	  for($k=0; $k<count($langs); $k++)
	  {
	    $langval = $langs[$k]['BITVAL'];
	    $root_id = (int)$db->fetch_atom("select ID_NAV from nav
	      where ROOT=". $root. " and LFT=1");

	    $res = $nest->nestSelect('', '', ' max(u.LFT) as P_LFT,', true);
	    $nar_kids = array ();
	    $nar_lft2id = array ();
	    while ($row = mysql_fetch_assoc($res))
	      $nar_lft2id[(int)$row['LFT']] = (int)$row['ID_NAV'];
	    mysql_data_seek($res, 0);
	    while ($row = mysql_fetch_assoc($res))
	      $nar_kids[(int)$nar_lft2id[$row['P_LFT']]][] = $row['ID_NAV'];
	    mysql_data_seek($res, 0);
	/** /
	    $code = array ("0 => array ('KIDS' => array (".
	      ($nar_kids[0] ? implode(', ', $nar_kids[0]) : ''). "))");
	    while($row=mysql_fetch_assoc($res))
	    {
	      $parent_id = (int)$nar_lft2id[$row['P_LFT']];
	      $code[] = $row['ID_NAV']." => array (
	  'IDENT' => '".$row['IDENT']."',
	  'LABEL' => '".$row['V1']."',
	  'LFT' => ".$row['LFT'].",
	  'RGT' => ".$row['RGT'].",
	  'LEVEL' => ".$row['level'].",
	  'B_VIS' => ".($row['B_VIS'] ? 'true':'false').",
	  'PARENT' => ". ($root_id == $parent_id ? 0 : $parent_id).",
	  'ALIAS' => '".$row['ALIAS']."',
	  'ID_NAV' => '".$row['ID_NAV']."',
	  'KIDS' => array (". (($tmp = $nar_kids[$row['ID_NAV']]) ? implode(', ', $tmp) : ''). ")
	)";
	    }
	    $code = "<". "?php \$ar_nav = array (\n".implode(",\n", $code)."); ?". ">";
	/*/
	/** /
	    $tmp = ($nar_kids[0] ? $nar_kids[0] : array ());
	    $ar_dump = array (0 => array ('KIDS' => $tmp, 'kidcount'=>count($tmp), 'B_VIS'=>true));
	/*/
	    $ar_dump = array (0 => array ('KIDS' => $nar_kids[0] ? $nar_kids[0] : array ()));
	/**/
	    while ($row = mysql_fetch_assoc($res))
	    {
	      $row['KIDS'] = (($tmp = $nar_kids[$row['ID_NAV']]) ? $tmp : array ());
	      $parent_id = (int)$nar_lft2id[$row['P_LFT']];
	      $row['PARENT'] = ($root_id == $parent_id ? 0 : $parent_id);

	      $row['LABEL'] = $row['V1'];
	      $row['LEVEL'] = $row['level'];
	      $row['B_VIS'] = !!$row['B_VIS'];

	$nar_tmp = $db->fetch_nar("select LFT, ifnull(ALIAS,IDENT) from nav
	  where ROOT=$row[ROOT] and LFT>1
	    and LFT<$row[LFT] and RGT>$row[RGT] order by LFT");
	$row['ident_path'] = implode('/', $nar_tmp);

	      $ar_dump[$row['ID_NAV']] = $row;
	    }
	/*
	// fuer mod_rewrite ------------------------------------------------------------
	foreach($ar_dump as $id=>$row)
	{
	  // 1. nach unten, bis eine Seite gefunden wird
	  $prow = &$ar_dump[$id];
	  while ($prow['PARENT'])
	    $prow = &$ar_dump[$prow['PARENT']];
	  $pid = $prow['ID_NAV'];

	  // $prow = level0-Reihe
	  if (!($s = $prow['ALIAS']))
	    $s = $prow['IDENT'];

	  if ($parent_id = $row['PARENT'])
	  {
	    $prow = &$ar_dump[$parent_id];
	    if ($s_path = $prow['ALIAS'])
	      ;
	    else
	      $s_path = $prow['IDENT'];
	    $s_path .= '/';
	  }
	  else
	    $s_path = '';
	  if ($s = $row['ALIAS'])
	    ;
	  else
	    $s = $row['IDENT'];

	  if (!$row['IDENT'])
	  {
	  }
	  $ar_dump[$id]['path'] = $s_path.$s;
	}
	// end mod_rewrite
	*/

	    $s_code = "<". "?php \$ar_nav = ". php_dump($ar_dump). "; ?". ">";
	#die(ht(dump($s_code)));
	/**/
	    #die(ht(dump($code)));
	    $fp = fopen($filename = '../cache/nav'. $root. '.'.$langs[$k]['ABBR'].'.php', 'w')
	      or die("Filesystem error");
	    @fwrite($fp, $s_code);
	    fclose($fp);
	    @chmod($filename, 0777);
	  } // for languages
	  $langval = $langval_bak;
	  // Zuordnung ident => id_nav
	  if ('v0'!=$do && 'v1'!=$do)
	  {

	    $ar_tmp = $db->fetch_nar('select ifnull(ALIAS, IDENT), ID_NAV from nav
	      where ROOT='. $root. " and IDENT>'' and LFT>1");

          $ar_tmp2 = $db->fetch_nar('select IDENT, ID_NAV from nav
           	      where ROOT='. $root. " and IDENT>'' and ALIAS IS NOT NULL and LFT>1");

                  $ar_tmp = array_merge($ar_tmp, $ar_tmp2);

	    $s_code = '<'. '?php $nar_ident2nav = '. php_dump($ar_tmp). "; ?". ">";
	    $fp = fopen($fn= '../cache/nav'. $root. '.php', 'w')
	      or die("Filesystem error");
	    fwrite($fp, $s_code);
	    fclose($fp);
	    @chmod($fn, 0777);
	  }

	  //Das cache fuer die Hilfe loeschen
	  @unlink ("../cache/helpnavi.php");
	  @unlink ("../cache/helpnavi1.php"); //fuer popup
	  #forward('index.php?page='. $s_page_alias."&ROOT=".$root.($id ? '&id='. $id."#nav".$id : ''));


}

function cache_faq()
{
  global $db,$langval,$s_lang,$ab_path;
  $s_lang_tmp = $s_lang;	
  $lang_tmp = $langval;
  $langs = $db->fetch_table("select * from lang order by BITVAL DESC");
  $ar_nav_list = $db->fetch_table("SELECT * FROM `nav` WHERE FK_MODUL=9");
  // schleife Seiten/Nav
  foreach ($ar_nav_list as $n => $ar_nav) {
	  for ($l=0; $l<count($langs); $l++)
	  {
		#echo $l."<br>";
		$GLOBALS['langval'] = $langs[$l]['BITVAL'];
		$s_lang = $langs[$l]['ABBR'];
		  
		$language = $GLOBALS["lang_list"][$s_lang];
		if ($language['DOMAIN'] != '') {
			$GLOBALS['nar_systemsettings']['SITE']['SITEURL'] = $language['DOMAIN'];
		}
		if ($language['BASE_URL'] != '') {
			$GLOBALS['nar_systemsettings']['SITE']['BASE_URL'] = $language['BASE_URL'];
		}
		  
		$ar = $files = array ();
		$file_main = fopen('../cache/faq.main.'.$s_lang.'_'.$ar_nav["ID_NAV"].'.htm', 'w+');
		if(!$file_main)
		{
		  $GLOBALS['tpl_content']->addvar('err', "Could not get cache file!");
		  break;
		}
		$main = $db->fetch_table($db->lang_select('faqkat')." where POS > 0 AND (FK_NAV IS NULL OR FK_NAV=".$ar_nav["ID_NAV"].") order by POS");
		for ($i=0; $i<count($main); $i++)
		{
		  $tpl_tmp = new Template($ab_path.'module/tpl/'.$s_lang.'/faq.mainrow.htm');
		  $tpl_tmp->addvar("IDENT", $ar_nav["IDENT"]);
		  $tpl_tmp->addvars($main[$i]);
		  $tpl_tmp->addlist('index', $fr = $db->fetch_table($db->lang_select('faq')."where FK_FAQKAT=".$main[$i]['ID_FAQKAT']."
			and POS > 0 order by POS"), '../module/tpl/'.$s_lang.'/faq.indexrow.htm');
		  $ar[] = $tpl_tmp->process();
		  $p_tmp = fopen('../cache/faq.'.$main[$i]['ID_FAQKAT'].'.'.$s_lang.'.htm', 'w+');
		  if(!$p_tmp)
		  {
			$GLOBALS['tpl_content']->addvar('err', 'Could not get cache file!');
			break (2);
		  }
		  $tpl_tmp = new Template($ab_path.'module/tpl/'.$s_lang.'/faq.mainrow.htm');
		  $tpl_tmp->addvars($main[$i]);
		  $tpl_tmp->addlist('index', $fr, '../module/tpl/'.$s_lang.'/faq.indexrow.htm');
		  $tpl_tmp->addlist('topics', $fr, '../module/tpl/'.$s_lang.'/faq.detailrow.htm');
		  $write_tmp = fwrite($p_tmp, $tpl_tmp->process());
		  if($write_tmp === FALSE)
		  {
			$GLOBALS['tpl_content']->addvar('err', 'Cache File could not be written');
			break (2);
		  }
		}
		#echo implode($ar)."<hr />";
		$write = true;
		if(!empty($ar))
		  $write = fwrite($file_main, implode($ar));
		if($write === FALSE)
		{
		  echo "abbruch?";
		  $GLOBALS['tpl_content']->addvar('err', 'Cache File could not be written');
		  break;
		}
	  }
  }
  $s_lang = $s_lang_tmp;
  $GLOBALS['langval'] = $lang_tmp;
  $language = $GLOBALS["lang_list"][$s_lang];
  if ($language['DOMAIN'] != '') {
	  $GLOBALS['nar_systemsettings']['SITE']['SITEURL'] = $language['DOMAIN'];
  }
  if ($language['BASE_URL'] != '') {
	  $GLOBALS['nar_systemsettings']['SITE']['BASE_URL'] = $language['BASE_URL'];
  }
} // <-- cache_faq()

function cache_nav($ar_root=NULL, $ar_roles=NULL)
{
  pageperm2role_rewrite((is_null($ar_root) ? -1 : $ar_root));
}

function cache_kat($n_root=NULL)
{
	// CMS-Kategorien
	$bak = array ($GLOBALS['root'], $GLOBALS['nest' ]);

	if (is_null($n_root)) {
		$n_root = $GLOBALS['db']->fetch_nar('select distinct ROOT from kat');
	}
	if (is_array ($n_root)) {
		foreach (array_keys($n_root) as $v) {
			cache_kat($v);
		}
	}
	else
	{
		$GLOBALS['root'] = $n_root;
		$GLOBALS['nest'] = new nestedsets('kat', $n_root);
		kat_cache_rewrite();
	}

	list($GLOBALS['root'], $GLOBALS['nest']) = $bak;

	// Marktplatz-Kategorien
	require_once $GLOBALS['ab_path']."sys/lib.pub_kategorien.php";
	CategoriesBase::deleteCache();
}

/**
 * Cache von Anbieter-Kategorien löschen
 */
function cache_kat_vendor() {
	global $ab_path;
	@system("rm -f ".$ab_path."cache/marktplatz/kat_anbieter_*.htm");
	@system("rm -f ".$ab_path."cache/marktplatz/vendor/inputfields_*.htm");
	@system("rm -f ".$ab_path."cache/marktplatz/vendor/search/*.htm");
}

/**
 * Cache von Gesuch-Kategorien löschen
 */
function cache_kat_request() {
	global $ab_path;
	@system("rm -f ".$ab_path."cache/marktplatz/kat_gesuche_*.htm");
}

/**
 * Cache von Job-Kategorien löschen
 */
function cache_kat_job() {
	global $ab_path;
	@system("rm -f ".$ab_path."cache/marktplatz/kat_jobs_*.htm");
}

function cache_kat_events() {
	global $ab_path;
	@system("rm -f ".$ab_path."cache/marktplatz/kat_calendar_events_*.htm");
}

function cache_kat_clubs() {
	global $ab_path;
	@system("rm -f ".$ab_path."cache/marktplatz/kat_club_*.htm");
}

function cache_itemkat() { cache_kat(1); }
function cache_newskat() { cache_kat(2); }

function cache_comments($s_table=NULL)
{
  global $db;
  $res = $db->querynow("select S_TABLE, FK, ifnull(count(ID_COMMENT_THREAD), 0), ifnull(count(ID_COMMENT), 0)
    from comment_thread
      left join comment c on FK_COMMENT_THREAD=ID_COMMENT_THREAD". (is_null($s_table) ? '' : "
    where S_TABLE". (is_array ($s_table)
        ? " in ('". implode("', '", $s_table). "')"
        : "='". mysql_escape_string($s_table). "'"
      )
    ). "
    group by S_TABLE, FK"
  );
  while (list($s_table, $fk, $count_t, $count_p) = mysql_fetch_row($res['rsrc']))
    $db->querynow("update `$s_table`
      set COUNT_THREADS=". $count_t. ", COUNT_POSTS=". $count_p. "
      where ID_". strtoupper($s_table). "=". $fk
    );
}

function update_infocache()
{
  global $db;
  $ar_lang = $db->fetch_table("select BITVAL, ABBR from lang where B_PUBLIC=1");
  for($n=0; $n<count($ar_lang); $n++)
  {
	  $ar = $db->fetch_table("select t.*, s.V1, s.V2, s.T1
	    from `infoseite` t
		 left join string_info s on s.S_TABLE='infoseite' and s.FK=t.ID_INFOSEITE and s.BF_LANG=if(t.BF_LANG_INFO & ".$ar_lang[$n]['BITVAL'].", ".$ar_lang[$n]['BITVAL'].", 1 << floor(log(t.BF_LANG_INFO+0.5)/log(2)))");
	  $ar_mod = $ar_page = $ar_byname = array();
	  for($i=0; $i<count($ar); $i++)
	  {
			if (!empty($ar[$i]['IDENT'])) {
				$ar_page[] = "'" . $ar[$i]['IDENT'] . "' => " . $ar[$i]['ID_INFOSEITE'] . "\r\n";
			}
			/*		elseif(!empty($ar[$i]['FK_NAV']))
          {
            $ar_modul[] = "'".$ar[$i]['FK_NAV']."' => ".$ar[$i]['ID_INFOSEITE']."\r\n";
          }
      */
			$ar_byname[] = "'" . $ar[$i]['V1'] . "' => " . $ar[$i]['ID_INFOSEITE'] . "\r\n";
			Api_ContentPageManagement::getInstance($db, $ar_lang[$n]['BITVAL'], $ar_lang[$n]['ABBR'])->cacheContentPage($ar[$i]);
	  }

	  $fp = @fopen($filename = "../cache/info.".$ar_lang[$n]['ABBR'].".php", "w");
	  if(!$fp)
		die("<h1>Fatal error!</h1><p>Cache file not found / is not writable</p>");
	  fwrite($fp, "<?php\r\n\$ar_info2page=array(".implode(",", $ar_page)."\r\n);
	\$ar_byname = array(".implode(",", $ar_byname).");\r\n?>");
	  fclose($fp);
	  @chmod($filename, 0777);
	  // \$ar_info2modul = array(".implode(",", $ar_modul).");
  }
}

function cache_cboxes()
{

  global $db, $ab_path;
  $ar_lang = $db->fetch_table("select BITVAL,ABBR from lang where B_PUBLIC=1");
  // schleife Sprachen
  for($i=0; $i<count($ar_lang); $i++)
  {
#echo "Lang: ".$ar_lang[$i]['ABBR']."<br />";
	$ar_box = $db->fetch_table("select t.*, s.T1
	  from `cbox` t
	   left join string_app s on s.S_TABLE='cbox' and s.FK=t.ID_CBOX and s.BF_LANG=if(t.BF_LANG_APP & ".$ar_lang[$i]['BITVAL'].", ".$ar_lang[$i]['BITVAL'].", 1 << floor(log(t.BF_LANG_APP+0.5)/log(2)))
	  where B_SYS <> 1 or B_SYS IS NULL");
    for($k=0; $k<count($ar_box); $k++)
	{
	  #die("test");
	  $name = "cbox.".$ar_lang[$i]['ABBR'].".".$ar_box[$k]['ID_CBOX'].".htm";
	  $ar_cbox = $ar_box[$k];
      $ar_kat = $db->fetch1("select LFT, RGT from kat
         where ID_KAT=".$ar_cbox['FK_KAT']);
      #echo "hallo????".ht(dump($ar_cbox));
	  #echo ht(dump($ar_kat));
	  $query = "select t.ID_NEWS, t.STAMP , s.V1, s.V2, t.IMG, t.IMGH, t.IMGW, t.FK_KAT,NAME,t.FK_AUTOR
        from `news` t
		 left join string_c s on s.S_TABLE='news' and s.FK=t.ID_NEWS and s.BF_LANG=if(t.BF_LANG_C & ".$ar_lang[$i]['BITVAL'].", ".$ar_lang[$i]['BITVAL'].", 1 << floor(log(t.BF_LANG_C+0.5)/log(2)))
		 left join kat ka on t.FK_KAT=ka.ID_KAT
		 left join user on t.FK_AUTOR = ID_USER
		where t.OK = 3
		  and ( ( ka.LFT >= ".$ar_kat['LFT']." and ka.RGT <= ".$ar_kat['RGT'].") and ka.ROOT = 2)
		  ".($ar_cbox['B_TOP'] ? " and t.B_TOP=1 " : "")."
		  ".($ar_cbox['FK_AUTOR'] > 0 ? " and t.FK_AUTOR=".$ar_cbox['FK_AUTOR'] : '')."
		order by t.NEWSNUMBER ASC
		LIMIT ".$ar_cbox['BEGINN'].", ".$ar_cbox['N_ARTIKEL']."
		";
	  $res = $db->querynow($query);
	  if(!$res['rsrc'])
	    die(ht(dump($res)));
      $tpl_table = new Template("tpl/de/empty.htm");
      $tpl_table->tpl_text = $ar_cbox['T1'];
      $tmp = $ar = array(); $n=1; $img=0;
	  #echo ht(dump($GLOBALS['lastresult']));
	  #echo $modpage."<hr>";
	  while($row = mysql_fetch_assoc($res['rsrc']))
	  {
        $ar_kat = $db->fetch1("select LFT, RGT from kat
         where ID_KAT=".$row['FK_KAT']);
		if (!is_array($ar_kat)) {
			// Fehlerhafte Kategorie-Zuordnung?
			continue;
		}
	    // Passende Modulseite finden
	  $modpage = $db->fetch_atom("select n.IDENT
		  from modul2nav m
		   left join nav n on m.FK_NAV = n.ID_NAV
		   left join kat k on m.FK=k.ID_KAT and m.S_MODUL='news_adv'
		  where ( k.LFT <= ".$ar_kat['LFT']." )
		  order by k.LFT DESC
		  limit 1
		  ");

		if($img > $ar_cbox['N_ARTIKEL_B'] || $ar_cbox['N_ARTIKEL_B'] == 0)
	      $row['IMG'] = NULL;
	    $tpl_tmp = new Template("tpl/de/empty.htm");
	    $tpl_tmp->addvar("URL", $nar_systemsettings['SITE']['SITEURL']);
		$tpl_tmp->addvar("href", $modpage);
	    $tpl_tmp->tpl_text = $ar_cbox['CROW'];
	    $tpl_tmp->addvars($row);
	    $tpl_tmp->addvar("even", ($n%2 ? 1 : '0'));
	    $tmp[] = $tpl_tmp;
	    $n++;
	    $img++;
	  }  // while Artikel
	  $tpl_table->addvar("liste", $tmp);
	  #die(ht(dump($tpl_table)));
      $filename = $ab_path."cache/".$name;

	  $fp = fopen($filename, "w");
	  if(!$fp)
	    die("Cache error! File &quot;".$name."&quot; not found!");
	  #chmod("../cache/".$name, 0777);
	  $write = fwrite($fp, $tpl_table->process());
	  if($write === false) {
		die("Cache Error. File &quot;".$name."&quot; is not writable");
	  }
	  fclose($fp);
	  chmod($filename, 0777);
#echo "watt???";
	} // Schleife C BOXES
  } // Sprachen Schleife
  cache_rss();
}

function cache_rss()
{
  global $db;
  global $nar_systemsettings;
  if(!$nar_systemsettings['RSS']['RSS_AKTIV'])
    return true;
  $ar_lang = $db->fetch_table("select BITVAL,ABBR from lang where B_PUBLIC=1");
  // schleife Sprachen
  for($i=0; $i<count($ar_lang); $i++)
  {
	  $name = "rss.".$ar_lang[$i]['ABBR'].".xml";
	  $query = "select t.ID_NEWS, t.STAMP , s.V1, s.V2, t.IMG, t.IMGH, t.IMGW, t.FK_KAT
        from `news` t
		 left join string_c s on s.S_TABLE='news' and s.FK=t.ID_NEWS and s.BF_LANG=if(t.BF_LANG_C & ".$ar_lang[$i]['BITVAL'].", ".$ar_lang[$i]['BITVAL'].", 1 << floor(log(t.BF_LANG_C+0.5)/log(2)))
		 left join kat ka on t.FK_KAT=ka.ID_KAT
		where t.OK = 3
		order by t.STAMP DESC
		LIMIT 10
		";
	  $res = $db->querynow($query);
	  if(!$res['rsrc'])
	    die(ht(dump($res)));

      $tpl_table = new Template(CacheTemplate::getHeadFile("skin/".$ar_lang[$i]['ABBR']."/index.rss.htm"));
	  $tpl_table->addvar("LINK", $nar_systemsettings['SITE']['SITEURL']);
      $tmp = $ar = array(); $n=1; $img=0;
	  while($row = mysql_fetch_assoc($res['rsrc']))
	  {
        $row['V1'] = $row['V1'];
        $row['V2'] = $row['V2'];

		//Mon, 30 Sep 2002 01:52:02 GMT

		$row['DATUM_RSS'] = date('D\, d M Y H:m:s', strtotime($row['STAMP']));

		$ar_kat = $db->fetch1("select LFT, RGT from kat
         where ID_KAT=".$row['FK_KAT']);
		if (!is_array($ar_kat)) {
			// Fehlerhafte Kategorie-Zuordnung?
			continue;
		}
	    // Passende Modulseite finden
	    $row['MODPAGE'] = $db->fetch_atom("select n.IDENT
		  from modul2nav m
		   left join nav n on m.FK_NAV = n.ID_NAV
		   left join kat k on m.FK=k.ID_KAT and m.S_MODUL='news_adv'
		  where ( k.LFT <= ".$ar_kat['LFT']." )
		  order by k.LFT DESC
		  limit 1
		  ");

	    $tpl_tmp = new Template(CacheTemplate::getHeadFile("skin/".$ar_lang[$i]['ABBR']."/index.rss.artikel.htm"));
	    $tpl_tmp->addvar("LINK", $nar_systemsettings['SITE']['SITEURL']);
	    $tpl_tmp->addvars($row);

	    $tpl_tmp->addvar("even", ($n%2 ? 1 : '0'));
	    $tmp[] = $tpl_tmp;
	    $n++;
	    $img++;
	  }  // while Artikel
	  $tpl_table->addvar("liste", $tmp);
	  $tpl_table->addvars($nar_systemsettings['RSS']);
	  $fp = @fopen($filename = "../cache/".$name, "w");
	  if(!$fp)
	    die("Cache error! File &quot;".$name."&quot; not found!");
	  $write = @fwrite($fp, $tpl_table->process());
	  if($write === FALSE)
	    die("Cache Error. File &quot;".$name."&quot; is not writable");
	  @fclose($fp);
	  @chmod($filename, 0777);
  } // Sprachen Schleife
}

function cache_partnerlinks($s_lang='de')
{
  global $db;

  $tmp_parter = $tmp_paid = array(); //Array initialisieren

	// Sichtbarkeit ermitteln und neu setzen
	$res = $db->querynow("update partnerlinks set VISIBLE = 0 where PAIDTIL <= now() and VISIBLE=1 and PAID=1");
	if ($res['int_result'] > 0 )
		eventlog("info", $res['int_result']." Parterlink/s ge&auml;ndert!");

	//Daten lesen
	$res = $db->querynow("SELECT * FROM `partnerlinks` where VISIBLE = 1 ORDER BY `LINKTITEL` ASC");

	$tpl_partner = new Template(CacheTemplate::getHeadFile("skin/".$s_lang."/cache_partnerlinks.htm")); //partnerlinks
	$tpl_paid = new Template(CacheTemplate::getHeadFile("skin/".$s_lang."/cache_partnerlinks.htm")); //paidlinks

	while($row = mysql_fetch_assoc($res['rsrc']))
	{
			if (($row['PAID']==1) ) //wenn datum in der Zukunft liegt und bezahl ist
				$tmp_paid[]=$row;
			else // schreibe Partnerlink
				$tmp_parter[]=$row;
	}



    $tpl_partner->addlist("liste", $tmp_parter, "../skin/".$s_lang."/cache_partnerlinks.row.htm");
	$fpl = fopen($filename = "../cache/partnerlinks.".$s_lang.".htm", 'w');
	$write_tmp = fwrite($fpl, $tpl_partner->process());
	fclose($fpl);
	@chmod($filename, 0777);

    $tpl_paid->addlist("liste", $tmp_paid, "../skin/".$s_lang."/cache_partnerlinks.row.htm");
	$fpl = fopen($filename = "../cache/paidlinks.".$s_lang.".htm", 'w');
	$write_tmp = fwrite($fpl, $tpl_paid->process());
	fclose($fpl);
	@chmod($filename, 0777);

	eventlog("info", "Parterlinks ".$s_lang." cached");

} // cache_partner

?>
