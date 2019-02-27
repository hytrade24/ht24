<?php
/* ###VERSIONSBLOCKINLCUDE### */



 if($_REQUEST['SETDEF'])
 {
   $db->querynow("update `option` set `value` = ".$_REQUEST['FK_SCRIPT']." where plugin='SCRIPT' and `typ`='DEFAULT'");
  // echo ht(dump($lastresult));
 } // setdef

 function datetokw($kw, $year)
 {   
   $jahresbeginn = mktime(0,0,0,1,1,$year);
   $anfangstage = date("w", $jahresbeginn-1)*86400;
   $datum = $jahresbeginn+(($kw-1)*86400*7)-$anfangstage;
   $next = date('d.m.Y', ($datum+(86400*7))-1);
   return date("d.m.y", $datum). " bis ".$next;
 } // datetokw()

 $year = ($_REQUEST['YEAR'] ? $_REQUEST['YEAR'] : date('Y'));
 $kw = ($_REQUEST['KW'] ? $_REQUEST['KW'] : date('W'));
 
 $years = array();
 for($i=date('Y'); $i<(date('Y')+3); $i++)
   $years[] = '<option value="'.$i.'"'.($year == $i ? ' selected' : '').'>'.$i.'</option>';

 $tpl_content->addvar("JAHRE", implode("\n", $years));
 
 $kws = array();
 $start = ($year == date("Y") ? date('W') : 1);

 if(substr($start, 0, 1) == 0)
 {
   $kw = $start;
   $start = substr($start, 1);    
 } // führende null
 else
   $kw = $start;
 
 ### belegte ausschließen
 $res = $db->querynow("select KW from scripttop where JAHR=".$year." and KW >= ".$kw);
 #echo ht(dump($res));
 $belegt = array();
 while($row = mysql_fetch_assoc($res['rsrc']))
   $belegt[] = $row['KW'];
 
 #echo ht(dump($belegt));
   
 for($i=$start; $i<54; $i++)
 {   
   if(in_array($i, $belegt))
     continue;
   $kws[] = '<option value="'.$i.'">KW '.$i.' - '.datetokw($i, $year).'</option>';
 }
 #echo ht(dump($kws));
 $tpl_content->addvar("KWS", implode("\n", $kws));
 
 $_REQUEST['is_default'] = ($db->fetch_atom("select `value` from `option` where plugin='SCRIPT' and `typ`='DEFAULT'") == $_REQUEST['FK_SCRIPT'] ? 1 : 0);
 
 if(!$_REQUEST['is_default'])
 {
   $SILENCE=false;
   $ar_def = $db->fetch1("select t.ID_SCRIPT_WORK as DEF_ID, s.V1 as DEF_V1 
     from `script_work` t 
	  left join string_script_work s on s.S_TABLE='script_work' 
	   and s.FK=t.ID_SCRIPT_WORK and s.BF_LANG=".$langval."
     where t.ID_SCRIPT_WORK=".$_REQUEST['FK_SCRIPT']);
   $tpl_content->addvars($ar_def);
 }
 
 $tpl_content->addvars($_REQUEST);
 

 
 if($_REQUEST['SAVE'] > '')
 {
   #die(ht(dump($_REQUEST)));
   $ch = $db->fetch_atom("select FK_SCRIPT from scripttop where KW='".(int)$_REQUEST['KW']."' and JAHR='".(int)$_REQUEST['YEAR']."'");
   if($ch)
     $tpl_content->addvar("err", "Diese Kalenderwoche ist bereits vergeben!");
   else
   {
     $db->querynow("insert into scripttop set FK_SCRIPT=".$_REQUEST['FK_SCRIPT'].",
	  KW=".$_REQUEST['KW'].", JAHR=".$_REQUEST['YEAR']);
	 $tpl_content->addvar("OK", 1);
   } // eintrag möglich 
 } // speichern

?>