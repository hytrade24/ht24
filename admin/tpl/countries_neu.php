<?php
/* ###VERSIONSBLOCKINLCUDE### */



 #$nar_art = $db->fetch_nar("select CODE,CODE from country group by CODE order by CODE");
 
 $err = array();
 
 if(count($_POST))
 {
  
   if(empty($_POST['CODE']))
     $err[] = "Sie müssen einen CODE angeben!";
    
   if(empty($_POST['V1']))
     $err[] = "Sie müssen ein Namen angeben!";
 
 
 if(count($err))
 {
   $err = implode('<br />', $err);
   $tpl_content->addvar('err', $err);
   $tpl_content->addvars($_POST);
 }
 else
 {
	   $check = $db->fetch_atom("select ID_COUNTRY from country where `CODE` ='".mysql_escape_string($_POST['CODE'])."'");
	   if(!$check)
	   {
	     unset($_POST['page']);
	     $db->update('country', $_POST);
	     //echo "DB Update ausgeführt MIT CHECK";
	     $tpl_content->addvar('ok', 1);
	     $tpl_content->addvar('content', implode('</td><td>', $_POST));
	   }
	   else
	   {
	     $tpl_content->addvar("err", "Dieses Land gibt es schon! Gehen Sie auf die L&auml;nder Seite und editieren Sie das bestehenden Land, um Veränderungen vorzunehmen.");
	   }
   }
 }

?>