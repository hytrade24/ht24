<?php
/* ###VERSIONSBLOCKINLCUDE### */



$idmodul = $db->fetch_atom("select ID_MODUL from modul where IDENT='galerie'"); 

if(isset($_REQUEST['id']))
  $tpl_content->addvar("edit", $_REQUEST['id']);

if(count($_POST))
{
  $check = $db->fetch_atom("select FK_NAV from modul2nav where FK_NAV =".$_REQUEST['FK_NAV']."
     and S_MODUL='galerie'");
  if(!$check)
    $db->querynow("insert into modul2nav set FK_NAV=".$_REQUEST['FK_NAV'].",
	    FK=0,S_MODUL='galerie'"); //die(ht(dump($lastresult)));
  $db->querynow("update modul2nav set FK=".$_POST['ID_GALERIE']." where 
     FK_NAV=".$_REQUEST['FK_NAV']." and S_MODUL='galerie'");
  
}
$ar = $db->fetch_table("select mo.*,t.*, s.V1, s.V2, s.T1 
       from nav t
	   left join modul2nav m on t.ID_NAV=m.FK_NAV
	   left join galerie mo on mo.ID_GALERIE=m.FK and S_MODUL='galerie'
	   left join string s on s.S_TABLE='nav' and s.FK=t.ID_NAV 
	     and s.BF_LANG=if(t.BF_LANG & 128, 128, 1 << floor(log(t.BF_LANG+0.5)/log(2)))
	  where FK_MODUL=".$idmodul);
#echo ht(dump($ar));

$tpl_content->addlist("liste", $ar, "tpl/de/modul_galerie_2nav.row.htm");


?>