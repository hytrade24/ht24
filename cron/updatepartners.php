<?php
/* ###VERSIONSBLOCKINLCUDE### */



  include_once $ab_path.'admin/sys/lib.cache.php';

  
function update_partnerlinks()
{
  global $db;
    $ar_lang = $db->fetch_table("select BITVAL, ABBR from lang where B_PUBLIC=1");
  	for($n=0; $n<count($ar_lang); $n++)
	  {
	    cache_partnerlinks($ar_lang[$n]['ABBR']);
	  }
 }

  update_partnerlinks();

?>