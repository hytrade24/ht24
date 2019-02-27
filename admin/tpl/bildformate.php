<?php
/* ###VERSIONSBLOCKINLCUDE### */



 function findcolor(&$row, $i)
 {
   global $db;
   if($row['LU_RGBFARBE'])
   {
     $ar = $db->fetch1($db->lang_select("lookup")." where ID_LOOKUP=".$row['LU_RGBFARBE']);
     $row['WERT'] = str_replace(".",",", $ar['VALUE']);
	 $row['FARBE'] = $ar['V1'];
   }
 }
 
 if(isset($_REQUEST['del']))
   $db->querynow("delete from bildformat where ID_BILDFORMAT=".$_REQUEST['del']);

 $tpl_content->addlist("formate", $db->fetch_table("select * from bildformat order by LABEL"),"tpl/de/bildformate.row.htm","findcolor");

?>