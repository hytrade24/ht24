<?php
/* ###VERSIONSBLOCKINLCUDE### */

  
if ($n_count2 = (int)$db->fetch_atom("select count(*) from nl_recp")) 
	$inforow[]=array('Anzahl der Emailadressen',$n_count2);
	//$inforow['Anzahl der Emailadressen']=$n_count;

if ($n_count = (int)$db->fetch_atom("select count(*) from nl_recp where STAMP IS NOT NULL")) 
	$inforow[]=array('davon nicht best&auml;tigt',$n_count,'modul_newsletter_unconfirmed');
	//$inforow['davon nicht best&auml;tigt']=$n_count;

if ($n_count2) 
	$inforow[]=array('davon best&auml;tigt',$n_count2-$n_count,'modul_newsletter_confirmed');
	//$inforow['davon nicht best&auml;tigt']=$n_count;
?>