<?php
/* ###VERSIONSBLOCKINLCUDE### */



 $id = (int)$_REQUEST['ID_DATEI'];
 
 if(count($_POST))
 {
   #echo ht(dump($_FILES));
   $err = array();
   if(empty($_POST['DSC']))
     $err[] = "Bitte geben Sie eine Beschreibung an!";
   if(!$_POST['ID_DATEI'])
   {
     if(empty($_FILES['DATEI']['tmp_name']))
	   $err[] = "Bitte wählen Sie eine Datei von Ihrem Computer!";
   }
   
   if(count($err))
   {
     $tpl_content->addvar("err", implode("<br />", $err));
   }
   else
   {
     if(!empty($_FILES['DATEI']['tmp_name']))
	 {
	   $filename = time().".".preg_replace("/([^a-z0-9_\.-])/si", "_", $_FILES['DATEI']['name']);
		 //die($filename);
	   $hack = explode(".", $filename);
	   $n = count($hack)-1;
	   $ext = $hack[$n];
	   //die($ext);
	   $move = move_uploaded_file($_FILES['DATEI']['tmp_name'], "../uploads/datei/".$filename);
	   if(!$move)
	     $err[] = "Datei Upload fehlgeschlagen!";
	   if(empty($err))
	   {
	     $ar = array(
		   'DATEINAME' => $filename,
		   'DATUM' => date('Y-m-d'),
		   'EXT' => $ext,
		   'DSC' => $_POST['DSC']
		 );
		 $id = $db->update("datei", $ar);
	   }
	   else
		 $tpl_content->addvar("err", implode("", $err));  
	 }
	 else
	 {
	     $ar = array(
		   'DATUM' => date('Y-m-d'),
		   'DSC' => $_POST['DSC']
		 );
		 $db->update("datei", $ar);	 
	 }
   }  
   if(empty($err))
     forward("index.php?page=datei_edit&frame=".$curframe."&ID_DATEI=".$id);
 }
 
 if($id)
   $tpl_content->addvars($db->fetch1("select * from datei where ID_DATEI=".$id));

?>
