<?php
/* ###VERSIONSBLOCKINLCUDE### */


$id = (int)$_REQUEST['ID_GUTSCHRIFT'];

if ($id > 0)
	$fkuser = $db->fetch_atom("SELECT FK_USER FROM gutschrift where ID_GUTSCHRIFT=". $id);
else
	$fkuser = (int)$_REQUEST['ID_USER'];

$tpl_content->addvar("ID_USER",$fkuser);

if (count($_POST))
{
  foreach($_POST as $k=>$v) if (strtoupper($k)==$k)
    $_POST[$k] = trim($v);
		
  $err = $msg = array ();
	
	#die(ht(dump($_POST)).'<hr>'.ht(dump($msg)));

  if (count($err))
  {
    $tpl_content->addvars($_POST);
    $tpl_content->addvar('err', implode('<br />', $err));
    $err = array ();
  }
  else
  {
		$_POST['BESTELLDATUM'] = $_POST['BESTELLDATUM_y'] . "-" . $_POST['BESTELLDATUM_m'] . "-" . $_POST['BESTELLDATUM_d'];
		$_POST['BEARBEITET'] = $_POST['BEARBEITET_y'] . "-" . $_POST['BEARBEITET_m'] . "-" . $_POST['BEARBEITET_d'];
		$_POST['BETRAG'] = str_replace(",",".",$_POST['BETRAG']);

		if ($_POST['STATUS'] == 1) // Betrag frei
		{
			$_POST['STORNIERT'] = 0;
			$_POST['BETRAGFREI'] = $_POST['BETRAG'];
			$_POST['BETRAGWARTEND'] = 0;
		}
		elseif ($_POST['STATUS'] == 0) // Betrag in Bearbeitung
		{
			$_POST['STORNIERT'] = 0;
			$_POST['BETRAGFREI'] = 0;
			$_POST['BETRAGWARTEND'] = $_POST['BETRAG'];
		}
		else // Storno
			$_POST['STORNIERT'] = 1;
			
    $id = $db->update('gutschrift', $_POST);
    forward('index.php?page=user_prov&ID_USER='.$fkuser);
  }
}

$data = ($id
  ? $db->fetch1($db->lang_select('gutschrift'). "where ID_GUTSCHRIFT=". $id)
  : $db->fetch_blank('gutschrift')
);
if (count($_POST))
  $data = array_merge($data, $_POST);

if ($data['STORNIERT'] == 1)
{
	$data['STATUS'] = 2;
	if ($data['BETRAGFREI'] > 0)
		$data['BETRAG'] = $data['BETRAGFREI'];
	else
		$data['BETRAG'] = $data['BETRAGWARTEND'];

}
elseif ($data['BETRAGFREI'] > 0)
{
	$data['BETRAG'] = $data['BETRAGFREI'];
	$data['STATUS'] = 1;
}
else
{
	$data['BETRAG'] = $data['BETRAGWARTEND'];
	$data['STATUS'] = 0;
}

$tpl_content->addvars($data);
#die(ht(dump($data)));
?>