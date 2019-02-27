<?php
/* ###VERSIONSBLOCKINLCUDE### */


$id = (int)$_REQUEST['ID_SCRIPT'];
$fk_kat = (int)$_REQUEST['FK_KAT'];
$root = 1;

if (count($_POST))
{
  date_implode($_POST, 'DATUM');
  $err = array ();
  // xxx todo: check post data
  if (!checkdate($_POST['DATUM_m'], $_POST['DATUM_d'], $_POST['DATUM_y']))
    $err[] = 'ung&uuml;ltiges Datum';
#die($_POST['DATUM']);
  if ($_POST['URL_HOME'] && !validate_url('http://'. $_POST['URL_HOME']))
    $err[] = 'Fehler in Home-URL.';
  if ($_POST['URL_DOWNLOAD'] && !validate_url('http://'. $_POST['URL_DOWNLOAD']))
    $err[] = 'Fehler in Download-URL.';

  // speichern
  if (!count($err))
  {
    if (!$id && !$_POST['FK_USER'])
      $_POST['FK_USER'] = $uid;
    $id = $db->update('enzeige', $_POST);
    forward('index.php?page=anzeige_edit&ID_ANZEIGE='. $id);
  }
  else
    $tpl_content->addvar('err', implode('<br />', $err));
}

if ($id)
  $item = $db->fetch1($db->lang_select('anzeige', '*, to_days(curdate())-to_days(DATUM) as age'). ' where ID_ANZEIGE='. $id);
else
  $item = $db->fetch_blank('anzeige');

$tpl_content->addvars(array_merge($item, $_POST));
?>