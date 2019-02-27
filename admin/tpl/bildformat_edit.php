<?php
/* ###VERSIONSBLOCKINLCUDE### */


 
 if(!empty($_REQUEST['ID_BILDFORMAT']))
 {
   $tpl_content->addvars($ar=$db->fetch1("select * from bildformat where ID_BILDFORMAT=".$_REQUEST['ID_BILDFORMAT']));
   $_REQUEST['T_QUADRAT'] = $ar['T_QUADRAT'];
#echo ht(dump($ar));
 }
 
 if(!isset($_REQUEST['T_QUADRAT']))
   $_REQUEST['T_QUADRAT'] = 0;
 
 $tpl_content->addvar("T_QUADRAT_".$_REQUEST['T_QUADRAT'], 1);
 
 if(count($_POST))
 {
#echo ht(dump($_POST)); die();
   $err = array();
   $tpl_content->addvars($_POST);
   
   if(empty($_POST['LABEL']))
     $err[] = "Kein Name angegeben";
   if(strlen($_POST['MAX_B'])!= strspn($_POST['MAX_B'],"0123456789"))
     $err[] = "Max. Breite ist keine ganze Zahl"; 
   if(strlen($_POST['MAX_H'])!= strspn($_POST['MAX_H'],"0123456789"))
     $err[] = "Max. Höhe ist keine ganze Zahl"; 
   if(strlen($_POST['MAX_TB'])!= strspn($_POST['MAX_TB'],"0123456789"))
     $err[] = "Max. Breite ( Vorschaubild ) ist keine ganze Zahl"; 	
   if(strlen($_POST['MAX_TH'])!= strspn($_POST['MAX_TH'],"0123456789"))
     $err[] = "Max. Höhe ( Vorschaubild ) ist keine ganze Zahl"; 	     
   if(count($err))
     $tpl_content->addvar("err", implode("<br />", $err));
   else
   {
     if(!isset($_POST['B_THUMB'])) $_POST['B_THUMB'] = 0;
     $bildformatId = $db->update("bildformat", $_POST);
	 forward("index.php?page=bildformat_edit&ID_BILDFORMAT=".($_REQUEST['ID_BILDFORMAT'] ? $_REQUEST['ID_BILDFORMAT'] : $bildformatId));
   }
 }

?>