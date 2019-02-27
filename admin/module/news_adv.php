<?php
/* ###VERSIONSBLOCKINLCUDE### */



### Basic File für Modul News Adv


/* 
   Templates Array
   Jedes Template muss als eigenes Array bestehen aus
   -Name, Dateiname und Beschreibung
 */

$ar_templates = array ( array (     "name" => "Artikel- Ansicht", 
                                    "tpl" => "news.htm", 
                                    "dsc" => "Stellt Artikel dar" ), 
                        array (     "name" => "Kommentar Zeile", 
                                    "tpl" => "news.comment.htm", 
                                    "dsc" => "Zeile für einzelne Kommentare unterhalb eines Artikels" ), 
                        array (     "name" => "Link Zeile", 
                                    "tpl" => "news.ref.htm", 
                                    "dsc" => "Zeile für Links unterhalb eines Artikels. z.B. Verwandte Artikel" ), 
                        array (     "name" => "Zeile Weitere Artikel", 
                                    "tpl" => "news.row.htm", 
                                    "dsc" => "Zeile für &quot;weitere Artikel&quot; unterhalb des aktuellen Artikels" ), 
                        array (     "name" => "News Archiv", "tpl" => "newsarchiv.htm", "dsc" => "Stellt das Archiv dar" ), array ("name" => "Zeile im Archiv", "tpl" => "newsarchiv.row.htm", "dsc" => "Zeile für einzelne Artikel im Archiv" ), array ("name" => "Kommentar", "tpl" => "newscomment.htm", "dsc" => "Stellt Kommentare in einer Liste dar" ), array ("name" => "Zeile Kommentare", "tpl" => "newscomment.row.htm", "dsc" => "Zeile für einzelne Kommentare in der Kommentarliste" ) );

$ar_darstellung = array ("news" => "Standard", "archiv" => "Archivdarstellung" );

?>
