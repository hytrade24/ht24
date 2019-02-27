<?php
/* ###VERSIONSBLOCKINLCUDE### */



 function imagenewsize($ar, $wi, $he)
 {
  $re=array ();
  if($ar[0] > $wi)
  {
   $teil = $ar[0]/$wi;
   $re[0] = $wi;
   $re[1] = round($ar[1]/$teil);
  }
  else
  {
   $re[0]=$ar[0];
   $re[1]=$ar[1];
  }
  if($re[1] > $he)
  {
   $teil = $re[1]/$he;
   $re[1] = $he;
   $re[0] = round($re[0]/$teil);
  }  
  return $re;
 }

 function image_resize($file, $opt, $target=false, $thumb=false)
 {
  //echo $target."<hr />";
  $hack = explode ("/", $file);
  $filename=$hack[(count($hack)-1)];
  if(!$target)
    $target = ($thumb ? "thumb_" : '').$filename;
  else
    $target = $target.($thumb ? "thumb_" : '').$filename;
 # echo ht(dump($opt));
  if(!$opt['MAX_B'] && !$opt['MAX_H'] && !$thumb)
  {
#echo $target; die();
	$size = getimagesize($file);
	copy ($file, $target);
	return array ("file" => str_replace("../", "", $target), "width" => $size[0], "height" => $size[1]);
  }
  else
  {
   $width  = ($thumb ? $opt['MAX_TB'] : $opt['MAX_B']);
   $height = ($thumb ? $opt['MAX_TH'] : $opt['MAX_H']);
   #echo $file;
   $org = getimagesize($file);
   $dm = imagenewsize($org,$width,$height);
#echo ht(dump($dm));
   $gd = gd_info();
   switch($org[2])
   {
    case 1:
	  if($gd['GIF Create Support'])
	    $fkt = 'imagecreatefromgif';
	  else
	    return false;
    break;
	case 2:
	  $fkt = 'imagecreatefromjpeg';
	break;
	case 3:
	  $fkt = 'imagecreatefrompng';
	break;
	default: return false;
   }
#echo ht(dump($opt));
   if($opt['T_QUADRAT'])
   {
#echo "HOHO";
	 $left=$top=0;
     if($dm[0] < $width)
       $left = round(($width-$dm[0])/2);
     if($dm[1] < $height)
       $top = round(($height-$dm[1])/2);
   }
   else
   {
     $width  = $dm[0];
	 $height = $dm[1];
   }
   
   $im = @imagecreatetruecolor($width,$height);
   if(!$im)
     return false;
   
   if($opt['T_QUADRAT'])
   {
     $color = $GLOBALS['db']->fetch_atom("select VALUE from lookup where ID_LOOKUP=".$opt['LU_RGBFARBE']);
	 $hack = explode(".", $color);
	 $color = @imageColorAllocate($im, $hack[0],$hack[1],$hack[2]);
     @imagefill($im,0,0,$color);
   }
#echo $target;   
   $m=@imagecopyresized ($im, $fkt($file), $left, $top, 0, 0, $dm[0], $dm[1], $org[0], $org[1] );
   if(!$m)
     return false;
   $c=imagejpeg($im, $target, 100);
   #echo "<p>".$target."</p>";
   chmod($target, 0777);
   if(!$c)
     return false;
   @imagedestroy($im);
   $find = explode("/", $file);
   $count = count($find)-1;
   $file = $find[$count];
   return array ("file" => str_replace("../", "", $target), "width" => $width, "height" => $height);
  }

 }

 function handle_img($file,$opt,$ar_more,$filename=false,$admin=false,$import=false)
 {
   $path_tmp = ($admin ? "../" : "").$GLOBALS['nar_systemsettings']['SITE']['PATH_UPLOADS']."/";
   if(!$import)
   {
     $up = fupload($file, $path_tmp, 'f', true);
     if(is_array($up))
	 {
       $GLOBALS['tpl_content']->addvar("err", implode("<br />", $up));
	   return false;
	 }
   }
   else
   {
     $up = $file; 
   }
   if(!$up)
    return false;
   else
   {
	 $path_target = $path_thumb = $path_tmp.$ar_more['DIR']."/";
#echo $path_target."<br />";
	 if($filename)
     {
       $ar_f = $GLOBALS['db']->fetch1("select * from img where ID_IMG=".$_REQUEST['ID_IMG']);
	   $path_target = ($admin ? "../" : "" ).$ar_f['SRC'];
	   $path_thumb =  ($admin ? "../" : "" ).$ar_f['SRC_T'];	   
	 }	 
	 // "merken" was quadratisiert wird
	 $q_thumb = $q_img = false;
	 if($opt['T_QUADRAT'] == 1)
	   $q_thumb=1;
	 if($opt['T_QUADRAT'] == 2)
	 {
       $q_thumb = $q_img = true;
	 }
     $opt['T_QUADRAT'] = $q_img;
	 $full_image = image_resize((!$import ? $path_tmp."/" : '' ).$up, $opt, $path_target);
	 if(!$full_image)
	 {
	   @unlink((!$import ? $path_tmp."/" : '' ).$up);
	   return false;
	 }
	 if($opt['B_THUMB'])
	 {
       $opt['T_QUADRAT'] = $q_thumb;
	   $thumbnail = image_resize((!$import ? $path_tmp."/" : '' ).$up, $opt, $path_target, true);	
	   if(!$thumbnail)
	   {
	     @unlink((!$import ? $path_tmp."/" : '' ).$up);
		 return false; 
	   }
	 }
	 @unlink((!$import ? $path_tmp."/" : '' ).$up);
	 #echo ht(dump(array("IMG" => $full_image, "THUMB" => ($thumbnail ? $thumbnail : NULL))));die();
	 return array("IMG" => $full_image, "THUMB" => ($thumbnail ? $thumbnail : NULL));
   }
 }

 //von jan
 //$ar_img - array aus post $_files
 //$path - wo solls gespeichert werden
 //$mpdule - zu welchem modul gehört das img
 //$label - label
 function upload_image($ar_img, $path, $module, $label = NULL, $fk_anzeige = NULL)
 {
 		$err = 0;
		if (!$ar_opt = $GLOBALS['db']->fetch1("select * from bildformat where LABEL = '".$module."'"))
			$ar_opt = $GLOBALS['db']->fetch1("select * from bildformat where  ID_BILDFORMAT = 9");
		
		$test = array('DIR' => $path);
		$handle = handle_img($ar_img,$ar_opt,$test,false,false);
		if(!$handle)
			$err = 1;
		
		if (!$err)
		{
			$ar=array();
			$ar['ID_IMG'] = ($_REQUEST['ID_IMG'] ? $_REQUEST['ID_IMG'] : NULL);
			$ar['WIDTH'] = $handle['IMG']['width'];
			$ar['HEIGHT'] = $handle['IMG']['height'];
			$ar['FK_USER'] = $GLOBALS['uid'];
			$ar['FK_GALERIE'] = ($ar_opt['ID_BILDFORMAT'] ? $ar_opt['ID_BILDFORMAT'] : NULL);
			$	 
			$ar['OK'] =1;
			$ar['SRC'] = $handle['IMG']['file'];
			$ar['FK_OWNER'] = $GLOBALS['uid'];
			$ar['SRC_T'] = ($handle['THUMB'] ? $handle['THUMB']['file'] : NULL); 
			$ar['WIDTH_T'] = ($handle['THUMB'] ? $handle['THUMB']['width'] : NULL);
			$ar['HEIGHT_T'] = ($handle['THUMB'] ? $handle['THUMB']['height'] : NULL);	
			$ar['ALT'] = $ar['TITLE'] = $label;
			$ar['DATUM'] = date('Y-m-d H:i');
			$ar['MODUL'] = $module;
			$ar['FK_ANZEIGE'] = $fk_anzeige;
			
			return $GLOBALS['db']->update("img", $ar);
		}
	}
?>
