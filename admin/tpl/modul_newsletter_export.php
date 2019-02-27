<?php
/* ###VERSIONSBLOCKINLCUDE### */



 if(count($_POST))
 {
   $liste = $db->fetch_table("select STAMP, EMAIL from nl_recp
     where CODE IS NULL ");
   $ar = array();
   $ar[] ="EMAIL";
   for($i=0; $i<count($liste); $i++)
   {
     $ar[] = $liste[$i]['EMAIL'];
   }
   $data = implode("\r\n", $ar);
   $fp = fopen("../uploads/nl_ex.csv", "w");
   fwrite($fp, $data);
   fclose($fp);
 }
 
 $check = @filemtime("../uploads/nl_ex.csv");
 
 if($check)
 {
   $tpl_content->addvar("DATE", date("Y-m-d H:i", $check));
 }

?>