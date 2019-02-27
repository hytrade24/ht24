<?php
/* ###VERSIONSBLOCKINLCUDE### */


  require_once 'banner_inc.php';
  register('img', 'dir', 'trgfile', 'mime', 'savedb');
  register_array('color', 'string', 'fontfile', 'fontsize', 'ofsx', 'ofsy');
  $mime = "png";
  $image = ($dir && strlen($img) ? $images[$dir][$img] : NULL);
  if ($image) 
  {
    /*if (!$mime)
    {
      header('content-type:image/'. $image['type']);
      $fp = fopen("$path_source/".$image['path'], 'rb');
      fpassthru($fp);
      fclose($fp);
      die();
    }
    else
    {die("hi"); */
      eval ("\$im = imagecreatefrom".$image[type]."('".$path_source."/".$image[path]."');");
      if ($im)
      {
$anz_layer = count($fontfile);
for($i=0; $i<$anz_layer; $i++)
{
        if (!$color[$i]) $color[$i]='000000';
/** /
        preg_match('/^(\x{2})(\x{2})(\x{2})$/', $color, $ar);
        $r = hexdec($ar[1]);
        $g = hexdec($ar[2]);
        $b = hexdec($ar[3]);
/*/
        sscanf($color[$i], '%2x%2x%2x', $r,$g,$b);

        $textcolor = imagecolorexact($im, $r,$g,$b);
        if (!(float)$fontsize[$i]) $fontsize[$i] = 20;
        if (0>$textcolor)
        {
          $textcolor = imagecolorallocate($im, $r,$g,$b);
          if(0>$textcolor)
            $textcolor = imagecolorclosest($im, $r,$g,$b);
        }
#        imagestring($im, $fontsize, $ofsx,$ofsy, $string, $textcolor);
        imagettftext($im, $fontsize[$i], $angle=0, $ofsx[$i], $ofsy[$i], $textcolor,
          "$path_fonts/$fontfile[$i].ttf", $string[$i]);
}
        header('content-type:image/'. $mime);
        eval('image'.$mime.'($im);');
        
      }
  }
  else
  {
    echo "&nbsp;no template image selected";
  }
  
  if ($trgfile && $savedb && strlen($img))
  {
		  $trgfile = strtolower($trgfile);
		  if(preg_match("/^[a-z0-9_-]*$/", $trgfile))
		  {
		  
		  $anz_layer = count($fontfile);

		  $db->querynow("delete from bannergenerator where name='".$trgfile."'");
		  
		  $id_img = $db->fetch_atom("select ID_IMG from img where SRC='uploads/bildergenerator/fertige/".$trgfile.".".$mime."'");
		  $insdate = date('Y-m-d H:i');
		  
		  $ar_dbimg= array();
		  if($id_img) $ar_dbimg['ID_IMG'] = $id_img; 
		  $ar_dbimg['WIDTH'] = substr($dir, 0, strpos($dir,'x'));
		  $ar_dbimg['HEIGHT'] = substr(strstr($dir, 'x'),1);
		  $ar_dbimg['FK_USER'] = $uid;
		  $ar_dbimg['FK_USER'] = 0;
		  $ar_dbimg['OK'] = 1;
		  $ar_dbimg['SRC'] = "uploads/bildergenerator/fertige/".$trgfile.".".$mime;
		  $ar_dbimg['SRC_T'] = $GLOBALS['nar_systemsettings']['SITE']['PATH_UPLOADS']."/bildergenerator/fertige/thumb_".$trgfile.".".$mime;
		  $ar_dbimg['WIDTH_T'] = 50;
		  $ar_dbimg['HEIGHT_T'] = 50;
		  $ar_dbimg['ALT'] = $ar['TITLE'] = "Gespeichert mit Bannergenerator am ".$insdate;
	   	  $ar_dbimg['DATUM'] = $insdate;
			 
		  $db->update("img", $ar_dbimg); 
			  
		  for($i=0; $i<$anz_layer; $i++)
		  {
            $db->querynow("insert into bannergenerator (name, img, dir, filename, layer, ofsx, ofsy, fontfile, fontsize, fontcolor, string)
						   values ('".$trgfile."','".$img."','".$dir."','uploads/bildergenerator/vorlagen/".$images[$dir][$img]['path']."','".$i."','".$ofsx[$i]."','".$ofsy[$i]."','".$fontfile[$i]."','".$fontsize[$i]."','".$color[$i]."','".$string[$i]."')");
		  }
		  
          #nur wenn bild als Datei gespeichert werden soll
		  #eval("image$mime(\$im,'$path_target/$trgfile.$mime');"); # Wenn Bild nicht in MedienDB eingetragen wird
		  eval("image$mime(\$im,'../uploads/bildergenerator/fertige/$trgfile.$mime');"); # Bild in MedienDB eintragen (zwischenspeichern)
		  
          include "sys/lib.media.php";
          $ar_opt = $db->fetch1("select * from bildformat where LABEL='Bildergenerator'");
          image_resize('../'.$GLOBALS['nar_systemsettings']['SITE']['PATH_UPLOADS'].'/bildergenerator/fertige/'.$trgfile.'.'.$mime, $ar_opt, $path_target.'/', true);
          unset($_SESSION['savedb']);
		  }
		  
  }

 ?>