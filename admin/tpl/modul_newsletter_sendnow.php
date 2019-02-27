<?php
/* ###VERSIONSBLOCKINLCUDE### */



  $tpl_content->addvars($_REQUEST); 
  if(isset($_REQUEST['do']) && $_REQUEST['do'] == "send")
  {
    $ar = $db->fetch1("select * from nl_log log
      left join nl letter on log.FK_NL=letter.ID_NL
    where FK_NL=".$_REQUEST['ID_NL']);
    $langval_alt = 1 << floor(log($ar['BF_LANG_MAIL']+0.5)/log(2));
   #die(ht(dump($ar)));
   $tpl_content->addvars($ar); 
   if($ar['VERSAND'] == 2)
   {
     $ar_mail = $db->fetch_table("select u.EMAIL, s.V1, s.T1 from `user` u 
       left join lang la on u.FK_LANG=la.ID_LANG
	   left join string_mail s on s.S_TABLE='nl' and s.FK=".$ar['ID_NL']."
         and s.BF_LANG=if(". $ar['BF_LANG_MAIL']. " & la.BITVAL, la.BITVAL, ". $langval_alt. ")
	   order by ID_USER
	  limit ".$ar['DONE'].",".$nar_systemsettings['NEWSLETTER']['mailperrun']);
   }
   else
   {
     $ar_mail = $db->fetch_table("select EMAIL, s.V1, s.T1 from `nl_recp` u 
       left join lang la on u.LANGVAL=la.BITVAL
	   left join string_mail s on s.S_TABLE='nl' and s.FK=".$ar['ID_NL']."
         and s.BF_LANG=if(". $ar['BF_LANG_MAIL']. " & la.BITVAL, la.BITVAL, ". $langval_alt. ")
	   where CODE IS NULL
	   order by ID_NL_RECP
	  limit ".$ar['DONE'].",".$nar_systemsettings['NEWSLETTER']['mailperrun']);   
   }   
   
   #die(ht(dump($lastresult)));
   
   $ID_MODULOPTION_NLE = $db->fetch_atom("select ID_MODULOPTION from `moduloption` where OPTION_VALUE='NEWSLETTER_EMAIL'");
   $absender = $db->fetch_atom($db->lang_select("string_opt","V1") . " where S_TABLE='moduloption' AND FK=".$ID_MODULOPTION_NLE);
   
   // Check if mails should be sent as HTML or plaintext
   //$IDhtml = $db->fetch_atom("select ID_MODULOPTION from `moduloption` where OPTION_VALUE='NEWSLETTER_TYPE'");
   $html = true; //$db->fetch_atom($db->lang_select("string_opt","V1") . " where S_TABLE='moduloption' AND FK=".$IDhtml);
   
   $mheader ="From: ".$nar_systemsettings['SITE']['SITENAME']." <".$absender.">\n"; 
   $mheader .="Reply-To: ".$absender."\n"; 
   $mheader .="Content-Type: ".($html ? "text/html" : "text" )."; charset=iso-8859-1\n";   
   $err = array();
   $m=0;

   ### Design einlesen 
   $design = file_get_contents($ab_path."cache/newsletterdesign.htm");

   for($i=0; $i<count($ar_mail); $i++)
   {
      $m++;
      $ar_mail[$i]['T1'] = str_replace("{NEWSLETTER}", $ar_mail[$i]['T1'], $design);
	  
	  #die(ht(dump($ar_mail[$i])));
	  		 			       
	  $senden = mail($ar_mail[$i]['EMAIL'], $ar_mail[$i]['V1'], $ar_mail[$i]['T1'], $mheader); 
	  if(!$senden)
	  $err[] = "Mail an ".$ar_mail[$i]['EMAIL']." konnte nicht zugestellt werden!";     
   }

   $done = $ar['DONE']+$m;
   if($done < $ar['TODO'])
   {
      $tpl_content->addvar("senden", 1); 
	  $weiter=true;
   }
   if(count($ar_mail) > 0)
       $lastresult = $db->querynow("update nl_log set DONE=".$done." where FK_NL=".$ar['ID_NL']);
   else
       $lastresult = $db->querynow("update nl_log set DONE=1 where FK_NL=".$ar['ID_NL']);
   if(!empty($lastresult['str_error']))
     $err[] = "Datenbankfehler! ".ht(dump($lastresult));
   if(count($err))
     $tpl_content->addvar("err", implode("<br />", $err)); 
   if(!$weiter)
     forward("index.php?frame=iframe&page=modul_newsletter_sendready");  
 }

?>