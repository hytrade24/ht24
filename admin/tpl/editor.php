<?php
/* ###VERSIONSBLOCKINLCUDE### */


 #die(print_r($_POST));
 if(count($_POST))
 {
   //$_POST['V1'] = str_replace('/', '\\/', $_POST['V1']);
   $tpl_content->addvars($_POST);
 }
?>