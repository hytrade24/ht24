<?php
/* ###VERSIONSBLOCKINLCUDE### */


$tpl_content->addvar('frame_'. $str_frame, 1);
$id = (int)$_REQUEST['ID_IMG'];
if (count($_POST))
{
  if (!$nar_systemsettings['sys_MAXIMGFILESIZE'])
    $nar_systemsettings['sys_MAXIMGFILESIZE'] = 81920;
  $err = array ();
  if (!($_POST['ALT'] = trim($_POST['ALT'])))
    $err[] = 'Alternativtext fehlt!';
  if (!$id && !count($_FILES))
    $err[] = 'Keine Date hochgeladen';
  else
  {
    $data = array (
      'ALT'=>$_POST['ALT'],
      'TITLE'=>(($title = trim($_POST['TITLE'])) ? $title : NULL)
    );
    if ($id)
      $data['ID_IMG'] = $id;
    else
    {
      $f = reset($_FILES);
#echo ht(dump($f));
      $ar_size = @getimagesize($f['tmp_name']);
#echo ht(dump($ar_size));
      if ($f['error'] || !file_exists($f['tmp_name']))
        $err[] = 'Fehler beim Upload';
      else
      {
        if ($ar_size[2]<1 || $ar_size[2]>3)
          $err[] = 'Unzul&auml;ssiger Dateityp.';
        if ($f['size'] > $nar_systemsettings['sys_MAXIMGFILESIZE'])
          $err[] = 'Datei zu gro&szlig;';
      }
      if (!count($err))
      {
        $fk_user = (1==$user['FK_GROUP'] ? 0:$uid); #xxx todo: FK_GROUP==1 ==> in_array (1, $roles)
        $s_fn =  uniqid("img/$fk_user/"). '.'.
          preg_replace('/[^a-z0-9_.[]!~-]/i', '', $f['name']);
        if (!file_exists("img/$fk_user"))
        {
          mkdir("img/$fk_user");
          chmod("img/$fk_user", 0777);
        }
#die(dump($s_fn));
        if (!move_uploaded_file($f['tmp_name'], $s_fn))
          $err[] = 'Fehler beim Verschieben';
        else
        {
          chmod($s_fn, 0666);
          $data['FK_USER']=$fk_user;
          $data['OK'] = 3;
          $data['SRC'] = $s_fn;
          $data['WIDTH'] = $ar_size[0];
          $data['HEIGHT'] = $ar_size[1];
        }
      }
    }
  }
  if (!count($err))
  {
    $lastresult = $db->update('img', $data);
    if (!$lastresult['rsrc'])
    {
      if ($s_fn)
        unlink($s_fn);
      $err[] = 'Fehler beim Eintragen in die Datenbank';
    }
  }
}
$data = ($id
  ? $db->fetch1("select * from img where ID_IMG=$id")
  : $db->fetch_blank('img')
);
$tpl_content->addvar('parent_modal', !$id);
if (count($err))
{
  $data = array_merge($data, $_POST);
  $tpl_content->addvar('err', implode('<br />', $err));
}
else
{
  if (count($_POST))
    $tpl_content->addvar('done', 1);
}
$tpl_content->addvars($data);
?>