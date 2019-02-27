<?

if($_REQUEST['KICK'])
{
  $db->querynow("update tutorial_live set OK=0 where ID_TUTORIAL_LIVE=".$_REQUEST['KICK']);
  todo('Neue Tutorials cachen', 'cron/tutorial_neu.php', NULL, NULL, date('Y-m-d H:i:s', strtotime('+1 minutes')));
  #die(ht(dump($_REQUEST)));
} // KICK

//rechte setzen
if (count($_POST) && !empty($_REQUEST['save']))
{
	include $ab_path."sys/lib.bbcode.php";
	$tmp = $_REQUEST['OK1'];
	$ok1 = ($tmp ? implode(', ', $tmp) : '0');
	$tmp = $_REQUEST['OK2'];
	$ok2 = ($tmp ? implode(', ', $tmp) : '0');
	$tmp = $_REQUEST['Okall'];
	$okall = implode(', ', $tmp);
	
	### betroffene parsen
	#echo ht(dump($_POST));
  require_once ("sys/lib.search.php");
  $search = new do_search($s_lang);
	for($i=0; $i<count($_POST['Okall']); $i++)
	{
	   #echo "TEST";
	   $id = $_POST['Okall'][$i];//."<br />";
	   if(in_array($_POST['Okall'][$i], $_POST['OK1']) && in_array($_POST['Okall'][$i], $_POST['OK2']))
	   {	     
		 $ar = $db->fetch1("select * from tutorial where ID_TUTORIAL=".$id);
		 $up=$db->querynow("update tutorial_live set 
		   FK_KAT=".$ar['FK_KAT'].", OK=3, titel='".sqlString($ar['titel'])."', discription='".sqlString($ar['discription'])."',
		    idate='".$ar['idate']."', STAMP_UPDATE='".$ar['STAMP_UPDATE']."' ,FK_USER=".$ar['FK_USER']."
		   where ID_TUTORIAL_LIVE=".$ar['ID_TUTORIAL']."	
			");
	    # echo ht(dump($up));
		 ### pages neu schreiben ###
	    
		$parsed = $ar['tutorial'];
		preg_match_all("%(\[CODE\]|\[PHP\])(.*?)(\[/CODE\]|\[/PHP\])%si", $parsed, $treffer, PREG_PATTERN_ORDER);
		$arr=array();
		
		foreach($treffer[0] as $key => $value)
		{
		  $arr[$key] = array("wert" => $value, "ident" => "/%/phpres id=".$key."/%/");
		  $parsed = str_replace($arr[$key]['wert'], $arr[$key]['ident'], $parsed);
		} // foreach ende
		
		// die zwischengespeicherten codes werden wieder eingetragen
		for($k=0; $k<count($arr); $k++)
		{
		  $parsed = str_replace($arr[$k]['ident'], $arr[$k]['wert'], $parsed);
		} // foreach ende
	
		preg_match_all("/(\[img:)(\s?)([0-9]{1,})(\])/si", $parsed, $trefferimg, PREG_PATTERN_ORDER);

		foreach($trefferimg[3] as $key => $value)
		{
		  $query = "select IMG, PATH from user2img where ID_USER2IMG = ".$value." and FK = ".$id." and WHAT = 'TUTORIAL'";
		  if($res = mysql_fetch_assoc(mysql_query($query)))
			$parsed = str_replace("[img:".$value."]", "<img src=\"/".$res['PATH'].$res['IMG']."\" />", $parsed);
		  else
			$parsed = str_replace("[img:".$value."]", "", $parsed);
		}
		
		$bbcode = new bbcode(); 
		#die(ht(dump($parsed)));
		$parsed = $bbcode->parseBB($parsed);
		
		$exp_parsed = explode("[pagebreak]", $parsed);
		
	    @mysql_query("delete from `tutorials_page` where tutid = ".(int)$id);
		//echo ht(dump($exp_parsed));
		for($k=0; $k<count($exp_parsed); $k++)
		{
	     mysql_query("insert into `tutorials_page` (tutid, pageno, tuttext) value ('".(int)$id."', '".($k+1)."', '".sqlString($exp_parsed[$k])."')");
	    }	
     //suchindex updaten
     $search->add_new_text($parsed." ".$_POST["titel"],$id,'tutorial');
     unset($bbcode,$parsed,$exp_parsed,$arr,$trefferimg);   
    } // ist freigeschaltet
    else {
     //suchindex entfernen
     $search->delete_from_searchindex($id,'tutorial');
     //$up = $db->querynow("update tutorial_live set OK=0 where ID_TUTORIAL_LIVE=".$id);
    } // nicht freigeschaltet    
	   #echo ht(dump($up));	   
	} // for all
	
	#die("STOP !!! Baustelle!");
	### // parsen ende
	
	### freischaltung (ok feld)
	$db->querynow("update tutorial set OK=if(ID_TUTORIAL in ($ok1),1,0)+if(ID_TUTORIAL in ($ok2),2,0) where ID_TUTORIAL in ($okall)");  		
	$tpl_content->addvar("UPDATE", 1);	
	
	todo('Neue Tutorials cachen', 'cron/tutorial_neu.php', NULL, NULL, date('Y-m-d H:i:s', strtotime('+5 minutes')));
}

if(count($_POST) && empty($_POST['save']))
{
  $db->querynow("delete from search where STAMP <= now()");
  $ar = array
  (
    'SER_PARAMS' => serialize($_POST),
	'STAMP' => date('Y-m-d H:i:s', strtotime('+8 hours'))
  );
  $id_s = $db->update("search", $ar);
  die(forward("index.php?page=modul_tutorial_overview&ID_S=".$id_s));
} // suchen

$ar_s = array
(
  'ALL_' => 1
);

if($_REQUEST['ID_S'])
{
  $ar = $db->fetch_atom("select SER_PARAMS from search where ID_SEARCH=".(int)$_REQUEST['ID_S']);
  $ar_s = unserialize($ar);  
} // suche aus db



if(!$ar_s['RED_'] && !$ar_s['ADM_'])
  $ar_s['ALL_']=1;

#echo ht(dump($ar_s));

$where = array();
if($ar_s['_NAME'])
{
  $ar_s['FK_USER'] = (int)$db->fetch_atom("select ID_USER from `user`where `NAME`='".sqlString($ar_s['_NAME'])."'");  
} // name


if($ar_s['ID'])
  $where[] = 't.ID_TUTORIAL='.$ar_s['ID'];
if($ar_s['FK_USER'])
  $where[] = 't.FK_USER='.$ar_s['FK_USER'];
if($ar_s['title'])
  $where[] = "t.`titel` LIKE '%".sqlString($ar_s['title'])."%'";
$ok=0;
if(!$ar_s['ALL_'])
{
  
  if($ar_s['RED_'])
    $ok+= 1;
  if($ar_s['ADM_'])
    $ok += 2;
  $where[] = "t.OK=".$ok;
} // nicht alle
else
  $where[] = "t.OK >= 0";

if($ar_s['FKL_KAT'])
  $where[] = "t.FK_KAT=".$ar_s['FK_KAT'];


$tpl_content->addvars($ar_s);

//löschen
if ($_REQUEST['DEL'])
{
	$id = (int)$_REQUEST['ID_TUTORIAL'];
	$res=$db->querynow("delete from tutorialview where FK_SCRIPT=".$id);
    $res = $db->querynow("update kommentar_kommentar set FK=0 where FK=".$id);	   
	$res = $db->querynow("delete from  searchdb_index_de where S_TABLE='tutorial' and FK_ID=".$id);
	$res = $db->querynow("update tutorial set OK=0 where ID_TUTORIAL=".$id);
	$res = $db->querynow("update tutorial_live set OK=0 where ID_TUTORIAL_LIVE=".$id);
    todo('Neue Tutorials cachen', 'cron/tutorial_neu.php', NULL, NULL, date('Y-m-d H:i:s', strtotime('+5 minutes')));
}

$nummer = 1;
function counter_(&$row)
{

   global $nummer, $table;
 
     $row['NUMMER'] = $nummer;
     $nummer++;	  
	 $search = array("FK" => $row['FK'],
	 			     "KOMM" => $table, 
					 "ORDERBY" => "STAMP", 
					 "updown" => "DESC");
	 $row['search_string'] = urlencode(serialize($search));
	 $row['table'] = $table;
	 $row['id_comment'] = $row['ID_KOMMENTAR_'.strtoupper($table)];
 
}


	// Tutorials / Seite
	$perpage = 10;
	// Rest
	$npage = ( $_REQUEST["npage"] ? $_REQUEST["npage"] : 1 );
	$limit = (($npage-1)*$perpage);
	$SILENCE=false;
	
	$data = $db->fetch_table("select t.ID_TUTORIAL,t.FK_KAT,t.FK_USER,t.OK,
	  t.idate,t.titel,if (t.OK&1,1,0) OK1, if (t.OK&2,1,0) OK2,NAME,s.V1,t.PCOUNT,t.LAST_COMMENT,
	  t.STAMP_UPDATE,
	  live.OK as ONLINE,
	  ".$npage." as npage, ".(int)$_REQUEST['ID_S']." as ID_S 
	 from tutorial t 
	  left join tutorial_live live on t.ID_TUTORIAL=live.ID_TUTORIAL_LIVE
	  left join user on t.FK_USER=ID_USER
	  left join tree_tutorial k on ID_TREE_TUTORIAL=t.FK_KAT
	  left join string_tree_tutorial s on s.FK=t.FK_KAT and 
	     s.BF_LANG=if(k.BF_LANG_TREE_TUTORIAL &  ".$langval.", ".$langval.", 1 << floor(log(k.BF_LANG_TREE_TUTORIAL+0.5)/log(2))) 
	 where ".implode(" and ", $where)."
	 order by STAMP_UPDATE DESC 
	 limit ".$limit.", ".$perpage);
    
	$tpl_content->addlist('liste', $data, 'tpl/de/modul_tutorial_overview.row.htm',counter_);


	$anzDaten = $db->fetch_atom("SELECT COUNT(*) FROM tutorial t where ".implode(" and ", $where));
	$tpl_content->addvar('pager', htm_browse($anzDaten,$npage,"index.php?page=modul_tutorial_overview&showStat=".$showStat."&ID_S=".(int)$_REQUEST['ID_S']."&npage=",$perpage));
	$tpl_content->addvar('anzDaten',$anzDaten);
	$tpl_content->addvar('npage',$npage);

?>