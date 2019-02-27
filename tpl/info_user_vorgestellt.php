<?php
/* ###VERSIONSBLOCKINLCUDE### */


### bestimmten user vorstellen
$file_name = $ab_path.'cache/marktplatz/start_uservorgestellt.'.$s_lang.'.htm';
$file = @filemtime($file_name);
$now = time();
$diff = (($now-$file)/60);
if($diff > 5)
{
	$tmp = new Template("tpl/".$s_lang."/index.user_vorgestellt.htm");
	//$userid = 7590;
	$userid = $db->fetch_atom("
		SELECT
			ID_USER
		FROM
			user
		WHERE
			 (
				SELECT
					COUNT(ads.FK_USER) AS XXX
				FROM
					ad_master ads
				WHERE
					ads.FK_USER=user.ID_USER
					AND ads.STATUS&3=1 AND (ads.DELETED=0)
			) >=3
		GROUP BY
			ID_USER
		ORDER BY
			RAND()
		LIMIT 1");
	if($userid)
	{
		$ar_user = $db->fetch1("
			SELECT
				user.`NAME`,
				user.FIRMA,
				user.CACHE,
				user.ID_USER,
				LEFT(user.UEBER, 200) AS DSC,
				user.UEBER
			FROM
				`user`
			WHERE
				ID_USER=".$userid);

		// artikel des Users
		$liste = $db->fetch_table("
			SELECT
				a.*,
				a.ID_AD_MASTER as ID_AD,
				a.BESCHREIBUNG AS DSC,
				i.SRC_THUMB,
				i.SRC,
				(SELECT sk.V1 FROM `string_kat` sk WHERE sk.FK=a.FK_KAT and sk.S_TABLE='kat'
		    		AND sk.BF_LANG=if(sk.BF_LANG & ".$langval.", ".$langval.", 128) LIMIT 1) as KAT
			FROM
				`ad_master` a
			JOIN
				ad_images i on i.FK_AD=a.ID_AD_MASTER
				AND i.IS_DEFAULT = 1
			WHERE
				a.FK_USER=".$userid."
				AND a.STATUS&3=1 AND (a.DELETED=0)
			ORDER BY
				RAND(),
				a.STAMP_START DESC
			LIMIT
				3");

		$tmp->addvars($ar_user, 'user_');
		$tmp->addlist("user_angebote", $liste, "tpl/".$s_lang."/index.user_angebote.htm");
		@file_put_contents($file_name, $tmp->process());
		chmod($file_name, 0777);
	}
}
$tpl_content->addvar("user_vorgestellt", @file_get_contents($file_name));