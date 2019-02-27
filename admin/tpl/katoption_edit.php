<?php
/* ###VERSIONSBLOCKINLCUDE### */



 if(isset($_GET['ID_KAT_OPTION']))
   $tpl_content->addvars($db->fetch1($db->lang_select('kat_option')."
      where ID_KAT_OPTION=".$_GET['ID_KAT_OPTION']));

 if(count($_POST))
 {
  $err=array ();
  $tpl_content->addvars($_POST);
  if(empty($_POST['V1']))
    $err[] = 'Kein Name angegeben';
  if(empty($_POST['ID_KAT_OPTION']) && empty($_POST['IDENT']))
    $err[] = 'Kein IDENT angegeben';
  $chk = $db->fetch_atom("select ID_KAT_OPTION from kat_option where
    IDENT='".mysql_escape_string($_POST['IDENT'])."'");
  if(!empty($chk))
    $err[] = 'Dieser IDENT ist bereits vergeben!';
  if(count($err))
    $tpl_content->addvar('err', implode('<br />', $err));
  else
  {
   $id = $db->update("kat_option", $_POST);
   if(!empty($_POST['ID_KAT_OPTION']))
     $id=$_POST['ID_KAT_OPTION'];
   forward('index.php?page=katoption_edit&ID_KAT_OPTION='.$id);
  }
 }

?>