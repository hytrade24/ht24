<?php
/* ###VERSIONSBLOCKINLCUDE### */

  


if ($_REQUEST['do']=='build_index') 
{
  $tpl_content->addvars($_REQUEST);  
  $tpl_content->addvar('indexing', 1);
}


?>