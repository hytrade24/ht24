<?php
/* ###VERSIONSBLOCKINLCUDE### */



 global $db;
 
 $interval = "7 day";
 
 $num = $db->fetch_atom("select count(*) from eventlog where STAMP < date_sub(now(), interval ".$interval.")");

 if($num)
 {
   $res = $db->querynow("delete from eventlog where STAMP < date_sub(now(), interval ".$interval.")");
   if($res['rsrc'])
   {
     eventlog("info", $num." Einträge aus Eventlog gelöscht!"); 
   }
   else
   {
     eventlog("error", $num." Eventlogeinträge konnten nicht gelöscht werden", $res['str_error']); 
   }
 } // wenn was da

?>