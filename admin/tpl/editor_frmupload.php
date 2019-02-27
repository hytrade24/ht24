<?php
/* ###VERSIONSBLOCKINLCUDE### */



 $str = $_SERVER['QUERY_STRING'];


 session_start($_GET['ebizkernel']);
 $tpl_content->addvars($_SESSION);
 $tpl_content->addvar('ID_USER', $_SESSION['uid']);

?>