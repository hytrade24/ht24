<?php
/* ###VERSIONSBLOCKINLCUDE### */



 #echo $db->lang_select("script");
 
 if($_REQUEST['DEL'])
 {
   $ar_bild = $db->fetch1("select * from user2img where ID_USER2IMG=".(int)$_REQUEST['DEL']);
   unlink($ab_path.$ar_bild['PATH'].$ar_bild['THUMB']);
   unlink($ab_path.$ar_bild['PATH'].$ar_bild['IMG']); 
   $db->querynow("delete from user2img where ID_USER2IMG=".(int)$_REQUEST['DEL']);
 } // del IMG
 
 if($_REQUEST['saved'])
   $tpl_content->addvar("SAVED", 1);
 
 if(count($_POST))
 {
   
   $err = array();
   if(!$_POST['FK_KAT'])
     $err[] = "Keine Kategorie gewählt!";
   if(empty($_POST['V1']))
     $err[] = "Kein Name angegeben!";
   if(empty($_POST['V2']))
     $err[] = "Keine Kurzbeschreibung angegeben!";	
   if(empty($_POST['T1']))
     $err[] = "Keine Beschreibung angegeben!";	 
   if(empty($_POST['LINK_DSC']))
     $err[] = "Kein Link angegeben!";	
   
   if(!isset($_POST['PAID']))
     $_POST['PAID'] = 0;
   #die(ht(dump($_POST)));
   if (is_array ($_POST['OK']))
	  $_POST['OK'] = array_sum($_POST['OK']);   
   
   if(count($err))
   {
     $tpl_content->addvar("err", implode("<br>", $err));
	 $tpl_content->addvars($_POST);
   } // err
   else
   {
     
	 todo("Kategorien neu cachen", "cron/recache_kat.php", NULL, NULL, NULL, 'script');
	 
	 if($_POST['KILL_VKAT'])
	   $_POST['VKAT']=NULL;
	 
	 $kat_create = '';
	 
	 if($_POST['VKAT'])
	 {
	   $check = $db->fetch_atom("select FK from string_tree_script where V1='".sqlString($_POST['VKAT'])."'");
	   if($check)
	   {
	     $_POST['VKAT']=NULL;
		 $kat_create = "&katcreated=1";
	   } // check 
	 } // VKAT  
	 #echo "<h1>TESTSTOPPPPPPP</h1>";
	 #$SILENCE=false;
	 $db->update("script_work", $_POST);	
   require_once ("sys/lib.search.php");
   $search = new do_search($s_lang);
	 if($_POST['OK'] == 3)
	 {
     //suchindex updaten
     $searchtext_script = $db->fetch_atom("SELECT CONCAT(T1,V1) FROM string_script_work WHERE".
      " S_TABLE='script_work' AND BF_LANG=".$langval." AND FK=".$_POST["ID_SCRIPT_WORK"]);
     $search->add_new_text($_POST['T1'].' '.$_POST['V1'],$_POST['ID_SCRIPT_WORK'],'script'); 
    
	   $_POST['ID_SCRIPT'] = $_POST['ID_SCRIPT_WORK'];
	   scriptfreischaltung($_POST['ID_SCRIPT_WORK']);
	   //$db->update("script", $_POST);
	 } // OK == 3
	 else {
     //suchindex entfernen
	   $search->delete_from_searchindex($_POST['ID_SCRIPT_WORK'],"script");
	 } // OK != 3
	 #die();
	 
	 //die("BAUSTELLE -&lt; Suche muss noch weg!");
	 $fwd = "index.php?page=script_edit&saved=1&ID_SCRIPT_WORK=".$_POST['ID_SCRIPT_WORK'].$kat_create;;
     die(forward($fwd));
   } // kein err 
 } // posst
 
 $ar = $db->fetch1("select t.*,ORG.OK as ONLINE, if (t.OK&1,1,0) OK1, if (t.OK&2,1,0) OK2, s.V1, s.V2, s.T1, u.`NAME` as UNAME 
  from `script_work` t 
   left join script ORG on t.ID_SCRIPT_WORK=ORG.ID_SCRIPT
   left join `user` u on t.FK_USER=u.ID_USER 
   left join string_script_work s 
    on s.S_TABLE='script_work' and s.FK=t.ID_SCRIPT_WORK 
	and s.BF_LANG=if(t.BF_LANG_SCRIPT_WORK & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_SCRIPT_WORK+0.5)/log(2))) 
  where ID_SCRIPT_WORK=".(int)$_REQUEST['ID_SCRIPT_WORK']);
 
 $views = (int)$db->fetch_atom("select sum(`VIEWS`) from scriptview where FK_SCRIPT=".$ar['ID_SCRIPT_WORK']);
 $tpl_content->addvar("VIEWS", $views);
 
 $clicks = (int)$db->fetch_atom("select sum(`CLICKS`) from scriptclick where FK_SCRIPT=".$ar['ID_SCRIPT_WORK']);
 $tpl_content->addvar("CLICKS", $clicks); 
 
 #echo ht(dump($ar));
 
 if($ar['STAMP'] < $ar['STAMP_UPDATE'])
   $ar['NEW'] = 1; 
 
 $ar['kosten'] = ($ar['COMMERCE'] ? 1 : 2);
 $tpl_content->addvars($ar);
 
 $ar_bilder = $db->fetch_table("select * from user2img where FK_USER=".$ar['FK_USER']." and WHAT='SCRIPT' and FK=".$ar['ID_SCRIPT_WORK']);
 $tpl_content->addlist("bilder", $ar_bilder, "tpl/de/script_edit.bilder.htm");
 
 ### zum User
 $liste = $db->fetch_table("select t.*, s.V1
  from `script_work` t 
   left join string_script_work s 
    on s.S_TABLE='script_work' and s.FK=t.ID_SCRIPT_WORK 
	and s.BF_LANG=if(t.BF_LANG_SCRIPT_WORK & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_SCRIPT_WORK+0.5)/log(2))) 
  where t.FK_USER=".$ar['FK_USER']." and ID_SCRIPT_WORK <> ".$ar['ID_SCRIPT_WORK']);
 
 if($liste)
  $tpl_content->addlist("user_liste", $liste, "tpl/de/script_edit.user.htm");
 

?>