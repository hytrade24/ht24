<?php
/* ###VERSIONSBLOCKINLCUDE### */



 if($_REQUEST['del'])
 {
   $db->querynow("delete from deadlink where FK_SCRIPT=".(int)$_REQUEST['del']);
 } // delete
 
 if($_REQUEST['kickDL'])
 {
   $db->querynow("update script set LINK_DL='' where ID_SCRIPT=".(int)$_REQUEST['del']);
 }
 
 $liste = $db->fetch_table("select t.*, s.V1
   from deadlink d
    left join script t on d.FK_SCRIPT=t.ID_SCRIPT
    left join string_script s on s.S_TABLE='script' 
	 and s.FK=t.ID_SCRIPT and s.BF_LANG=if(t.BF_LANG_SCRIPT & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_SCRIPT+0.5)/log(2)))
   group by FK_SCRIPT
   order by d.STAMP ASC");

 $tpl_content->addlist("liste", $liste, "tpl/de/deadlinks.row.htm");

?>