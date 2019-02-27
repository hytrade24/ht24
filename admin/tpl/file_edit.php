<?php
/* ###VERSIONSBLOCKINLCUDE### */


$id = (int)$_REQUEST['ID_TPLFILE'];
$navid = (int)$_REQUEST['ID_NAV'];
$navrow = $db->fetch1("select * from nav where ID_NAV=". $id);

if (count($_POST))
{
  $err = array ();
  if (!$id)
    if (!preg_match('/^'. preg_quote($navrow['IDENT']). '(\.[a-z_0-9])*\.(php|htm)$/', $_POST['fname']))
      $err[] = 'Bitte geben Sie einen g&uuml;ltigen Dateinamen an!';
    else
      $_POST['FN'] = $_POST['dir']. '/'. $_POST['fname'];
  $_POST['IDENT'] = $navrow['IDENT'];
  if (!count($err))
  {
    $_POST['STAMP_MODIFY'] = date('Y-m-d H:i:s');
    $id = $db->update('tplfile', $_POST);
    forward('index.php?nav='. $id_nav. '&ID_TPLFILE='. $id);
  }
  else
    $tpl_content->addvar('err', implode('<br>', $err));
}

$tpl_content->addvar('ID_NAV', $navid);
if ($id)
  $file = $db->fetch1("select * from tplfile where ID_TPLFILE=$id");
if (!$file)
{
  $file = $db->fetch_blank('tplfile');
  $file['FN'] = $_REQUEST['path'];
}

$file['fdir'] = dirname($file['FN']);
$file['fname'] = basename($file['FN']);
#echo ht(dump($file));echo ht(dump($tpl_content->vars));
$tpl_content->addvars($file);
$tpl_content->addvar('use_ha', $file['B_WYSIWYG'] || (!$id && preg_match('/\.htm$/', $file['fname'])));
#echo $file['B_WYSIWYG'];
/*
  $path = $_REQUEST['path'];
  $dir = dirname($path);
  $filename = basename($path);

  if (count($_POST))
  {
    $msg = array ();
    $path = ($dir=$_POST['dir']).'/'.($filename = $_POST['filename']);
    if (!preg_match('/^[a-z_0-9\.]+$/i', $filename))
      $msg[] = $err_syntaxfn;
    if (false===strpos($filename, '.'))
      $msg[] = $err_noext;

    if (!count($msg))
    {
      if (!$fp = fopen($path, 'w'))
        $msg[] = $err_fopen;
      else
      {
        fputs($fp, $_POST['body']);
        fclose($fp);
      }
    }
    if (count($msg))
      $tpl_content->addvar('msg', implode('<br />', $msg));
    else
      forward('index.php?nav='. $id_nav. '&path='. rawurlencode($path)
        . '&ID_NAV='. $_POST['ID_NAV']);
  }
  else
  {
    if ($body = @file($path))
      $body = implode('', $body);
  }

/*
  $ar_diroptions = array ();
  $dir = opendir('.');
  while ($fn = readdir($dir)) if (is_dir($fn) && ereg('^(tpl)\.', $fn))
    $ar_diroptions[$fn] = '
    <option '. ($fn==$curdir ? 'selected ' : '')
      . 'value="'. stdHtmlentities($fn). '">'. stdHtmlentities($fn). '</option>';
  ksort($ar_diroptions);
  $tpl_content->addvar('diroptions', $ar_diroptions);
*/
  $tpl_content->addvar('dir', $dir);
  $tpl_content->addvar('filename', $filename);
  $tpl_content->addvar('ID_NAV', $_REQUEST['ID_NAV']);
  $tpl_content->addvar('body', $body);
#  $tpl_content->addvar('body', addnoparse($body));
?>