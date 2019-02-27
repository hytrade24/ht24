<?php
/* ###VERSIONSBLOCKINLCUDE### */



 // Optionen f�r Modulzuordnung
 $ar_modul = $db->fetch1("select m.*, k.LFT,k.RGT from modul2nav m
	left join kat k on FK=ID_KAT
	where FK_NAV=".$id_nav);
 
 // Mode- Auswahl
 // Was soll angezeigt werden
 // Artikel || �bersicht || Archiv || || Kommentare

$darstellung = (empty($ar_modul['DARSTELLUNG']) ? 'news' : $ar_modul['DARSTELLUNG']);

if(!empty($ar_modul))
{ 
  $smode = ($ar_params[3] ? $ar_params[3] : $darstellung);
#echo $smode;
  include "module/news_adv/".$smode.".php";
}
#echo ht(dump($ar_params));


?>