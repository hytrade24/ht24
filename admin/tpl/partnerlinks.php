<?php
/* ###VERSIONSBLOCKINLCUDE### */

# Berni  27.02.08



 if($_REQUEST['del'])
 {
   $db->querynow("delete from `partnerlinks` where ID_PARTNERLINKS=".$_REQUEST['del']);
 } // löschen
 
 
 
 $ar_liste = $db->fetch_table(" SELECT * FROM `partnerlinks` ORDER BY `VISIBLE` DESC, `LINKTITEL` ASC");
  $tpl_content->addlist("liste", $ar_liste, "tpl/de/partnerlinks.row.htm");
?>