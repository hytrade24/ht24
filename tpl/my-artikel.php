<?php
/* ###VERSIONSBLOCKINLCUDE### */



 $npage = ($ar_params[3] ? (int)$ar_params[3] : 1);
 $perpage = 20;
 $limit = ($npage*$perpage)-$perpage;
 
 $all = $db->fetch_atom("select * from news where FK_AUTOR=".$uid);
 
 die($db->lang_select("news"));
 
 $liste = $db->querynow("
   
 ");

?>