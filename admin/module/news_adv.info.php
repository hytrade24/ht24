<?php
/* ###VERSIONSBLOCKINLCUDE### */

  
$n_count = (int)$db->fetch_atom("select count(*) from news");
	$inforow[]=array('Anzahl der Artikel',$n_count,'modul_news_adv_artikelliste');
	//$inforow['Anzahl der Emailadressen']=$n_count;

	$n_count = (int)$db->fetch_atom("select count(*) from news where OK=3");
	$inforow[]=array('davon sichtbar',$n_count,'modul_news_adv_artikelliste&RED_=1&ADM_=2&FG_=1');
	//$inforow['davon nicht best&auml;tigt']=$n_count;

$n_count = (int)$db->fetch_atom("select count(*) from news where OK=0 or OK=2");
	$inforow[]=array('In Bearbeitung ...',$n_count,'modul_news_adv_artikelliste&RED_=0&ADM_=0&FG_=1');
	//$inforow['davon nicht best&auml;tigt']=$n_count;

$n_count = (int)$db->fetch_atom("select count(*) from news where OK=1");
	$inforow[]=array('warten auf Freigabe (ADM)',$n_count,'modul_news_adv_artikelliste&RED_=1&ADM_=0&FG_=1');

?>