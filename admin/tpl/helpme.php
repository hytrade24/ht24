<?php
/* ###VERSIONSBLOCKINLCUDE### */

  
$nav_current = $db->fetch1("
select t.*, s.V1, s.V2, s.T1 from nav t left 
join string s on s.S_TABLE='nav' and s.FK=t.ID_NAV and s.BF_LANG=if(t.BF_LANG & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG+0.5)/log(2))) 
where IDENT='".$_REQUEST['forpage']."'");
$tpl_content->addvar('forpage', $nav_current['V1']);

$helptext = $db->fetch1("select s.T1 from `hilfe` t left join string_hilfe s on s.S_TABLE='hilfe' and s.FK=t.ID_HILFE and s.BF_LANG=if(t.BF_LANG_HILFE & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_HILFE+0.5)/log(2))) where SYS_NAME='".$_REQUEST['forpage']."' AND BF_LANG_HILFE=".$langval."");
#echo $db->lang_select("hilfe") . " where SYS_NAME='".$_REQUEST['forpage']."' AND BF_LANG_HILFE=".$langval;
$tpl_content->addvar('helptext', $helptext);

?>