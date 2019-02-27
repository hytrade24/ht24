<?php
/* ###VERSIONSBLOCKINLCUDE### */


/*echo "POST :".ht(dump($_POST));
echo "GET: ".ht(dump($_GET));
echo "FILES: ".ht(dump($_FILES));*/
//die();
 if($_FILES['BILD']['tmp_name'])
 {
#die("STOP mal ");
   $_POST['DIR'] = str_replace('../uploads/', '', $_POST['DIR']);
   include "sys/lib.media.php";
   $f_name = false;
   if(!empty($_POST['ID_IMG']))
     $f_name = true;
   $ar_opt = $db->fetch1("select * from bildformat where ID_BILDFORMAT=".$_POST['FK_BILDFORMAT']);
   $handle = handle_img($_FILES['BILD'],$ar_opt,$_POST,$f_name,true);   
   if(!$handle)
     $err[] = "Bild konnte nicht erzeugt werden!";
   else
   {
     $ar=array();
	 $ar['ID_IMG'] = ($_REQUEST['ID_IMG'] ? $_REQUEST['ID_IMG'] : NULL);
	 $ar['WIDTH'] = $handle['IMG']['width'];
	 $ar['HEIGHT'] = $handle['IMG']['height'];
	 $ar['FK_USER'] = $uid;	 
	 $ar['OK'] =1;
	 $ar['SRC'] = $handle['IMG']['file'];
	 $ar['SRC_T'] = ($handle['THUMB'] ? $handle['THUMB']['file'] : NULL); 
	 $ar['WIDTH_T'] = ($handle['THUMB'] ? $handle['THUMB']['width'] : NULL);
	 $ar['HEIGHT_T'] = ($handle['THUMB'] ? $handle['THUMB']['height'] : NULL);	
	 $ar['ALT'] = $ar['TITLE'] = $_POST['ALT'];
	 $ar['DATUM'] = date('Y-m-d H:i');
#echo ht(dump($ar)); 
	 $db->update("img", $ar); 
#echo ht(dump($lastresult)); die();
	 die(forward("index.php?frame=iframe&page=file_browser&dir=".$_REQUEST['DIR']));
   }  
  if(count($err))
    $tpl_content->addvar("err", implode("<br />", $err)); 
 }


function myround($f)
{
  return floor(0.5+$f);
}
if (isset($_REQUEST['newfolder']) && $_REQUEST['newfolder']==1)
{
  $err = array();
  if (!$_REQUEST['NEWFOLDER']=preg_replace("/([^a-z0-9_-])/si", "_", $_REQUEST['NEWFOLDER']))
    $err[] = "Kein Ordnername angegeben";
  else
  {
    umask(0);
	$dir = @mkdir($new = $_REQUEST['dir']."/".$_REQUEST['NEWFOLDER']);
	if (!$dir)
	  $err[] = "Ordner konnte nicht angelegt werden!";
    umask(0);
	$chm = chmod($new, 0777);
  }
#echo $_REQUEST['NEWFOLDER']; die();
  if (count($err))
    $tpl_content->addvar("err", implode("<br />", $err));
  else
    $_REQUEST['dir'] = $new;
}

if (!empty($_GET['image']))
{
 $im = str_replace($nar_systemsettings['SITE']['SITEURL'].'/', '', $_GET['image']);
 $_REQUEST['LOADIMG']="../".$im;
 $fn_image = basename($im);
 $_REQUEST['dir'] = str_replace($fn_image, '', $_REQUEST['LOADIMG']);
}
else
  $fn_image = basename($_REQUEST['LOADIMG']);
if (!isset($_REQUEST['dir']))
  $_REQUEST['dir'] = '../uploads';
if (!strstr($_REQUEST['dir'], "/uploads"))
  die("Wrong Directory!");

