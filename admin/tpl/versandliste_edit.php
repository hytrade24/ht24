<?php
/* ###VERSIONSBLOCKINLCUDE### */



 if(isset($_REQUEST['ID_NL_GROUP']) && !count($_POST))
   $tpl_content->addvars($db->fetch1("select * from nl_group where ID_NL_GROUP=".$_REQUEST['ID_NL_GROUP'])); 
 
 if(count($_POST))
 {
  $tpl_content->addvars($_POST);
  $err=array();
  $lastresult = $db->querynow($_POST['QUERY_COUNT']);
  if(!empty($lastresult['str_error']))
    $err[] = "Count Query fehlgeschlagen: ".$lastresult['str_error']; 
  else
  {
   $ar = mysql_fetch_row($lastresult['rsrc']);
   if(!isset($ar[0]) || !is_numeric($ar[0]))
     $err[] = "Das erste Element in der Count Zeile ist keine Zahl!";  
  }
  $lastresult = $db->querynow($_POST['QUERY_DATA']." LIMIT 1");
  if(!empty($lastresult['str_error']))
    $err[] = "Select Query fehlgeschlagen: ".$lastresult['str_error']; 	
  else
  {
   $ar = mysql_fetch_assoc($lastresult['rsrc']);
   if(!isset($ar['EMAIL']))
     $err[] = "In Kein Email Feld selektiert!"; 
  }
  if(count($err))
    $tpl_content->addvar("err", implode("<br />", $err)); 
  else
    $db->update("nl_group", $_POST); 
 }
 
?>