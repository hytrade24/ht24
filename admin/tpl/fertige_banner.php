<?php
/* ###VERSIONSBLOCKINLCUDE### */


require_once 'sys/lib.banner.php';

  if ('rm'==$_GET['do']) # Bild löschen...
  {
    $name = substr($_GET['fn'], 0, -4);
	$rmfile = @unlink("$path_target/$_GET[fn]"); # Physikalische Datei entfernen
	@unlink($path_target.'/thumb_'.$_GET[fn]); # Eventuelles Thumbnail entfernen
	#$rmdb = $db->querynow("delete from bannergenerator where name='".$name."'"); # Datenbankeintrag entfernen
	$rmmedien = $db->querynow("delete from img where SRC='uploads/bildergenerator/fertige/".$_GET['fn']."'"); # Aus Mediendatenbank entfernen
    if (rmfile && rmdb && rmmedien)
      $tpl_content->addvar("geloescht", "<i>".$path_target."/".$_GET['fn']."</i> gel&ouml;scht.<br>");
    
  }
	  
  $dp = @opendir($path_target);
  $ar_images = array();

  while ($fn = @readdir($dp))
  {
    if (preg_match('/\.(gif|jpeg|png)$/', $fn) && !strstr($fn, "thumb_"))
    {
      list($w,$h,$typ) = getimagesize("$path_target/$fn");
      $ar_images["{$w}x{$h}"][] = $fn;
    }
  }
  uksort($ar_images, 'nxncmp');
  foreach($ar_images as $fmt=>$files)
  {
    $output_images .= '
      <h1>'. $fmt. '</h1>';
    sort($files);
    foreach($files as $fn)
      $output_images .= '
        <img src="'. "$path_target/$fn". '" alt="'. stdHtmlentities($fn). '"><br>'.
        stdHtmlentities($fn). '<a href="index.php?page=fertige_banner&do=rm&fn='. urlencode($fn). '"
        onClick="return confirm(\'Sind Sie sicher, dass dies Banner nicht mehr benötigt wird?\');"><img
        src="gfx/btn.del.gif" width="32" height="16" alt="Bild l&ouml;schen" border="0"></a><br>';
$tpl_content->addvar('output_images', $output_images);
  }
?>
