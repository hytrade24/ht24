<?php
/* ###VERSIONSBLOCKINLCUDE### */



$file_name = $ab_path.'cache/marktplatz/new_users.'.$s_lang.'.htm';
$file = @filemtime($file_name);
$now = time();
$diff = (($now-$file)/60);

if($diff >= 60 || !$file) {
	$tmp = new Template("tpl/".$s_lang."/new_users_cache.htm");
	$liste = $db->fetch_table("
		select
			u.NAME as `USER`,
			am.FK_USER
		from
			ad_master am
		join
			user u on am.FK_USER=u.ID_USER and u.STAT=1
		where
			(am.STATUS&3)=1 AND (am.DELETED=0)
		group by
			am.FK_USER
		order by
			am.FK_USER DESC
		limit
			".(int)$nar_systemsettings['MARKTPLATZ']['INDEX_NEW_USERS']);

	if(!empty($liste)) {
		$tmp->addlist("liste_users", $liste, "tpl/".$s_lang."/new_users.row.htm");
	}
	@file_put_contents($file_name, $tmp->process());
	chmod($file_name, 0777);
}

$tpl_content->addvar("users_box", file_get_contents($file_name));

?>