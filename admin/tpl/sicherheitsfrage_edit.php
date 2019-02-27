<?php
/* ###VERSIONSBLOCKINLCUDE### */



 if(count($_POST))
 {
   if(!$_POST['B_VIS'])
     $_POST['B_VIS']=0;
   
   $err = array();
   
   $x = array('ß', 'ä', 'ö', 'ü');
   $new = array('ss', 'ae', 'oe', 'ue');
   
   $_POST['V2'] = str_replace($x, $new, $_POST['V2']);   
   
   if(!$_POST['V1'])
     $err[] = "Bitte geben Sie eine Frage ein";
   if(!$_POST['V2'])
     $err[] = "Bitte geben Sie eine Antwort ein!";
   $id = (int)$_POST['ID_QUESTION'];
   $check = $db->fetch_atom("select FK from string_app where V1='".sqlString($_POST['QUESTION'])."' and S_TABLE='question'");
   if($check && $check != $id)
     $err[] = "Diese Frage wurde bereits angelegt!";
   
   if(count($err))
   {
     $tpl_content->addvars($_POST);
	 $tpl_content->addvar("err", implode("<br />", $err));
   }
   else
   {
     $new_id = $db->update("question", $_POST);
	 if(!$id)
	   $id = $new_id;
	 die(forward("index.php?page=sicherheitsfrage_edit&OK=1&ID_QUESTION=".$id));
   } // kein error
 } // post
 elseif($_REQUEST['ID_QUESTION'])
 {
   #echo $db->lang_select("question");
   $ar = $db->fetch1("select t.*, s.V1, s.V2, s.T1 from `question` t 
    left join string_app s on s.S_TABLE='question' 
	 and s.FK=t.ID_QUESTION 
	 and s.BF_LANG=if(t.BF_LANG_APP & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_APP+0.5)/log(2))) 
    where ID_QUESTION=".$_REQUEST['ID_QUESTION']."
	");
   $tpl_content->addvars($ar);
 } // ids givern
 if($_REQUEST['OK'])
   $tpl_content->addvar("OK", 1);

?>