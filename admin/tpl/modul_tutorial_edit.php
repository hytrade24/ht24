<?php
/* ###VERSIONSBLOCKINLCUDE### */


$SILENCE=false;
 $id = (int)$_REQUEST['ID_TUTORIAL'];
 
 if($_GET['OK'])
   $tpl_content->addvar("SAVED", 1);
 
 if($_REQUEST['delimg'])
 {
   $img = $db->fetch1("select * from user2img where ID_USER2IMG=".(int)$_REQUEST['delimg']);
   @unlink($ab_path.$img['PATH'].$img['IMG']);
   if($img['THUMB'])
     @unlink($ab_path.$img['PATH'].$img['THUMB']);
   $db->querynow("delete from user2img where ID_USER2IMG=".(int)$_REQUEST['delimg']);
   
   $text = $db->fetch_atom("select tutorial from tutorial where ID_TUTORIAL=".$id);
   $text = str_replace("[img:".$_REQUEST['delimg']."]", "", $text);
   $db->querynow("update tutorial set tutorial='".sqlString($text)."' 
    where ID_TUTORIAL=".$id);
   die(forward('index.php?page=modul_tutorial_edit&ID_TUTORIAL='.$id));
   
 } // bild löschen
 
 if(count($_POST))
 {
   include $ab_path."sys/lib.bbcode.php";
   if($_POST['vorschau'])
   {
	 ### parser start
	    $parsed = $_POST['tutorial'];
		preg_match_all("%(\[CODE\]|\[PHP\])(.*?)(\[/CODE\]|\[/PHP\])%si", $parsed, $treffer, PREG_PATTERN_ORDER);
		
		foreach($treffer[0] as $key => $value)
		{
		  $arr[$key] = array("wert" => $value, "ident" => "/%/phpres id=".$key."/%/");
		  $parsed = str_replace($arr[$key]['wert'], $arr[$key]['ident'], $parsed);
		} // foreach ende
		
		// die zwischengespeicherten codes werden wieder eingetragen
		for($i=0; $i<count($arr); $i++)
		{
		  $parsed = str_replace($arr[$i]['ident'], $arr[$i]['wert'], $parsed);
		} // foreach ende
	
		preg_match_all("/(\[img:)(\s?)(.*?)(\])/si", $parsed, $trefferimg, PREG_PATTERN_ORDER);

		foreach($trefferimg[3] as $key => $value)
		{
		  $query = "select IMG, PATH from user2img where ID_USER2IMG = ".$value." and FK = ".$_POST['ID_TUTORIAL']." and WHAT = 'TUTORIAL'";
		  if($res = mysql_fetch_assoc(mysql_query($query)))
			$parsed = str_replace("[img:".$value."]", "<img src=\"/".$res['PATH'].$res['IMG']."\" />", $parsed);
		  else
			$parsed = str_replace("[img:".$value."]", "", $parsed);
		}
		
		$bbcode = new bbcode(); 
		#die(ht(dump($parsed)));
		$parsed = $bbcode->parseBB($parsed);
		
		$exp_parsed = explode("[pagebreak]", $parsed);

        for($i=0; $i<count($exp_parsed); $i++)
		{
		  $exp_parsed[$i] = '<div style="background-color: #DBDBDB; border: 1px #cccccc solid;">Seite: <b>'.($i+1).'</b><br>'.$exp_parsed[$i]."</div>";
		}
	    $vorschau = implode("<br>", $exp_parsed);
		$tpl_content->addvar("preview", $vorschau);	 
		#die("hallo?");
	 ### parser ende 
   } // vorschau
   else
   {
     $_POST['OK'] = array_sum($_POST['OK']);
	 date_implode($_POST, "idate");
	 #echo ht(dump($_POST));
	 $db->update("tutorial", $_POST);
   require_once ("sys/lib.search.php");
   $search = new do_search($s_lang);
	 if($_POST['OK'] == 3)
	 {
	    $parsed = $_POST['tutorial'];
		preg_match_all("%(\[CODE\]|\[PHP\])(.*?)(\[/CODE\]|\[/PHP\])%si", $parsed, $treffer, PREG_PATTERN_ORDER);
		
		foreach($treffer[0] as $key => $value)
		{
		  $arr[$key] = array("wert" => $value, "ident" => "/%/phpres id=".$key."/%/");
		  $parsed = str_replace($arr[$key]['wert'], $arr[$key]['ident'], $parsed);
		} // foreach ende
		
		// die zwischengespeicherten codes werden wieder eingetragen
		for($i=0; $i<count($arr); $i++)
		{
		  $parsed = str_replace($arr[$i]['ident'], $arr[$i]['wert'], $parsed);
		} // foreach ende
	
		preg_match_all("/(\[img:)(\s?)(.*?)(\])/si", $parsed, $trefferimg, PREG_PATTERN_ORDER);

		foreach($trefferimg[3] as $key => $value)
		{
		  $query = "select IMG, PATH from user2img where ID_USER2IMG = ".$value." and FK = ".$_POST['ID_TUTORIAL']." and WHAT = 'TUTORIAL'";
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
		for($i=0; $i<count($exp_parsed); $i++)
		{
	     mysql_query("insert into `tutorials_page` (tutid, pageno, tuttext) value ('".(int)$id."', '".($i+1)."', '".sqlString($exp_parsed[$i])."')");
	    }
		
	   # echo ht(dump($res));die();
	  #die("Stopp mal wegen parser .... ");
     //suchindex updaten
     $search->add_new_text($parsed." ".$_POST["titel"],$_POST['ID_TUTORIAL'],'tutorial');
	 } else {
     //suchindex entfernen
     $search->delete_from_searchindex($_POST['ID_TUTORIAL'],'tutorial');
	 }
        $ar_t=$db->fetch1("select * from tutorial where ID_TUTORIAL=".$_POST['ID_TUTORIAL']); 
		$res = $db->querynow("update tutorial_live set 
		   FK_KAT=".$_POST['FK_KAT'].", OK=".$_POST['OK'].", titel='".sqlString($_POST['titel'])."', discription='".sqlString($_POST['discription'])."',
		    idate='".$ar_t['idate']."',STAMP_UPDATE='".$ar_t['STAMP_UPDATE']."', FK_USER=".$ar_t['FK_USER']."
		   where ID_TUTORIAL_LIVE=".$_POST['ID_TUTORIAL']);	   
	   #die(ht(dump($res)));
	   todo('Neue Tutorials cachen', 'cron/tutorial_neu.php', NULL, NULL, date('Y-m-d H:i:s', strtotime('+5 minutes')));	 
	 if($_POST['KICK'])
	 {
	   $db->querynow("update tutorial_live set OK=0 where ID_TUTORIAL_LIVE=".$id);
	   $db->querynow("update tutorial set OK=0 where ID_TUTORIAL=".$id);	   
	 } // KICK IT
	 #die("was?");
	 die(forward("index.php?page=modul_tutorial_edit&OK=1&ID_TUTORIAL=".$id));
   } // keine Vorschau
   $tpl_content->addvars($_POST);
   $ar=$db->fetch1("select t.FK_USER, u.NAME as UNAME from tutorial t
    left join `user` u on t.FK_USER=u.ID_USER
   where ID_TUTORIAL=".$id." 
	");
   $tpl_content->addvars($ar);
 } // post
 else
 {
    $ar = $db->fetch1("select t.*, tl.OK as ONLINE, u.NAME as UNAME 
	 from tutorial t
	  left join tutorial_live tl on t.ID_TUTORIAL=tl.ID_TUTORIAL_LIVE
	  left join `user` u on t.FK_USER=u.ID_USER
	 where t.ID_TUTORIAL=".$id);
	$tpl_content->addvars($ar);		
 } // kein post
 
 ### bilder
 $img = $db->fetch_table("select * from user2img where FK=".$id." and WHAT='tutorial'");
 $tpl_content->addlist("BILDER", $img, "tpl/de/modul_tutorial_edit.row.htm");
 
 ### weiutere
 $weitere = $db->fetch_table("select ID_TUTORIAL, titel from tutorial where
   FK_USER=".$ar['FK_USER']." and ID_TUTORIAL <> ".$id);
 $tpl_content->addlist("weitere", $weitere, "tpl/de/modul_tutorial_edit.weitere.htm");
 
 ### klicks
 $tpl_content->addvar("KLICKS", $db->fetch_atom("select sum(VIEWS) from tutorialview where FK_TUTORIAL=".$id));

?>