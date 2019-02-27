<?php
/* ###VERSIONSBLOCKINLCUDE### */



$mem = preg_replace("/([a-z])*/si", "", get_cfg_var('memory_limit')); 

 if(count($_POST))
 {
   $tpl_content->addvars($_POST); 
   if($_POST['do']=='rm')
     @unlink("../uploads/import/image/".$_POST['IMG']);
   else
   {
       $err = array();

	   if($_POST['DIR'] == "")
	     $err[] = "Sie dürfen nicht in das ../upload/ Verzeichnis importieren, wählen sie ein Unterverzeichnis!";

	   if(!file_exists("../uploads/".$_POST['DIR']))
	     $err[] = "Das angegebene Verzeichnis existiert nicht. Erstellen Sie es zuerst, oder geben ein anderes Verzeichnis an.";

	   if($_POST['NAME_'] != "") 
	     $ownerid = $db->fetch_atom("select ID_USER from user where NAME='".$_POST['NAME_']."'");
	   else
	   	$ownerid = $uid;

	   if($ownerid < 1) 
	     $err[] = "Der angegebene Besitzer konnte nicht gefunden werden!";

	   $img = "../uploads/import/image/".$_POST['IMG'];
	   $ar_opt = $db->fetch1("select * from bildformat where ID_BILDFORMAT=".$_POST['FK_BILDFORMAT']);

	   if(!count($err)) {
		   include "sys/lib.media.php";
		   $handle = handle_img($img,$ar_opt,$_POST,false,true,true);
		   
		   if(!$handle)
		     $err[] = "Fehler beim Bildimport!";
		}
		
		if(!count($err)) {
			 $ar=array();
			 $ar['ID_IMG'] = ($_REQUEST['ID_IMG'] ? $_REQUEST['ID_IMG'] : NULL);
			 $ar['WIDTH'] = $handle['IMG']['width'];
			 $ar['HEIGHT'] = $handle['IMG']['height'];
			 $ar['FK_GALERIE'] = ($_POST['FK_GALERIE'] ? $_POST['FK_GALERIE'] : NULL); 	 
			 $ar['OK'] =1;
			 $ar['SRC'] = $handle['IMG']['file'];
			 $ar['FK_USER'] = $ownerid;
			 $ar['SRC_T'] = ($handle['THUMB'] ? $handle['THUMB']['file'] : NULL); 
			 $ar['WIDTH_T'] = ($handle['THUMB'] ? $handle['THUMB']['width'] : NULL);
			 $ar['HEIGHT_T'] = ($handle['THUMB'] ? $handle['THUMB']['height'] : NULL);	
			 $ar['ALT'] = $ar['TITLE'] = $_POST['ALT'];
			 $ar['DATUM'] = date('Y-m-d H:i');
		#echo ht(dump($ar)); 
			 $db->update("img", $ar);
			 //forward("index.php?page=bildimport");   
		   }
		else {
		$tpl_content->addvar("err", implode("<br />", $err)); 
	   } // error
   	 } // wenn import
 } // post
 
 function get_dirs($start)
 {
   $d = dir($start);
   $ar_dirs = array();
   while (false !== ($entry = $d->read())) 
   {     
	 //$ar_dirs[] = $entry;;
	 if(!is_file($start.$entry) && $entry != "." && $entry != "..")
	   $ar_dirs[] = $entry;
   }
   $d->close();
   return $ar_dirs;   
 }

 $ar_dirs = get_dirs($up_dir = "../uploads/");
 //$dirs = '<option value="Image">Image</option>';
 for($i=0; $i<count($ar_dirs); $i++)
 {
   $sel="";
   if($_POST['DIR'] == $ar_dirs[$i])
     $sel = " selected";
   $dirs .= '<option value="'.$ar_dirs[$i].'"'.$sel.'>uploads/'.$ar_dirs[$i].'</option>';
 }
 
 $tpl_content->addvar("DIRS", $dirs); 
 
 $img=false;
 $d = dir($folder = "../uploads/import/image/");
 while (false !== ($entry = $d->read()))
 {
   if(@getimagesize($folder.$entry))
   {
     $img = $entry;
	 break;
   }
 }

if($img)
{
 $tpl_content->addvar("filesize", $filesize = round((filesize($folder.$img)/1024),2));
 $sizes = getimagesize($folder.$img);
 $mem_needle=(((($sizes[0]*$sizes[1])*3)/1024)/1024);

 if($mem_needle>$mem)
   $tpl_content->addvar("TOBIG", 1);
 
 $tpl_content->addvar("w", $sizes[0]);
 $tpl_content->addvar("h", $sizes[1]);
 
 $tpl_content->addvar("IMG", $img);
 $tpl_content->addvar("ALT", substr(str_replace("_", " ", $img),0,strrpos($img,".")));
} 
if(!$img && count($_POST))
  forward("index.php?page=bildimport"); 
?>