<?php
/* ###VERSIONSBLOCKINLCUDE### */



 if($_REQUEST['del'] == "rss")
 {
   $ar_lang = $db->fetch_table("select * from `lang` where `B_PUBLIC`=1");
   #echo ht(dump($lastresult));
   for($i = 0; $i<count($ar_lang); $i++)
   {
     unlink ("../cache/rss.".$ar_lang[$i]['ABBR'].".xml");
   }
   $tpl_content->addvar("deleted", 1);
 }

 $tpl_content->addvars($nar_systemsettings['RSS']);
 

?>