if (isset($_REQUEST['LOADIMG']))
{
#echo ht(dump($_REQUEST));
  $tpl_content->addvar('LOADIMG', $_REQUEST['LOADIMG']);
  $org = getimagesize($_REQUEST['LOADIMG']);
  $tpl_content->addvar('org_w', $org_w = $org[0]);
  $tpl_content->addvar('org_h', $org_h = $org[1]);
  if (!empty($_REQUEST['IMGW']) && is_numeric($_REQUEST['IMGW']))
    $org_w = (int)$_REQUEST['IMGW'];
/*
 {
if (1)#   if ($org_w > $_REQUEST['IMGW'])
   {
   $t = $org_w/$_REQUEST['IMGW'];
   $org_h = myround($org_h/$t);
   $org_w=(int)$_REQUEST['IMGW'];
#echo dump($org_h);
   }
   else
     $_REQUEST['IMGW'] = $org_w;
 }
*/
  if (!empty($_REQUEST['IMGH']) && is_numeric($_REQUEST['IMGH']))
    $org_h = (int)$_REQUEST['IMGH'];
/*
 {
if (1)#   if ($org_h > $_REQUEST['IMGH'])
   {
     $t = $org_h/$_REQUEST['IMGH'];
   $org_w = round($org_w/$t);
   $org_h = $_REQUEST['IMGH'];
   }
   else
     $_REQUEST['IMGH']=$org_h;
 }
*/
#echo ht(dump($org_w).dump($org_h));
  $tpl_content->addvar('IMGH', $org_h);
  $tpl_content->addvar('IMGW', $org_w);
}

$ar=$tmp=array ();
if ($_REQUEST['dir'] != "/uploads")
{
  $hack = explode("/", $_REQUEST['dir']);
  array_shift($hack);
  $tmp = "..";
  foreach ($hack as $s_dir)
  {
    $tmp .= "/". $s_dir;
    $ar[] = array ('folder'=>$s_dir, 'link'=>$tmp);
  }
  $tpl_content->addlist("browse", $ar, "tpl/de/file_browser.browse.htm");
}
$tpl_content->addvar('dir', $_REQUEST['dir']);
$ar = $folders = $thumbs = array ();

$dp = opendir($_REQUEST['dir']);
while ($entry = readdir($dp)) if (!preg_match('/^\./', $entry))
{
   if (is_dir($_REQUEST['dir']."/".$entry))
     $folders[] = array ('NAME' => $entry, 'selectable' => NULL,
      'folder' => 1);
   elseif (stristr($entry, "thumb_"))
     $thumbs[] = str_replace("thumb_","", $entry);
   else
     $ar[] = array ('NAME' => $entry,'selectable'=>true, 'is_current'=>$entry==$fn_image);
}
if ($folders)
  $folders[count($folders)-1]['is_last'] = true;

if(count($ar))
{
  for($i=0; $i<count($ar); $i++)
  {
    if(in_array($ar[$i]['NAME'], $thumbs))
	  $ar[$i]['thumb'] = 1;
	else
	  $ar[$i]['thumb'] = 0;
  }
}

$tpl_content->addvar('URL', $nar_systemsettings['SITE']['SITEURL']);

function rowcmp ($a, $b) { return strcasecmp($a['NAME'], $b['NAME']); }


if ($folders)
{
  usort($folders, 'rowcmp');
  $tpl_content->addlist('folders', $folders, 'tpl/de/file_browser.row.htm');
}
if ($ar)
{
  usort($ar, 'rowcmp');
  $tpl_content->addlist('files', $ar, 'tpl/de/file_browser.row.htm');
}

closedir($dp);

#echo $_REQUEST['dir'], ' // ', $_REQUEST['NEWFOLDER'];
$tpl_content->addvar('is_root', '../uploads'==$_REQUEST['dir']);
$tpl_content->addvar('updir', $s_updir = preg_replace('%^(.*)\/[^/]+$%', '$1', $_REQUEST['dir']));
$tpl_content->addvar('parent_is_root', '../uploads'==$s_updir);
/*
$tpl_content->addvar('dir', 
$tpl_content
*/

?>