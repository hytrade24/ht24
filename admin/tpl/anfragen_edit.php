<?php
/* ###VERSIONSBLOCKINLCUDE### */


$id = (int)$_REQUEST['id'];

if ($_REQUEST['do']=='rm' and !empty($id))
{
  $db->querynow('delete from `anfrage` where ID_ANFRAGE='. $id);
  forward('index.php?page=anfragen');
}

if (count($_POST))
{
  $db->update('anfrage', $_POST);
  forward('index.php?page=anfragen');
}
$data = $db->fetch1("SELECT * FROM anfrage where ID_ANFRAGE=". $id);
#echo ht(dump($data));
$tpl_content->addvars($data);
?>