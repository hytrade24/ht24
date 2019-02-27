<?php
/* ###VERSIONSBLOCKINLCUDE### */



$tpl_content->addvar("ID_NAV", $_REQUEST['id']);

if(count($_POST))
{
  if($db->fetch_atom("select FK_NAV from modul2nav where FK_NAV =".$_REQUEST['id']))
  {
    $up = $db->querynow("update modul2nav set DARSTELLUNG='".$_POST['DARSTELLUNG']."',
      FK=".$_POST['ID_KAT'].", INT_LIMIT=".(int)$_POST['INT_LIMIT']."
     where FK_NAV=".$_REQUEST['id']);
  }
  else
  {
    $up = $db->querynow("insert into modul2nav set DARSTELLUNG='".$_POST['DARSTELLUNG']."',
      FK=".$_POST['ID_KAT'].", INT_LIMIT=".(int)$_POST['INT_LIMIT'].", FK_NAV=".$_REQUEST['id'].",
	  S_MODUL='news_adv'");  
  }
  if(!$up['rsrc'])
    die(ht(dump($up)));  
  $tpl_content->addvar("ok", 1);

}

// aktuelle Seite
$seite = $db->fetch1("select s.V1 as SEITE,mm.DARSTELLUNG, mm.FK_NAV, mm.FK as ID_KAT, INT_LIMIT
    from `nav` t left join string s on s.S_TABLE='nav' 
	  and s.FK=t.ID_NAV and s.BF_LANG=if(t.BF_LANG & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG+0.5)/log(2)))
	 left join modul2nav mm on mm.FK_NAV=".$_REQUEST['id']."
	where ID_NAV=".$_REQUEST['id']); 

$tpl_content->addvars($seite);

// Array mit Darstellungsweisen holen
include "module/news_adv.php";

$sel = array();
$sel[] = '<select name="DARSTELLUNG">';
foreach($ar_darstellung as $key => $value)
{
  $sel[] = '<option value="'.$key.'" '.($key == $seite['DARSTELLUNG'] ? 'selected="selected"' : '').'>'.$value.'</option>';
}
$sel[] = "</select>";

$tpl_content->addvar("select", implode($sel));

?>