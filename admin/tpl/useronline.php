<?php
/* ###VERSIONSBLOCKINLCUDE### */

# Berni  28.02.08
# Schmalle 29.02.2008

$nummer = 1;
function counter_(&$row,$i)
{
   $row['counter']=$i+1;
   
   global $nummer;
   if($row['USER'])
   {
     $row['NUMMER'] = $nummer;
     $nummer++;
   }
}

 $ar_liste = $db->fetch_table("SELECT o.*,TIMEDIFF(o.LASTACTIV,now()) as inactivsince
   FROM `useronline` o
   ORDER BY o.USERIP ASC");
  $tpl_content->addlist("liste", $ar_liste, "tpl/de/useronline.row.htm",'counter_');
?>