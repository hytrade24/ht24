<?php
/* ###VERSIONSBLOCKINLCUDE### */



 $tpl_content->addlist("liste", $db->fetch_table($db->lang_select("nl","*,log.DONE,log.TODO")." 
    left join nl_log log on ID_NL=FK_NL 
	order by STAMP DESC"), 
    'tpl/de/modul_newsletter_uebersicht.row.htm');
?>