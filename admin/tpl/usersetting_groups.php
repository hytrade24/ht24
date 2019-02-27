<?php
/* ###VERSIONSBLOCKINLCUDE### */


 
 if($_REQUEST['del'])
   $db->delete("setting_group", $_REQUEST['del']);
 
 $liste = $db->fetch_table("select t.*, s.V1, s.V2, s.T1,count(u.ID_USERSETTING) as N_SETTINGS 
    from `setting_group` t 
	 left join string_app s on s.S_TABLE='setting_group' 
	  and s.FK=t.ID_SETTING_GROUP 
	  and s.BF_LANG=if(t.BF_LANG_APP & 128, 128, 1 << floor(log(t.BF_LANG_APP+0.5)/log(2)))
	 left join usersetting u on t.ID_SETTING_GROUP=u.FK_SETTING_GROUP
	group by t.ID_SETTING_GROUP
	order by s.V1");

 $tpl_content->addlist("liste", $liste, "tpl/de/usersetting_groups.row.htm");
?>