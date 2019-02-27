<?php
/* ###VERSIONSBLOCKINLCUDE### */



require_once 'sys/lib.banner.php';
  register('dir', 'img');
$images=get_vorlagen();

//echo ht(dump($images));
  #if (!($fmt = $_GET['fmt']))
    #$fmt = $dir;
  $curimg = (strlen($img) && $dir==$fmt ? $img : -1);

//Verzeichniss erstellen
  uksort($images, 'nxncmp');
  foreach($images as $str_format=>$ar_images)
    $output_formats .= '
  <li><a href="index.php?page=vorlagebilder&fmt='.$str_format.'">'.
      $str_format. '</a> ('. count($ar_images). ' Bilder)</li>';
$tpl_content->addvar('list_formats', $output_formats);
//ende 

  if ($fmt && $images[$fmt] && count($images[$fmt]) && $fmt != "db")
  {
    foreach($images[$fmt] as $i=>$file)
    {
	
      $fn = stdHtmlentities($file['path']);
      $url = rawurlencode($file['path']);
	  
	  $vorlage_check = $db->fetch1("select ID from bannergenerator where dir='".$fmt."' and img=".$i); // Für Löschbutton überprüfen, ob Vorlage genutzt wird
	  if($vorlage_check)
		  $template_images .= '<a href="index.php?page=texte_bearbeiten&fmt='. $fmt. '&dir='. $fmt. '&img='. $i. '&path='.$fn.'"><img id="img'. $i.
			'" src="'. $path_source.'/'.$url.
			'" title="'. $fn . ','.$file[bits].' Bits"></a>  <a onClick="return confirm(\'Diese Vorlage wirklich löschen?\n Sie haben Bilder gespeichert, welche diese Vorlage benutzen. Diese Bilder werden beim Löschen ebenfalls entfernt!\')"  href="index.php?page=vorlagebilder&rm=phys&fn='.$fn.'&fmt='.$fmt.'&img='.$i.'"><img src="gfx/btn.del.gif" /></a><br /><br />'."\n";
      else
	    $template_images .= '####<a href="index.php?page=texte_bearbeiten&path='.$fn.'"><img id="img'. $i.
			'"  src="'. $path_source.'/'.$url.
			'" title="'. $fn . ''.$file[bits].' Bits"></a>  <a  href="index.php?page=vorlagebilder&rm=phys&fn='.$fn.'" onClick="return confirm(\'Diese Vorlage wirklich löschen?\')"><img src="gfx/btn.del.gif" /></a><br /><br />'."\n";

	
	}
	$tpl_content->addvar('template_images', $template_images);
  }
  
  if($_GET['rm'] == 'phys' && $_GET['fn']) // Vorlage löschen
  { 
    @unlink("$path_source/$_GET[fn]"); // Physikalische Vorlage löschen
	
    # Nur, wenn Ergebnisbild auch aus Medien Datenbank gelöscht werden soll:
	$db->querynow("delete from img where src='uploads/bildergenerator/images/".$_GET['fn']."'"); // Aus Mediendatenbank entfernen

	if($_GET['fmt'] && $_GET['img'])
	  $db->querynow("delete from bannergenerator where img=".$_GET['img']." and dir='".$_GET['fmt']."'"); // DB Bilder löschen, welche diese Vorlage verwenden

	//include("tpl/bilder_index_erstellen.php");
	forward("index.php?page=vorlagebilder&rm=done&fn=".$_GET['fn']);
  }
  
  if($_GET['rm'] == "done" && $_GET[fn])
    $tpl_content->addvar("deleted", "Bild <i>".$_GET[fn]."</i> wurde erfolgreich gelöscht.");


?>