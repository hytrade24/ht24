<?php
/* ###VERSIONSBLOCKINLCUDE### */



require_once "sys/tabledef.php";
tabledef::getFieldInfo("artikel_001");
echo ht(dump(tabledef::$field_info));
die();

?>