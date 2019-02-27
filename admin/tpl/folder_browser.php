<?php
/* ###VERSIONSBLOCKINLCUDE### */


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
  $_REQUEST['dir'] = '../'.$nar_systemsettings['SITE']['PATH_UPLOADS'];
if (!strstr($_REQUEST['dir'], "/".$nar_systemsettings['SITE']['PATH_UPLOADS']))
  die("Wrong Directory!");
  
$ar=$tmp=array ();
if ($_REQUEST['dir'] != "../".$nar_systemsettings['SITE']['PATH_UPLOADS'])
{
  //$tpl_content->addvar("dir", $_REQUEST['dir']);
  $hack = explode("/", $_REQUEST['dir']);
  array_shift($hack);
  $tmp = "..";
  foreach ($hack as $s_dir)
  {
    $tmp .= "/". $s_dir;
    $ar[] = array ('folder'=>$s_dir, 'link'=>$tmp);
  }
  $tpl_content->addlist("browse", $ar, "tpl/de/folder_browser.browse.htm");
}
$tpl_content->addvar('dir', $_REQUEST['dir']);
$dir2 = str_replace("../", "", $_REQUEST['dir']);
$dir2 = str_replace($nar_systemsettings['SITE']['PATH_UPLOADS']."/", "", $dir2);

if(strlen($dir2) >= 1 && $_REQUEST['dir'] != "../".$nar_systemsettings['SITE']['PATH_UPLOADS'])
  $tpl_content->addvar("dir2", $dir2);

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
$tpl_content->addvar('is_root', '../'.$nar_systemsettings['SITE']['PATH_UPLOADS']==$_REQUEST['dir']);
$tpl_content->addvar('updir', $s_updir = preg_replace('%^(.*)\/[^/]+$%', '$1', $_REQUEST['dir']));
$tpl_content->addvar('parent_is_root', '../'.$nar_systemsettings['SITE']['PATH_UPLOADS']==$s_updir);

























/* OLD CODE *\
 function get_dirs($start)
 {
   $d = dir($start);
   $ar_dirs = array();
   while (false !== ($entry = $d->read())) 
   {     
	 if(!is_file($start.$entry) && $entry != "." && $entry != "..")
	   $ar_dirs[] = $entry;
   }
   $d->close();
   return $ar_dirs;
 }
 
 $searchdir = "../uploads/";
 
 if($_GET['dir'] != "") 
   $searchdir = "../uploads/".$_GET['dir']."/";
 
 
 #echo $_GET['dir'];
 
 $ar_dirs = get_dirs($searchdir);
 for($i=0; $i<count($ar_dirs); $i++)
 {
   #if($_POST['DIR'] == $ar_dirs[$i])
   $ar_dir[$i] = "<a href=\"index.php?page=bildimport_popup&dir=".$_GET['dir']."/".$ar_dirs[$i]."\">/".$ar_dirs[$i]."/</a><br />";
 }
 
 $rest = substr($_GET['dir'], 0, strrpos($_GET['dir'],"/"));
 #echo $rest;
 $ar_dir[count($ar_dir)] = "<a href=\"index.php?page=bildimport_popup&dir=".$rest."\">..</a>";
 
 $tpl_content->addlist("DIRS", $ar_dir, 'tpl/de/bildimport_popup.row.htm');
 */
?>