<?php
/* ###VERSIONSBLOCKINLCUDE### */



  $einheit = NULL;
  
  if($_REQUEST['ok'])
    $tpl_content->addvar("ok",1);
  
  if(count($_POST))
  {
    date_implode($_POST, 'FIRST',true);
	
	$err=array();
	if(empty($_POST['DSC']))
	  $err[] = "Bitte eine Beschreibung angeben!";
	if($_POST['FIRST'] <= date('Y-m-d H:i'))
	  $err[] = "Das Startdatum liegt in der Vergangenheit!";
	if(!(int)$_POST['ALL_X'])
	  $err[] = "Die Regelmäßigkeit muss mind. 1 Minute betragen!";
	if(!$_POST['CODE'] && !$_POST['DATEI'])
	  $err[] = "Bitte Datei und/oder Code eingeben!";
	if($_POST['DATEI'])
	{
	  $test = @file_get_contents($datei = $ab_path.$_POST['DATEI']);
	  if(!$test)
	    $err[] = "Die angegebene Datei ".$datei." exitiert nicht!";
	}
	
	if(empty($err))
	{
	  $id = $_POST['ID_CRONTAB'];
	  $id_new = $db->update("crontab", $_POST);
	  if(!$id)
	    $id = $id_new;	  
	  die(forward("index.php?page=crontab_edit&ID_CRONTAB=".$id."&ok=1"));
	}
	else
	{
	  $einheit = $_POST['EINHEIT'];
	  $tpl_content->addvars($_POST);
	  $tpl_content->addvar("PRIO_".$_POST['PRIO'], 1);
	  $tpl_content->addvar("err", implode("<br>", $err));
	} 
  } // post
  else
  {
    if($_REQUEST['ID_CRONTAB'])
	{
	  $ar = $db->fetch1("select * from crontab where ID_CRONTAB=".$_REQUEST['ID_CRONTAB']);
	  $ar['PRIO_'.$ar['PRIO']] = 1;
	  $tpl_content->addvars($ar);
	  $einheit = $ar['EINHEIT'];
	} // id 
  } // kein post
  
  $ar_einheiten = array
  (
    'minute' => 'Minuten',
	'hour' => 'Stunden',
	'day' => 'Tage',
	'week' => 'Wochen',
	'month' => 'Monate',
	'year' => 'Jahre'
  );
  
  $ar = array();
  foreach($ar_einheiten as $key => $value)
  {
    $ar[] = '<option value="'.$key.'" '.($key == $einheit ? ' selected' : '').'>'.$value.'</option>';
  }
  
  $tpl_content->addvar("liste_einheit", implode("<br>", $ar));

?>
