<?php
/* ###VERSIONSBLOCKINLCUDE### */



 global $db;
 //echo dump($nar_systemsettings);
 
 $tage = $db->fetch_atom("select `value` from `option` where 
   plugin='scripte' and `typ`='deadlinktime'");
 
 $res = $db->querynow("select * from script where DEAD is not NULL and 
   '".date('Y-m-d', strtotime('-'.$tage.' days'))."' > DEAD and OK = 3 ");
 
 #echo dump($res);
 $n=0; 
 while($row = mysql_fetch_assoc($res['rsrc']))
 {
   $db->querynow("update script set OK=0 where ID_SCRIPT=".$row['ID_SCRIPT']);
   $db->querynow("update script_work set OK=0 where ID_SCRIPT_WORK=".$row['ID_SCRIPT']);
   $n++;
 } // while gefundene

 if($n)
 {
   eventlog("warning", $n." Scripte mit Deadlink deaktiviert!");
   todo("Kategorien neu cachen", "cron/recache_kat.php", NULL, NULL, date('Y-m-d H:i', strtotime('+10 minutes')), 'script');
   todo("Neue Scripte neu cachen", "cron/script_day.php", NULL, NULL, NULL, date('Y-m-d H:i', strtotime('+7 minutes')), 'script');
   todo("Scriptr der Woche neu cachen", "cron/wo0chenscript.php", NULL, NULL, date('Y-m-d H:i', strtotime('+5 minutes')), 'script');
 } // deaktivieruing

?>