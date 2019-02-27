<?php
/* ###VERSIONSBLOCKINLCUDE### */


 
 function topdays(&$ar, $i)
 {
   
   $jahresbeginn = mktime(0,0,0,1,1,$ar['JAHR']);
   $anfangstage = date("w", $jahresbeginn-1)*86400;
   $ar['VON'] = $jahresbeginn+(($ar['KW']-1)*86400*7)-$anfangstage;
   $ar['BIS'] = date('d.m.Y', ($ar['VON']+(86400*7))-1);
   $ar['VON'] = date('Y-m-d', $ar['VON']);
   #echo ht(dump($ar));
 } // topdays
 
 if(count($_POST))
 {
   //die("HUHU");
   $err = array();
   date_implode($_POST, 'STAMP');
   date_implode($_POST, 'STAMP_UPDATE');   
   
   if(!$_POST['PAID'])
     $_POST['PAID'] = 0;
   
   if (is_array ($_POST['OK']))
	  $_POST['OK'] = array_sum($_POST['OK']);   
   
   if(count($err))
   {
     $tpl_content->addvar("err", implode("<br>", $err));
	 $tpl_content->addvars($_POST);
   } // err
   else
   {
	 require_once ("sys/lib.search.php");
	 $search = new do_search($s_lang,false);
	 $search->add_new_text(($_POST['OK'] == 3 ? $_POST['T1'].' '.$_POST['V1'] : ''),$_POST['ID_SCRIPT'],'script');     
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
	 #$db->update("script_work", $_POST);
	 $up = $db->querynow("update script_work set STAMP_UPDATE='".$_POST['STAMP_UPDATE']."', 
	    STAMP='".$_POST['STAMP']."', OK='".$_POST['OK']."', PAID='".($_POST['PAID'] ? 1 : 0)."', DEAD=NULL
	   where ID_SCRIPT_WORK=".$_POST['ID_SCRIPT_WORK']);
	 #die(ht(dump($GLOBALS['lastresult'])));
   require_once ("sys/lib.search.php");
   $search = new do_search($s_lang);
	 if($_POST['OK'] == 3)
	 {
     //suchindex updaten
     $searchtext_script = $db->fetch_atom("SELECT CONCAT(T1,V1) FROM string_script_work WHERE".
      " S_TABLE='script_work' AND BF_LANG=".$langval." AND FK=".$_POST["ID_SCRIPT_WORK"]);
     $search->add_new_text($searchtext_script,$_POST['ID_SCRIPT_WORK'],'script');
     
	   $_POST['ID_SCRIPT'] = $_POST['ID_SCRIPT_WORK'];
	   //$db->update("script", $_POST);
	   scriptfreischaltung($_POST['ID_SCRIPT_WORK']);
	 } // OK == 3 
   else {
     $search->delete_from_searchindex($_POST['ID_SCRIPT_WORK'],"script");
   } // OK != 3
	 $tpl_content->addvar("SAVED", 1);
	 todo("Neue Scripte cachen", "cron/script_day.php", NULL, NULL, date('Y-m-d H:i:s', strtotime('+5 minutes')), '');
   } // kein err 
 } // posst
 
 $ar = $db->fetch1("select t.*,left(t.LINK_DSC, 20) as LINK_DSC_SHORT,left(t.LINK_DL, 20) as LINK_DL_SHORT, 
  ORG.OK as ONLINE, s.V1, s.V2, s.T1, u.`NAME` as UNAME 
  from `script_work` t 
   left join script ORG on t.ID_SCRIPT_WORK=ORG.ID_SCRIPT
   left join `user` u on t.FK_USER=u.ID_USER 
   left join string_script_work s 
    on s.S_TABLE='script_work' and s.FK=t.ID_SCRIPT_WORK 
	and s.BF_LANG=if(t.BF_LANG_SCRIPT_WORK & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_SCRIPT_WORK+0.5)/log(2))) 
  where ID_SCRIPT_WORK=".(int)$_REQUEST['ID_SCRIPT_WORK']);
#echo ht(dump($lastresult));
 ### topscript?
 $kw = date('W');
 if(substr($kw, 0, 1) == 0)
   $kw = substr($kw, 1);    
 $topdays = $db->fetch_table("select * from scripttop where FK_SCRIPT=".$ar['ID_SCRIPT_WORK']." and KW >=".$kw);
 $tpl_content->addlist("topdays", $topdays, "tpl/de/script.topday.htm", "topdays");
 
 #echo ht(dump($ar));
 
 if($ar['STAMP'] < $ar['STAMP_UPDATE'])
   $ar['NEW'] = 1;
 
 
 $ar_bilder = $db->fetch_table("select * from user2img where FK_USER=".$ar['FK_USER']." and WHAT='SCRIPT' and FK=".$ar['ID_SCRIPT_WORK']);
 $tpl_content->addlist("bilder", $ar_bilder, "tpl/de/script_edit.bilder.htm");

 $ar['npic'] = (int)count($ar_bilder);
 $ar['kosten'] = ($ar['COMMERCE'] ? 1 : 2);

 $tpl_content->addvars($ar);

?>