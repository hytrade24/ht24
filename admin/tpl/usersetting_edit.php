<?php
/* ###VERSIONSBLOCKINLCUDE### */



 $id = (int)$_REQUEST['ID_USERSETTING'];
 
 if($_REQUEST['ok']==1)
   $tpl_content->addvar("ok", 1);
 
 $ar_typen = array
 (
   'check' => "Checkbox",
   'text' => "Textfeld",
   'tpl_funktion' => "Template Funktion",
   'tpl_lookup' => "Wert aus Lookups"
 ); 
 
 if(count($_POST))
 {
   $err=array();
   if(empty($_POST['V1']))
   {
     $err[] = "Bitte geben Sie der Einstellung einen Namen!";
   } // err
   
   if(count($err))
   {
     $tpl_content->addvars($_POST);
	 $tpl_content->addvar("err", implode("<br />", $err));
   }
   else
   {
	 if(!$_POST['B_VIS'])
	   $_POST['B_VIS'] = 0;
	 $id_neu = $db->update("usersetting", $_POST);
	 $id = ($id ? $id : $id_neu);
	 die(forward("index.php?page=usersetting_edit&ok=1&ID_USERSETTING=".$id));
   } // kein fehler
 } // post
 
 if($id)
 {
   $ar = $db->fetch1("select t.*, s.V1, s.V2, s.T1 
    from `usersetting` t 
	 left join string_app s on s.S_TABLE='usersetting' 
	  and s.FK=t.ID_USERSETTING 
	  and s.BF_LANG=if(t.BF_LANG_APP & 128, 128, 1 << floor(log(t.BF_LANG_APP+0.5)/log(2)))
    where ID_USERSETTING=".$id);
   $tpl_content->addvars($ar);
 } // id
 
 $typen = array();
 foreach($ar_typen as $key => $value)
 {
   $selected = "";
   if($_POST['TYP'] == $key || $key == $ar['TYP'])
   {
     $selected = " selected ";
   }
   $typen[] = '<option value="'.$key.'"'.$selected.'>'.stdHtmlentities($value).'</option>';
 }
 $tpl_content->addvar("TYPEN", implode("\n", $typen));
 
?>