<?php
/* ###VERSIONSBLOCKINLCUDE### */


 
 if($_REQUEST['ID_MESSAGE'])
   $id = (int)$_REQUEST['ID_MESSAGE'];
 
 if($_REQUEST['ok'])
   $tpl_content->addvar("ok", 1);
 
 if(count($_POST))
 {
   $err = array();
   if(empty($_REQUEST['FKT']))
     $err[] = "Bitte eine Funktion angeben";
   if(empty($_POST['V1']))
     $err[] = "Bitte geben Sie einen Text ein!";
   
   if(empty($err))
   {
     $db->update("message", $_POST);
	 forward("index.php?page=message_edit&ID_MESSAGE=".$_POST['ID_MESSAGE']."&ok=1");
   }
   else
   {
     $tpl_content->addvar("err", implode("<br />", $err));
	 $tpl_content->addvars($_POST);
   }
 } // post
  
 if($id)
 {
   $ar_m = $db->fetch1("select t.*, s.V1, s.V2, s.T1 
    from `message` t 
	 left join string_app s 
	 on s.S_TABLE='message' 
	 and s.FK=t.ID_MESSAGE 
	 and s.BF_LANG=if(t.BF_LANG_APP & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_APP+0.5)/log(2)))
	where ID_MESSAGE=".$id."
	");
   if(empty($err))
     $tpl_content->addvars($ar_m);
   $_REQUEST['FKT_SELECT'] = $ar_m['FKT'];
 } // if($id)
 
 $res = $db->querynow("select distinct FKT from message order by FKT");
 $ar=array();
 while($row = mysql_fetch_assoc($res['rsrc']))
 {
   $ar[] = '<option value="'.$row['FKT'].'" '.($row['FKT'] == $_REQUEST['FKT_SELECT'] ? ' selected' :'').'>'.stdHtmlentities($row['FKT']).'</option>';
 }
 $tpl_content->addvar("liste_fkt", '<option value="">Funktion ausw&auml;hlen</option>'.implode("\n", $ar));
 

?>