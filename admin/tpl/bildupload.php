<?php
/* ###VERSIONSBLOCKINLCUDE### */


 function get_dirs($start)
 {
   $d = dir($start);
   $ar_dirs = array();
   while (false !== ($entry = $d->read())) 
   {     
	 //$ar_dirs[] = $entry;;
	 if(is_dir($start.$entry) && $entry != "." && $entry != "..")
	   $ar_dirs[] = $entry;
   }
   $d->close();
   return $ar_dirs;   
 }

 $uploadpath = $nar_systemsettings['SITE']['PATH_UPLOADS'];
 $imagepath = $nar_systemsettings['SITE']['MEDIAPATH'];

 $tpl_content->addvar("UPLOADPATH", "../".$uploadpath);

 if(isset($_REQUEST['edit'])) # vorhandenes Bild wird bearbeite => Daten des Bildes laden
 {
   $_REQUEST = array_merge($_REQUEST, $db->fetch1("select * from img where ID_IMG=".$_REQUEST['edit']));
   $_REQUEST['NAME_'] = GetUsername($_REQUEST['FK_USER']);
 }
 elseif(!$_REQUEST['NAME_']) # Name des Besitzers nicht von letztem Speichern übergeben => lade eingeloggten User als Besitzer
  $_REQUEST['NAME_'] = $user['NAME'];
 
 $tpl_content->addvars($_REQUEST);

 $ar_dirs = get_dirs($up_dir = "../" . $uploadpath."/".$imagepath);

 for($i=0; $i<count($ar_dirs); $i++)
 {
   $sel="";
   if($_POST['DIR'] == $ar_dirs[$i])
     $sel = " selected";
   $dirs .= '<option value="'.$ar_dirs[$i].'"'.$sel.'>'.$ar_dirs[$i].'</option>';
 }
 
 $tpl_content->addvar("DIRS", $dirs);
 
 if($_FILES['BILD']['tmp_name'])
 {
	$replace = strpos($_FILES['BILD']['name'], "~");
	if ($replace)
	{
		$_FILES['BILD']['name'] = str_replace("~", "_", $_FILES['BILD']['name']);
	}
   include_once "sys/lib.media.php";
   $f_name = false;
   if(!empty($_POST['ID_IMG']))
     $f_name = true;

   $ar_opt = $db->fetch1("select * from bildformat where ID_BILDFORMAT=".$_POST['FK_BILDFORMAT']);
   
   if($ar_opt['LABEL'] == "Bildergenerator")
     $_POST['DIR'] = "bildergenerator/vorlagen";
   elseif($_POST['DIR'] == "")
     $err[] = "Sie dürfen nicht in das Verzeichnis ../".$uploadpath."/ hochladen. Bitte wählen Sie ein Unterverzeichnis!";

   if(!file_exists("../".$uploadpath."/".$_POST['DIR']))
     $err[] = "Das angegebene Verzeichnis existiert nicht. Erstellen Sie es zuerst oder geben Sie ein anderes Verzeichnis an.";

   if(empty($_POST['FK_AUTOR']))
   {
  	 if(empty($_POST['NAME_']))
     {
  	   $_POST['NAME_'] = $user['NAME'];
 	     $ownerid = $uid;
     }
  	 else
  	 {
  	   $ownerid = GetUserID($_POST['NAME_']);
  	   if($ownerid <= 0)
    		 $err[] = "Benutzer nicht gefunden!";
  	 }
   }  
   else
     $ownerid = $_POST['FK_AUTOR'];  # nach Auswahl durch Popup

   if(!count($err)) 
   {
   	$handle = handle_img($_FILES['BILD'],$ar_opt,$_POST,$f_name,true); 
	
    if(!$handle)
	   $err[] = "Fehler beim Bildimport!";

    if(!count($err))
    {
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
		 if ($_REQUEST['IMG_TEXT'] == "")
		 {
			$ar['IMG_TEXT'] = NULL;
		 }
		 else
		 {
		  $ar['IMG_TEXT'] = $_REQUEST['IMG_TEXT'];
		 }
  	 $id = $db->update("img", $ar); 
  	 if(!$_REQUEST['frompopup'])
  	   forward("index.php?page=bildupload&frame=".$tpl_content->vars['curframe']."&edit=".$id."&id=".$id);
     else
       opener_refresh();
    }
   }
 }
 elseif(count($_POST)) # nach Bearbeiten des Bildes
 {
   if(!empty($_POST['ID_IMG']))
   {
  	 $db->update("img", $_POST);
  	 if (!$_REQUEST['frompopup'])
  		forward("index.php?page=bildupload&edit=".$_POST['ID_IMG']."&id=".$_POST['ID_IMG']);
     else
       opener_refresh();
   }
   else
     $err[] = "Keine Datei hochgeladen!"; 
 }
 
 $tpl_content->addvar("frompopup",$_REQUEST['frompopup']);
 
 if (!$_REQUEST['DIR'])
 {
  $tpl_content->addvar('DIR',$imagepath);
 }

 if (!$_REQUEST['FK_BILDFORMAT'])
   $tpl_content->addvar("FK_BILDFORMAT", GetModuleValue("galerie","FK_BILDFORMAT"));

 if(count($err))
    $tpl_content->addvar("err", implode("<br />", $err));
 
 if(!empty($_REQUEST['FK_USER']))
   $tpl_content->addvar("NAME_",GetUserName($_REQUEST['FK_USER']));

 $tpl_content->addvar("MEDIADATENBANK","1");
 
 if($ar_opt['LABEL'] == "Bildergenerator")
   include("tpl/bilder_index_erstellen.php");

?>
