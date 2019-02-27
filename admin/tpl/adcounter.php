<?php
/* ###VERSIONSBLOCKINLCUDE### */



 if(isset($_REQUEST['do']))
 {  
  require_once 'sys/lib.nestedsets.php'; // Nested Sets
  $root = root('kat');
  $nest = new nestedsets('kat', $root, 'ins'==$do);
  $res = $nest->nestSelect();
  while($row=mysql_fetch_assoc($res))
  {
   $count = $db->fetch_atom("select count(*) as alle from anzeige 
      where FK_KAT=".$row['ID_KAT']." and BF_VIS = 3 and STAMP_END >= NOW()");
   $db->update("kat",array ("ID_KAT" => $row['ID_KAT'], "C_ADS" => $count));
  }
  
  while($row=mysql_fetch_assoc($res))
  {
   $kidcount = $db->fetch_atom("select sum C_ADS as all from kat where 
      LFT > ".$row['LFT']." and RGT < ".$row['RGT']);
   $db->querynow("update kat set C_ADS=C_ADS+".$kidcount." where ID_KAT=".$row['ID_KAT']);
  }
 }

?>