<?php
/* ###VERSIONSBLOCKINLCUDE### */

  
/*
2006-05-04   BB  Druckt den Contentbereich aus.  Als Template wird skin/index_print.htm verwendet.
*/
$do_print = true;
$what = str_replace(".htm", "", $_REQUEST['what']);
include ("index.php");
?>