<?php
/* ###VERSIONSBLOCKINLCUDE### */


 // Helfer zum debuggen von Datenbank-Aufrufen
 //
 // Benutzung:      Einfach NACH dem die Querys ausgeführt wurden per include einbinden:
 //                 include("../debug_querys.php");
 // Ausgaben:       Das Script gibt für alle Querys die im Global "ar_query_log" sind eine Statistik aus mit:
 //                 - Der schnellsten Query (+ Anzahl der benutzungen)
 //                 - Der langsamsten Query (+ Anzahl der benutzungen)
 //                 - Der häufigste Query (+ Anzahl der benutzungen)
 //                 - Gesamtzeit für Datenbank-Abfragen
 //                 - Durchschnittszeit pro Query
 //                 - Anzahl der insgesamt ausgeführten Querys

 // DEBUG !! <----------------------------------
 $query_max = -1;
 $query_min = -1;
 $query_count = count($GLOBALS['ar_query_log']);
 $query_maxcount = -1;
 $query_repeated = array();
 $query_average = 0;
 foreach($GLOBALS['ar_query_log'] as $query_id => $query_data) {
   $query_time = round($query_data['flt_runtime'] * 1000, 5);
   $query_string = $query_data['str_query'];
   if ($query_repeated[$query_string]) { $query_repeated[$query_string]++; } else { $query_repeated[$query_string] = 1; }
   $query_average += $query_time;
   if (($query_max < 0) || ($query_max < $query_time)) {
     $query_max = $query_time;
     $query_max_str = $query_string;
   }
   if (($query_min < 0) || ($query_min > $query_time)) {
     $query_min = $query_time;
     $query_min_str = $query_string;
   }
   if (($query_maxcount < 0) || ($query_maxcount < $query_repeated[$query_string])) {
     $query_maxcount = $query_time;
     $query_maxcount_str = $query_string;
   }
 }
 $query_max_count = $query_repeated[$query_max_str];
 $query_min_count = $query_repeated[$query_min_str];
 $query_maxcount_count = $query_repeated[$query_maxcount_str];
 echo("<table width='100%' id='debug_time'>"); 
 echo("<tr>");
 echo("  <th><strong>Schnellste Query</strong></th>");
 echo("  <th><strong>Langsamste Query</strong></th>");
 echo("  <th><strong>Häufigste Query</strong></th>");
 echo("  <th><strong>Gesamt</strong></th>");
 echo("  <th><strong>Durchschnitt</strong></th>");
 echo("</tr>");
 echo("<tr>");
 echo("  <td>".$query_min."ms (".$query_min_count."x aufgerufen)</td>");
 echo("  <td>".$query_max."ms (".$query_max_count."x aufgerufen)</td>");
 echo("  <td>".$query_maxcount."ms (".$query_maxcount_count."x aufgerufen)</td>");
 echo("  <td>".$query_average."ms</td>");
 echo("  <td>".round($query_average/$query_count, 5)."ms</td>");
 echo("</tr>");
 echo("<tr>");
 echo("  <td><input style='width:100%;' value=\"".$query_min_str."\"></td>");
 echo("  <td><input style='width:100%;' value=\"".$query_max_str."\"></td>");
 echo("  <td><input style='width:100%;' value=\"".$query_maxcount_str."\"></td>");
 echo("  <td colspan='2'>($query_count Querys ausgeführt)</td>");
 echo("</tr>");
 echo("</table>");
 // DEBUG !! <----------------------------------
?>