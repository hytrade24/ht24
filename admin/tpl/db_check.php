<?php
/* ###VERSIONSBLOCKINLCUDE### */



 if($_REQUEST['DO'] == 'REPAIR')
 {
   $res = $db->querynow("update nav set `ALIAS` = NULL
     where `ALIAS`= ''");
   $tpl_content->addvar("REPAIR", $res['int_result']);	 
 } // reparieren

 $num = $db->fetch_atom("select count(*) from nav where 
  `ALIAS` = '' and `ALIAS` is not NULL");

 $tpl_content->addvar("NUM", $num);
 
 $ar = array();
 
 $res = mysql_query("SHOW TABLE STATUS from ".$db->str_dbname);
 while($row = mysql_fetch_assoc($res))
 {
   #$tab = mysql_query("SHOW COLUMNS FROM ".$row[0]);
   #echo ht(dump($row));
   $e = $e2 = 'KB';
   $b = $b2 = NULL;
   $val = round((($row['Data_length']+$row['Index_length'])/1024),2);
   $over  = round(($row['Data_free']/1024),2);
   
   if($val > 1024)
   {
     $val = round($val/1024,2);
	 $e='MB';
	 if($val > 10)
	   $b = 1;
   }
   
   if($over > 1024)
   {
     $over = round($over/1024,2);
	 $e2='MB';
	 $b2 = 1;
   }
      
   $ar[] = array
   (
     'TABLE' => $row['Name'],
	 'ROWS' => $row['Rows'],
	 'SIZE' => $val,
	 'E' => $e,
	 'B' => $b,
	 'OVER' => $over,
	 'E2' => $e2,
	 'B2' => $b2
   );
 }
 $tpl_content->addlist("liste", $ar, "tpl/de/db_check.row.htm");

?>