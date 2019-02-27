<?php
/* ###VERSIONSBLOCKINLCUDE### */

 
 function reloadpage($more="")
 {
  GLOBAL $id,$_POST;
  forward("index.php?page=modul_galerie_details&id=".$id."&FK_BILDFORMAT=".$_POST['FK_BILDFORMAT']."&NAME_=".$_POST['NAME_']."&OK=".$_POST['OK'].$more);
 }

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

 $tpl_content->addvars($ar_gal = $db->fetch1("select *,ID_GALERIE as FK_GALERIE from galerie where ID_GALERIE=".$_REQUEST['id']));

 $uploadpath = $nar_systemsettings['SITE']['PATH_UPLOADS'];
 $imagepath = $nar_systemsettings['GALERIE']['IMAGEPATH'];

 $tpl_content->addvar("UPLOADPATH", "../".$uploadpath);

 if($_REQUEST['SAVED'])
   $tpl_content->addvar("hinweis","Das Bild wurde gespeichert.");
 elseif($_REQUEST['DELETED'])
   $tpl_content->addvar("hinweis","Das Bild wurde gelöscht.");
 elseif($_REQUEST['CHANGED'])
   $tpl_content->addvar("hinweis","Die Änderungen wurden gespeichert.");
 
 $id = $_REQUEST['id'];  
 $tpl_content->addvar("id", $id); 
 
 if(isset($_GET['edit'])) # vorhandenes Bild wird bearbeite => Daten des Bildes laden
 {
   $_REQUEST = array_merge($_REQUEST, $db->fetch1("select * from img where ID_IMG=".$_GET['edit']));
   $_REQUEST['NAME_'] = GetUsername($_REQUEST['FK_USER']);
 }
 elseif(!$_REQUEST['NAME_']) # Name des Besitzers nicht von letztem Speichern übergeben => lade Defaultbesitzer der Galerie
  $_REQUEST['NAME_'] = GetUsername($ar_gal['FK_USER']);

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
 
 $id = $_REQUEST['id'];  
 $tpl_content->addvar("id", $id); 
  
 if(isset($_GET['del']))
 {
   $src = $db->fetch1("select SRC,SRC_T from img where ID_IMG=".$_GET['del']); 
   $unlink = @unlink("../".$src['SRC']);
   $unlink = @unlink("../".$src['SRC_T']);
   $db->querynow("delete from img where ID_IMG=".$_GET['del']);
   reloadpage("&DELETED=1");
 }
 
 if($_FILES['BILD']['tmp_name'])
 {
   include_once "sys/lib.media.php";
   $f_name = false;
   if(!empty($_POST['ID_IMG']))
     $f_name = true;

   $ar_opt = $db->fetch1("select * from bildformat where ID_BILDFORMAT=".$_POST['FK_BILDFORMAT']);

   $handle = handle_img($_FILES['BILD'],$ar_opt,$_POST,$f_name,true);
   if(empty($_POST['FK_AUTOR']))
   {
  	 if(empty($_POST['NAME_']))
     {
  	   $_POST['NAME_'] = $user['NAME'];
 	     $_POST['FK_USER'] = $uid;
     }
  	 else
  	 {
  	   $idu = GetUserID($_POST['NAME_']);
  	   if($idu <= 0)
  		 $err[] = "Benutzer nicht gefunden!";
  	   else
  	     $_POST['FK_USER'] = $idu;
  	 }
   }  
   else
     $_POST['FK_USER'] = $_POST['FK_AUTOR'];  # nach Auswahl durch Popup

   if(empty($_POST['DIR']) || !is_dir("../" . $uploadpath . "/".$_POST['DIR']))
     $err[] = "Kein gültiges Verzeichnis angegeben!";
   if(empty($err))
   {   
	   if(!$handle)
  		 $err[] = "Bild konnte nicht erzeugt werden!";
	   else
	   {   
  		 $ar=array();
  		 $ar['ID_IMG'] = ($_REQUEST['ID_IMG'] ? $_REQUEST['ID_IMG'] : NULL);
  		 $ar['WIDTH'] = $handle['IMG']['width'];
  		 $ar['HEIGHT'] = $handle['IMG']['height'];
  		 $ar['FK_USER'] = $_POST['FK_USER'];
  		 $ar['FK_GALERIE'] = $id;
  		 $ar['OK'] = ($_POST['OK'] ? 1 : 0);
  		 $ar['SRC'] = $handle['IMG']['file'];
  		 $ar['SRC_T'] = ($handle['THUMB'] ? $handle['THUMB']['file'] : NULL); 
  		 $ar['WIDTH_T'] = ($handle['THUMB'] ? $handle['THUMB']['width'] : NULL);
  		 $ar['HEIGHT_T'] = ($handle['THUMB'] ? $handle['THUMB']['height'] : NULL);	
  		 $ar['ALT'] = $ar['TITLE'] = $_POST['ALT'];
  		 $ar['DATUM'] =  date('Y-m-d H:i');
  		 $db->update("img", $ar); 
       reloadpage("&SAVED=1");
	   }   
   }
 }
 elseif(count($_POST)) # nach Bearbeiten des Bildes
 {
   if(!empty($_POST['ID_IMG']))
   {
  	 $_POST['OK'] = ($_POST['OK'] ? 1 : NULL);
	   $db->update("img", $_POST);
     reloadpage("&CHANGED=1");
   }
   else
     $err[] = "Keine Datei hochgeladen!"; 
 } 
 
 if(count($err))
    $tpl_content->addvar("err", implode("<br />", $err));
 
 $ar_img = $db->fetch_table("select * from img where FK_GALERIE=".$id." ORDER BY DATUM DESC");
 $k=1;
 $tmp=array();
 for($i=0; $i<$all=count($ar_img); $i++)
 {
   if($k==($ar_gal['IMG_ROW']+1))
     $k=1;
   $tpl_tmp = new Template("tpl/de/modul_galerie_details.row.htm");
   $tpl_tmp->addvar("k", $k);
   $ar_img[$i]['NAME'] = GetUserName($ar_img[$i]['FK_USER']);
   $tpl_tmp->addvars($ar_img[$i]);      
   if($i == $all-1)
     $tpl_tmp->addvar("end", 1);
   $tmp[] = $tpl_tmp;
   $k++;
 }
 
 $tpl_content->addvar("bilder", $tmp); 
 
 if (!$_REQUEST['DIR'])
 {
  $tpl_content->addvar('DIR',$imagepath);
 }

 if (!$_REQUEST['OK'])
   $tpl_content->addvar("OK", GetModuleValue("galerie","MOD"));

 if (!$_REQUEST['FK_BILDFORMAT'])
   $tpl_content->addvar("FK_BILDFORMAT", GetModuleValue("galerie","FK_BILDFORMAT"));

?>