<?php
/* ###VERSIONSBLOCKINLCUDE### */



 $id = (int)$_REQUEST['ID_SETTING_GROUP'];
 
 if($_REQUEST['ok']==1)
   $tpl_content->addvar("ok", 1);
 
 if(count($_POST))
 {
   if(empty($_POST['V1']))
   {
     $tpl_content->addvar("err", "Bitte geben Sie der Gruppe einen namen!");
     $tpl_content->addvars($_POST);
   } // err
   else
   {
     if(!$_POST['B_VIS'])
	   $_POST['B_VIS'] = 0;
	 $id_neu = $db->update("setting_group", $_POST);
	 $id = ($id ? $id : $id_neu);
	 die(forward("index.php?page=usersetting_group_edit&ok=1&ID_SETTING_GROUP=".$id));
   } // kein fehler
 } // post
 
 if($id)
 {
   $ar = $db->fetch1("select t.*, s.V1, s.V2, s.T1 
    from `setting_group` t 
	 left join string_app s on s.S_TABLE='setting_group' 
	  and s.FK=t.ID_SETTING_GROUP 
	  and s.BF_LANG=if(t.BF_LANG_APP & 128, 128, 1 << floor(log(t.BF_LANG_APP+0.5)/log(2)))
    where ID_SETTING_GROUP=".$id);
   $tpl_content->addvars($ar);
 } // id
 
?>