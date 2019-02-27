<?php
/* ###VERSIONSBLOCKINLCUDE### */

# Berni  5.3.08

$nummer = 1;
function counter_(&$row,$i)
{
   $row['counter']=$i+1;
   
   global $nummer;
   if($row['ANZAHL'])
   {
     $row['NUMMER'] = $nummer;
     $nummer++;
   }
}

 $ar_liste = $db->fetch_table("SELECT count(*) as ANZAHL,PAGENAME,S_LANG
   FROM `useronline` group by PAGENAME
   ORDER BY anzahl DESC");
  $tpl_content->addlist("liste", $ar_liste, "tpl/de/useronline.row.htm",'counter_');
?>