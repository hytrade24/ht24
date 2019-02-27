<?php
/* ###VERSIONSBLOCKINLCUDE### */



 if(count($_POST))
 {
//echo ht(dump($lastresult));  
  $err=array (); 
  if(strlen($_POST['V1']) < 2)
    $err[] = "Bitten geben Sie einen eindeutigen Namen an";
  if(!isset($_POST['ID_ANZART']))
  {
    $check=$db->fetch_atom($db->lang_select("anzart","*,V1")." where V1='".mysql_escape_string($_POST['V1'])."'");
	if(!empty($check))
	  $err[] = "Dieser name ist bereits vergeben!"; 
  } 
  else
    $id = $_POST['ID_ANZART'];
  if(!empty($err))
    $tpl_content->addvar("err", implode("<br />", $err)); 
  else
  {
    $lastresult = $db->querynow("SHOW COLUMNS FROM anzart");
    while($row = mysql_fetch_assoc($lastresult['rsrc']))
    {
#echo ht(dump($row)); 
     if($row['Type'] == "tinyint(1)")
     {
      if(!isset($_POST[$row['Field']]))
	    $_POST[$row['Field']] = NULL; 
     }
    } 
#die(ht(dump($_POST))); 
	$db->update("anzart", $_POST);
	if(!isset($id))
	  $id = $lastresult['int_result']; 
	forward("index.php?page=verkaufsart_edit&ID_ANZART=".$id); 
  }
#  echo ht(dump($_POST)); 
 }

 if(isset($_REQUEST['ID_ANZART']) && empty($_POST))
 {
  $tpl_content->addvars($db->fetch1($db->lang_select("anzart")." where ID_ANZART=".$_REQUEST['ID_ANZART'])); 
 }

?>
