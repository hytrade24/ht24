<?php
/* ###VERSIONSBLOCKINLCUDE### */



if ($n_count = ( int ) $db->fetch_atom ( "select count(*) from img 
   where 
    OK = 0 and FK_GALERIE >0 " ))
	$inforow [] = array ('Nicht freigeschaltete Bilder', $n_count, "modul_galerie_bilder&FREE=1" );

?>