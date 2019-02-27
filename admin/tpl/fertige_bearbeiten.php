<?php
/* ###VERSIONSBLOCKINLCUDE### */



require_once 'sys/lib.banner.php';
// register('dir', 'img');

    # Einträge aus DB auslesen
    $imgs = $db->fetch_table("select name,img,dir,filename from bannergenerator group by name");
	$ar_anz = count($imgs);
	for($i=0; $i<$ar_anz; $i++)
	{
	  if(file_exists($path_target.'/thumb_'.$imgs[$i]['name'].'.png'))
	    $imgs[$i]['thumb'] = 1;
	}
	#echo ht(dump($imgs));
	#echo "<br >"; echo ht(dump($_SESSION));
	$tpl_content->addlist("db_images", $imgs, "tpl/de/fertige_bearbeiten.row.htm" );
  
  if($_GET['rm'] == 'db' && $_GET['name']) // Nur in DB gespeichertes Bild löschen
  {
    if($db->querynow("delete from bannergenerator where name='".$_GET['name']."'")) // löschen
      forward("index.php?page=fertige_bearbeiten&rm=done&fn=".$_GET['name']);
  }
    
  if($_GET['rm'] == "done" && $_GET[fn])
    $tpl_content->addvar("deleted", "Bild <i>".$_GET[fn]."</i> wurde erfolgreich gelöscht.");
    
  $tpl_content->addvar("sess", $sess);

?>