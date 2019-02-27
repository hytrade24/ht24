<?php
/* ###VERSIONSBLOCKINLCUDE### */


 #echo ht(dump($_COOKIE));
 #echo ht(dump(session_id())); die();
 $tpl_content->addvar('sess', session_id());
 $tpl_content->addvar('sessname', session_name());

?>