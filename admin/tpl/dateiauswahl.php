<?php
/* ###VERSIONSBLOCKINLCUDE### */



 $ar = $db->fetch_table("select * from datei order by DSC");
 $tpl_content->addlist("liste",$ar, "tpl/de/dateiauswahl.row.htm");

?>
