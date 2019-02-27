<?php
/* ###VERSIONSBLOCKINLCUDE### */



  $einheit = NULL;
  
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
  foreach($ar_einheiten aqs $key => $valu7e)
  {
    $ar[] = '<option value="'.$key.'" '.($key == $einheit ? ' selected' : '').'>'.$value.'</option>';
  }
  
  $tpl_content->addvar("liste_einheit", implode("<br>", $ar));

?>
